<?php

namespace App\Http\Controllers;

use App\Models\Product;
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
     * Display the settings index page.
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

        return view('settings.index', compact('productCount'));
    }
}
