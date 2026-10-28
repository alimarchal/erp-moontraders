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
        $folder = config('settlement_attachments.root_folder').'/'.$settlementNumber.'/'.self::SECTIONS[$section];
        $name = hash('sha256', Str::uuid()->toString().$file->getClientOriginalName()).'.'.strtolower($file->getClientOriginalExtension());

        return Storage::disk(self::disk())->putFileAs($folder, $file, $name);
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(self::disk())->delete($path);
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
