{{-- Import from Excel (payroll-import-excel): download the template, fill it by employee code, upload it. Opens on the
     "pr-import" event, and again by itself when an upload was refused, listing each mistake with its row. --}}
@php
    $isSalaries = $importKind === 'salaries';
    $importErrors = session('import_kind') === $importKind ? (array) session('import_errors', []) : [];
    $importMonth = $isSalaries ? null : ($month ?? now()->format('Y-m'));
@endphp
<div x-data="{ open: @js($importErrors !== []), busy: false, file: '' }" @pr-import.window="open = true; busy = false" @keydown.escape.window="open = false">
    <div class="pr-sheet-wrap" x-show="open" x-cloak role="dialog" aria-modal="true" aria-labelledby="pr-import-title">
        <div class="pr-scrim" @click="open = false" x-show="open" x-transition.opacity></div>
        <form method="POST" action="{{ route('payroll.import.store', $importKind) }}" enctype="multipart/form-data" class="pr-sheet" style="max-width:620px" x-show="open" @submit="busy = true"
            x-transition:enter="pr-enter" x-transition:enter-start="pr-from" x-transition:enter-end="pr-to" x-transition:leave="pr-leave" x-transition:leave-start="pr-to" x-transition:leave-end="pr-from">
            @csrf
            <div class="pr-sheet-head">
                <div>
                    <h3 id="pr-import-title">{{ $isSalaries ? 'Import salary set-up from Excel' : 'Import incentives & adjustments from Excel' }}
                        <button type="button" class="rg-info no-print" @click.stop="$dispatch('rg-help', { topic: 'import' })" aria-label="Help: Excel import" title="Help: Excel import">i</button></h3>
                    <p>{{ $isSalaries ? 'Basic salary, salary type, pay method and each earning or deduction, by employee code.' : 'Many employees, different amounts, one time or every month, by employee code.' }}</p>
                </div>
                <button type="button" class="pr-x" @click="open = false" aria-label="Close">✕</button>
            </div>
            <div class="pr-sheet-body" style="display:grid; gap:14px">
                @if ($importErrors)
                    <div role="alert" style="border-radius:12px; padding:10px 14px; background:#fff1f0; color:#a50e0e; font-size:13.5px">
                        <b>Nothing was imported. {{ session('import_error_count', count($importErrors)) }} {{ \Illuminate\Support\Str::plural('mistake', (int) session('import_error_count', count($importErrors))) }} to fix:</b>
                        <ul style="margin:6px 0 0 18px; max-height:180px; overflow-y:auto; list-style:disc">
                            @foreach ($importErrors as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <ol style="margin:0 0 0 18px; display:grid; gap:10px; font-size:14px; list-style:decimal">
                    <li>
                        <a href="{{ route('payroll.import.template', array_filter(['kind' => $importKind, 'month' => $importMonth])) }}" class="pr-btn pr-btn-sm">Download template</a>
                        <span class="pr-label" style="display:block; font-size:12.5px; margin-top:4px">
                            @if ($isSalaries)
                                Comes filled with every employee and what they have today. Drop-down lists for salary type, pay by, category and calculation.
                            @else
                                One row per employee for {{ \Carbon\Carbon::parse($importMonth.'-01')->format('F Y') }}. Drop-down lists for category and One time / Every month.
                            @endif
                        </span>
                    </li>
                    <li>Fill it in Excel by employee code; keep the sheet names and the headings in row 1.</li>
                    <li>
                        <label class="pr-field"><span>Upload the filled file (.xlsx)</span>
                            <input type="file" name="file" accept=".xlsx,.xls" required class="pr-input" @change="file = $event.target.files[0]?.name || ''">
                        </label>
                    </li>
                </ol>

                @if ($isSalaries)
                    <label style="display:flex; align-items:flex-start; gap:8px; font-size:14px">
                        <input type="checkbox" class="pr-check" name="replace" value="1" checked style="margin-top:3px">
                        <span>Replace: employees listed in “Earnings &amp; Deductions” keep only the categories listed there
                            <span class="pr-label" style="display:block; font-size:12px">Untick to only add or change the listed ones and keep the rest.</span></span>
                    </label>
                @endif
                <p class="pr-label" style="font-size:12.5px; margin:0">Every row is checked first. If any row has a mistake nothing is saved and each mistake is listed here with its row number.</p>
            </div>
            <div class="pr-sheet-foot">
                <span class="pr-foot-note" x-text="file || 'No file chosen'"></span>
                <button type="button" class="pr-btn" @click="open = false">Cancel</button>
                <button type="submit" class="pr-btn pr-btn-primary" :disabled="busy || ! file" x-text="busy ? 'Importing…' : 'Import'">Import</button>
            </div>
        </form>
    </div>
</div>
