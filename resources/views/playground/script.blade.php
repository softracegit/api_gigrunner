<script>
    (function () {
        const i18n = @json($i18n);
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
                const splitters = 10;

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
                tokenPreview.innerHTML = '<code style="color: var(--muted);">' + i18n.noToken + '</code>';
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
                    statusLine.textContent = i18n.needAuth;
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
                statusLine.textContent = i18n.networkError;
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
                statusLine.textContent = i18n.needFile;
                return;
            }
            const token = getToken();
            if (!token) {
                statusLine.className = 'status-line err';
                statusLine.textContent = i18n.needAuth;
                return;
            }

            const form = new FormData();
            form.append('file', input.files[0]);
            form.append('kind', document.getElementById('audioKind').value);

            statusLine.className = 'status-line';
            statusLine.textContent = i18n.uploading;

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
                statusLine.textContent = i18n.networkError;
                responseBox.textContent = String(e);
            }
        });

        document.getElementById('btnAudioStatus').addEventListener('click', function () {
            const id = document.getElementById('audioJobId').value.trim();
            if (!id) {
                statusLine.className = 'status-line err';
                statusLine.textContent = i18n.needJobId;
                return;
            }
            callApi('GET', '/audio/jobs/' + encodeURIComponent(id), null, true);
        });
        document.getElementById('btnAudioResult').addEventListener('click', function () {
            const id = document.getElementById('audioJobId').value.trim();
            if (!id) {
                statusLine.className = 'status-line err';
                statusLine.textContent = i18n.needJobId;
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
            statusLine.textContent = i18n.tokenCleared;
            responseBox.textContent = '{}';
        });

        function responseText() {
            return (responseBox.textContent || '').trim() || '{}';
        }

        function prettyJsonText() {
            const raw = responseText();
            try {
                return JSON.stringify(JSON.parse(raw), null, 2);
            } catch (e) {
                return raw;
            }
        }

        document.getElementById('btnCopyResponse').addEventListener('click', async function () {
            const text = prettyJsonText();
            try {
                await navigator.clipboard.writeText(text);
                statusLine.className = 'status-line ok';
                statusLine.textContent = i18n.copied;
            } catch (e) {
                statusLine.className = 'status-line err';
                statusLine.textContent = i18n.copyFailed;
            }
        });

        document.getElementById('btnSaveResponse').addEventListener('click', function () {
            const text = prettyJsonText();
            if (!text || text === '{}') {
                statusLine.className = 'status-line err';
                statusLine.textContent = i18n.nothingToSave;
                return;
            }

            const stamp = new Date().toISOString().replace(/[:.]/g, '-');
            const blob = new Blob([text], { type: 'application/json;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'gigrunner-response-' + stamp + '.json';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);

            statusLine.className = 'status-line ok';
            statusLine.textContent = i18n.saved;
        });

        renderToken();
    })();
</script>
