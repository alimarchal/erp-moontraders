<?php

namespace App\Support\Menus;

use App\Models\User;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * A page of links grouped by area (the Settings and Reports index pages). Each layout of the
 * page reads the same groups, so the permission checks live here once and not in every view.
 *
 * @phpstan-type MenuItemDefinition array{label: string, description: string, url: string, permission: string|list<string>, icon?: string, count?: Closure(): int, keywords?: string}
 * @phpstan-type MenuGroupDefinition array{key: string, name: string, urdu: string, color: string, icon: string, items: list<MenuItemDefinition>}
 * @phpstan-type MenuItem array{id: string, label: string, description: string, url: string, icon: string, count: int|null, search: string}
 * @phpstan-type MenuGroup array{key: string, name: string, urdu: string, color: string, icon: string, items: list<MenuItem>}
 */
abstract class Menu
{
    /**
     * The categories of the page's Apple layout (Option C): key, name, blurb, colour (CSS), icon and the ids of the
     * links in each. Pages without an Apple layout leave it empty.
     *
     * @var list<array{key: string, name: string, blurb: string, color: string, icon: string, ids: list<string>}>
     */
    public const APPLE_CATEGORIES = [];

    /**
     * Every group and link of the page, before permissions are applied. A permission given as a
     * list means the user needs any one of them.
     *
     * @return list<MenuGroupDefinition>
     */
    abstract protected function definitions(): array;

    /**
     * The groups the user may see, each holding only the links the user is allowed to open.
     * Groups left with no links are dropped. Counts are only run for links that are shown.
     *
     * @return list<MenuGroup>
     */
    public function groupsFor(User $user): array
    {
        $groups = [];

        foreach ($this->definitions() as $group) {
            $items = [];

            foreach ($group['items'] as $item) {
                if (! $this->permits($user, $item['permission'])) {
                    continue;
                }

                $items[] = [
                    'id' => Str::slug($item['label']),
                    'label' => $item['label'],
                    'description' => $item['description'],
                    'url' => $item['url'],
                    'icon' => $item['icon'] ?? $group['icon'],
                    'count' => isset($item['count']) ? (int) ($item['count'])() : null,
                    'search' => Str::lower(implode(' ', [$item['label'], $item['description'], $item['keywords'] ?? '', $group['name']])),
                ];
            }

            if ($items !== []) {
                $groups[] = [...$group, 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * The user's links in the Apple layout's categories. Empty categories are dropped; a link no category lists lands
     * in a last "More" category, so a new link never disappears from the page.
     *
     * @return list<array{key: string, name: string, blurb: string, color: string, icon: string, items: list<MenuItem>}>
     */
    public function appleCategoriesFor(User $user): array
    {
        $items = collect($this->groupsFor($user))->flatMap(fn (array $group) => $group['items'])->keyBy('id');
        $placed = [];
        $categories = [];

        foreach ([...static::APPLE_CATEGORIES, ['key' => 'more', 'name' => 'More', 'blurb' => 'Everything else.', 'color' => '#8e8e93', 'icon' => 'doc', 'ids' => null]] as $category) {
            $ids = $category['ids'] ?? $items->keys()->diff($placed)->values()->all();
            $categoryItems = collect($ids)
                ->filter(fn (string $id) => $items->has($id))
                ->map(fn (string $id) => [...$items[$id], 'search' => Str::lower($items[$id]['search'].' '.$category['name'])])
                ->values()
                ->all();
            $placed = [...$placed, ...$ids];

            if ($categoryItems !== []) {
                $categories[] = [...Arr::except($category, 'ids'), 'items' => $categoryItems];
            }
        }

        return $categories;
    }

    /**
     * @param  string|list<string>  $permission
     */
    protected function permits(User $user, string|array $permission): bool
    {
        return is_array($permission) ? $user->canAny($permission) : $user->can($permission);
    }
}
