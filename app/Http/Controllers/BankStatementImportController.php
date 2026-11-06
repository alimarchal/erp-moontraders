<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankStatementImportRequest;
use App\Models\BankAccount;
use App\Models\BankStatementImport;
use App\Services\BankRegister\BankRegisterException;
use App\Services\BankRegister\BankStatementImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BankStatementImportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:bank-statement-import-list', only: ['index', 'show']),
            new Middleware('can:bank-statement-import-create', only: ['create', 'store', 'confirm', 'destroy']),
        ];
    }

    public function index(): View
    {
        $imports = BankStatementImport::query()->with(['bankAccount', 'importer'])->latest()->paginate(25);

        return view('bank-statement-imports.index', ['imports' => $imports]);
    }

    public function create(): View
    {
        return view('bank-statement-imports.create', [
            'bankAccounts' => BankAccount::active()->orderBy('account_name')->get(['id', 'account_name', 'account_number']),
        ]);
    }

    public function store(BankStatementImportRequest $request, BankStatementImportService $service): RedirectResponse
    {
        $account = BankAccount::findOrFail($request->integer('bank_account_id'));

        try {
            $import = $service->preview($account, $request->file('file'), $request->safe()->only([
                'opening_balance', 'closing_balance', 'control_total_debit', 'control_total_credit',
            ]), $request->user());
        } catch (BankRegisterException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('bank-statement-imports.show', $import)->with('success', 'File read. Check the summary below, then confirm the import.');
    }

    public function show(BankStatementImport $bankStatementImport): View
    {
        return view('bank-statement-imports.show', ['import' => $bankStatementImport->load(['bankAccount', 'importer'])]);
    }

    public function confirm(BankStatementImport $bankStatementImport, BankStatementImportService $service): RedirectResponse
    {
        try {
            $import = $service->commit($bankStatementImport, request()->user());
        } catch (BankRegisterException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('bank-statement-entries.index', ['filter' => ['import_id' => $import->id]])
            ->with('success', "{$import->imported_rows} entries imported, {$import->skipped_rows} duplicate(s) skipped.");
    }

    public function destroy(BankStatementImport $bankStatementImport): RedirectResponse
    {
        if ($bankStatementImport->status !== 'previewed') {
            return back()->with('error', 'Only an import that has not been confirmed can be discarded.');
        }

        Storage::disk(config('bank.attachments.disk'))->delete($bankStatementImport->file_path);
        $bankStatementImport->delete();

        return redirect()->route('bank-statement-imports.index')->with('success', 'Import discarded.');
    }
}
