{{-- Expense voucher page: a summary card that reads like the voucher (amount, who, method, status) with the
     next actions, then the details, the ledger effect, the notes paid out and the attachments. --}}
<style>
    .ev-show { display: flex; flex-direction: column; gap: 16px; }
    html.apl-on .ev-show .ak-card { border: 0; border-radius: 20px; box-shadow: 0 0 0 1px rgba(0,0,0,.04), 0 1px 2px rgba(0,0,0,.05), 0 8px 24px -8px rgba(0,0,0,.14); }
    .ev-banner { padding: 12px 16px; border-radius: 14px; font-size: 14px; line-height: 1.5; }
    .ev-banner-red { background: rgba(255,59,48,.1); color: #9b1c1c; }
    .ev-banner-amber { background: rgba(255,159,10,.14); color: #7a4100; }
    .ev-banner-sub { display: block; color: inherit; opacity: .85; }

    .ev-summary { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 16px 24px; padding: 22px 24px; }
    .ev-kicker { margin: 0; font-size: 13px; font-weight: 500; color: #6e6e73; }
    .ev-amount { margin: 6px 0 0; font-size: 36px; font-weight: 600; line-height: 1.05; letter-spacing: -.03em; color: #1d1d1f; font-variant-numeric: tabular-nums; }
    .ev-amount small { margin-right: 6px; font-size: 18px; font-weight: 500; letter-spacing: 0; color: #6e6e73; }
    .ev-who { margin: 8px 0 0; font-size: 15px; color: #424245; overflow-wrap: anywhere; }
    .ev-who b { color: #1d1d1f; font-weight: 600; }
    .ev-who a { color: #0066cc; text-decoration: none; }
    .ev-who a:hover { text-decoration: underline; }
    .ev-who span { color: #6e6e73; }
    .ev-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
    .ev-chip { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 980px; font-size: 12px; font-weight: 600; background: rgba(120,120,128,.14); color: #3a3a40; white-space: nowrap; }
    .gl-stage { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 980px; font-size: 12px; font-weight: 600; white-space: nowrap; }
    .gl-stage::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .gl-stage-green { background: rgba(52,199,89,.14); color: #1f7a35; }
    .gl-stage-amber { background: rgba(255,159,10,.16); color: #8a4b00; }
    .gl-stage-red { background: rgba(255,59,48,.12); color: #c4161c; }
    .gl-stage-grey { background: rgba(120,120,128,.14); color: #3a3a40; }

    .ev-summary-actions { display: flex; flex-wrap: wrap; align-content: flex-start; justify-content: flex-end; gap: 8px; max-width: 420px; }
    .ev-summary-actions form { margin: 0; }
    html.apl-on .ev-summary-actions .ak-btn { height: 38px; padding: 0 16px; border-radius: 980px !important; text-transform: none; font-weight: 500; transition: transform .1s ease-out, background-color .15s ease; }
    html.apl-on .ev-summary-actions .ak-btn:active { transform: scale(.97); }
    html.apl-on .ev-summary-actions .ak-btn-primary { background: #0071e3 !important; border-color: #0071e3 !important; color: #fff !important; }
    html.apl-on .ev-summary-actions .ak-btn-ghost { background: rgba(120,120,128,.14) !important; border: 0 !important; color: #1d1d1f !important; }
    html.apl-on .ev-summary-actions .ev-danger { color: #d70015 !important; }
    .ev-hint { margin: 0; font-size: 13px; color: #6e6e73; }
    .ev-summary-actions .ev-hint { flex-basis: 100%; text-align: right; }

    .ev-steps { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 4px 0 0; padding: 14px 0 0; list-style: none; border-top: 1px solid rgba(60,60,67,.1); }
    .ev-steps li { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 500; color: #6e6e73; }
    .ev-steps li + li::before { content: ''; width: 22px; height: 1.5px; background: rgba(60,60,67,.18); }
    .ev-steps .dot { width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: rgba(120,120,128,.14); font-size: 11.5px; }
    .ev-steps li.is-done { color: #1f7a35; } .ev-steps li.is-done .dot { background: #34c759; color: #fff; }
    .ev-steps li.is-now { color: #1d1d1f; } .ev-steps li.is-now .dot { background: #0071e3; color: #fff; }
    .ev-steps li.is-bad { color: #c4161c; } .ev-steps li.is-bad .dot { background: #ff3b30; color: #fff; }

    .ev-grid { display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr); align-items: start; }
    @media (min-width: 1024px) { .ev-grid { grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); } }
    .ev-side { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    .ev-pad { padding: 18px 22px; }
    .ev-h2 { margin: 0 0 10px; font-size: 17px; font-weight: 600; letter-spacing: -.01em; color: #1d1d1f; }
    .ev-h2 + .ev-hint { margin: -6px 0 10px; }
    .ev-dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 24px; margin: 0; }
    .ev-dl dt { font-size: 12.5px; font-weight: 500; color: #6e6e73; }
    .ev-dl dd { margin: 2px 0 0; font-size: 15px; font-weight: 500; color: #1d1d1f; overflow-wrap: anywhere; }
    .ev-dl .ev-wide { grid-column: 1 / -1; }
    .ev-sub { display: block; font-size: 13px; font-weight: 400; color: #6e6e73; }
    .ev-lines { width: 100%; border-collapse: collapse; font-size: 14px; }
    .ev-lines th { text-align: left; font-size: 12px; font-weight: 600; color: #6e6e73; padding: 6px 4px; border-bottom: 1px solid rgba(60,60,67,.14); }
    .ev-lines td { padding: 7px 4px; border-bottom: 1px solid rgba(60,60,67,.08); color: #1d1d1f; }
    .ev-lines .n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .ev-lines .ev-total td { font-weight: 600; border-bottom: 0; }
    .ev-file-list { display: flex; flex-wrap: wrap; gap: 8px; }
    html.apl-on .ev-file-list .ak-btn { border-radius: 980px !important; background: rgba(120,120,128,.14) !important; border: 0 !important; color: #1d1d1f !important; text-transform: none; }

    @media (max-width: 767px) {
        .ev-summary { grid-template-columns: minmax(0, 1fr); padding: 18px; }
        .ev-summary-actions { justify-content: stretch; max-width: none; }
        .ev-summary-actions > *, .ev-summary-actions form, .ev-summary-actions form .ak-btn { flex: 1 1 calc(50% - 4px); justify-content: center; }
        .ev-summary-actions .ev-hint { text-align: left; }
        .ev-amount { font-size: 30px; }
        .ev-dl { grid-template-columns: minmax(0, 1fr); }
        .ev-pad { padding: 16px 18px; }
    }
    @media (prefers-reduced-motion: reduce) { html.apl-on .ev-summary-actions .ak-btn:active { transform: none; } }
    @media print { .ev-summary-actions, .ev-steps { display: none !important; } }
</style>
