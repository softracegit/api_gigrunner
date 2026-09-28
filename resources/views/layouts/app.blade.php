<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'GigRunner API')</title>
    <style>
        :root {
            --bg: #0f1419;
            --panel: #1a222c;
            --text: #e8eef4;
            --muted: #8b9aab;
            --accent: #3d9cfd;
            --accent-2: #2a7fd4;
            --danger: #f07178;
            --ok: #7fd99a;
            --border: #2a3542;
            --input: #121820;
            --method-get: #7fd99a;
            --method-post: #3d9cfd;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", system-ui, sans-serif;
            background: radial-gradient(1200px 600px at 10% -10%, #1b2a3a 0%, var(--bg) 55%);
            color: var(--text);
        }
        .topnav {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 16px;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            background: color-mix(in srgb, var(--panel) 80%, transparent);
            backdrop-filter: blur(8px);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .topnav .logo {
            font-weight: 700;
            letter-spacing: 0.04em;
            text-decoration: none;
            color: var(--text);
            font-size: 0.95rem;
        }
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
        .topnav a.nav:hover { color: var(--text); background: #222b36; }
        .topnav a.nav.active { color: var(--text); background: #243040; }
        .topnav .nav-right {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px 12px;
        }
        .lang-switch {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 2px;
            background: #121820;
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
            background: #243040;
        }
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
            color: #041018;
            font-weight: 650;
            font-size: 0.95rem;
            cursor: pointer;
            text-decoration: none;
        }
        .btn:hover { background: var(--accent-2); color: #041018; }
        .btn.secondary {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }
        .btn.secondary:hover { border-color: var(--muted); background: #222b36; }
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
            color: #b7c6d6;
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
            background: #161e27;
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
        .toc a:hover { background: #222b36; }
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

        /* Full-screen shells (docs / playground) */
        html, body.shell-page {
            height: 100%;
            overflow: hidden;
        }
        body.shell-page {
            display: flex;
            flex-direction: column;
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

        /* Docs layout */
        .docs-shell {
            display: grid;
            grid-template-columns: 220px 1fr;
            min-height: 0;
            flex: 1;
            overflow: hidden;
        }
        .docs-side {
            border-right: 1px solid var(--border);
            background: #141b24;
            padding: 20px 14px;
            overflow-y: auto;
        }
        .docs-side .side-title {
            font-size: 0.72rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
            margin: 0 0 12px 8px;
        }
        .docs-side a {
            display: block;
            color: var(--muted);
            text-decoration: none;
            font-size: 0.9rem;
            padding: 8px 10px;
            border-radius: 8px;
            margin-bottom: 2px;
        }
        .docs-side a[href^="#guia-"] {
            font-size: 0.82rem;
            padding: 5px 10px 5px 18px;
            opacity: 0.92;
        }
        .docs-side a:hover { color: var(--text); background: #222b36; }
        .docs-side a.active { color: var(--text); background: #243040; }
        .docs-main {
            overflow-y: auto;
            padding: 28px 32px 48px;
        }
        .docs-main .card { max-width: 780px; }
        .docs-main .endpoint { max-width: 780px; }

        /* Playground layout — 3 resizable columns */
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
            background: #141b24;
        }
        .pg-col-actions {
            flex: 1 1 auto;
            min-width: 240px;
        }
        .pg-col-response {
            flex: 0 0 360px;
            width: 360px;
            background: #121820;
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
            background: #0d1218;
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
            html, body.shell-page { height: auto; overflow: auto; }
            body.shell-page { display: block; }
            .wrap.shell { overflow: visible; display: block; }
            .docs-shell { grid-template-columns: 1fr; overflow: visible; }
            .docs-side {
                position: sticky;
                top: 0;
                z-index: 5;
                border-right: 0;
                border-bottom: 1px solid var(--border);
                display: flex;
                flex-wrap: wrap;
                gap: 4px;
                padding: 10px;
            }
            .docs-side .side-title { width: 100%; margin: 0 0 6px 4px; }
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
        <a class="logo" href="{{ route('home') }}">GigRunner API</a>
        <div class="nav-right">
            <div class="nav-links">
                <a class="nav {{ request()->routeIs('docs') ? 'active' : '' }}" href="{{ route('docs') }}">{{ __('ui.nav_docs') }}</a>
                <a class="nav {{ request()->routeIs('playground') ? 'active' : '' }}" href="{{ route('playground') }}">{{ __('ui.nav_playground') }}</a>
                @auth
                    <a class="nav {{ request()->routeIs('account') ? 'active' : '' }}" href="{{ route('account') }}">{{ __('ui.nav_account') }}</a>
                @else
                    <a class="nav {{ request()->routeIs('login') ? 'active' : '' }}" href="{{ route('login') }}">{{ __('ui.nav_login') }}</a>
                    <a class="nav {{ request()->routeIs('register') ? 'active' : '' }}" href="{{ route('register') }}">{{ __('ui.nav_register') }}</a>
                @endauth
            </div>
            <div class="lang-switch" aria-label="Language">
                <a href="{{ route('locale.switch', 'pt') }}" class="{{ app()->getLocale() === 'pt' ? 'active' : '' }}">PT</a>
                <a href="{{ route('locale.switch', 'en') }}" class="{{ app()->getLocale() === 'en' ? 'active' : '' }}">EN</a>
            </div>
        </div>
    </nav>
    <div class="wrap @yield('wrap_class')">
        @yield('content')
    </div>
</body>
</html>
