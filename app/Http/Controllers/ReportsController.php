<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

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
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('reports.index');
    }
}
