{{-- The Quick look window shared by the Goods Issues and Tickets lists (classes gq-*): a centred card on
     desktop, a bottom sheet on phones, with the scrim, chips, amount, line list and footer button. --}}
<style>
        .gq-wrap { position: fixed; inset: 0; z-index: 60; display: flex; align-items: center; justify-content: center; padding: 24px 16px; }
        .gq-scrim { position: absolute; inset: 0; background: rgba(0,0,0,.28); -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px); }
        .gq-box { position: relative; width: 100%; max-width: 560px; max-height: min(86vh, 760px); display: flex; flex-direction: column; overflow: hidden; background: #fff; border-radius: 22px; box-shadow: 0 0 0 1px rgba(0,0,0,.05), 0 24px 64px -12px rgba(0,0,0,.35); transform-origin: center; }
        .gq-head { padding: 20px 22px 0; }
        .gq-top { display: flex; align-items: center; gap: 8px; }
        .gq-num { margin: 0; font-size: 15px; font-weight: 600; color: #1d1d1f; letter-spacing: -.01em; }
        .gq-close { margin-left: auto; width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 50%; background: rgba(120,120,128,.14); color: #3a3a40; cursor: pointer; transition: transform .12s ease; }
        .gq-close:active { transform: scale(.94); }
        .gq-close:focus-visible { outline: 2px solid #0071e3; outline-offset: 2px; }
        .gq-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
        .gq-chip { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 980px; font-size: 12px; font-weight: 600; }
        .gq-chip-dot::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .gq-green { background: rgba(52,199,89,.14); color: #1f7a35; } .gq-amber { background: rgba(255,159,10,.16); color: #8a4b00; }
        .gq-red { background: rgba(255,59,48,.12); color: #c4161c; } .gq-grey { background: rgba(120,120,128,.14); color: #3a3a40; }
        .gq-blue { background: rgba(0,113,227,.1); color: #0058b0; }
        .gq-amount { margin: 14px 0 0; font-size: 34px; font-weight: 600; letter-spacing: -.03em; line-height: 1.05; color: #1d1d1f; font-variant-numeric: tabular-nums; }
        .gq-amount small { font-size: 17px; font-weight: 500; color: #6e6e73; margin-right: 4px; }
        .gq-who { margin: 6px 0 0; font-size: 14px; line-height: 1.5; color: #424245; }
        .gq-who b { color: #1d1d1f; font-weight: 600; }
        .gq-who span { color: #6e6e73; }
        .gq-list-head { display: flex; justify-content: space-between; margin: 16px 22px 0; padding-bottom: 6px; border-bottom: 1px solid rgba(60,60,67,.14); font-size: 11.5px; font-weight: 600; color: #6e6e73; }
        .gq-list { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; margin: 0; padding: 0 22px; list-style: none; }
        .gq-list li { display: flex; align-items: baseline; gap: 12px; padding: 9px 0; border-bottom: 1px solid rgba(60,60,67,.08); font-size: 14px; }
        .gq-list li:last-child { border-bottom: 0; }
        .gq-line-name { flex: 1 1 auto; min-width: 0; color: #1d1d1f; overflow-wrap: anywhere; }
        .gq-line-name small { display: block; margin-top: 1px; font-size: 12px; color: #6e6e73; }
        .gq-line-val { flex: none; font-weight: 600; color: #1d1d1f; font-variant-numeric: tabular-nums; }
        .gq-supp { margin-left: 4px; padding: 0 7px; border-radius: 980px; font-size: 10.5px; font-weight: 600; background: rgba(175,82,222,.14); color: #7a2fa3; }
        .gq-foot { padding: 12px 22px 18px; border-top: 1px solid rgba(60,60,67,.14); background: #fbfbfd; }
        .gq-totals { display: flex; justify-content: space-between; gap: 12px; font-size: 14px; color: #424245; }
        .gq-totals b { color: #1d1d1f; font-variant-numeric: tabular-nums; }
        .gq-open { display: flex; align-items: center; justify-content: center; gap: 6px; height: 40px; margin-top: 12px; border-radius: 980px; background: #0071e3; color: #fff !important; font-size: 14.5px; font-weight: 500; text-decoration: none !important; transition: transform .12s ease, background-color .15s ease; }
        .gq-open:hover { background: #0077ed; }
        .gq-open:active { transform: scale(.98); }
        .gq-enter { transition: opacity .2s cubic-bezier(.23,1,.32,1), transform .2s cubic-bezier(.23,1,.32,1); }
        .gq-leave { transition: opacity .12s ease-out, transform .12s ease-out; }
        .gq-from { opacity: 0; transform: scale(.96) translateY(6px); }
        .gq-fade-enter { transition: opacity .2s ease; } .gq-fade-leave { transition: opacity .12s ease; } .gq-fade-from { opacity: 0; }
        @media (max-width: 640px) {
            .gq-wrap { align-items: flex-end; padding: 0; }
            .gq-box { max-width: none; max-height: 90vh; border-radius: 20px 20px 0 0; }
            .gq-from { transform: translateY(24px); opacity: 0; }
            .gq-head { padding: 16px 18px 0; } .gq-list-head { margin: 14px 18px 0; } .gq-list { padding: 0 18px; } .gq-foot { padding: 12px 18px calc(16px + env(safe-area-inset-bottom)); }
            .gq-amount { font-size: 30px; }
        }
        @media (prefers-reduced-motion: reduce) { .gq-enter, .gq-leave { transition: opacity .15s ease; } .gq-from { transform: none; } }
</style>
