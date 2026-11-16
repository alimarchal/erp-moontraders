<?php

namespace App\Http\Controllers;

use App\Enums\PageLayout;
use App\Models\AppSetting;
use App\Models\Product;
use App\Support\Menus\SettingsMenu;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class SettingsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:setting-view'),
        ];
    }

    /**
     * Display the settings index page in the layout a super admin chose on Settings → Appearance.
     */
    public function index(): View
    {
        $user = auth()->user();
        $isScopedUser = $user->is_super_admin !== 'Yes'
            && ! $user->hasRole('super-admin')
            && ! $user->hasRole('admin')
            && $user->supplier_id;

        $productCount = Product::query()
            ->when($isScopedUser, fn ($q) => $q->where('supplier_id', (int) $user->supplier_id))
            ->count();

        return match (AppSetting::layoutFor('settings')) {
            PageLayout::Classic => view('settings.index', compact('productCount')),
            PageLayout::OptionA => view('settings.layouts.option-a', ['groups' => (new SettingsMenu($productCount))->groupsFor($user)]),
            PageLayout::OptionB => view('settings.layouts.option-b', ['groups' => (new SettingsMenu($productCount))->groupsFor($user)]),
        };
    }
}
