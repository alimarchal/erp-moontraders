<?php
namespace App\Helpers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class FileStorageHelper
{
    /**
     * Store uploaded files and create database records (PUBLIC - storage/app/public/)
     */
    public static function storeFiles(
        array $files,
        string $modelClass,
        string $folderName,
        array $relationData = [],
        string $subFolder = null,
        array $additionalFields = []
    ): array {
        return self::storeFilesWithDisk($files, $modelClass, $folderName, 'public', $relationData, $subFolder, $additionalFields);
    }

    /**
     * Store uploaded files and create database records (PRIVATE - storage/app/)
     */
    public static function storePrivateFiles(
        array $files,
        string $modelClass,
        string $folderName,
        array $relationData = [],
        string $subFolder = null,
        array $additionalFields = []
    ): array {
        return self::storeFilesWithDisk($files, $modelClass, $folderName, 'local', $relationData, $subFolder, $additionalFields);
    }

    /**
     * Store single file (PUBLIC - storage/app/public/) - Direct URL access
     */
    public static function storeSingleFile($file, string $folderName, string $subFolder = null): string
    {
        return self::storeSingleFileWithDisk($file, $folderName, 'public', $subFolder);
    }

    /**
     * Store single file (PRIVATE - storage/app/) - Controlled access only
     */
    public static function storeSinglePrivateFile($file, string $folderName, string $subFolder = null): string
    {
        return self::storeSingleFileWithDisk($file, $folderName, 'local', $subFolder);
    }

    /**
     * Delete file from public storage
     */
    public static function deleteFile(string $filePath): bool
    {
        return Storage::disk('public')->delete($filePath);
    }

    /**
     * Delete file from private storage
     */
    public static function deletePrivateFile(string $filePath): bool
    {
        return Storage::disk('local')->delete($filePath);
    }

    /**
     * Core method for storing multiple files
     */
    private static function storeFilesWithDisk(
        array $files,
        string $modelClass,
        string $folderName,
        string $disk,
        array $relationData = [],
        string $subFolder = null,
        array $additionalFields = []
    ): array {
        $attachments = [];

        foreach ($files as $file) {
            if ($file->isValid()) {
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                //$storagePath = $folderName . ($subFolder ? '/' . $subFolder : '');
                $storagePath = $folderName;
                $path = $file->storeAs($storagePath, $filename, $disk);

                $data = array_merge([
                    'filename' => $filename,
                    'original_filename' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'uploaded_by' => auth()->id(),
                    'storage_disk' => $disk
                ], $relationData, $additionalFields);

                $attachment = $modelClass::create($data);
                $attachments[] = $attachment;
            }
        }

        return $attachments;
    }

    /**
     * Core method for storing single file
     */
    private static function storeSingleFileWithDisk($file, string $folderName, string $disk, string $subFolder = null): string
    {
        if (!$file || !$file->isValid()) {
            throw new \Exception('Invalid file provided');
        }

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        // $storagePath = $folderName . ($subFolder ? '/' . $subFolder : '');
        $storagePath = $folderName;

        return $file->storeAs($storagePath, $filename, $disk);
    }
}