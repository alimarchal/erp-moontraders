{{--
    Reports, Option C (Apple): the shared Apple menu layout with the task categories of
    App\Support\Menus\ReportsMenu::APPLE_CATEGORIES and a star to pin a report to the top.

    @var list<array{key: string, name: string, blurb: string, color: string, icon: string, items: list<array<string, mixed>>}> $categories
--}}
<x-app-layout>
    <x-menu-page-head />
    <x-menu-apple page="reports" title="Reports" noun="report" :categories="$categories" mode="pins" />
</x-app-layout>
