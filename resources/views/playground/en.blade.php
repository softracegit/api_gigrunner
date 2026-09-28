<div class="pg-shell pg-compact" id="pgShell">
    <aside class="pg-col pg-col-auth" id="pgColAuth">
        <div class="pg-col-scroll">
            <h1>Playground</h1>
            <p class="sub">Register and login (same as the app).</p>

            <div class="card">
                <h2>Register</h2>
                <div class="field">
                    <label for="regName">Name</label>
                    <input id="regName" type="text" value="Playground Test">
                </div>
                <div class="field">
                    <label for="regEmail">Email</label>
                    <input id="regEmail" type="email" value="">
                </div>
                <div class="field">
                    <label for="regPassword">Password</label>
                    <input id="regPassword" type="password" value="password123">
                </div>
                <button type="button" class="btn" id="btnRegister">POST /register</button>
            </div>

            <div class="card">
                <h2>Login</h2>
                <div class="field">
                    <label for="loginEmail">Email</label>
                    <input id="loginEmail" type="email" value="">
                </div>
                <div class="field">
                    <label for="loginPassword">Password</label>
                    <input id="loginPassword" type="password" value="password123">
                </div>
                <button type="button" class="btn" id="btnLogin">POST /login</button>
            </div>
        </div>
    </aside>

    <div class="pg-splitter" data-split="auth-actions" title="Drag to resize"></div>

    <section class="pg-col pg-col-actions" id="pgColActions">
        <div class="pg-col-scroll">
            <div class="card">
                <h2>API session</h2>
                <div class="meta">
                    <div class="meta-row"><span>Base</span><div><code id="baseUrl">{{ url('/api/v1') }}</code></div></div>
                    <div class="meta-row"><span>Token</span><div id="tokenPreview"><code style="color: var(--muted);">— none —</code></div></div>
                </div>
                <div class="pg-actions" style="margin-top: 12px;">
                    <button type="button" class="btn secondary" id="btnClearToken">Clear token</button>
                    <a class="btn secondary" href="{{ route('docs') }}">Docs</a>
                </div>
            </div>

            <div class="card">
                <h2>Requests</h2>
                <div class="pg-actions">
                    <button type="button" class="btn secondary" id="btnHealth">GET /health</button>
                    <button type="button" class="btn secondary" id="btnMe">GET /me</button>
                    <button type="button" class="btn secondary" id="btnLicense">GET /license</button>
                    <button type="button" class="btn secondary" id="btnCredits">GET /credits</button>
                    <button type="button" class="btn danger" id="btnLogout">POST /logout</button>
                </div>
            </div>

            <div class="card">
                <h2>Licence</h2>
                <p class="sub">Free trial or revoke. Paid licence comes from the pack below.</p>
                <div class="pg-actions">
                    <button type="button" class="btn secondary" id="btnActivateLicense">30-day trial</button>
                    <button type="button" class="btn secondary" id="btnRevokeLicense">Revoke</button>
                </div>
            </div>

            <div class="card">
                <h2>Buy (test)</h2>
                <p class="sub">Simulates payment. Standard = €10 → licence + 50 AI.</p>
                <div class="pg-actions">
                    <button type="button" class="btn" id="btnBuyStandard" data-pack="license_standard">Licence €10 + 50 AI</button>
                    <button type="button" class="btn secondary" id="btnBuyAi50" data-pack="ai_50">+50 AI (€5)</button>
                    <button type="button" class="btn secondary" id="btnBuyAi200" data-pack="ai_200">+200 AI (€15)</button>
                </div>
            </div>

            <div class="card">
                <h2>Consume credits</h2>
                <p class="sub">What the app does before using AI.</p>
                <div class="pg-actions">
                    <button type="button" class="btn secondary" id="btnConsumeAi">Consume 1 AI</button>
                </div>
            </div>

            <div class="card">
                <h2>Audio (Magic Chords)</h2>
                <p class="sub">Needs licence + credits. Consumes 1 AI when creating the job.</p>
                <div class="field">
                    <label for="audioFile">MP3 file (or audio)</label>
                    <input id="audioFile" type="file" accept="audio/mpeg,audio/mp3,audio/*,.mp3,.wav,.flac,.ogg,.m4a">
                </div>
                <div class="field">
                    <label for="audioUrl">…or audio URL</label>
                    <input id="audioUrl" type="text" value="https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3">
                </div>
                <div class="field">
                    <label for="audioKind">Kind</label>
                    <select id="audioKind">
                        <option value="analyze">analyze (chords / tempo / key)</option>
                        <option value="transcribe">transcribe (lyrics)</option>
                    </select>
                </div>
                <div class="field">
                    <label for="audioJobId">Job ID</label>
                    <input id="audioJobId" type="text" placeholder="filled after create" value="">
                </div>
                <div class="pg-actions">
                    <button type="button" class="btn" id="btnAudioUpload">Upload file</button>
                    <button type="button" class="btn secondary" id="btnAudioCreate">POST by URL</button>
                    <button type="button" class="btn secondary" id="btnAudioStatus">GET status</button>
                    <button type="button" class="btn secondary" id="btnAudioResult">GET result</button>
                </div>
            </div>
        </div>
    </section>

    <div class="pg-splitter" data-split="actions-response" title="Drag to resize"></div>

    <aside class="pg-col pg-col-response" id="pgColResponse">
        <div class="pg-response-head">
            <h2>Response</h2>
            <div id="statusLine" class="status-line" style="margin: 0;">Ready.</div>
        </div>
        <div class="code-box">
            <pre id="responseBox">{}</pre>
        </div>
    </aside>
</div>

@include('playground.script', [
    'i18n' => [
        'noToken' => '— none —',
        'needAuth' => 'No token — log in or register first.',
        'networkError' => 'Network error',
        'needFile' => 'Choose an audio file first.',
        'needJobId' => 'Enter a Job ID.',
        'tokenCleared' => 'Token cleared.',
        'ready' => 'Ready.',
        'uploading' => 'POST /audio/jobs (upload) …',
    ],
])
