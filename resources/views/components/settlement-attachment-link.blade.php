@props(['row' => null, 'exhibit' => null])
@if ($row && $row->attachment_url)
    <button type="button" class="print:hidden inline-flex items-center gap-1 align-middle text-green-700 hover:text-green-900 text-xs font-semibold" title="View attachment"
        onclick="SettlementAttachments.previewUrl(@js($row->attachment_url), @js(strtolower(pathinfo($row->attachment, PATHINFO_EXTENSION))), @js($exhibit ? 'Att. '.$exhibit['no'].' - '.$exhibit['label'].': '.$exhibit['title'] : 'Attachment'))">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
        @if ($exhibit)Att. {{ $exhibit['no'] }}@endif
    </button>
    @if ($exhibit)<span class="hidden print:inline text-xs">Att. {{ $exhibit['no'] }}</span>@endif
@endif
