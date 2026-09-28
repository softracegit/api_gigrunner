<div class="docs-shell">
    <aside class="docs-side">
        <p class="side-title">Documentation</p>
        <a href="#inicio" class="docs-top active" data-docs-link>Overview</a>

        <details class="docs-nav-group" open>
            <summary>Integration guide</summary>
            <div class="docs-nav-sub">
                <a href="#guia" data-docs-link>Overview</a>
                <a href="#guia-setup" data-docs-link>Setup</a>
                <a href="#guia-login" data-docs-link>Login</a>
                <a href="#guia-arranque" data-docs-link>Startup</a>
                <a href="#guia-ai" data-docs-link>Using AI</a>
                <a href="#guia-audio" data-docs-link>Audio analysis</a>
                <a href="#guia-offline" data-docs-link>Offline</a>
                <a href="#guia-erros" data-docs-link>App errors</a>
                <a href="#guia-checklist" data-docs-link>Checklist</a>
            </div>
        </details>

        <details class="docs-nav-group" open>
            <summary>Concepts</summary>
            <div class="docs-nav-sub">
                <a href="#fluxo" data-docs-link>Flow</a>
                <a href="#auth" data-docs-link>Authentication</a>
                <a href="#credits-model" data-docs-link>Licence vs credits</a>
                <a href="#erros" data-docs-link>Common errors</a>
                <a href="#user" data-docs-link>User object</a>
            </div>
        </details>

        <details class="docs-nav-group" open>
            <summary>Endpoints</summary>
            <div class="docs-nav-sub">
                <div class="docs-nav-label">Auth</div>
                <a href="#health" data-docs-link>GET /health</a>
                <a href="#register" data-docs-link>POST /register</a>
                <a href="#login" data-docs-link>POST /login</a>
                <a href="#me" data-docs-link>GET /me</a>
                <a href="#logout" data-docs-link>POST /logout</a>
                <div class="docs-nav-label">Licence</div>
                <a href="#license" data-docs-link>GET /license</a>
                <a href="#activate-test" data-docs-link>POST /activate-test</a>
                <a href="#revoke" data-docs-link>POST /revoke</a>
                <div class="docs-nav-label">Credits</div>
                <a href="#credits" data-docs-link>GET /credits</a>
                <a href="#credits-consume" data-docs-link>POST /consume</a>
                <a href="#credits-purchase" data-docs-link>POST /purchase-test</a>
                <div class="docs-nav-label">Audio</div>
                <a href="#audio-jobs" data-docs-link>POST /audio/jobs</a>
                <a href="#audio-jobs-list" data-docs-link>GET /audio/jobs</a>
                <a href="#audio-job-status" data-docs-link>GET /jobs/{id}</a>
                <a href="#audio-job-result" data-docs-link>GET /result</a>
            </div>
        </details>

        <a class="docs-footer-link" href="{{ route('playground') }}">Playground →</a>
    </aside>

    <main class="docs-main" id="docsMain">
        <div id="inicio">
            <h1>API documentation</h1>
            <p class="sub">
                Base URL: <code>{{ url('/api/v1') }}</code><br>
                Full endpoint reference + <a href="#guia" style="color: var(--accent);">integration guide</a> for the GigRunner / Cue Companion app.
            </p>
        </div>

        <div class="card" id="guia">
            <h2 style="margin-top: 0;">Integration guide (app)</h2>
            <p style="margin: 0; color: var(--muted); line-height: 1.55;">
                For whoever implements the desktop/mobile client.
                The website handles registration/purchase; the app handles login, licence validation and AI credit consumption.
            </p>
        </div>

        <div class="card" id="guia-setup">
            <h2 style="margin-top: 0;">1. Setup</h2>
            <div class="meta">
                <div class="meta-row"><span>Base URL</span><div><code>{{ url('/api/v1') }}</code></div></div>
                <div class="meta-row"><span>Format</span><div>JSON · <code>Content-Type: application/json</code> · <code>Accept: application/json</code></div></div>
                <div class="meta-row"><span>Auth</span><div><code>Authorization: Bearer &lt;token&gt;</code></div></div>
            </div>
            <p class="hint" style="margin-top: 12px;">
                During development you can use the <a href="{{ route('playground') }}" style="color: var(--accent);">Playground</a> to validate responses before wiring the app.
            </p>
        </div>

        <div class="card" id="guia-login">
            <h2 style="margin-top: 0;">2. Login (once / when expired)</h2>
            <p style="margin: 0 0 12px; color: var(--muted); line-height: 1.55;">
                App login screen → <code>POST /login</code> with email and password.
            </p>
            <div class="code-box">
<pre>POST /api/v1/login
{ "email": "user@email.com", "password": "…" }

→ 200
{
  "user": { "id", "uuid", "name", "email" },
  "license": { "valid", "status", "plan", "starts_at", "expires_at" },
  "credits": { "ai": 50 },
  "token": "1|…"
}</pre>
            </div>
            <p style="margin: 12px 0 0; color: var(--muted); line-height: 1.55;">
                <strong style="color: var(--text);">Persist securely on disk:</strong>
                <code>token</code>, <code>user.uuid</code>, <code>license</code>, <code>credits</code>,
                and <code>last_validated_at = now</code>.
            </p>
            <p style="margin: 8px 0 0; color: var(--muted); line-height: 1.55;">
                If <code>license.valid === false</code>: only allow the “activate / buy licence” screen
                (open the website in the browser). Do not unlock the full app.
            </p>
        </div>

        <div class="card" id="guia-arranque">
            <h2 style="margin-top: 0;">3. Next startup (token already stored)</h2>
            <ol style="margin: 0; padding-left: 18px; color: var(--muted); line-height: 1.6;">
                <li>Read token from disk. If missing → login screen.</li>
                <li>When online: <code>GET /license</code> (or <code>GET /me</code> if you also want user + credits).</li>
                <li>Update local cache (<code>license</code>, <code>credits</code> if present, <code>last_validated_at</code>).</li>
                <li>If <code>valid === true</code> → normal app. Otherwise → block / ask to purchase.</li>
                <li>If <strong style="color: var(--text);">401</strong> → invalid token → clear cache → login.</li>
            </ol>
            <p class="hint" style="margin-top: 12px;">
                The user does <strong>not</strong> log in again on every launch if the token is still valid.
            </p>
        </div>

        <div class="card" id="guia-ai">
            <h2 style="margin-top: 0;">4. Using AI (required)</h2>
            <p style="margin: 0 0 12px; color: var(--muted); line-height: 1.55;">
                Before every AI request to your infra / model:
            </p>
            <div class="code-box">
<pre>POST /api/v1/credits/consume
Authorization: Bearer …
{ "type": "ai", "amount": 1, "reason": "cue_suggest" }

→ 200  { "ok": true, "balance": 49, "credits": { "ai": 49 } }
→ 402  { "message": "Insufficient credits.", "credits": { "ai": 0 } }</pre>
            </div>
            <ul style="margin: 12px 0 0; padding-left: 18px; color: var(--muted); line-height: 1.55;">
                <li><strong style="color: var(--text);">200</strong> → update local balance → run AI.</li>
                <li><strong style="color: var(--text);">402</strong> → do not call AI; show “buy more credits” UI (link to site).</li>
                <li>Do not rely only on a cached balance to authorise spend — the server decides.</li>
                <li>Optional: send a unique <code>reference</code> per operation (e.g. job id) for auditing / future double-spend protection.</li>
            </ul>
        </div>

        <div class="card" id="guia-audio">
            <h2 style="margin-top: 0;">4b. Audio analysis (Magic Chords)</h2>
            <p style="margin: 0 0 12px; color: var(--muted); line-height: 1.55;">
                The app must <strong style="color: var(--text);">not</strong> call Magic Chords directly.
                Use GigRunner URLs. The backend picks the provider (today: Magic Chords).
                Requires a valid licence; consumes <strong style="color: var(--text);">1 AI credit</strong> when creating the job.
            </p>
            <div class="code-box">
<pre>POST /api/v1/audio/jobs
{ "url": "https://…/song.mp3", "kind": "analyze" }
  or multipart: file + kind

→ 201 { "job": { "id", "status", "progress", … }, "credits" }

GET /api/v1/audio/jobs/{id}           → poll until status=complete
GET /api/v1/audio/jobs/{id}/result    → { format, cues[], meta }</pre>
            </div>
            <p class="hint" style="margin-top: 12px;">
                <code>kind</code>: <code>analyze</code> (chords) or <code>transcribe</code> (lyrics).
                <code>result.cues</code> is the app format (<code>chord</code> / <code>lyric</code>).
                Jobs take time — poll every 2–5s.
            </p>
        </div>

        <div class="card" id="guia-offline">
            <h2 style="margin-top: 0;">5. Offline (proposal)</h2>
            <p style="margin: 0 0 12px; color: var(--muted); line-height: 1.55;">
                Rehearsals may have no network. Suggested rule (agree on N):
            </p>
            <ul style="margin: 0; padding-left: 18px; color: var(--muted); line-height: 1.55;">
                <li>If cache has <code>license.valid === true</code> and
                    <code>now - last_validated_at &lt; N days</code> (e.g. <strong style="color: var(--text);">7</strong>)
                    → allow app use.</li>
                <li>AI features <strong style="color: var(--text);">require network</strong> (they need
                    <code>/credits/consume</code>). Offline → disable AI or show a clear message.</li>
                <li>When back online → revalidate immediately (<code>GET /license</code> + optional <code>GET /credits</code>).</li>
                <li>If online the API says invalid/revoked → block even with an old cache.</li>
            </ul>
        </div>

        <div class="card" id="guia-erros">
            <h2 style="margin-top: 0;">6. What the app should do per error</h2>
            <div class="meta">
                <div class="meta-row"><span>401</span><div>Clear token → login screen</div></div>
                <div class="meta-row"><span>402</span><div>No AI credits → buy / top-up UI</div></div>
                <div class="meta-row"><span>422</span><div>Show validation message (wrong login, etc.)</div></div>
                <div class="meta-row"><span>5xx / network</span><div>Soft retry; on startup, apply offline rule</div></div>
            </div>
        </div>

        <div class="card" id="guia-checklist">
            <h2 style="margin-top: 0;">7. Client checklist</h2>
            <ul style="margin: 0; padding-left: 18px; color: var(--muted); line-height: 1.7;">
                <li>□ Configurable Base URL (dev / production)</li>
                <li>□ Login → store <code>token</code> + <code>uuid</code> + <code>license</code> + <code>credits</code></li>
                <li>□ Startup with token → <code>GET /license</code> (do not ask for password again)</li>
                <li>□ Block app if <code>license.valid === false</code></li>
                <li>□ Before generic AI → <code>POST /credits/consume</code>; handle 402</li>
                <li>□ Audio analysis → <code>POST /audio/jobs</code> + poll + <code>/result</code></li>
                <li>□ “Register / Buy” link → website (browser)</li>
                <li>□ Optional logout → <code>POST /logout</code> + clear disk</li>
                <li>□ Documented offline rule (N days; AI online only)</li>
            </ul>
            <p class="hint" style="margin-top: 14px;">
                Test endpoints (<code>activate-test</code>, <code>purchase-test</code>) are for backend/playground —
                the production app does not need to call them; purchase will be on the website.
            </p>
        </div>

        <div class="card" id="fluxo">
            <h2 style="margin-top: 0;">Typical flow (summary)</h2>
            <ol style="margin: 0; padding-left: 18px; color: var(--muted); line-height: 1.6;">
                <li>User creates an account on the <strong style="color: var(--text);">website</strong>.</li>
                <li>Buys a licence (e.g. €10) → gets an active app + initial credits (e.g. 50 AI).</li>
                <li>In the <strong style="color: var(--text);">app</strong>: login → token + license + credits.</li>
                <li>Before each AI use: <code>POST /credits/consume</code>. If 402 → no balance, ask for top-up.</li>
            </ol>
        </div>

        <div class="card" id="auth">
            <h2 style="margin-top: 0;">Authentication</h2>
            <p style="margin: 0; color: var(--muted); line-height: 1.5;">
                Protected endpoints need the header:<br>
                <code>Authorization: Bearer &lt;token&gt;</code><br>
                <code>Accept: application/json</code><br><br>
                The token comes from <code>POST /login</code> or <code>POST /register</code>.
                The end user <strong style="color: var(--text);">never</strong> creates tokens by hand — that is the app’s job.
            </p>
        </div>

        <h2 id="endpoints">Endpoints</h2>

        <div class="endpoint" id="health">
            <div class="endpoint-head">
                <span class="method get">GET</span>
                <span class="endpoint-path">/api/v1/health</span>
                <span class="badge">public</span>
            </div>
            <div class="endpoint-body">
                Checks whether the API is online.<br>
                <strong>Response:</strong> <code>{ "ok": true, "app": "...", "env": "..." }</code>
            </div>
        </div>

        <div class="endpoint" id="register">
            <div class="endpoint-head">
                <span class="method post">POST</span>
                <span class="endpoint-path">/api/v1/register</span>
                <span class="badge">public</span>
            </div>
            <div class="endpoint-body">
                Creates an account.<br>
                <strong>Body:</strong> <code>name</code>, <code>email</code>, <code>password</code>, <code>password_confirmation</code><br>
                <strong>Response 201:</strong> <code>{ user, license, credits, token }</code>
            </div>
        </div>

        <div class="endpoint" id="login">
            <div class="endpoint-head">
                <span class="method post">POST</span>
                <span class="endpoint-path">/api/v1/login</span>
                <span class="badge">public</span>
            </div>
            <div class="endpoint-body">
                App login.<br>
                <strong>Body:</strong> <code>email</code>, <code>password</code><br>
                <strong>Response:</strong> <code>{ user, license, credits, token }</code>
            </div>
        </div>

        <div class="endpoint" id="me">
            <div class="endpoint-head">
                <span class="method get">GET</span>
                <span class="endpoint-path">/api/v1/me</span>
                <span class="badge">Bearer</span>
            </div>
            <div class="endpoint-body">
                Account data + licence + credits.<br>
                <strong>Response:</strong> <code>{ user, license, credits }</code>
            </div>
        </div>

        <div class="endpoint" id="license">
            <div class="endpoint-head">
                <span class="method get">GET</span>
                <span class="endpoint-path">/api/v1/license</span>
                <span class="badge">Bearer</span>
            </div>
            <div class="endpoint-body">
                Licence state (what the app validates on launch).<br>
                <strong>Response:</strong>
                <div class="code-box" style="margin-top: 10px;">
<pre>{
  "license": {
    "valid": true,
    "status": "active",
    "plan": "trial",
    "starts_at": "2026-09-27T00:00:00+01:00",
    "expires_at": "2026-10-27T00:00:00+01:00"
  }
}</pre>
                </div>
                No licence: <code>valid: false</code>, <code>status: "inactive"</code>.
            </div>
        </div>

        <div class="endpoint" id="activate-test">
            <div class="endpoint-head">
                <span class="method post">POST</span>
                <span class="endpoint-path">/api/v1/license/activate-test</span>
                <span class="badge">Bearer · test</span>
            </div>
            <div class="endpoint-body">
                Creates a 30-day trial licence (until payments exist).<br>
                <strong>Response 201:</strong> <code>{ license }</code>
            </div>
        </div>

        <div class="endpoint" id="revoke">
            <div class="endpoint-head">
                <span class="method post">POST</span>
                <span class="endpoint-path">/api/v1/license/revoke</span>
                <span class="badge">Bearer · test</span>
            </div>
            <div class="endpoint-body">
                Revokes the current licence (to test app lockout).<br>
                <strong>Response:</strong> <code>{ license }</code>
            </div>
        </div>

        <div class="card" id="credits-model">
            <h2 style="margin-top: 0;">Model: licence vs credits</h2>
            <p style="margin: 0 0 12px; color: var(--muted); line-height: 1.55;">
                <strong style="color: var(--text);">Licence</strong> = may use the app (gate).<br>
                <strong style="color: var(--text);">Credits</strong> = balance per type (currently only <code>ai</code>) for paid actions.
            </p>
            <p style="margin: 0; color: var(--muted); line-height: 1.55;">
                You do not need to “check credits” in a loop. The rule is:
            </p>
            <ul style="color: var(--muted); line-height: 1.55;">
                <li>On app open → <code>GET /license</code> (and optionally offline cache).</li>
                <li>Before each AI action → <code>POST /credits/consume</code> (atomic on the server).</li>
                <li>If 402 → show “no credits” and a link to buy a pack.</li>
            </ul>
            <p style="margin: 12px 0 0; color: var(--muted); line-height: 1.55;">
                There is a <strong style="color: var(--text);">ledger</strong> (<code>credit_transactions</code>) to audit purchases and consumption.
            </p>
        </div>

        <div class="endpoint" id="credits">
            <div class="endpoint-head">
                <span class="method get">GET</span>
                <span class="endpoint-path">/api/v1/credits</span>
                <span class="badge">Bearer</span>
            </div>
            <div class="endpoint-body">
                Balances + available packs.<br>
                <strong>Response:</strong> <code>{ credits: { ai }, packs: [...] }</code>
            </div>
        </div>

        <div class="endpoint" id="credits-consume">
            <div class="endpoint-head">
                <span class="method post">POST</span>
                <span class="endpoint-path">/api/v1/credits/consume</span>
                <span class="badge">Bearer</span>
            </div>
            <div class="endpoint-body">
                Consumes AI credits.<br>
                <strong>Body:</strong> <code>type</code> (<code>ai</code>), <code>amount</code>? (default 1), <code>reason</code>?<br>
                <strong>200:</strong> <code>{ ok, type, balance, credits }</code><br>
                <strong>402:</strong> insufficient credits
            </div>
        </div>

        <div class="endpoint" id="credits-purchase">
            <div class="endpoint-head">
                <span class="method post">POST</span>
                <span class="endpoint-path">/api/v1/credits/purchase-test</span>
                <span class="badge">Bearer · test</span>
            </div>
            <div class="endpoint-body">
                Simulates a pack purchase (until Stripe exists).<br>
                <strong>Body:</strong> <code>{ "pack": "license_standard" }</code><br>
                Packs: <code>license_standard</code> (€10 → licence + 50 AI), <code>ai_50</code>, <code>ai_200</code>.
            </div>
        </div>

        <div class="endpoint" id="audio-jobs">
            <div class="endpoint-head">
                <span class="method post">POST</span>
                <span class="endpoint-path">/api/v1/audio/jobs</span>
                <span class="badge">Bearer</span>
            </div>
            <div class="endpoint-body">
                Creates analysis/transcription. Current provider: <strong>Magic Chords</strong>. Consumes 1 AI credit.<br>
                <strong>JSON:</strong> <code>{ "url": "https://…", "kind": "analyze"|"transcribe" }</code><br>
                <strong>multipart:</strong> <code>file</code> + <code>kind</code><br>
                <strong>201:</strong> <code>{ job, credits }</code> · <strong>402</strong> no credits · <strong>403</strong> no licence
            </div>
        </div>

        <div class="endpoint" id="audio-jobs-list">
            <div class="endpoint-head">
                <span class="method get">GET</span>
                <span class="endpoint-path">/api/v1/audio/jobs</span>
                <span class="badge">Bearer</span>
            </div>
            <div class="endpoint-body">
                Lists the authenticated user’s audio jobs (upload/URL history).<br>
                <strong>Query:</strong> <code>per_page</code>?, <code>kind</code>?, <code>status</code>?<br>
                <strong>Response:</strong> <code>{ jobs: [{ id, kind, status, source: { type, name, url }, … }], meta }</code>
            </div>
        </div>

        <div class="endpoint" id="audio-job-status">
            <div class="endpoint-head">
                <span class="method get">GET</span>
                <span class="endpoint-path">/api/v1/audio/jobs/{id}</span>
                <span class="badge">Bearer</span>
            </div>
            <div class="endpoint-body">
                Job status (<code>queued</code> / <code>processing</code> / <code>complete</code> / <code>failed</code>) + progress.
            </div>
        </div>

        <div class="endpoint" id="audio-job-result">
            <div class="endpoint-head">
                <span class="method get">GET</span>
                <span class="endpoint-path">/api/v1/audio/jobs/{id}/result</span>
                <span class="badge">Bearer</span>
            </div>
            <div class="endpoint-body">
                Normalized result for the app (provider-agnostic).<br>
                <strong>Response:</strong>
                <div class="code-box" style="margin-top: 10px;">
<pre>{
  "format": 1,
  "cues": [
    {
      "id": "1789941334196048_1412144128",
      "name": "F#m",
      "timeMs": 293166,
      "kind": "chord",
      "channel": 1,
      "number": 60,
      "value": 100,
      "durationMs": 3391
    },
    {
      "id": "1789941914663835_2617840123",
      "name": "The Sun is the same…",
      "timeMs": 292499,
      "kind": "lyric",
      "channel": 1,
      "number": 60,
      "value": 100,
      "durationMs": 6545
    }
  ],
  "meta": { "provider": "magic_chords", "bpm": 120, "key": "F#m", "durationMs": 552000 }
}</pre>
                </div>
                Cue <code>kind</code>: <code>chord</code> or <code>lyric</code>.
                <strong>409</strong> if not yet <code>complete</code>.
            </div>
        </div>

        <div class="endpoint" id="logout">
            <div class="endpoint-head">
                <span class="method post">POST</span>
                <span class="endpoint-path">/api/v1/logout</span>
                <span class="badge">Bearer</span>
            </div>
            <div class="endpoint-body">
                Revokes the current token.<br>
                <strong>Response:</strong> <code>{ "ok": true }</code>
            </div>
        </div>

        <div class="card" id="erros">
            <h2 style="margin-top: 0;">Common errors</h2>
            <div class="meta">
                <div class="meta-row"><span>401</span><div>Missing or invalid token</div></div>
                <div class="meta-row"><span>402</span><div>Insufficient credits (consume)</div></div>
                <div class="meta-row"><span>422</span><div>Validation (wrong password, invalid pack, etc.)</div></div>
            </div>
        </div>

        <div class="card" id="user">
            <h2 style="margin-top: 0;"><code>user</code> object</h2>
            <div class="code-box">
<pre>{
  "id": 1,
  "uuid": "63f50785-…",
  "name": "Name",
  "email": "user@email.com"
}</pre>
            </div>
            <p class="hint" style="margin-top: 12px;">
                The <code>uuid</code> is stable — the app can store it locally with the token and licence state.
            </p>
        </div>
    </main>
</div>

<script>
    (function () {
        const main = document.getElementById('docsMain');
        const links = document.querySelectorAll('[data-docs-link]');

        function setActive() {
            const hash = window.location.hash || '#inicio';
            links.forEach(function (a) {
                a.classList.toggle('active', a.getAttribute('href') === hash);
            });
        }

        links.forEach(function (a) {
            a.addEventListener('click', function () {
                links.forEach(l => l.classList.remove('active'));
                a.classList.add('active');
            });
        });

        window.addEventListener('hashchange', setActive);
        setActive();

        links.forEach(function (a) {
            a.addEventListener('click', function (e) {
                const id = a.getAttribute('href');
                if (!id || !id.startsWith('#')) return;
                const el = document.querySelector(id);
                if (!el || !main) return;
                e.preventDefault();
                history.replaceState(null, '', id);
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                setActive();
            });
        });
    })();
</script>
