<?php

namespace App\Http\Controllers;

use App\Models\BankStatementEntry;
use App\Services\BankRegister\BankApprovalRules;
use App\Services\BankRegister\BankEntryApprovalService;
use App\Services\BankRegister\BankRegisterException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class BankApprovalController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:bank-statement-entry-list', only: ['index']),
            new Middleware('can:bank-statement-entry-approve', only: ['approve', 'bulkApprove']),
            new Middleware('can:bank-statement-entry-reject', only: ['reject']),
        ];
    }

    /**
     * The checker's inbox: everything waiting for approval, biggest amounts first.
     */
    public function index(Request $request, BankApprovalRules $rules): View
    {
        $entries = BankStatementEntry::query()
            ->with(['company', 'category', 'bankAccount', 'submitter', 'attachments'])
            ->where('status', BankStatementEntry::STATUS_PENDING_APPROVAL)
            ->orderByRaw('(debit + credit) DESC')
            ->paginate(25);

        $user = $request->user();

        return view('bank-approvals.index', [
            'entries' => $entries,
            'approvedCounts' => $entries->mapWithKeys(fn (BankStatementEntry $e) => [$e->id => $rules->approvedLevels($e)]),
            'thresholds' => config('bank.maker_checker'),
            'canAct' => fn (BankStatementEntry $e) => $this->canAct($e, $user, $rules),
        ]);
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

    private function canAct(BankStatementEntry $entry, $user, BankApprovalRules $rules): bool
    {
        if (! config('bank.maker_checker.allow_self_approval') && $entry->submitted_by === $user->id) {
            return false;
        }

        $approved = $rules->approvedLevels($entry);

        if ($approved->contains(fn ($approval) => $approval->user_id === $user->id)) {
            return false;
        }

        return $approved->count() + 1 < 2 || $user->can('bank-statement-entry-approve-high-value');
    }
}
