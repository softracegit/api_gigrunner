# GigRunner API

API Laravel para autenticação, registo e licenças do GigRunner.

## Base URL

| Ambiente | URL |
|----------|-----|
| Local | `http://127.0.0.1:8000/api/v1/` |
| Produção (futuro) | `https://gigrunner.studio/api/v1/` |

## Setup local (XAMPP)

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
