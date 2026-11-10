<?php

namespace App\Http\Controllers;

use App\Models\BankStatementEntry;
use App\Services\BankRegister\BankEntryApprovalService;
use App\Services\BankRegister\BankRegisterException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BankApprovalController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:bank-statement-entry-approve', only: ['approve', 'bulkApprove']),
            new Middleware('can:bank-statement-entry-reject', only: ['reject']),
        ];
    }

    public function approve(Request $request, BankStatementEntry $bankStatementEntry, BankEntryApprovalService $approvals): RedirectResponse
    {
        try {
            $entry = $approvals->approve($bankStatementEntry, $request->user(), $request->input('comment'));
        } catch (BankRegisterException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $entry->status === BankStatementEntry::STATUS_POSTED
            ? "Approved and posted. Receipt {$entry->receipt_no}."
            : 'Approved. A senior approver is still needed before it is posted.');
    }

    public function reject(Request $request, BankStatementEntry $bankStatementEntry, BankEntryApprovalService $approvals): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $approvals->reject($bankStatementEntry, $request->user(), $data['reason']);
        } catch (BankRegisterException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Entry rejected and sent back to the maker.');
    }

    public function bulkApprove(Request $request, BankEntryApprovalService $approvals): RedirectResponse
    {
        $ids = array_map('intval', (array) $request->input('ids', []));

        if ($ids === []) {
            return back()->with('error', 'Tick at least one entry first.');
        }

        try {
            $result = $approvals->bulkApprove($ids, $request->user());
        } catch (BankRegisterException $e) {
            return back()->with('error', $e->getMessage());
        }

        $redirect = back()->with('success', "{$result['approved']} entries approved.");

        return $result['failed'] ? $redirect->with('warning', collect($result['failed'])->map(fn ($m, $id) => "#{$id}: {$m}")->take(5)->implode("\n")) : $redirect;
    }
}
