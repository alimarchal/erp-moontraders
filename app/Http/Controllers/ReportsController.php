<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
