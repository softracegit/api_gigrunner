<div class="docs-shell">
    <aside class="docs-side">
        <p class="side-title">Documentação</p>
        <a href="#inicio" class="active" data-docs-link>Início</a>
        <a href="#guia" data-docs-link>Guia de integração</a>
        <a href="#guia-setup" data-docs-link>— Setup</a>
        <a href="#guia-login" data-docs-link>— Login</a>
        <a href="#guia-arranque" data-docs-link>— Arranque</a>
        <a href="#guia-ai" data-docs-link>— Usar AI</a>
        <a href="#guia-audio" data-docs-link>— Análise áudio</a>
        <a href="#guia-offline" data-docs-link>— Offline</a>
        <a href="#guia-erros" data-docs-link>— Erros na app</a>
        <a href="#guia-checklist" data-docs-link>— Checklist</a>
        <a href="#fluxo" data-docs-link>Fluxo</a>
        <a href="#auth" data-docs-link>Autenticação</a>
        <a href="#endpoints" data-docs-link>Endpoints</a>
        <a href="#health" data-docs-link>GET /health</a>
        <a href="#register" data-docs-link>POST /register</a>
        <a href="#login" data-docs-link>POST /login</a>
        <a href="#me" data-docs-link>GET /me</a>
        <a href="#license" data-docs-link>GET /license</a>
        <a href="#activate-test" data-docs-link>POST /license/activate-test</a>
        <a href="#revoke" data-docs-link>POST /license/revoke</a>
        <a href="#credits-model" data-docs-link>Modelo créditos</a>
        <a href="#credits" data-docs-link>GET /credits</a>
        <a href="#credits-consume" data-docs-link>POST /credits/consume</a>
        <a href="#credits-purchase" data-docs-link>POST /credits/purchase-test</a>
        <a href="#audio-jobs" data-docs-link>POST /audio/jobs</a>
        <a href="#audio-job-status" data-docs-link>GET /audio/jobs/{id}</a>
        <a href="#audio-job-result" data-docs-link>GET /audio/jobs/{id}/result</a>
        <a href="#logout" data-docs-link>POST /logout</a>
        <a href="#erros" data-docs-link>Erros</a>
        <a href="#user" data-docs-link>Objecto user</a>
        <a href="{{ route('playground') }}">Playground →</a>
    </aside>

    <main class="docs-main" id="docsMain">
        <div id="inicio">
            <h1>Documentação da API</h1>
            <p class="sub">
                Base URL: <code>{{ url('/api/v1') }}</code><br>
                Referência completa dos endpoints + <a href="#guia" style="color: var(--accent);">guia de integração</a> para a app GigRunner / Cue Companion.
            </p>
        </div>

        <div class="card" id="guia">
            <h2 style="margin-top: 0;">Guia de integração (app)</h2>
            <p style="margin: 0; color: var(--muted); line-height: 1.55;">
                Destinado a quem implementa o cliente desktop/mobile.
                O site trata de registo/compra; a app trata de login, validar licença e consumir créditos AI.
            </p>
        </div>

        <div class="card" id="guia-setup">
            <h2 style="margin-top: 0;">1. Setup</h2>
            <div class="meta">
                <div class="meta-row"><span>Base URL</span><div><code>{{ url('/api/v1') }}</code></div></div>
                <div class="meta-row"><span>Formato</span><div>JSON · <code>Content-Type: application/json</code> · <code>Accept: application/json</code></div></div>
                <div class="meta-row"><span>Auth</span><div><code>Authorization: Bearer &lt;token&gt;</code></div></div>
            </div>
            <p class="hint" style="margin-top: 12px;">
                Em desenvolvimento podes usar o <a href="{{ route('playground') }}" style="color: var(--accent);">Playground</a> para validar as respostas antes de ligar a app.
            </p>
        </div>

        <div class="card" id="guia-login">
            <h2 style="margin-top: 0;">2. Login (uma vez / quando expirar)</h2>
            <p style="margin: 0 0 12px; color: var(--muted); line-height: 1.55;">
                Ecrã de login na app → <code>POST /login</code> com email e password.
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
                <strong style="color: var(--text);">Guardar em disco (seguro):</strong>
                <code>token</code>, <code>user.uuid</code>, <code>license</code>, <code>credits</code>,
                e <code>last_validated_at = now</code>.
            </p>
            <p style="margin: 8px 0 0; color: var(--muted); line-height: 1.55;">
                Se <code>license.valid === false</code>: deixa entrar só ao ecrã “activa / compra licença”
                (abre o site no browser). Não desbloqueies a app completa.
            </p>
        </div>

        <div class="card" id="guia-arranque">
            <h2 style="margin-top: 0;">3. Arranque seguinte (já tem token)</h2>
            <ol style="margin: 0; padding-left: 18px; color: var(--muted); line-height: 1.6;">
                <li>Ler token do disco. Se não houver → ecrã de login.</li>
                <li>Com rede: <code>GET /license</code> (ou <code>GET /me</code> se quiseres user + credits também).</li>
                <li>Actualizar cache local (<code>license</code>, <code>credits</code> se vierem, <code>last_validated_at</code>).</li>
                <li>Se <code>valid === true</code> → app normal. Caso contrário → bloquear / pedir compra.</li>
                <li>Se <strong style="color: var(--text);">401</strong> → token inválido → limpar cache → login.</li>
            </ol>
            <p class="hint" style="margin-top: 12px;">
                O utilizador <strong>não</strong> volta a fazer login em cada abertura se o token ainda for válido.
            </p>
        </div>

        <div class="card" id="guia-ai">
            <h2 style="margin-top: 0;">4. Usar AI (obrigatório)</h2>
            <p style="margin: 0 0 12px; color: var(--muted); line-height: 1.55;">
                Antes de cada pedido AI à tua infra / modelo:
            </p>
            <div class="code-box">
<pre>POST /api/v1/credits/consume
Authorization: Bearer …
{ "type": "ai", "amount": 1, "reason": "cue_suggest" }

→ 200  { "ok": true, "balance": 49, "credits": { "ai": 49 } }
→ 402  { "message": "Créditos insuficientes.", "credits": { "ai": 0 } }</pre>
            </div>
            <ul style="margin: 12px 0 0; padding-left: 18px; color: var(--muted); line-height: 1.55;">
                <li><strong style="color: var(--text);">200</strong> → actualiza saldo local → executa a AI.</li>
                <li><strong style="color: var(--text);">402</strong> → não chames a AI; UI “compra mais créditos” (link ao site).</li>
                <li>Não confies só no saldo em cache para autorizar o gasto — o servidor decide.</li>
                <li>Opcional: envia <code>reference</code> único por operação (ex. id do job) para auditar / evitar double-spend no futuro.</li>
            </ul>
        </div>

        <div class="card" id="guia-audio">
            <h2 style="margin-top: 0;">4b. Análise de áudio (Magic Chords)</h2>
            <p style="margin: 0 0 12px; color: var(--muted); line-height: 1.55;">
                A app <strong style="color: var(--text);">não</strong> chama a Magic Chords directamente.
                Usa as URLs GigRunner. O backend escolhe o provider (hoje: Magic Chords).
                Requer licença válida; consome <strong style="color: var(--text);">1 crédito AI</strong> ao criar o job.
            </p>
            <div class="code-box">
<pre>POST /api/v1/audio/jobs
{ "url": "https://…/song.mp3", "kind": "analyze" }
  ou multipart: file + kind

→ 201 { "job": { "id", "status", "progress", … }, "credits" }

GET /api/v1/audio/jobs/{id}           → poll até status=complete
GET /api/v1/audio/jobs/{id}/result    → acordes, tempo, key, …</pre>
            </div>
            <p class="hint" style="margin-top: 12px;">
                <code>kind</code>: <code>analyze</code> (acordes/tempo/key) ou <code>transcribe</code> (letras).
                Os jobs demoram — faz poll a cada 2–5s.
            </p>
        </div>

        <div class="card" id="guia-offline">
            <h2 style="margin-top: 0;">5. Offline (proposta)</h2>
            <p style="margin: 0 0 12px; color: var(--muted); line-height: 1.55;">
                Ensaios podem não ter rede. Regra sugerida (acordar valor de N):
            </p>
            <ul style="margin: 0; padding-left: 18px; color: var(--muted); line-height: 1.55;">
                <li>Se há cache com <code>license.valid === true</code> e
                    <code>now - last_validated_at &lt; N dias</code> (ex.: <strong style="color: var(--text);">7</strong>)
                    → permite usar a app.</li>
                <li>Funções AI <strong style="color: var(--text);">requerem rede</strong> (porque precisam de
                    <code>/credits/consume</code>). Sem rede → desactivar AI ou mensagem clara.</li>
                <li>Quando voltar a rede → revalidar logo (<code>GET /license</code> + opcional <code>GET /credits</code>).</li>
                <li>Se online a API disser inválida/revogada → bloquear mesmo com cache antigo.</li>
            </ul>
        </div>

        <div class="card" id="guia-erros">
            <h2 style="margin-top: 0;">6. O que a app deve fazer por erro</h2>
            <div class="meta">
                <div class="meta-row"><span>401</span><div>Limpar token → ecrã login</div></div>
                <div class="meta-row"><span>402</span><div>Sem créditos AI → UI compra / top-up</div></div>
                <div class="meta-row"><span>422</span><div>Mostrar mensagem de validação (login incorrecto, etc.)</div></div>
                <div class="meta-row"><span>5xx / rede</span><div>Retry suave; se arranque, aplicar regra offline</div></div>
            </div>
        </div>

        <div class="card" id="guia-checklist">
            <h2 style="margin-top: 0;">7. Checklist para o cliente</h2>
            <ul style="margin: 0; padding-left: 18px; color: var(--muted); line-height: 1.7;">
                <li>□ Base URL configurável (dev / produção)</li>
                <li>□ Login → guardar <code>token</code> + <code>uuid</code> + <code>license</code> + <code>credits</code></li>
                <li>□ Arranque com token → <code>GET /license</code> (não pedir password outra vez)</li>
                <li>□ Bloquear app se <code>license.valid === false</code></li>
                <li>□ Antes de AI genérica → <code>POST /credits/consume</code>; tratar 402</li>
                <li>□ Análise áudio → <code>POST /audio/jobs</code> + poll + <code>/result</code></li>
                <li>□ Link “Registar / Comprar” → site (browser)</li>
                <li>□ Logout opcional → <code>POST /logout</code> + limpar disco</li>
                <li>□ Regra offline documentada (N dias; AI só online)</li>
            </ul>
            <p class="hint" style="margin-top: 14px;">
                Endpoints de teste (<code>activate-test</code>, <code>purchase-test</code>) são para o backend/playground —
                a app de produção não precisa de os chamar; a compra será no site.
            </p>
        </div>

        <div class="card" id="fluxo">
            <h2 style="margin-top: 0;">Fluxo típico (resumo)</h2>
            <ol style="margin: 0; padding-left: 18px; color: var(--muted); line-height: 1.6;">
                <li>Utilizador cria conta no <strong style="color: var(--text);">site</strong>.</li>
                <li>Compra licença (ex.: 10€) → fica com app activa + créditos iniciais (ex.: 50 AI).</li>
                <li>Na <strong style="color: var(--text);">app</strong>: login → token + license + credits.</li>
                <li>Antes de cada uso AI: <code>POST /credits/consume</code>. Se 402 → sem saldo, pedir top-up.</li>
            </ol>
        </div>

        <div class="card" id="auth">
            <h2 style="margin-top: 0;">Autenticação</h2>
            <p style="margin: 0; color: var(--muted); line-height: 1.5;">
                Endpoints protegidos precisam do header:<br>
                <code>Authorization: Bearer &lt;token&gt;</code><br>
                <code>Accept: application/json</code><br><br>
                O token vem em <code>POST /login</code> ou <code>POST /register</code>.
                O utilizador final <strong style="color: var(--text);">nunca</strong> gera tokens à mão — isso é só a app.
            </p>
        </div>

        <h2 id="endpoints">Endpoints</h2>

        <div class="endpoint" id="health">
            <div class="endpoint-head">
                <span class="method get">GET</span>
                <span class="endpoint-path">/api/v1/health</span>
                <span class="badge">público</span>
            </div>
            <div class="endpoint-body">
                Verifica se a API está online.<br>
                <strong>Resposta:</strong> <code>{ "ok": true, "app": "...", "env": "..." }</code>
            </div>
        </div>

        <div class="endpoint" id="register">
            <div class="endpoint-head">
                <span class="method post">POST</span>
                <span class="endpoint-path">/api/v1/register</span>
                <span class="badge">público</span>
            </div>
            <div class="endpoint-body">
                Cria conta.<br>
                <strong>Body:</strong> <code>name</code>, <code>email</code>, <code>password</code>, <code>password_confirmation</code><br>
                <strong>Resposta 201:</strong> <code>{ user, license, credits, token }</code>
            </div>
        </div>

        <div class="endpoint" id="login">
            <div class="endpoint-head">
                <span class="method post">POST</span>
                <span class="endpoint-path">/api/v1/login</span>
                <span class="badge">público</span>
            </div>
            <div class="endpoint-body">
                Login da app.<br>
                <strong>Body:</strong> <code>email</code>, <code>password</code><br>
                <strong>Resposta:</strong> <code>{ user, license, credits, token }</code>
            </div>
        </div>

        <div class="endpoint" id="me">
            <div class="endpoint-head">
                <span class="method get">GET</span>
                <span class="endpoint-path">/api/v1/me</span>
                <span class="badge">Bearer</span>
            </div>
            <div class="endpoint-body">
                Dados da conta + licença + créditos.<br>
                <strong>Resposta:</strong> <code>{ user, license, credits }</code>
            </div>
        </div>

        <div class="endpoint" id="license">
            <div class="endpoint-head">
                <span class="method get">GET</span>
                <span class="endpoint-path">/api/v1/license</span>
                <span class="badge">Bearer</span>
            </div>
            <div class="endpoint-body">
                Estado da licença (o que a app valida ao abrir).<br>
                <strong>Resposta:</strong>
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
                Sem licença: <code>valid: false</code>, <code>status: "inactive"</code>.
            </div>
        </div>

        <div class="endpoint" id="activate-test">
            <div class="endpoint-head">
                <span class="method post">POST</span>
                <span class="endpoint-path">/api/v1/license/activate-test</span>
                <span class="badge">Bearer · teste</span>
            </div>
            <div class="endpoint-body">
                Cria uma licença trial de 30 dias (enquanto não há pagamento).<br>
                <strong>Resposta 201:</strong> <code>{ license }</code>
            </div>
        </div>

        <div class="endpoint" id="revoke">
            <div class="endpoint-head">
                <span class="method post">POST</span>
                <span class="endpoint-path">/api/v1/license/revoke</span>
                <span class="badge">Bearer · teste</span>
            </div>
            <div class="endpoint-body">
                Revoga a licença actual (para testar bloqueio na app).<br>
                <strong>Resposta:</strong> <code>{ license }</code>
            </div>
        </div>

        <div class="card" id="credits-model">
            <h2 style="margin-top: 0;">Modelo: licença vs créditos</h2>
            <p style="margin: 0 0 12px; color: var(--muted); line-height: 1.55;">
                <strong style="color: var(--text);">Licença</strong> = pode usar a app (porta de entrada).<br>
                <strong style="color: var(--text);">Créditos</strong> = saldo por tipo (agora só <code>ai</code>) para acções pagas.
            </p>
            <p style="margin: 0; color: var(--muted); line-height: 1.55;">
                Não precisas de “verificar créditos” em ciclo. A regra é:
            </p>
            <ul style="color: var(--muted); line-height: 1.55;">
                <li>Ao abrir a app → <code>GET /license</code> (e opcionalmente cache offline).</li>
                <li>Antes de cada acção AI → <code>POST /credits/consume</code> (atómico no servidor).</li>
                <li>Se 402 → mostra “sem créditos” e link para comprar pack.</li>
            </ul>
            <p style="margin: 12px 0 0; color: var(--muted); line-height: 1.55;">
                Há um <strong style="color: var(--text);">ledger</strong> (<code>credit_transactions</code>) para auditar compras e consumos.
            </p>
        </div>

        <div class="endpoint" id="credits">
            <div class="endpoint-head">
                <span class="method get">GET</span>
                <span class="endpoint-path">/api/v1/credits</span>
                <span class="badge">Bearer</span>
            </div>
            <div class="endpoint-body">
                Saldos + packs disponíveis.<br>
                <strong>Resposta:</strong> <code>{ credits: { ai }, packs: [...] }</code>
            </div>
        </div>

        <div class="endpoint" id="credits-consume">
            <div class="endpoint-head">
                <span class="method post">POST</span>
                <span class="endpoint-path">/api/v1/credits/consume</span>
                <span class="badge">Bearer</span>
            </div>
            <div class="endpoint-body">
                Consome créditos AI.<br>
                <strong>Body:</strong> <code>type</code> (<code>ai</code>), <code>amount</code>? (default 1), <code>reason</code>?<br>
                <strong>200:</strong> <code>{ ok, type, balance, credits }</code><br>
                <strong>402:</strong> créditos insuficientes
            </div>
        </div>

        <div class="endpoint" id="credits-purchase">
            <div class="endpoint-head">
                <span class="method post">POST</span>
                <span class="endpoint-path">/api/v1/credits/purchase-test</span>
                <span class="badge">Bearer · teste</span>
            </div>
            <div class="endpoint-body">
                Simula compra de pack (até existir Stripe).<br>
                <strong>Body:</strong> <code>{ "pack": "license_standard" }</code><br>
                Packs: <code>license_standard</code> (10€ → licença + 50 AI), <code>ai_50</code>, <code>ai_200</code>.
            </div>
        </div>

        <div class="endpoint" id="audio-jobs">
            <div class="endpoint-head">
                <span class="method post">POST</span>
                <span class="endpoint-path">/api/v1/audio/jobs</span>
                <span class="badge">Bearer</span>
            </div>
            <div class="endpoint-body">
                Cria análise/transcrição. Provider actual: <strong>Magic Chords</strong>. Consome 1 crédito AI.<br>
                <strong>JSON:</strong> <code>{ "url": "https://…", "kind": "analyze"|"transcribe" }</code><br>
                <strong>multipart:</strong> <code>file</code> + <code>kind</code><br>
                <strong>201:</strong> <code>{ job, credits }</code> · <strong>402</strong> sem créditos · <strong>403</strong> sem licença
            </div>
        </div>

        <div class="endpoint" id="audio-job-status">
            <div class="endpoint-head">
                <span class="method get">GET</span>
                <span class="endpoint-path">/api/v1/audio/jobs/{id}</span>
                <span class="badge">Bearer</span>
            </div>
            <div class="endpoint-body">
                Estado do job (<code>queued</code> / <code>processing</code> / <code>complete</code> / <code>failed</code>) + progresso.
            </div>
        </div>

        <div class="endpoint" id="audio-job-result">
            <div class="endpoint-head">
                <span class="method get">GET</span>
                <span class="endpoint-path">/api/v1/audio/jobs/{id}/result</span>
                <span class="badge">Bearer</span>
            </div>
            <div class="endpoint-body">
                Resultado completo do provider (acordes, tempo, key, …).<br>
                <strong>409</strong> se ainda não estiver <code>complete</code>.
            </div>
        </div>

        <div class="endpoint" id="logout">
            <div class="endpoint-head">
                <span class="method post">POST</span>
                <span class="endpoint-path">/api/v1/logout</span>
                <span class="badge">Bearer</span>
            </div>
            <div class="endpoint-body">
                Revoga o token actual.<br>
                <strong>Resposta:</strong> <code>{ "ok": true }</code>
            </div>
        </div>

        <div class="card" id="erros">
            <h2 style="margin-top: 0;">Erros comuns</h2>
            <div class="meta">
                <div class="meta-row"><span>401</span><div>Token em falta ou inválido</div></div>
                <div class="meta-row"><span>402</span><div>Créditos insuficientes (consume)</div></div>
                <div class="meta-row"><span>422</span><div>Validação (password errada, pack inválido, etc.)</div></div>
            </div>
        </div>

        <div class="card" id="user">
            <h2 style="margin-top: 0;">Objecto <code>user</code></h2>
            <div class="code-box">
<pre>{
  "id": 1,
  "uuid": "63f50785-…",
  "name": "Nome",
  "email": "user@email.com"
}</pre>
            </div>
            <p class="hint" style="margin-top: 12px;">
                O <code>uuid</code> é estável — a app pode guardá-lo localmente junto com o token e o estado da licença.
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
