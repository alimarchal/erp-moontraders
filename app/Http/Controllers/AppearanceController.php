<?php

namespace App\Http\Controllers;

use App\Enums\PageLayout;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Settings → Appearance: a super admin picks, for everyone, which layout the dashboard,
 * the Settings page and the Reports page use.
 */
class AppearanceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:manage-appearance'),
        ];
    }

    public function edit(): View
    {
        $current = collect(AppSetting::LAYOUT_PAGES)
            ->mapWithKeys(fn (array $layouts, string $page) => [$page => AppSetting::layoutFor($page)->value])
            ->all();

        return view('settings.appearance', ['current' => $current]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = collect(AppSetting::LAYOUT_PAGES)
            ->mapWithKeys(fn (array $layouts, string $page) => [
                $page => ['required', Rule::in(array_map(fn (PageLayout $layout) => $layout->value, $layouts))],
            ])
            ->all();

        $validated = $request->validate($rules);

        foreach ($validated as $page => $layout) {
            AppSetting::put('layout.'.$page, $layout, $request->user()->id);
        }

        return redirect()->route('settings.appearance.edit')->with('success', 'Layouts saved. Everyone sees the new layouts from their next page load.');
    }
}
