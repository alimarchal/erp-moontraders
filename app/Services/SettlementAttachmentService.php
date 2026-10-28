<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
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
}
