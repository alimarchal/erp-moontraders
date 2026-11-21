{{-- The part of the Apple skin that must be in <head>: the --apl tokens, the grey canvas and the page header
     (x-page-header buttons). Pushed there by x-apple-skin so the nav and header are drawn in the skin from the
     first frame; the rest of the skin (x-bank.apple-style) stays in the page, after the shared ui-style. --}}
<script>document.documentElement.classList.add('apl-on');</script>
<style>
    :root {
        --apl-bg: #f5f5f7;
        --apl-surface: #ffffff;
        --apl-fill: rgba(118, 118, 128, .12);
        --apl-fill-2: rgba(118, 118, 128, .08);
        --apl-sep: rgba(60, 60, 67, .14);
        --apl-label: #1d1d1f;
        --apl-label-2: #6e6e73;
        --apl-label-3: #86868b;
        --apl-blue: #0071e3;
        --apl-blue-hover: #0077ed;
        --apl-blue-tint: rgba(0, 113, 227, .1);
        --apl-green: #1a7f37;
        --apl-green-tint: rgba(52, 199, 89, .14);
        --apl-red: #d70015;
        --apl-red-tint: rgba(255, 59, 48, .12);
        --apl-orange: #b25000;
        --apl-orange-tint: rgba(255, 149, 0, .16);
        --apl-indigo-tint: rgba(88, 86, 214, .12);
        --apl-radius: 18px;
        --apl-radius-sm: 10px;
        --apl-shadow: 0 1px 1px rgba(0, 0, 0, .02), 0 4px 18px rgba(0, 0, 0, .05);
        --apl-ease-out: cubic-bezier(.23, 1, .32, 1);
        --apl-font: -apple-system, BlinkMacSystemFont, "SF Pro Text", "SF Pro Display", system-ui, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }

    /* Canvas: the whole page sits on Apple's light grey, cards float on it */
    .apl-on body { background: var(--apl-bg); }
    .apl-on body .min-h-screen { background: var(--apl-bg) !important; }

    /* ---------- Header: the canvas material with a hairline (not sticky: it is tall and would hide rows) ---------- */
    .apl-on header.bg-white {
        background: rgba(245, 245, 247, .72) !important;
        -webkit-backdrop-filter: saturate(180%) blur(20px); backdrop-filter: saturate(180%) blur(20px);
        box-shadow: 0 1px 0 var(--apl-sep) !important;
    }

    /* ---------- Screens on the older page header (x-page-header) and Tailwind tables: Categories, report sheets ---------- */
    .apl-on header.bg-white h2 { font-family: var(--apl-font); font-size: 28px; font-weight: 700; letter-spacing: -.022em; color: var(--apl-label); }
    .apl-on header.bg-white .bg-blue-950 {
        height: 36px; padding: 0 12px; border: 0; border-radius: 980px; background: var(--apl-fill) !important; color: var(--apl-label) !important;
        transition: background-color .2s ease, transform .16s var(--apl-ease-out);
    }
    .apl-on header.bg-white .bg-blue-950 svg { width: 20px; height: 20px; }
    .apl-on header.bg-white .bg-blue-950:hover { background: rgba(118, 118, 128, .2) !important; }
    .apl-on header.bg-white .bg-blue-950:active { transform: scale(.95); }
    .apl-on header.bg-white .flex > a.bg-blue-950:first-child, .apl-on header.bg-white .flex > div:first-child > a.bg-blue-950 { background: var(--apl-blue) !important; color: #fff !important; }
    /* The create button: a compact blue pill on the same 36px line as the grey icon buttons */
    .apl-on header.bg-white a.bg-blue-950[data-create] {
        gap: 6px; padding: 0 14px 0 11px; margin-right: 6px; font-family: var(--apl-font); font-size: 14px; font-weight: 500;
        letter-spacing: -.01em; text-transform: none; box-shadow: 0 1px 2px rgba(0, 113, 227, .25);
    }
    .apl-on header.bg-white a.bg-blue-950[data-create] svg { width: 16px; height: 16px; stroke-width: 2.4; }
    .apl-on header.bg-white a.bg-blue-950[data-create]:hover { background: #0077ed !important; }
    .apl-on header.bg-white a.bg-blue-950[data-create]:focus-visible { outline: 2px solid var(--apl-blue); outline-offset: 2px; box-shadow: none; }
    @media (max-width: 767px) {
        .apl-on header.bg-white a.bg-blue-950[data-create] { width: 36px; padding: 0; justify-content: center; margin-right: 2px; }
        .apl-on header.bg-white a.bg-blue-950[data-create] svg { width: 18px; height: 18px; }
    }
</style>
