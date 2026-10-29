<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Optional PDF or image on an expense voucher, kept on the same disk and with the same limits as the
 * settlement attachments, in a folder of its own: Vouchers/{dd-mm-yyyy of the voucher}/{hash}.ext
 */
class ExpenseVoucherAttachmentService
{
    public const ROOT_FOLDER = 'Vouchers';

    public static function disk(): string
    {
        return SettlementAttachmentService::disk();
    }

    /**
     * Rules for the upload field, from the shared attachment settings.
     *
     * @return array<int, string>
     */
    public static function rules(): array
    {
        return ['nullable', 'file', 'mimes:'.implode(',', config('settlement_attachments.extensions')), 'max:'.config('settlement_attachments.max_kb')];
    }

    public function store(UploadedFile $file, CarbonInterface $voucherDate): string
    {
        $folder = self::ROOT_FOLDER.'/'.$voucherDate->format('d-m-Y');
        $name = hash('sha256', Str::uuid()->toString().$file->getClientOriginalName()).'.'.strtolower($file->getClientOriginalExtension());

        return Storage::disk(self::disk())->putFileAs($folder, $file, $name);
    }

    public function delete(?string $path): void
    {
        if ($path && str_starts_with($path, self::ROOT_FOLDER.'/') && ! str_contains($path, '..')) {
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
