<div class="pg-shell pg-compact" id="pgShell">
    <aside class="pg-col pg-col-auth" id="pgColAuth">
        <div class="pg-col-scroll">
            <h1>Playground</h1>
            <p class="sub">Registo e login (como a app).</p>

            <div class="card">
                <h2>Registo</h2>
                <div class="field">
                    <label for="regName">Nome</label>
                    <input id="regName" type="text" value="Teste Playground">
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

    <div class="pg-splitter" data-split="auth-actions" title="Arrastar para redimensionar"></div>

    <section class="pg-col pg-col-actions" id="pgColActions">
        <div class="pg-col-scroll">
            <div class="card">
                <h2>Sessão API</h2>
                <div class="meta">
                    <div class="meta-row"><span>Base</span><div><code id="baseUrl">{{ url('/api/v1') }}</code></div></div>
                    <div class="meta-row"><span>Token</span><div id="tokenPreview"><code style="color: var(--muted);">— nenhum —</code></div></div>
                </div>
                <div class="pg-actions" style="margin-top: 12px;">
                    <button type="button" class="btn secondary" id="btnClearToken">Limpar token</button>
                    <a class="btn secondary" href="{{ route('docs') }}">Docs</a>
                </div>
            </div>

            <div class="card">
                <h2>Pedidos</h2>
                <div class="pg-actions">
                    <button type="button" class="btn secondary" id="btnHealth">GET /health</button>
                    <button type="button" class="btn secondary" id="btnMe">GET /me</button>
                    <button type="button" class="btn secondary" id="btnLicense">GET /license</button>
                    <button type="button" class="btn secondary" id="btnCredits">GET /credits</button>
                    <button type="button" class="btn danger" id="btnLogout">POST /logout</button>
                </div>
            </div>

            <div class="card">
                <h2>Licença</h2>
                <p class="sub">Trial grátis ou revogar. A licença paga vem no pack abaixo.</p>
                <div class="pg-actions">
                    <button type="button" class="btn secondary" id="btnActivateLicense">Trial 30 dias</button>
                    <button type="button" class="btn secondary" id="btnRevokeLicense">Revogar</button>
                </div>
            </div>

            <div class="card">
                <h2>Comprar (teste)</h2>
                <p class="sub">Simula pagamento. Standard = 10€ → licença + 50 AI.</p>
                <div class="pg-actions">
                    <button type="button" class="btn" id="btnBuyStandard" data-pack="license_standard">Licença 10€ + 50 AI</button>
                    <button type="button" class="btn secondary" id="btnBuyAi50" data-pack="ai_50">+50 AI (5€)</button>
                    <button type="button" class="btn secondary" id="btnBuyAi200" data-pack="ai_200">+200 AI (15€)</button>
                </div>
            </div>

            <div class="card">
                <h2>Consumir créditos</h2>
                <p class="sub">Como a app faz antes de usar AI.</p>
                <div class="pg-actions">
                    <button type="button" class="btn secondary" id="btnConsumeAi">Consumir 1 AI</button>
                </div>
            </div>

            <div class="card">
                <h2>Áudio (Magic Chords)</h2>
                <p class="sub">Precisa de licença + créditos. Consome 1 AI ao criar o job.</p>
                <div class="field">
                    <label for="audioFile">Ficheiro MP3 (ou áudio)</label>
                    <input id="audioFile" type="file" accept="audio/mpeg,audio/mp3,audio/*,.mp3,.wav,.flac,.ogg,.m4a">
                </div>
                <div class="field">
                    <label for="audioUrl">…ou URL do áudio</label>
                    <input id="audioUrl" type="text" value="https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3">
                </div>
                <div class="field">
                    <label for="audioKind">Tipo</label>
                    <select id="audioKind">
                        <option value="analyze">analyze (acordes / tempo / key)</option>
                        <option value="transcribe">transcribe (letras)</option>
                    </select>
                </div>
                <div class="field">
                    <label for="audioJobId">Job ID</label>
                    <input id="audioJobId" type="text" placeholder="preenchido após criar" value="">
                </div>
                <div class="pg-actions">
                    <button type="button" class="btn" id="btnAudioUpload">Upload ficheiro</button>
                    <button type="button" class="btn secondary" id="btnAudioCreate">POST por URL</button>
                    <button type="button" class="btn secondary" id="btnAudioStatus">GET status</button>
                    <button type="button" class="btn secondary" id="btnAudioResult">GET result</button>
                </div>
            </div>
        </div>
    </section>

    <div class="pg-splitter" data-split="actions-response" title="Arrastar para redimensionar"></div>

    <aside class="pg-col pg-col-response" id="pgColResponse">
        <div class="pg-response-head">
            <div class="pg-response-title">
                <h2>Resposta</h2>
                <div id="statusLine" class="status-line" style="margin: 0;">Pronto.</div>
            </div>
            <div class="pg-response-actions">
                <button type="button" class="pg-icon-btn" id="btnCopyResponse" title="Copiar" aria-label="Copiar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <rect x="9" y="9" width="13" height="13" rx="2"/>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                    </svg>
                </button>
                <button type="button" class="pg-icon-btn" id="btnSaveResponse" title="Guardar JSON" aria-label="Guardar JSON">
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
        'noToken' => '— nenhum —',
        'needAuth' => 'Sem token — faz login ou registo primeiro.',
        'networkError' => 'Erro de rede',
        'needFile' => 'Escolhe um ficheiro de áudio primeiro.',
        'needJobId' => 'Indica um Job ID.',
        'tokenCleared' => 'Token limpo.',
        'ready' => 'Pronto.',
        'uploading' => 'POST /audio/jobs (upload) …',
        'copied' => 'Copiado.',
        'copyFailed' => 'Não foi possível copiar.',
        'saved' => 'JSON guardado.',
        'nothingToSave' => 'Nada para guardar.',
    ],
])
