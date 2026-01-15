<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Display the settings index page.
     */
    public function index(): View
    {
        return view('settings.index');
    }
}
