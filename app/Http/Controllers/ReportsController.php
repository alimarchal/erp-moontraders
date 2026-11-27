<?php

namespace App\Http\Controllers;

use App\Enums\PageLayout;
use App\Models\AppSetting;
use App\Support\Menus\ReportsMenu;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class ReportsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:view-any-report'),
        ];
    }

    /**
     * Display the reports index page in the layout a super admin chose on Settings → Appearance.
     */
    public function index(): View
    {
        return match (AppSetting::layoutFor('reports')) {
            PageLayout::Classic => view('reports.index'),
            PageLayout::OptionA => view('reports.layouts.option-a', ['groups' => (new ReportsMenu)->groupsFor(auth()->user())]),
            PageLayout::OptionB => view('reports.layouts.option-b', ['groups' => (new ReportsMenu)->groupsFor(auth()->user())]),
            PageLayout::OptionC => view('reports.layouts.option-c', ['categories' => (new ReportsMenu)->appleCategoriesFor(auth()->user())]),
        };
    }
}
