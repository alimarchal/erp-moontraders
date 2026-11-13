<?php

namespace App\Services;

use App\Models\SalesSettlement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SettlementAttachmentService
{
    public const SECTIONS = [
        'credit_sales' => 'credit-sales',
        'cheques' => 'cheques',
        'bank_transfers' => 'bank-transfers',
        'bank_slips' => 'bank-slips',
        'recoveries' => 'recoveries',
    ];

    public static function enabled(): bool
    {
        return (bool) config('settlement_attachments.enabled');
    }

    /** Heading of each section as the user sees it in the settlement. */
    public const SECTION_LABELS = [
        'credit_sales' => 'Credit Sales Detail',
        'recoveries' => 'Recoveries Detail',
        'cheques' => 'Cheque Payments',
        'bank_transfers' => 'Bank Transfers',
        'bank_slips' => 'Bank Slips / Deposits',
    ];

    /**
     * Whether a file is compulsory for every row of the section (SS_*_ATTACHMENT=true in .env).
     */
    public static function isRequired(string $section): bool
    {
        return self::enabled() && (bool) config('settlement_attachments.required.'.$section, false);
    }

    /**
     * Rows that must carry a file but do not, by section. A settlement with any of them cannot be posted.
     * Cash recoveries need no file: only a recovery paid by bank does.
     *
     * @return array<int, string> one sentence per section, naming the rows
     */
    public static function missingRequired(SalesSettlement $settlement): array
    {
        $sections = [
            'credit_sales' => ['creditSales', 'customer', fn ($r) => 'invoice '.($r->invoice_number ?: '#'.$r->id), fn ($r) => true],
            'recoveries' => ['recoveries', 'customer', fn ($r) => 'recovery '.($r->recovery_number ?: '#'.$r->id), fn ($r) => $r->payment_method !== 'cash'],
            'cheques' => ['cheques', 'customer', fn ($r) => 'cheque '.($r->cheque_number ?: '#'.$r->id), fn ($r) => true],
            'bank_transfers' => ['bankTransfers', 'customer', fn ($r) => 'transfer '.($r->reference_number ?: '#'.$r->id), fn ($r) => true],
            'bank_slips' => ['bankSlips', 'bankAccount', fn ($r) => 'slip '.($r->reference_number ?: '#'.$r->id), fn ($r) => true],
        ];

        $messages = [];

        foreach ($sections as $section => [$relation, $with, $name, $needsFile]) {
            if (! self::isRequired($section)) {
                continue;
            }

            $rows = $settlement->{$relation}()->with($with)->get()->filter(fn ($row) => $needsFile($row) && blank($row->attachment));

            if ($rows->isNotEmpty()) {
                $messages[] = self::SECTION_LABELS[$section].': attach a file to '.$rows->map(fn ($row) => $name($row))->take(5)->join(', ').($rows->count() > 5 ? ' and '.($rows->count() - 5).' more' : '');
            }
        }

        return $messages;
    }

    public static function disk(): string
    {
        return (string) config('settlement_attachments.disk');
    }

    public static function isAllowed(UploadedFile $file): bool
    {
        return $file->isValid()
            && in_array(strtolower($file->getClientOriginalExtension()), config('settlement_attachments.extensions'), true)
            && $file->getSize() <= config('settlement_attachments.max_kb') * 1024;
    }

    /**
     * Store under sales-settlement/{settlement_number}/{section}/{hash}.ext and return the path.
     */
    public function store(UploadedFile $file, string $settlementNumber, string $section): string
    {
        $file = app(AttachmentImageOptimizer::class)->optimize($file);

        $folder = config('settlement_attachments.root_folder').'/'.$settlementNumber.'/'.self::SECTIONS[$section];
        $name = hash('sha256', Str::uuid()->toString().$file->getClientOriginalName()).'.'.strtolower($file->getClientOriginalExtension());

        $path = Storage::disk(self::disk())->putFileAs($folder, $file, $name);
        $this->storeThumbnail($file, $path);

        return $path;
    }

    /**
     * Where the small copy of a photo lives: a thumbs/ folder beside it, always as a JPEG.
     */
    public static function thumbPath(string $path): string
    {
        return trim(dirname($path), '/').'/thumbs/'.pathinfo($path, PATHINFO_FILENAME).'.jpg';
    }

    /**
     * Write the small copy shown in lists and previews. A PDF, or a picture GD cannot read, gets none, and the full
     * file is shown instead.
     */
    private function storeThumbnail(UploadedFile $file, string $path): void
    {
        if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true) || ! function_exists('imagecreatefromstring')) {
            return;
        }

        try {
            $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

            if ($image === false) {
                return;
            }

            $limit = max(64, (int) config('attachment_images.thumb_px', 320));
            $ratio = min(1, $limit / max(imagesx($image), imagesy($image)));
            $thumb = imagecreatetruecolor(max(1, (int) round(imagesx($image) * $ratio)), max(1, (int) round(imagesy($image) * $ratio)));
            imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));
            imagecopyresampled($thumb, $image, 0, 0, 0, 0, imagesx($thumb), imagesy($thumb), imagesx($image), imagesy($image));

            ob_start();
            imagejpeg($thumb, null, 70);
            Storage::disk(self::disk())->put(self::thumbPath($path), (string) ob_get_clean());
        } catch (\Throwable $e) {
            Log::warning('A settlement attachment thumbnail could not be made', ['path' => $path, 'error' => $e->getMessage()]);
        }
    }

    public const STAGING_FOLDER = '_staging';

    /**
     * Store an upload BEFORE the database transaction of a settlement starts: the shrinking and the upload to S3 can
     * take seconds and must not keep the Goods Issue locked. The file waits in sales-settlement/_staging/; once the
     * settlement is saved, {@see moveStagedUpload()} puts it in its settlement's own section folder.
     */
    public function storeUpload(UploadedFile $file): string
    {
        $file = app(AttachmentImageOptimizer::class)->optimize($file);
        $name = hash('sha256', Str::uuid()->toString().$file->getClientOriginalName()).'.'.strtolower($file->getClientOriginalExtension());

        $path = Storage::disk(self::disk())->putFileAs(config('settlement_attachments.root_folder').'/'.self::STAGING_FOLDER, $file, $name);
        $this->storeThumbnail($file, $path);

        return $path;
    }

    public function isStaged(?string $path): bool
    {
        return $path !== null && str_starts_with($path, config('settlement_attachments.root_folder').'/'.self::STAGING_FOLDER.'/') && ! str_contains($path, '..');
    }

    /**
     * Move a staged file to sales-settlement/{settlement no}/{section}/ and return its new path. It copies the stream and
     * then deletes the staged file instead of using Flysystem's move(), because move() also reads the object's ACL and
     * that needs the extra s3:GetObjectAcl permission the documented IAM policy does not grant. If the move fails the
     * staged path is returned, which still opens (nothing checks the folder), and the file is not lost.
     */
    public function moveStagedUpload(string $stagedPath, string $settlementNumber, string $section): string
    {
        $target = config('settlement_attachments.root_folder').'/'.$settlementNumber.'/'.self::SECTIONS[$section].'/'.basename($stagedPath);

        $moved = $this->moveFile($stagedPath, $target);

        if ($moved && Storage::disk(self::disk())->exists(self::thumbPath($stagedPath))) {
            $this->moveFile(self::thumbPath($stagedPath), self::thumbPath($target));
        }

        return $moved ? $target : $stagedPath;
    }

    /**
     * Copy a file to its new path and delete the old one; false (and the file left where it was) when that fails.
     */
    private function moveFile(string $stagedPath, string $target): bool
    {
        try {
            $disk = Storage::disk(self::disk());

            if (config('filesystems.disks.'.self::disk().'.driver') === 's3') {
                $bucket = config('filesystems.disks.'.self::disk().'.bucket');
                $prefix = trim((string) config('filesystems.disks.'.self::disk().'.root', ''), '/');
                $key = fn (string $path): string => ltrim(($prefix !== '' ? $prefix.'/' : '').$path, '/');

                $disk->getClient()->copyObject([
                    'Bucket' => $bucket,
                    'Key' => $key($target),
                    'CopySource' => rawurlencode($bucket.'/'.$key($stagedPath)),
                ]);
                $disk->delete($stagedPath);

                return true;
            }

            $stream = $disk->readStream($stagedPath);

            if ($stream === null || ! $disk->writeStream($target, $stream)) {
                return false;
            }

            if (is_resource($stream)) {
                fclose($stream);
            }

            $disk->delete($stagedPath);

            return true;
        } catch (\Throwable $e) {
            Log::warning('A settlement attachment could not be moved out of staging', ['path' => $stagedPath, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(self::disk())->delete([$path, self::thumbPath($path)]);
        }
    }

    public function belongsToSettlement(?string $path, string $settlementNumber): bool
    {
        return $path !== null
            && str_starts_with($path, config('settlement_attachments.root_folder').'/'.$settlementNumber.'/')
            && ! str_contains($path, '..');
    }

    /**
     * Numbered attachment list for a settlement (Att. 1, 2, ...), used by the show page and the PDF.
     *
     * @return Collection<int, array{no: int, key: string, section: string, label: string, title: string, reference: string, amount: float, path: string, ext: string, url: string, is_pdf: bool}>
     */
    public static function exhibits(SalesSettlement $settlement): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        $definitions = [
            'credit_sales' => ['creditSales', 'Credit Sale', fn ($r) => [$r->customer->customer_name ?? '-', $r->invoice_number, $r->sale_amount]],
            'recoveries' => ['recoveries', 'Recovery (Bank)', fn ($r) => [$r->customer->customer_name ?? '-', $r->recovery_number, $r->amount]],
            'cheques' => ['cheques', 'Cheque', fn ($r) => [$r->customer->customer_name ?? '-', 'Chq # '.$r->cheque_number, $r->amount]],
            'bank_transfers' => ['bankTransfers', 'Bank Transfer', fn ($r) => [$r->customer->customer_name ?? ($r->bankAccount->account_name ?? '-'), $r->reference_number, $r->amount]],
            'bank_slips' => ['bankSlips', 'Bank Slip / Deposit', fn ($r) => [$r->bankAccount->account_name ?? '-', $r->reference_number, $r->amount]],
        ];

        $exhibits = collect();
        $no = 0;

        foreach ($definitions as $section => [$relation, $label, $describe]) {
            foreach ($settlement->{$relation} as $row) {
                if (! $row->attachment) {
                    continue;
                }

                [$title, $reference, $amount] = $describe($row);

                $exhibits->push([
                    'no' => ++$no,
                    'key' => $section.':'.$row->id,
                    'section' => $section,
                    'label' => $label,
                    'title' => (string) $title,
                    'reference' => (string) ($reference ?? ''),
                    'amount' => (float) $amount,
                    'path' => $row->attachment,
                    'ext' => strtolower(pathinfo($row->attachment, PATHINFO_EXTENSION)),
                    'url' => $row->attachment_url,
                    'is_pdf' => strtolower(pathinfo($row->attachment, PATHINFO_EXTENSION)) === 'pdf',
                ]);
            }
        }

        return $exhibits;
    }

    /**
     * Inline data URI of an image attachment (PDF engines cannot call the protected route).
     * Returns null for PDFs or when the file cannot be read.
     */
    public static function imageDataUri(string $path): ?string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return null;
        }

        try {
            $contents = Storage::disk(self::disk())->get($path);

            if ($contents === null) {
                return null;
            }

            if ($ext === 'webp') {
                $image = function_exists('imagecreatefromstring') ? @imagecreatefromstring($contents) : false;

                if ($image === false) {
                    return null;
                }

                ob_start();
                imagepng($image);
                $contents = (string) ob_get_clean();
                $ext = 'png';
            }

            return 'data:image/'.($ext === 'jpg' ? 'jpeg' : $ext).';base64,'.base64_encode($contents);
        } catch (\Throwable $e) {
            Log::warning('Settlement attachment could not be embedded', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
