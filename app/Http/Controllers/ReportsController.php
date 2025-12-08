<?php

namespace App\Http\Controllers;

class ReportsController extends Controller
{
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
