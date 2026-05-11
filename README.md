# Monitool

Адмін-панель для автоматичного моніторингу доступності доменів. Backend на Laravel 12. Фронтенд — окремий репозиторій.

## Стек

- **PHP** 8.4-fpm (alpine) + XDebug
- **Laravel** 12
- **PostgreSQL** 16
- **Redis** 7 — cache, session, queue
- **Nginx** 1.27
- **Docker Compose** — інфраструктура локального запуску

## Сервіси (docker-compose)

| Service     | Призначення                                           |
|-------------|-------------------------------------------------------|
| `nginx`     | HTTP-вхід, статика, проксі до php-fpm                 |
| `app`       | php-fpm — основний обробник HTTP-запитів              |
| `worker`    | `php artisan queue:work` — обробка фонових задач      |
| `scheduler` | `php artisan schedule:work` — періодичні задачі       |
| `db`        | PostgreSQL 16                                         |
| `redis`     | Redis 7                                               |

`app`, `worker`, `scheduler` зібрані з одного образу — масштабуються незалежно:

```bash
docker compose up -d --scale worker=4
```

## Локальний запуск

### 1. Підготовка

```bash
cp .env.example .env
```

За потреби — змінити `APP_PORT`, `DB_PORT`, `REDIS_PORT`, `XDEBUG_STORM_PORT`, `XDEBUG_STORM_SERVER_NAME` у `.env`.

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

Відкрити [http://localhost:8080](http://localhost:8080) — має бути Laravel welcome.

## XDebug + PhpStorm

Контейнери `app`, `worker`, `scheduler` отримують з `.env` runtime-конфіг:

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
# артізан-команди в app-контейнері
docker compose exec app php artisan <command>

# composer
docker compose exec app composer <command>

# зайти у shell контейнера
docker compose exec app sh

# подивитись логи воркера / шедулера
docker compose logs -f worker scheduler

# перебудувати образ після змін у Dockerfile
docker compose build --no-cache
```

## Структура

```
.
├── app/, bootstrap/, config/, ...    Laravel 12 app
├── docker/
│   ├── php/         Dockerfile, php.ini, xdebug.ini, entrypoint.sh
│   └── nginx/       default.conf
├── docker-compose.yml
└── .env.example
```
