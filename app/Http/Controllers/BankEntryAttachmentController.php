<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankAttachmentRequest;
use App\Models\Attachment;
use App\Models\BankEntryAuditLog;
use App\Models\BankStatementEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankEntryAttachmentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:bank-statement-entry-attach', only: ['store', 'destroy']),
            new Middleware('can:bank-statement-entry-list', only: ['download']),
        ];
    }

    public function store(BankAttachmentRequest $request, BankStatementEntry $bankStatementEntry): RedirectResponse
    {
        $disk = config('bank.attachments.disk');

        foreach ($request->file('files') as $file) {
            $path = $file->store("bank-attachments/{$bankStatementEntry->id}", $disk);

            $bankStatementEntry->attachments()->create([
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'description' => $request->input('description'),
                'label' => $request->input('label'),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        BankEntryAuditLog::record($bankStatementEntry, 'attachment_added', null, ['files' => count($request->file('files')), 'label' => $request->input('label')], $request->user()->id);

        return back()->with('success', count($request->file('files')).' file(s) attached.');
    }

    /**
     * Files are on the private disk; only signed-in users with access to the register can fetch them.
     */
    public function download(Attachment $attachment): StreamedResponse
    {
        abort_unless($attachment->attachable_type === BankStatementEntry::class, 404);

        $disk = Storage::disk(config('bank.attachments.disk'));
        abort_unless($disk->exists($attachment->file_path), 404);

        return $disk->download($attachment->file_path, $attachment->file_name);
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        abort_unless($attachment->attachable_type === BankStatementEntry::class, 404);

        $entry = $attachment->attachable;

        if ($entry->status === BankStatementEntry::STATUS_RECONCILED) {
            return back()->with('error', 'This entry is reconciled, so its attachments are locked.');
        }

        Storage::disk(config('bank.attachments.disk'))->delete($attachment->file_path);
        $attachment->delete();
        BankEntryAuditLog::record($entry, 'attachment_removed', ['file_name' => $attachment->file_name]);

        return back()->with('success', 'Attachment removed.');
    }
}
