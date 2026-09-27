# GigRunner API

API Laravel para autenticação, registo e licenças do GigRunner.

## Base URL

| Ambiente | URL |
|----------|-----|
| Local | `http://127.0.0.1:8000/api/v1/` |
| Produção (futuro) | `https://gigrunner.studio/api/v1/` |

## Web

| URL | Para quê |
|-----|----------|
| `/` | Hub |
| `/docs` | Documentação da API |
| `/playground` | Testar API no browser (sem Postman) |
| `/register`, `/login`, `/account` | Área web do cliente (sessão + licença de teste) |

## Auth API (`/api/v1`)


| Método | Path | Auth | Descrição |
|--------|------|------|-----------|
| POST | `/register` | — | Cria conta → `{ user, license, token }` |
| POST | `/login` | — | Login → `{ user, license, token }` |
| GET | `/me` | Bearer | Conta + licença |
| GET | `/license` | Bearer | Só estado da licença |
| POST | `/license/activate-test` | Bearer | Trial 30 dias (teste, sem pagamento) |
| POST | `/license/revoke` | Bearer | Revoga licença actual (teste) |
| GET | `/credits` | Bearer | Saldos + packs |
| POST | `/credits/consume` | Bearer | Consome créditos (`type`, `amount`) |
| POST | `/credits/purchase-test` | Bearer | Simula compra de pack |
| POST | `/audio/jobs` | Bearer | Análise/transcrição (Magic Chords) |
| GET | `/audio/jobs/{id}` | Bearer | Estado do job |
| GET | `/audio/jobs/{id}/result` | Bearer | Resultado |
| POST | `/logout` | Bearer | Revoga o token actual |

### Créditos

Tipos: `ai` (extensível em `config/credits.php`).

Pack exemplo `license_standard`: 10,00 € → licença `standard` + 50 AI.

### Licença (`license`)

```json
{
  "valid": true,
  "status": "active",
  "plan": "trial",
  "starts_at": "...",
  "expires_at": "..."
}
```

Sem licença: `valid: false`, `status: "inactive"`.

## Web de teste

Com `php artisan serve`, abre:

- [http://127.0.0.1:8000/register](http://127.0.0.1:8000/register)
- [http://127.0.0.1:8000/login](http://127.0.0.1:8000/login)
- [http://127.0.0.1:8000/account](http://127.0.0.1:8000/account) — ver conta + gerar token API


Requisitos: PHP 8.2+, Composer, Git. MySQL do XAMPP opcional (por defeito usa SQLite).

```bash
git clone https://github.com/softracegit/api_gigrunner.git
cd api_gigrunner
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Teste rápido:

```bash
curl http://127.0.0.1:8000/api/v1/health
```

### MySQL (como no projeto Admin)

1. Arranca MySQL no XAMPP.
2. Cria a BD `api_gigrunner`.
3. No `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=api_gigrunner
DB_USERNAME=root
DB_PASSWORD=
```

4. `php artisan migrate`

## Git / deploy

- Trabalho local + `git push` para este repositório.
- Deploy no teu servidor (SSH) depois; migração para `gigrunner.studio` mais tarde.
