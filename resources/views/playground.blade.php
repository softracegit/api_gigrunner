@extends('layouts.app')

@section('title', 'Playground — GigRunner API')
@section('body_class', 'shell-page')
@section('wrap_class', 'shell')

@section('content')
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
                <p class="sub">Trial gratis ou revogar. A licença paga vem no pack abaixo.</p>
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
                <p class="sub">Precisa licença + créditos. Consome 1 AI ao criar o job.</p>
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
            <h2>Resposta</h2>
            <div id="statusLine" class="status-line" style="margin: 0;">Pronto.</div>
        </div>
        <div class="code-box">
            <pre id="responseBox">{}</pre>
        </div>
    </aside>
</div>

<script>
    (function () {
        const STORAGE_KEY = 'gigrunner_api_token';
        const WIDTHS_KEY = 'gigrunner_pg_widths';
        const MIN = 180;
        const shell = document.getElementById('pgShell');
        const colAuth = document.getElementById('pgColAuth');
        const colActions = document.getElementById('pgColActions');
        const colResponse = document.getElementById('pgColResponse');

        function applyWidths(authW, responseW) {
            colAuth.style.flex = '0 0 ' + authW + 'px';
            colAuth.style.width = authW + 'px';
            colResponse.style.flex = '0 0 ' + responseW + 'px';
            colResponse.style.width = responseW + 'px';
        }

        function saveWidths() {
            localStorage.setItem(WIDTHS_KEY, JSON.stringify({
                auth: Math.round(colAuth.getBoundingClientRect().width),
                response: Math.round(colResponse.getBoundingClientRect().width),
            }));
        }

        function loadWidths() {
            try {
                const saved = JSON.parse(localStorage.getItem(WIDTHS_KEY) || '{}');
                if (saved.auth && saved.response) {
                    applyWidths(saved.auth, saved.response);
                }
            } catch (e) {}
        }

        function setupSplitter(el, mode) {
            el.addEventListener('pointerdown', function (e) {
                if (window.matchMedia('(max-width: 860px)').matches) return;
                e.preventDefault();
                el.classList.add('is-dragging');
                document.body.classList.add('is-resizing-cols');
                el.setPointerCapture(e.pointerId);

                const startX = e.clientX;
                const startAuth = colAuth.getBoundingClientRect().width;
                const startResponse = colResponse.getBoundingClientRect().width;
                const shellW = shell.getBoundingClientRect().width;
                const splitters = 10; // 2 * 5px

                function onMove(ev) {
                    const dx = ev.clientX - startX;
                    let authW = startAuth;
                    let responseW = startResponse;

                    if (mode === 'auth-actions') {
                        authW = startAuth + dx;
                    } else {
                        responseW = startResponse - dx;
                    }

                    const maxAuth = shellW - splitters - MIN - MIN;
                    const maxResponse = shellW - splitters - MIN - MIN;
                    authW = Math.max(MIN, Math.min(maxAuth, authW));
                    responseW = Math.max(MIN, Math.min(maxResponse, responseW));

                    // Keep middle usable
                    if (authW + responseW > shellW - splitters - MIN) {
                        if (mode === 'auth-actions') {
                            authW = shellW - splitters - MIN - responseW;
                        } else {
                            responseW = shellW - splitters - MIN - authW;
                        }
                    }

                    applyWidths(authW, responseW);
                }

                function onUp(ev) {
                    el.classList.remove('is-dragging');
                    document.body.classList.remove('is-resizing-cols');
                    el.releasePointerCapture(ev.pointerId);
                    el.removeEventListener('pointermove', onMove);
                    el.removeEventListener('pointerup', onUp);
                    el.removeEventListener('pointercancel', onUp);
                    saveWidths();
                }

                el.addEventListener('pointermove', onMove);
                el.addEventListener('pointerup', onUp);
                el.addEventListener('pointercancel', onUp);
            });
        }

        document.querySelectorAll('.pg-splitter').forEach(function (el) {
            setupSplitter(el, el.getAttribute('data-split'));
        });
        loadWidths();

        const baseUrl = document.getElementById('baseUrl').textContent.trim();
        const responseBox = document.getElementById('responseBox');
        const statusLine = document.getElementById('statusLine');
        const tokenPreview = document.getElementById('tokenPreview');
        const regEmail = document.getElementById('regEmail');
        const loginEmail = document.getElementById('loginEmail');
        const random = Math.floor(Math.random() * 100000);
        regEmail.value = 'playground_' + random + '@test.local';
        loginEmail.value = regEmail.value;

        function getToken() {
            return localStorage.getItem(STORAGE_KEY) || '';
        }

        function setToken(token) {
            if (token) localStorage.setItem(STORAGE_KEY, token);
            else localStorage.removeItem(STORAGE_KEY);
            renderToken();
        }

        function renderToken() {
            const t = getToken();
            if (!t) {
                tokenPreview.innerHTML = '<code style="color: var(--muted);">— nenhum —</code>';
                return;
            }
            const short = t.length > 36 ? t.slice(0, 18) + '…' + t.slice(-10) : t;
            tokenPreview.innerHTML = '<code>' + short + '</code>';
        }

        async function callApi(method, path, body, useAuth) {
            const headers = {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            };
            if (useAuth) {
                const token = getToken();
                if (!token) {
                    statusLine.className = 'status-line err';
                    statusLine.textContent = 'Sem token — faz login ou registo primeiro.';
                    responseBox.textContent = '{}';
                    return null;
                }
                headers['Authorization'] = 'Bearer ' + token;
            }

            statusLine.className = 'status-line';
            statusLine.textContent = method + ' ' + path + ' …';

            try {
                const res = await fetch(baseUrl + path, {
                    method,
                    headers,
                    body: body ? JSON.stringify(body) : undefined,
                });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch { json = { raw: text }; }

                if (json.token) {
                    setToken(json.token);
                    if (json.user && json.user.email) loginEmail.value = json.user.email;
                }

                statusLine.className = 'status-line ' + (res.ok ? 'ok' : 'err');
                statusLine.textContent = res.status + ' ' + res.statusText;
                responseBox.textContent = JSON.stringify(json, null, 2);
                return json;
            } catch (e) {
                statusLine.className = 'status-line err';
                statusLine.textContent = 'Erro de rede';
                responseBox.textContent = String(e);
                return null;
            }
        }

        document.getElementById('btnRegister').addEventListener('click', function () {
            callApi('POST', '/register', {
                name: document.getElementById('regName').value,
                email: regEmail.value,
                password: document.getElementById('regPassword').value,
                password_confirmation: document.getElementById('regPassword').value,
            }, false);
        });
        document.getElementById('btnLogin').addEventListener('click', function () {
            callApi('POST', '/login', {
                email: loginEmail.value,
                password: document.getElementById('loginPassword').value,
            }, false);
        });
        document.getElementById('btnHealth').addEventListener('click', () => callApi('GET', '/health', null, false));
        document.getElementById('btnMe').addEventListener('click', () => callApi('GET', '/me', null, true));
        document.getElementById('btnLicense').addEventListener('click', () => callApi('GET', '/license', null, true));
        document.getElementById('btnCredits').addEventListener('click', () => callApi('GET', '/credits', null, true));
        document.getElementById('btnActivateLicense').addEventListener('click', () => callApi('POST', '/license/activate-test', {}, true));
        document.getElementById('btnRevokeLicense').addEventListener('click', () => callApi('POST', '/license/revoke', {}, true));
        document.getElementById('btnConsumeAi').addEventListener('click', () => callApi('POST', '/credits/consume', { type: 'ai', amount: 1, reason: 'playground' }, true));

        ['btnBuyStandard', 'btnBuyAi50', 'btnBuyAi200'].forEach(function (id) {
            document.getElementById(id).addEventListener('click', function () {
                callApi('POST', '/credits/purchase-test', { pack: this.getAttribute('data-pack') }, true);
            });
        });

        document.getElementById('btnAudioCreate').addEventListener('click', async function () {
            const json = await callApi('POST', '/audio/jobs', {
                url: document.getElementById('audioUrl').value,
                kind: document.getElementById('audioKind').value,
            }, true);
            if (json && json.job && json.job.id) {
                document.getElementById('audioJobId').value = json.job.id;
            }
        });

        document.getElementById('btnAudioUpload').addEventListener('click', async function () {
            const input = document.getElementById('audioFile');
            if (!input.files || !input.files[0]) {
                statusLine.className = 'status-line err';
                statusLine.textContent = 'Escolhe um ficheiro de áudio primeiro.';
                return;
            }
            const token = getToken();
            if (!token) {
                statusLine.className = 'status-line err';
                statusLine.textContent = 'Sem token — faz login ou registo primeiro.';
                return;
            }

            const form = new FormData();
            form.append('file', input.files[0]);
            form.append('kind', document.getElementById('audioKind').value);

            statusLine.className = 'status-line';
            statusLine.textContent = 'POST /audio/jobs (upload) …';

            try {
                const res = await fetch(baseUrl + '/audio/jobs', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + token,
                    },
                    body: form,
                });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch { json = { raw: text }; }

                statusLine.className = 'status-line ' + (res.ok ? 'ok' : 'err');
                statusLine.textContent = res.status + ' ' + res.statusText;
                responseBox.textContent = JSON.stringify(json, null, 2);

                if (json && json.job && json.job.id) {
                    document.getElementById('audioJobId').value = json.job.id;
                }
            } catch (e) {
                statusLine.className = 'status-line err';
                statusLine.textContent = 'Erro de rede';
                responseBox.textContent = String(e);
            }
        });

        document.getElementById('btnAudioStatus').addEventListener('click', function () {
            const id = document.getElementById('audioJobId').value.trim();
            if (!id) {
                statusLine.className = 'status-line err';
                statusLine.textContent = 'Indica um Job ID.';
                return;
            }
            callApi('GET', '/audio/jobs/' + encodeURIComponent(id), null, true);
        });
        document.getElementById('btnAudioResult').addEventListener('click', function () {
            const id = document.getElementById('audioJobId').value.trim();
            if (!id) {
                statusLine.className = 'status-line err';
                statusLine.textContent = 'Indica um Job ID.';
                return;
            }
            callApi('GET', '/audio/jobs/' + encodeURIComponent(id) + '/result', null, true);
        });

        document.getElementById('btnLogout').addEventListener('click', async function () {
            await callApi('POST', '/logout', {}, true);
            setToken('');
        });
        document.getElementById('btnClearToken').addEventListener('click', function () {
            setToken('');
            statusLine.className = 'status-line';
            statusLine.textContent = 'Token limpo.';
            responseBox.textContent = '{}';
        });

        renderToken();
    })();
</script>
@endsection
