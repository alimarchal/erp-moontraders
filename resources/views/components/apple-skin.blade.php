{{-- The Apple-style skin (system font, grey canvas, pill buttons, segmented tabs, sheets) for screens built on the
     shared ak- / uf- styles. Every rule is scoped under html.apl-on. The class, the tokens and the page header rules
     go into <head> (x-apple-head-style) so a reload never flashes the old navy header; the rest stays here, after
     the page's ui-style, so the cascade order is unchanged. --}}
@once
    @push('header')
        <x-apple-head-style />
    @endpush
@endonce
<x-bank.apple-style />
