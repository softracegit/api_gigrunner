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
                    <button type="button" class="btn secondary" id="btnAudioList">GET /audio/jobs</button>
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
                <h2>Music create</h2>
                <p class="sub">ElevenLabs — generate from prompt (2 AI). Does not call analyze.</p>
                <div class="field">
                    <label for="createPrompt">Prompt</label>
                    <input id="createPrompt" type="text" value="alternative rock with fuzz riff">
                </div>
                <div class="field">
                    <label for="createDuration">Duration (seconds)</label>
                    <input id="createDuration" type="number" min="3" max="600" value="12">
                </div>
                <div class="field">
                    <label><input type="checkbox" id="createInstrumental"> force instrumental</label>
                </div>
                <div class="field">
                    <label for="createJobId">Create job ID</label>
                    <input id="createJobId" type="text" placeholder="filled after create" value="">
                </div>
                <div class="pg-actions">
                    <button type="button" class="btn" id="btnMusicCreate">POST /music/create</button>
                    <button type="button" class="btn secondary" id="btnMusicCreateStatus">GET status</button>
                    <button type="button" class="btn secondary" id="btnMusicCreateResult">GET result</button>
                </div>
            </div>

            <div class="card">
                <h2>Music analyze</h2>
                <p class="sub">Chords + lyrics (Magic Chords) and/or stems (ElevenLabs).</p>
                <div class="field">
                    <label for="audioFile">MP3 file (or audio)</label>
                    <input id="audioFile" type="file" accept="audio/mpeg,audio/mp3,audio/*,.mp3,.wav,.flac,.ogg,.m4a">
                </div>
                <div class="field">
                    <label for="audioUrl">…or audio URL</label>
                    <input id="audioUrl" type="text" value="https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3">
                </div>
                <div class="field" style="display:flex;gap:16px;flex-wrap:wrap;">
                    <label><input type="checkbox" id="optChords" checked> chords</label>
                    <label><input type="checkbox" id="optLyrics" checked> lyrics</label>
                </div>
                <div class="field">
                    <label for="optStems">Stems (comma-separated)</label>
                    <input id="optStems" type="text" placeholder="vocals,drums,bass,guitar" value="vocals,guitar,bass,drums">
                </div>
                <div class="field">
                    <label for="audioGranularity">Lyrics granularity</label>
                    <select id="audioGranularity">
                        <option value="phrase">phrase (default)</option>
                        <option value="word">word</option>
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
            <div class="pg-response-title">
                <h2>Response</h2>
                <div id="statusLine" class="status-line" style="margin: 0;">Ready.</div>
            </div>
            <div class="pg-response-actions">
                <button type="button" class="pg-icon-btn" id="btnCopyResponse" title="Copy" aria-label="Copy">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <rect x="9" y="9" width="13" height="13" rx="2"/>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                    </svg>
                </button>
                <button type="button" class="pg-icon-btn" id="btnSaveResponse" title="Save JSON" aria-label="Save JSON">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <path d="M7 10l5 5 5-5"/>
                        <path d="M12 15V3"/>
                    </svg>
                </button>
            </div>
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
        'copied' => 'Copied.',
        'copyFailed' => 'Could not copy.',
        'saved' => 'JSON saved.',
        'nothingToSave' => 'Nothing to save.',
    ],
])
