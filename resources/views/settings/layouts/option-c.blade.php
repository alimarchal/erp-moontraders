{{--
    Settings, Option C (Apple): the shared Apple menu layout with the task categories of
    App\Support\Menus\SettingsMenu::APPLE_CATEGORIES, each page's count, and a "Recently used" row.

    @var list<array{key: string, name: string, blurb: string, color: string, icon: string, items: list<array<string, mixed>>}> $categories
--}}
<x-app-layout>
    <x-menu-page-head />
    <x-menu-apple page="settings" title="Settings" noun="page" :categories="$categories" mode="recent" />
</x-app-layout>
