<?php

namespace App\Console\Commands;

use App\Services\SettlementAttachmentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('settlement-attachments:prune-staging {--hours=24 : Delete staged files older than this many hours}')]
#[Description('Delete settlement uploads left in the staging folder by a save that never finished (a refused or resent form)')]
class PruneSettlementAttachmentStaging extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk(SettlementAttachmentService::disk());
        $folder = config('settlement_attachments.root_folder').'/'.SettlementAttachmentService::STAGING_FOLDER;
        $cutoff = now()->subHours(max(1, (int) $this->option('hours')))->getTimestamp();

        $old = collect($disk->allFiles($folder))->filter(fn (string $path) => $disk->lastModified($path) < $cutoff)->values();

        if ($old->isNotEmpty()) {
            $disk->delete($old->all());
        }

        $this->info("Deleted {$old->count()} staged file(s) older than {$this->option('hours')} hours.");

        return self::SUCCESS;
    }
}
