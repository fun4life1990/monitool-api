# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Monitool — backend для адмін-панелі автоматичного моніторингу доступності доменів. API-only Laravel-додаток; фронтенд у окремому репозиторії.

**Stack:** PHP 8.4 · Laravel 13 · PostgreSQL 16 · Redis 7 · Nginx 1.27 · Docker Compose.
**Auth:** Laravel Sanctum (Bearer tokens) — `App\Models\User` має трейт `HasApiTokens`.

Quickstart і опис env — у `README.md`. Тут — лише те, що Claude має знати для коректної роботи в репозиторії.

---

## CRITICAL: console commands run **only inside the container**

Хост не має PHP/composer і не повинен їх мати. Усі `artisan`/`composer`/`pint`/`tinker` виклики **виключно** через `docker compose exec`:

```bash
docker compose exec -T app php artisan <command>
docker compose exec -T app composer <command>
docker compose exec -T app vendor/bin/pint --dirty --format agent
```

- `-T` потрібен для non-interactive (CI/скрипти/Claude tool calls); прибирай його лише для tinker або інтерактивних команд.
- Якщо стек не піднятий: `docker compose up -d` (з кореня проекту). Не намагатись запустити `php artisan` через `php` на хості — це fail by design.
- НЕ пропонувати `composer install` / `npm` / `php -S` напряму — тільки через контейнер.
- Якщо потрібна shell у контейнері: `docker compose exec app sh`.

## Container layout

`docker-compose.yml` піднімає `nginx`, `app` (php-fpm), `db` (PostgreSQL 16), `redis` (7), `mailpit`. Фонові сервіси (queue worker і scheduler) **навмисно** не у compose — їх запускають вручну з консолі коли потрібно:

```bash
# окремі термінали:
docker compose exec app php artisan horizon          # фонова черга (Horizon, queues: checks, notifications, default)
docker compose exec app php artisan schedule:work    # cron-розклад (бере таски з routes/console.php)
```

Наслідки:
- Зміни PHP-коду одразу видно `app` (live volume). Якщо запущено `horizon`/`schedule:work` — їх треба перезапустити (Ctrl+C і знову `exec`), бо вони тримають клас-кеш у пам'яті.
- Зміни у `Dockerfile`/`docker/php/*.ini` — `docker compose build && docker compose up -d --force-recreate`.
- Логи Horizon і `schedule:work` ідуть у foreground відповідного терміналу. Horizon UI: `http://localhost:8080/horizon` (тільки при `APP_ENV=local`).

## Routing & bootstrap

- Routing/middleware/exceptions реєструються **у `bootstrap/app.php`** через `Application::configure()->withRouting()->withMiddleware()->withExceptions()`. `app/Http/Kernel.php` та `app/Console/Kernel.php` у Laravel 11+ не існують.
- Поточні routes-файли: `routes/api.php` (під `/api`, з `throttle:api` + `SubstituteBindings` за замовчанням), `routes/web.php` (зараз тільки `/swagger`), `routes/console.php`.
- Health check `/up` — реєструється у `withRouting(health: '/up')`. НЕ створювати окремий controller для нього.
- Console команди в `app/Console/Commands/` auto-discover, ручна реєстрація не потрібна.

## Laravel 13 conventions

- Casts моделей — через метод `casts(): array`, не через `$casts`-property.
- Eager-load обмежується нативно: `$q->latest()->limit(10)`.
- Зміна стовпця у міграції — **повторно вказувати всі попередні атрибути** колонки, інакше вони дропаються.
- Для нових endpoint-ів — API versioning (`/api/v1/...`) + Eloquent API Resources як response layer.
- Лінки до інших pages/маршрутів — через `route('name', [...])` з named routes, не строкові URL.

## PHP style (8.4)

- Curly braces завжди, навіть для одно-line тіл if/for/foreach.
- Constructor property promotion: `public function __construct(public DomainChecker $checker) {}`. Не залишати порожніх `__construct()`.
- Explicit return types + type hints на всіх параметрах: `function probe(Domain $domain, ?int $timeoutMs = null): CheckResult`.
- PHPDoc замість inline-коментарів; inline — лише для нетривіальної логіки.
- Array shape definitions у PHPDoc для array-структур.
- TitleCase для Enum cases (`HttpGet`, `HttpHead`).
- Назви методів/змінних — описові: `isReachable`, не `check()`.

## Formatting (mandatory after PHP edits)

```bash
docker compose exec -T app vendor/bin/pint --dirty --format agent
```

Запускати тільки fix (без `--test`).

## Artisan & deps

- Створення файлів — через `php artisan make:*` з `--no-interaction`. Дивись опції: `php artisan <command> --help`.
- Не міняти composer-залежності без узгодження з користувачем.
- Конфіги читати з `config/` або `php artisan config:show <dot.path>`.
- Env-змінні — з `.env` напряму (читати, не пробувати `$_ENV`).

## Tools (Boost MCP)

`laravel/boost` встановлений як dev-залежність — коли доступні відповідні tools, **використовуй їх перед ручними альтернативами**:

- `search-docs` — version-specific Laravel/Sanctum/Pint документація. Викликати **перед** змінами коду по темі; кілька broad-queries (`['rate limiting', 'middleware rate limiting']`), без назв пакетів у query.
- `database-query` — read-only SQL замість `tinker --execute 'DB::...'`.
- `database-schema` — структура таблиць перед написанням міграцій/моделей.
- `tinker` (через MCP) або CLI: `docker compose exec app php artisan tinker --execute 'User::count();'` (одинарні лапки!).
- `browser-logs` — для frontend-помилок (тут не релевантно, фронт окремо).

## OpenAPI

- Канонічний spec — `openapi.yaml` у корені проекту.
- UI: `GET /swagger` (рендерить `resources/views/swagger.blade.php` через Swagger UI з unpkg CDN) — доступне лише при `config('app.swagger.enable') === true`.
- При додаванні/зміні endpoint-у — **обов'язково оновити `openapi.yaml`**. Автогенерації з анотацій немає.

## Що **не** інсталювати / не створювати

Цей API-backend свідомо мінімалістичний. Раніше було видалено як зайве — не повертати без обговорення:

- Тестова інфраструктура (PHPUnit/Pest, `tests/`, `phpunit.xml`). Якщо потрібні тести — узгодити з юзером, потім `composer require --dev phpunit/phpunit` + `php artisan make:test`.
- `fakerphp/faker`, `database/factories/`, `database/seeders/`.
- `laravel/sail` (є власний docker-compose), `laravel/pail` (логи через `docker compose logs`).
- Frontend tooling (npm, vite, `resources/css`, `resources/js`, `@vite(...)`) — фронт у окремому репо.
- `welcome.blade.php` / `GET /` — health-чек грає `/up`.
- `public/info.php` чи будь-який `phpinfo()` у public — security risk.
