<?php

namespace App\Models;

use App\Enums\PageLayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * One app-wide choice, stored as a key and a value (for example "layout.dashboard" => "a").
 * All rows are read once and cached; saving a value clears the cache.
 */
class AppSetting extends Model
{
    public const CACHE_KEY = 'app_settings.all';

    /**
     * The pages whose layout a super admin can choose, and the layouts each one offers.
     *
     * @var array<string, array<int, PageLayout>>
     */
    public const LAYOUT_PAGES = [
        'dashboard' => [PageLayout::Classic, PageLayout::OptionA],
        'settings' => [PageLayout::Classic, PageLayout::OptionA, PageLayout::OptionB],
        'reports' => [PageLayout::Classic, PageLayout::OptionA, PageLayout::OptionB, PageLayout::OptionC],
        'goods_receipt_notes' => [PageLayout::Classic, PageLayout::OptionA, PageLayout::OptionB, PageLayout::OptionC, PageLayout::OptionD, PageLayout::OptionE, PageLayout::OptionF, PageLayout::OptionG, PageLayout::OptionH, PageLayout::OptionI],
        'goods_receipt_notes_index' => [PageLayout::Classic, PageLayout::OptionA, PageLayout::OptionB, PageLayout::OptionC, PageLayout::OptionD],
    ];

    protected $fillable = ['key', 'value', 'updated_by'];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function valueOf(string $key, ?string $default = null): ?string
    {
        /** @var array<string, string|null> $all */
        $all = Cache::rememberForever(self::CACHE_KEY, fn (): array => self::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default;
    }

    public static function put(string $key, ?string $value, ?int $userId = null): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $userId]);
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The layout chosen for a page, or Classic when none is chosen or the stored value is
     * not one the page offers.
     */
    public static function layoutFor(string $page): PageLayout
    {
        $layout = PageLayout::tryFrom((string) self::valueOf('layout.'.$page, PageLayout::Classic->value));

        return in_array($layout, self::LAYOUT_PAGES[$page] ?? [], true) ? $layout : PageLayout::Classic;
    }
}
