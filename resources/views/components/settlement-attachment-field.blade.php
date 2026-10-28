{{-- Optional file input for the modal's "add entry" form. Needs `pendingAttachment` in the Alpine scope. --}}
@if (\App\Services\SettlementAttachmentService::enabled())
    <div x-data>
        <label class="block text-xs font-semibold text-gray-700 mb-1">Attachment (Optional)
            <span class="font-normal text-gray-400">PDF, JPG, PNG, WEBP up to {{ round(config('settlement_attachments.max_kb') / 1024) }} MB</span>
        </label>
        <div class="flex flex-wrap items-center gap-2">
            <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp"
                @change="pendingAttachment = SettlementAttachments.register($event.target)"
                class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-green-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-green-700 hover:file:bg-green-100" />
            <template x-if="pendingAttachment">
                <span class="inline-flex items-center gap-1 text-xs text-green-700 bg-green-50 rounded px-2 py-1">
                    <span class="truncate max-w-[14rem]" x-text="pendingAttachment.name"></span>
                    <button type="button" class="text-red-500 hover:text-red-700"
                        @click="delete SettlementAttachments.files[pendingAttachment.key]; pendingAttachment = null">&times;</button>
                </span>
            </template>
        </div>
    </div>
@endif
