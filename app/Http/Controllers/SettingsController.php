<?php

namespace App\Http\Controllers;

class SettingsController extends Controller
{
    /**
     * Display the settings index page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('settings.index');
    }
}
