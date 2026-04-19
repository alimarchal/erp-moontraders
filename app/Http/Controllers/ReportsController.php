<?php

namespace App\Http\Controllers;

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
     * Display the reports index page.
     *
     * @return View
     */
    public function index()
    {
        return view('reports.index');
    }
}
