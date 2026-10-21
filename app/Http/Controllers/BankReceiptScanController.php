<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankReceiptScanRequest;
use App\Models\BankAccount;
use App\Models\BankReceiptScan;
use App\Models\BankStatementEntry;
use App\Services\BankRegister\BankReceiptScanService;
use App\Services\BankRegister\BankRegisterException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankReceiptScanController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:bank-receipt-scan-list', only: ['index', 'file']),
            new Middleware('can:bank-receipt-scan-create', only: ['store']),
            new Middleware('can:bank-receipt-scan-link', only: ['link']),
            new Middleware('can:bank-receipt-scan-delete', only: ['destroy']),
        ];
    }

    /**
     * The scan inbox: every receipt photographed or scanned and not yet tied to a bank line.
     */
    public function index(Request $request, BankReceiptScanService $service): View
    {
        $filters = (array) $request->input('filter', []);
        $status = $filters['status'] ?? BankReceiptScan::STATUS_PENDING;

        $scans = BankReceiptScan::query()
            ->with(['bankAccount', 'uploader', 'entry'])
            ->where('status', $status)
            ->when($filters['bank_account_id'] ?? null, fn ($q, $v) => $q->where('bank_account_id', $v))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('scan_date', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('scan_date', '<=', $v))
            ->orderByDesc('scan_date')
            ->orderByDesc('id')
            ->paginate(24)
            ->withQueryString();

        return view('bank-receipt-scans.index', [
            'scans' => $scans,
            'filters' => $filters + ['status' => $status],
            'suggestions' => $status === BankReceiptScan::STATUS_PENDING ? $scans->getCollection()->mapWithKeys(fn (BankReceiptScan $s) => [$s->id => $service->suggestions($s)]) : collect(),
            'pendingCount' => BankReceiptScan::where('status', BankReceiptScan::STATUS_PENDING)->count(),
            'bankAccounts' => BankAccount::orderBy('account_name')->get(['id', 'account_name']),
        ]);
    }

    public function store(BankReceiptScanRequest $request, BankReceiptScanService $service): RedirectResponse
    {
        $scans = $service->store($request->file('files'), $request->safe()->only(['bank_account_id', 'scan_date', 'amount', 'direction', 'note']), $request->user());

        return redirect()->route('bank-receipt-scans.index')->with('success', $scans->count().' scan(s) saved in the inbox. Link each one to its bank entry when you have it.');
    }

    public function link(Request $request, BankReceiptScan $bankReceiptScan, BankReceiptScanService $service): RedirectResponse
    {
        $data = $request->validate(['bank_statement_entry_id' => ['required', 'exists:bank_statement_entries,id']]);

        try {
            $service->link($bankReceiptScan, BankStatementEntry::findOrFail($data['bank_statement_entry_id']), $request->user());
        } catch (BankRegisterException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Receipt attached to the bank entry.');
    }

    public function destroy(BankReceiptScan $bankReceiptScan): RedirectResponse
    {
        if ($bankReceiptScan->status === BankReceiptScan::STATUS_LINKED) {
            return back()->with('error', 'A linked scan is already an attachment of its entry. Remove it from the entry instead.');
        }

        Storage::disk(config('bank.attachments.disk'))->delete($bankReceiptScan->file_path);
        $bankReceiptScan->delete();

        return back()->with('success', 'Scan deleted.');
    }

    /**
     * Show the scan in the browser (image or PDF) from the private disk.
     */
    public function file(BankReceiptScan $bankReceiptScan): StreamedResponse
    {
        $disk = Storage::disk(config('bank.attachments.disk'));
        abort_unless($disk->exists($bankReceiptScan->file_path), 404);

        return $disk->response($bankReceiptScan->file_path, $bankReceiptScan->file_name);
    }
}
