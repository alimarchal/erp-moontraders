<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class BankRegisterHelpController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:bank-statement-entry-list')];
    }

    /**
     * Urdu (right-to-left) user guide with screenshots of every screen.
     */
    public function __invoke(): View
    {
        return view('bank-register.help');
    }
}
