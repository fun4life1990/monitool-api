# Monitool

Адмін-панель для автоматичного моніторингу доступності доменів. Backend на Laravel 13. Фронтенд — окремий репозиторій.

## Стек

- **PHP** 8.4-fpm (alpine) + XDebug
- **Laravel** 13
- **PostgreSQL** 16
- **Redis** 7 — cache, session, queue (Horizon)
- **Nginx** 1.27
- **Mailpit** — локальний SMTP-перехоплювач для розробки
- **Docker Compose** — інфраструктура локального запуску

## Сервіси (docker-compose)

| Service     | Призначення                                           |
|-------------|-------------------------------------------------------|
| `nginx`     | HTTP-вхід, статика, проксі до php-fpm                 |
| `app`       | php-fpm — основний обробник HTTP-запитів              |
| `db`        | PostgreSQL 16                                         |
| `redis`     | Redis 7                                               |
| `mailpit`   | SMTP (1025) + UI (8025) для розробки                  |

Фонові процеси (Horizon, scheduler) **не запускаються в compose** — їх піднімають вручну з консолі коли потрібно (див. розділ «Фонові задачі»).

## Локальний запуск

### 1. Підготовка

```bash
cp .env.example .env
```

За потреби — змінити `APP_PORT`, `DB_PORT`, `REDIS_PORT`, `MAILPIT_SMTP_PORT`, `MAILPIT_UI_PORT`, `XDEBUG_STORM_PORT`, `XDEBUG_STORM_SERVER_NAME` у `.env`.

### 2. Підняти стек

```bash
docker compose build
docker compose up -d
```

При першому старті `entrypoint.sh` автоматично виконає `composer install`, якщо `vendor/` відсутній.

### 3. Згенерувати `APP_KEY` (якщо ще не згенерований) і виконати міграції

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

### 4. Перевірити

- API health-check: [http://localhost:8080/up](http://localhost:8080/up) → 200.
- Swagger UI: [http://localhost:8080/swagger](http://localhost:8080/swagger) (при `SWAGGER_ENABLE=true`).
- Mailpit inbox: [http://localhost:8025](http://localhost:8025).
- Horizon dashboard: [http://localhost:8080/horizon](http://localhost:8080/horizon) (тільки `APP_ENV=local`).

## Фонові задачі

Перевірки доменів виконуються асинхронно через Horizon, а cron-розклад (раз на хвилину) ставить нові job-и. Локально їх запускають вручну в окремих терміналах:

```bash
# Термінал 1 — обробник черг
docker compose exec app php artisan horizon

# Термінал 2 — scheduler (cron-like loop)
docker compose exec app php artisan schedule:work
```

Альтернатива `schedule:work` для разової перевірки — `php artisan schedule:run` (виконати все, що по cron повинно було спрацювати).

Horizon UI на `APP_ENV != local` гейтиться cookie-перевіркою через `HORIZON_AUTH_COOKIE_NAME` + `HORIZON_AUTH_COOKIE_VALUE` (виставити вручну у браузері перед відкриттям `/horizon`). У local — UI відкритий без cookie.

## CORS

CORS-конфіг — `config/cors.php`, керується env `CORS_ALLOWED_ORIGINS`:

- `*` — будь-який origin (підходить для локальної розробки)
- список через кому, напр. `https://app.example.com,https://admin.example.com`

## API

- Канонічна специфікація — `openapi.yaml` у корені.
- Базовий префікс — `/api/v1`.
- Авторизація — Laravel Sanctum, **виключно Bearer токени** (SPA/cookie-flow відключені; `/sanctum/csrf-cookie` не реєструється). Токен видається `/api/v1/auth/register` або `/api/v1/auth/login` і передається у заголовку `Authorization: Bearer <token>`.

## Email

Локально пошта йде у Mailpit (`MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025`). UI: [http://localhost:8025](http://localhost:8025).

## XDebug + PhpStorm

Контейнер `app` отримує з `.env` runtime-конфіг:

```yaml
XDEBUG_CONFIG: "client_host=host.docker.internal client_port=${XDEBUG_STORM_PORT} mode=debug"
PHP_IDE_CONFIG: "serverName=${XDEBUG_STORM_SERVER_NAME}"
```

Як підключити PhpStorm:

1. **Settings → PHP → Debug** — порт `XDEBUG_STORM_PORT` (за замовчанням `9003`).
2. **Settings → PHP → Servers** — додати сервер з ім'ям, що дорівнює `XDEBUG_STORM_SERVER_NAME` (за замовчанням `monitool`), host `localhost`, port `8080`, mapping `/var/www` → корінь проекту.
3. Натиснути **Start Listening for PHP Debug Connections** → виставити breakpoint → відкрити сторінку у браузері.

XDebug працює у `start_with_request=trigger` режимі — увімкнеться автоматично, коли PhpStorm слухає підключення.

## Корисні команди

```bash
# artisan
docker compose exec app php artisan <command>

# composer
docker compose exec app composer <command>

# зайти у shell контейнера
docker compose exec app sh

# одноразово прогнати scheduler без long-running
docker compose exec app php artisan schedule:run

# Pint (formatter)
docker compose exec -T app vendor/bin/pint --dirty --format agent

# перебудувати образ після змін у Dockerfile
docker compose build --no-cache
```

## Production / деплой

В репо є production-артефакти для self-host або DigitalOcean App Platform — локальний `docker-compose.yml` для них **не використовується**.

- **`Dockerfile`** (корінь) — multi-stage образ:
  1. `vendor` (composer:2) — `composer install --no-dev --optimize-autoloader` (з `--ignore-platform-req=ext-pcntl`, бо `pcntl` ставиться у runtime-стадії).
  2. `runtime` (php:8.4-fpm-alpine) — php-fpm + nginx + supervisor у одному образі, слухає `:8080`. Конфіги — у `docker/prod/` (`php.ini`, `php-fpm.conf`, `nginx.conf`, `supervisord.conf`, `entrypoint.sh`).
- **`docker/prod/entrypoint.sh`** — на старті чистить і перебудовує `config:cache` / `route:cache` / `event:cache` під runtime env, потім `exec` основного процесу (`supervisord`).
- **`.do/app.yaml`** — DigitalOcean App Platform spec. Описує:
  - `services.api` — HTTP-сервіс (health-check `GET /up`, port 8080);
  - `workers.horizon` — `php artisan horizon`;
  - `workers.scheduler` — `php artisan schedule:work`;
  - `jobs.migrate` (PRE_DEPLOY) — `php artisan migrate --force`;
  - managed PostgreSQL + Valkey (Redis-сумісний) кластери, підключені через `${monitool-postgres.*}` / `${monitool-redis.DATABASE_URL}`.

Перед першим deploy потрібно проставити секрети у App Platform: `APP_KEY` (`php artisan key:generate --show`) і `HORIZON_AUTH_COOKIE_VALUE`.

Self-host локально без compose:

```bash
docker build -t monitool:prod .
docker run --rm -p 8080:8080 --env-file .env.production monitool:prod
```

## Структура

```
.
├── app/, bootstrap/, config/, ...    Laravel 13 app
├── docker/
│   ├── php/         dev: Dockerfile, php.ini, xdebug.ini, entrypoint.sh
│   ├── nginx/       dev: default.conf
│   └── prod/        prod: nginx.conf, php-fpm.conf, php.ini, supervisord.conf, entrypoint.sh
├── docker-compose.yml    dev стек
├── Dockerfile            prod образ (multi-stage)
├── .do/app.yaml          DigitalOcean App Platform spec
├── openapi.yaml          OpenAPI 3.0 spec
└── .env.example
```
