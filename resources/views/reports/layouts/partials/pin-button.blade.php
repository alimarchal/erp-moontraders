{{--
    The star that pins or unpins one report for this user.

    @var array{id: string, label: string} $item
--}}
<button type="button" x-on:click="toggle(@js($item['id']))"
    x-bind:aria-pressed="isSaved(@js($item['id'])).toString()"
    x-bind:title="isSaved(@js($item['id'])) ? 'Unpin' : 'Pin to the top'"
    x-bind:class="isSaved(@js($item['id'])) ? 'text-amber-500' : 'text-slate-300 hover:text-slate-500'"
    aria-label="Pin {{ $item['label'] }}"
    class="{{ $class ?? '' }} shrink-0 grid place-items-center w-8 h-8 rounded-lg hover:bg-slate-100 transition-colors">
    <x-menu-icon name="star" class="w-4 h-4" x-bind:fill="isSaved({{ \Illuminate\Support\Js::from($item['id']) }}) ? 'currentColor' : 'none'" />
</button>
