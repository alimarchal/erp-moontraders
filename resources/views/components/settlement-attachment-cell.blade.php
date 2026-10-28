{{-- Paperclip / change / remove controls for one entry row. Must be used inside an Alpine scope exposing `entry`. --}}
@if (\App\Services\SettlementAttachmentService::enabled())
    <div class="inline-flex items-center gap-1" x-data>
        <template x-if="SettlementAttachments.hasAttachment(entry)">
            <button type="button" @click="SettlementAttachments.preview(entry)" title="View attachment"
                class="inline-flex items-center justify-center w-7 h-7 rounded bg-green-50 text-green-700 hover:bg-green-100">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
            </button>
        </template>
        <label class="inline-flex items-center justify-center px-1.5 h-7 rounded bg-gray-100 text-gray-600 hover:bg-gray-200 text-[10px] font-semibold cursor-pointer" title="Attach or replace file">
            <span x-text="SettlementAttachments.hasAttachment(entry) ? 'Change' : 'Attach'"></span>
            <input type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.webp" @change="SettlementAttachments.applyToEntry(entry, $event.target)">
        </label>
        <template x-if="SettlementAttachments.hasAttachment(entry)">
            <button type="button" @click="SettlementAttachments.clearEntry(entry)" title="Remove attachment"
                class="inline-flex items-center justify-center w-6 h-7 text-red-500 hover:text-red-700 text-sm">&times;</button>
        </template>
    </div>
@endif
