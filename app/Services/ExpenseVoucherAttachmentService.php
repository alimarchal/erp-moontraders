<?php

namespace App\Services;

use App\Models\ExpenseVoucher;
use App\Models\ExpenseVoucherAttachment;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Optional PDF or image on an expense voucher, kept under Vouchers/{dd-mm-yyyy of the voucher}/{hash}.ext
 * on the disk set in config/voucher_attachments.php (local, or s3 through VOUCHER_ATTACHMENTS_DISK).
 */
class ExpenseVoucherAttachmentService
{
    public static function enabled(): bool
    {
        return (bool) config('voucher_attachments.enabled');
    }

    public static function disk(): string
    {
        return (string) config('voucher_attachments.disk');
    }

    public static function rootFolder(): string
    {
        return (string) config('voucher_attachments.root_folder');
    }

    /**
     * Rules for the upload field, from the shared attachment settings.
     *
     * @return array<int, string>
     */
    public static function rules(): array
    {
        return ['nullable', 'file', 'mimes:'.implode(',', config('voucher_attachments.extensions')), 'max:'.config('voucher_attachments.max_kb')];
    }

    /**
     * Rules for the multi-file field (`attachments[]`).
     *
     * @return array<string, array<int, string>>
     */
    public static function manyRules(): array
    {
        return [
            'attachments' => ['nullable', 'array', 'max:'.config('voucher_attachments.max_files', 10)],
            'attachments.*' => ['file', 'mimes:'.implode(',', config('voucher_attachments.extensions')), 'max:'.config('voucher_attachments.max_kb')],
        ];
    }

    /**
     * Shrink and store the files. Done BEFORE the voucher's database transaction opens, so the slow part (the picture work
     * and the upload to S3) never keeps the database busy; {@see record()} then only writes the rows.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{file_name: string, file_type: string|null, file_size: int|false, file_path: string}>
     */
    public function upload(array $files, CarbonInterface $voucherDate): array
    {
        $optimizer = app(AttachmentImageOptimizer::class);
        $uploaded = [];

        try {
            foreach ($files as $original) {
                $file = $optimizer->optimize($original);
                $uploaded[] = [
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'file_path' => $this->store($file, $voucherDate),
                ];
            }
        } catch (\Throwable $e) {
            $this->discard($uploaded);

            throw $e;
        }

        return $uploaded;
    }

    /**
     * Write the rows for files already stored by {@see upload()}.
     *
     * @param  array<int, array{file_name: string, file_type: string|null, file_size: int|false, file_path: string}>  $uploaded
     */
    public function record(ExpenseVoucher $voucher, array $uploaded): void
    {
        foreach ($uploaded as $file) {
            $voucher->attachments()->create($file + ['created_by' => auth()->id()]);
        }
    }

    /**
     * Remove files that were stored but never recorded (the save failed).
     *
     * @param  array<int, array{file_path: string}>  $uploaded
     */
    public function discard(array $uploaded): void
    {
        foreach ($uploaded as $file) {
            $this->delete($file['file_path']);
        }
    }

    /**
     * Keep every uploaded file as an attachment of the voucher (upload and record in one go).
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function attach(ExpenseVoucher $voucher, array $files): void
    {
        $uploaded = $this->upload($files, $voucher->voucher_date);

        try {
            $this->record($voucher, $uploaded);
        } catch (\Throwable $e) {
            $this->discard($uploaded);

            throw $e;
        }
    }

    public function remove(ExpenseVoucherAttachment $attachment): void
    {
        $this->delete($attachment->file_path);
        $attachment->delete();
    }

    public function store(UploadedFile $file, CarbonInterface $voucherDate): string
    {
        $folder = self::rootFolder().'/'.$voucherDate->format('d-m-Y');
        $name = hash('sha256', Str::uuid()->toString().$file->getClientOriginalName()).'.'.strtolower($file->getClientOriginalExtension());

        return Storage::disk(self::disk())->putFileAs($folder, $file, $name);
    }

    public function delete(?string $path): void
    {
        if ($path && str_starts_with($path, self::rootFolder().'/') && ! str_contains($path, '..')) {
            Storage::disk(self::disk())->delete($path);
        }
    }

    public function exists(?string $path): bool
    {
        return $path !== null && Storage::disk(self::disk())->exists($path);
    }

    public function response(string $path)
    {
        return Storage::disk(self::disk())->response($path);
    }
}
