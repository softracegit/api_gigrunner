<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'GigRunner API')</title>
    <script>
        (function () {
            try {
                var t = localStorage.getItem('gigrunner_theme');
                if (t !== 'light' && t !== 'dark') {
                    t = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
                }
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
    <style>
        :root,
        html[data-theme="dark"] {
            --bg: #0f1419;
            --bg-glow: #1b2a3a;
            --panel: #1a222c;
            --panel-2: #141b24;
            --text: #e8eef4;
            --muted: #8b9aab;
            --accent: #3d9cfd;
            --accent-2: #2a7fd4;
            --brand: #fdb05e;
            --danger: #f07178;
            --ok: #7fd99a;
            --border: #2a3542;
            --input: #121820;
            --hover: #222b36;
            --active: #243040;
            --endpoint-head: #161e27;
            --code-muted: #b7c6d6;
            --btn-fg: #041018;
            --nav-bg: color-mix(in srgb, #1a222c 80%, transparent);
            --method-get: #7fd99a;
            --method-post: #3d9cfd;
            --theme-icon-sun: none;
            --theme-icon-moon: block;
            color-scheme: dark;
        }
        html[data-theme="light"] {
            --bg: #f4f6f9;
            --bg-glow: #dce8f5;
            --panel: #ffffff;
            --panel-2: #eef2f7;
            --text: #15202b;
            --muted: #5b6b7c;
            --accent: #1a7fd4;
            --accent-2: #1569b0;
            --brand: #e8942e;
            --danger: #d64545;
            --ok: #2a9d5c;
            --border: #d5dde6;
            --input: #f7f9fc;
            --hover: #e8eef5;
            --active: #dce6f0;
            --endpoint-head: #f0f4f8;
            --code-muted: #4a5a6a;
            --btn-fg: #ffffff;
            --nav-bg: color-mix(in srgb, #ffffff 88%, transparent);
            --method-get: #2a9d5c;
            --method-post: #1a7fd4;
            --theme-icon-sun: block;
            --theme-icon-moon: none;
            color-scheme: light;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", system-ui, sans-serif;
            background: radial-gradient(1200px 600px at 10% -10%, var(--bg-glow) 0%, var(--bg) 55%);
            color: var(--text);
        }
        .topnav {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 16px;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
            background: var(--nav-bg);
            backdrop-filter: blur(8px);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .topnav .logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-decoration: none;
            color: var(--text);
            font-size: 0.95rem;
        }
        .topnav .logo img {
            width: 28px;
            height: 28px;
            display: block;
            flex-shrink: 0;
        }
        .topnav .logo-text { line-height: 1; }
        .topnav .nav-links {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .topnav a.nav {
            color: var(--muted);
            text-decoration: none;
            font-size: 0.9rem;
            padding: 6px 10px;
            border-radius: 8px;
        }
        .topnav a.nav:hover { color: var(--text); background: var(--hover); }
        .topnav a.nav.active { color: var(--text); background: var(--active); }
        .topnav .nav-right {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px 12px;
        }
        .lang-switch,
        .theme-toggle {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 2px;
            background: var(--input);
        }
        .lang-switch a {
            color: var(--muted);
            text-decoration: none;
            font-size: 0.78rem;
            font-weight: 650;
            letter-spacing: 0.04em;
            padding: 5px 9px;
            border-radius: 6px;
        }
        .lang-switch a:hover { color: var(--text); }
        .lang-switch a.active {
            color: var(--text);
            background: var(--active);
        }
        .theme-toggle {
            cursor: pointer;
            color: var(--muted);
            background: var(--input);
            padding: 6px 8px;
        }
        .theme-toggle:hover { color: var(--text); border-color: var(--muted); }
        .theme-toggle svg { width: 16px; height: 16px; display: block; }
        .theme-toggle .icon-sun { display: var(--theme-icon-sun); }
        .theme-toggle .icon-moon { display: var(--theme-icon-moon); }
        .wrap {
            max-width: 440px;
            margin: 0 auto;
            padding: 36px 20px 64px;
        }
        .wrap.wide { max-width: 720px; }
        .wrap.docs { max-width: 820px; }
        .brand {
            font-size: 0.85rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 8px;
        }
        h1 {
            margin: 0 0 8px;
            font-size: 1.6rem;
            font-weight: 650;
        }
        h2 {
            margin: 28px 0 12px;
            font-size: 1.15rem;
            font-weight: 650;
        }
        .sub {
            color: var(--muted);
            margin: 0 0 28px;
            line-height: 1.45;
            font-size: 0.95rem;
        }
        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 16px;
        }
        label {
            display: block;
            font-size: 0.85rem;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .field { margin-bottom: 16px; }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="file"],
        select,
        textarea {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--input);
            color: var(--text);
            font-size: 1rem;
            font-family: inherit;
        }
        input[type="file"] {
            padding: 10px;
            font-size: 0.85rem;
        }
        textarea {
            font-family: ui-monospace, Consolas, monospace;
            font-size: 0.85rem;
            min-height: 100px;
            resize: vertical;
        }
        input:focus, select:focus, textarea:focus {
            outline: 2px solid color-mix(in srgb, var(--accent) 50%, transparent);
            border-color: var(--accent);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px 16px;
            border: 0;
            border-radius: 10px;
            background: var(--accent);
            color: var(--btn-fg);
            font-weight: 650;
            font-size: 0.95rem;
            cursor: pointer;
            text-decoration: none;
        }
        .btn:hover { background: var(--accent-2); color: var(--btn-fg); }
        .btn.secondary {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }
        .btn.secondary:hover { border-color: var(--muted); background: var(--hover); }
        .btn.danger {
            background: transparent;
            border: 1px solid color-mix(in srgb, var(--danger) 45%, var(--border));
            color: var(--danger);
        }
        .btn.sm { width: auto; padding: 8px 12px; font-size: 0.85rem; }
        .actions { display: grid; gap: 10px; margin-top: 8px; }
        .actions.row { grid-template-columns: 1fr 1fr; }
        .flash {
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 0.92rem;
        }
        .flash.ok {
            background: color-mix(in srgb, var(--ok) 15%, transparent);
            border: 1px solid color-mix(in srgb, var(--ok) 35%, transparent);
            color: var(--ok);
        }
        .errors {
            background: color-mix(in srgb, var(--danger) 12%, transparent);
            border: 1px solid color-mix(in srgb, var(--danger) 35%, transparent);
            color: var(--danger);
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }
        .errors ul { margin: 0; padding-left: 18px; }
        .meta { display: grid; gap: 10px; }
        .meta-row {
            display: grid;
            grid-template-columns: 100px 1fr;
            gap: 8px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            font-size: 0.95rem;
        }
        .meta-row:last-child { border-bottom: 0; }
        .meta-row span { color: var(--muted); }
        .meta-row code, .token, code {
            font-family: ui-monospace, Consolas, monospace;
            font-size: 0.82rem;
            word-break: break-all;
            color: var(--text);
        }
        .token-box, .code-box {
            margin-top: 8px;
            padding: 12px;
            background: var(--input);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow-x: auto;
        }
        .code-box pre {
            margin: 0;
            font-family: ui-monospace, Consolas, monospace;
            font-size: 0.8rem;
            line-height: 1.45;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .links {
            margin-top: 18px;
            text-align: center;
            color: var(--muted);
            font-size: 0.9rem;
        }
        .links a { color: var(--accent); text-decoration: none; }
        .links a:hover { text-decoration: underline; }
        .hint {
            margin-top: 20px;
            font-size: 0.82rem;
            color: var(--muted);
            line-height: 1.45;
        }
        .hint code {
            font-family: ui-monospace, Consolas, monospace;
            font-size: 0.78rem;
            color: var(--code-muted);
        }
        .check { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: 0.9rem; margin-bottom: 16px; }
        .endpoint {
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 12px;
            background: var(--panel);
        }
        .endpoint-head {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            background: var(--endpoint-head);
        }
        .method {
            font-family: ui-monospace, Consolas, monospace;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            letter-spacing: 0.04em;
        }
        .method.get { background: color-mix(in srgb, var(--method-get) 18%, transparent); color: var(--method-get); }
        .method.post { background: color-mix(in srgb, var(--method-post) 18%, transparent); color: var(--method-post); }
        .endpoint-path {
            font-family: ui-monospace, Consolas, monospace;
            font-size: 0.9rem;
        }
        .badge {
            margin-left: auto;
            font-size: 0.75rem;
            color: var(--muted);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 2px 8px;
        }
        .endpoint-body { padding: 14px 16px 16px; font-size: 0.92rem; color: var(--muted); line-height: 1.5; }
        .endpoint-body strong { color: var(--text); font-weight: 600; }
        .toc { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 24px; }
        .toc a {
            color: var(--accent);
            text-decoration: none;
            font-size: 0.85rem;
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 6px 12px;
        }
        .toc a:hover { background: var(--hover); }
        .status-line {
            font-family: ui-monospace, Consolas, monospace;
            font-size: 0.85rem;
            margin-bottom: 8px;
            color: var(--muted);
        }
        .status-line.ok { color: var(--ok); }
        .status-line.err { color: var(--danger); }
        .grid-2 {
            display: grid;
            gap: 16px;
        }
        @media (min-width: 720px) {
            .grid-2 { grid-template-columns: 1fr 1fr; }
        }

        body.shell-page {
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        html:has(body.shell-page) {
            height: 100%;
            overflow: hidden;
        }
        body.shell-page .topnav {
            position: static;
            flex: 0 0 auto;
        }
        .wrap.shell {
            max-width: none;
            width: 100%;
            margin: 0;
            padding: 0;
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .docs-shell {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 0;
            flex: 1;
            overflow: hidden;
        }
        .docs-side {
            border-right: 1px solid var(--border);
            background: var(--panel-2);
            padding: 16px 12px 24px;
            overflow-y: auto;
        }
        .docs-side .side-title {
            font-size: 0.72rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
            margin: 0 0 12px 8px;
        }
        .docs-side > a.docs-top {
            display: block;
            color: var(--muted);
            text-decoration: none;
            font-size: 0.9rem;
            padding: 8px 10px;
            border-radius: 8px;
            margin-bottom: 2px;
        }
        .docs-side > a.docs-top:hover { color: var(--text); background: var(--hover); }
        .docs-side > a.docs-top.active { color: var(--text); background: var(--active); }
        .docs-nav-group {
            margin: 6px 0 4px;
            border: 1px solid transparent;
            border-radius: 10px;
        }
        .docs-nav-group > summary {
            list-style: none;
            cursor: pointer;
            color: var(--text);
            font-size: 0.82rem;
            font-weight: 650;
            padding: 8px 10px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            user-select: none;
        }
        .docs-nav-group > summary::-webkit-details-marker { display: none; }
        .docs-nav-group > summary::after {
            content: '';
            width: 0.4rem;
            height: 0.4rem;
            border-right: 1.5px solid var(--muted);
            border-bottom: 1.5px solid var(--muted);
            transform: rotate(-45deg);
            transition: transform 0.15s ease;
            flex-shrink: 0;
        }
        .docs-nav-group[open] > summary::after {
            transform: rotate(45deg);
        }
        .docs-nav-group > summary:hover { background: var(--hover); }
        .docs-nav-sub {
            padding: 2px 0 6px 6px;
            display: grid;
            gap: 1px;
        }
        .docs-nav-sub a {
            display: block;
            color: var(--muted);
            text-decoration: none;
            font-size: 0.82rem;
            padding: 6px 10px;
            border-radius: 8px;
        }
        .docs-nav-sub a:hover { color: var(--text); background: var(--hover); }
        .docs-nav-sub a.active { color: var(--text); background: var(--active); }
        .docs-nav-label {
            font-size: 0.68rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--muted);
            padding: 8px 10px 4px;
            opacity: 0.85;
        }
        .docs-side .docs-footer-link {
            display: block;
            margin-top: 14px;
            color: var(--accent);
            text-decoration: none;
            font-size: 0.88rem;
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        .docs-side .docs-footer-link:hover { background: var(--hover); }
        .docs-main {
            overflow-y: auto;
            padding: 28px 32px 48px;
        }
        .docs-main .card { max-width: 780px; }
        .docs-main .endpoint { max-width: 780px; }

        .pg-shell {
            display: flex;
            flex-direction: row;
            min-height: 0;
            flex: 1;
            overflow: hidden;
        }
        .pg-col {
            display: flex;
            flex-direction: column;
            min-width: 180px;
            min-height: 0;
            overflow: hidden;
        }
        .pg-col-scroll {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 16px;
        }
        .pg-col-auth {
            flex: 0 0 300px;
            width: 300px;
            background: var(--panel-2);
        }
        .pg-col-actions {
            flex: 1 1 auto;
            min-width: 240px;
        }
        .pg-col-response {
            flex: 0 0 360px;
            width: 360px;
            background: var(--input);
            display: flex;
            flex-direction: column;
        }
        .pg-splitter {
            flex: 0 0 5px;
            width: 5px;
            cursor: col-resize;
            background: var(--border);
            position: relative;
            z-index: 2;
            touch-action: none;
        }
        .pg-splitter:hover,
        .pg-splitter.is-dragging {
            background: var(--accent);
        }
        .pg-splitter::after {
            content: '';
            position: absolute;
            inset: 0 -4px;
        }
        body.is-resizing-cols {
            cursor: col-resize;
            user-select: none;
        }
        body.is-resizing-cols iframe,
        body.is-resizing-cols .pg-col {
            pointer-events: none;
        }
        .pg-response-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 14px;
            border-bottom: 1px solid var(--border);
            flex: 0 0 auto;
        }
        .pg-response-head h2 {
            margin: 0;
            font-size: 0.85rem;
            font-weight: 650;
        }
        .pg-col-response .code-box {
            margin: 0;
            border: 0;
            border-radius: 0;
            flex: 1;
            min-height: 0;
            overflow: auto;
            background: color-mix(in srgb, var(--input) 70%, var(--bg));
        }
        .pg-col-response .code-box pre {
            padding: 12px 14px;
            font-size: 0.78rem;
        }
        .pg-compact .card {
            padding: 14px;
            margin-bottom: 12px;
        }
        .pg-compact .field { margin-bottom: 10px; }
        .pg-compact input {
            padding: 9px 11px;
            font-size: 0.9rem;
        }
        .pg-compact h1 {
            font-size: 1.15rem;
            margin-bottom: 4px;
        }
        .pg-compact h2 {
            margin: 0 0 10px;
            font-size: 0.95rem;
        }
        .pg-compact .sub {
            margin-bottom: 12px;
            font-size: 0.82rem;
        }
        .pg-compact .btn { padding: 10px 12px; font-size: 0.88rem; }
        .pg-compact .meta-row {
            grid-template-columns: 64px 1fr;
            padding: 6px 0;
            font-size: 0.85rem;
        }
        .pg-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .pg-actions .btn { width: 100%; }

        @media (max-width: 860px) {
            html:has(body.shell-page),
            body.shell-page { height: auto; overflow: auto; }
            body.shell-page { display: block; }
            .wrap.shell { overflow: visible; display: block; }
            .docs-shell { grid-template-columns: 1fr; overflow: visible; }
            .docs-side {
                position: sticky;
                top: 0;
                z-index: 5;
                border-right: 0;
                border-bottom: 1px solid var(--border);
                max-height: 42vh;
            }
            .docs-main { overflow: visible; padding: 20px 16px 40px; }
            .pg-shell {
                display: block;
                overflow: visible;
            }
            .pg-splitter { display: none; }
            .pg-col {
                width: 100% !important;
                flex: none !important;
                min-height: auto;
                overflow: visible;
            }
            .pg-col-response {
                min-height: 240px;
                border-top: 1px solid var(--border);
            }
            .pg-col-response .code-box {
                max-height: 280px;
            }
            .pg-col-auth { border-bottom: 1px solid var(--border); }
            .pg-col-actions { border-bottom: 1px solid var(--border); }
        }
    </style>
</head>
<body class="@yield('body_class')">
    <nav class="topnav">
        <a class="logo" href="{{ route('home') }}">
            <img src="{{ asset('images/logo.png') }}" alt="" width="28" height="28">
            <span class="logo-text">GigRunner API</span>
        </a>
        <div class="nav-right">
            <div class="nav-links">
                <a class="nav {{ request()->routeIs('docs') ? 'active' : '' }}" href="{{ route('docs') }}">{{ __('ui.nav_docs') }}</a>
                <a class="nav {{ request()->routeIs('playground') ? 'active' : '' }}" href="{{ route('playground') }}">{{ __('ui.nav_playground') }}</a>
                @auth
                    <a class="nav {{ request()->routeIs('account') ? 'active' : '' }}" href="{{ route('account') }}">{{ __('ui.nav_account') }}</a>
                @endauth
            </div>
            <button type="button" class="theme-toggle" id="themeToggle" aria-label="Toggle theme" title="Dark / Light">
                <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="4"/>
                    <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
                </svg>
                <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M21 14.5A8.5 8.5 0 0 1 9.5 3 7 7 0 1 0 21 14.5z"/>
                </svg>
            </button>
            <div class="lang-switch" aria-label="Language">
                <a href="{{ route('locale.switch', 'pt') }}" class="{{ app()->getLocale() === 'pt' ? 'active' : '' }}">PT</a>
                <a href="{{ route('locale.switch', 'en') }}" class="{{ app()->getLocale() === 'en' ? 'active' : '' }}">EN</a>
            </div>
        </div>
    </nav>
    <div class="wrap @yield('wrap_class')">
        @yield('content')
    </div>
    <script>
        (function () {
            var btn = document.getElementById('themeToggle');
            if (!btn) return;
            btn.addEventListener('click', function () {
                var next = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', next);
                try { localStorage.setItem('gigrunner_theme', next); } catch (e) {}
            });
        })();
    </script>
</body>
</html>
