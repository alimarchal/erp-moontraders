<?php

namespace App\Services\BankRegister;

use App\Models\BankEntryAuditLog;
use App\Models\BankStatementEntry;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Stores receipts / slips / cheque copies on the private disk and links them to a statement line.
 */
class BankEntryAttachmentService
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @return int number of files attached
     */
    public function attach(BankStatementEntry $entry, array $files, string $label, ?string $description, User $user): int
    {
        $disk = config('bank.attachments.disk');

        foreach ($files as $file) {
            $path = $file->store("bank-attachments/{$entry->id}", $disk);

            $entry->attachments()->create([
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'description' => $description,
                'label' => $label,
                'uploaded_by' => $user->id,
            ]);
        }

        BankEntryAuditLog::record($entry, 'attachment_added', null, ['files' => count($files), 'label' => $label], $user->id);

        return count($files);
    }
}
