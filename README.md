# Buffet

Вебзастосунок для керування та перегляду розкладки страв на полицях буфету.

## Стек

- Symfony (PHP)
- PostgreSQL
- Nginx + PHP-FPM (Docker)
- Xdebug

## Швидкий старт (Docker)

```bash
docker compose up -d
```

Після запуску:
- Публічна сторінка: http://localhost:8082/buffet
- Адмінка: http://localhost:8082/admin

## Ініціалізація

```bash
chmod +x init-symfony.sh
./init-symfony.sh
```

## База даних

- **Host:** `postgres` (з php-контейнера) або `127.0.0.1` (з хоста)
- **Port:** 5432
- **Database:** symfony
- **User:** symfony
- **Password:** symfony

`DATABASE_URL` задано в `docker-compose.yml` і може бути перевизначено у `.env`.

## API (стеки)

Базовий шлях: `/api/stacks`

- `POST /merge` — обʼєднати два стеки
  - payload: `{ "sourceStackId": 1, "targetStackId": 2 }`
- `POST /unstack` — зняти один елемент зі стеку
  - payload: `{ "stackId": 1 }`
- `POST /add` — додати страву до стеку
  - payload: `{ "dishId": 1, "targetStackId": 2 }`
- `PATCH /{stackId}` — перемістити стек
  - payload: `{ "shelfId": 1, "x": 10, "y": 20 }`
- `DELETE /{stackId}` — видалити стек

## Архітектура

- Контролери: `app/src/Controller` (адмінка, публічний перегляд, API).
- Сервіси: `app/src/Service` (бізнес-логіка стеків і побудова розкладки).
- Репозиторії: `app/src/Repository` (доступ до БД та перевірка колізій при збереженні).
- Шаблони: `app/templates` (адмін і публічний інтерфейс).
- Міграції: `app/migrations` (схема БД і обмеження на колізії).

## Колізії (проблематика і реалізація)

Проблема: під час розміщення або переміщення стеків на полицях елементи можуть накладатися один на одного. Це призводить до некоректної розкладки, коли дві позиції займають один і той самий простір.

Рішення реалізоване на рівні БД:
- У PostgreSQL використовується GiST exclusion constraint `stack_no_overlap`.
- Обмеження забороняє перетин прямокутників всередині однієї полиці.
- Прямокутник будується з координат `x`, `y`, `width`, `height`.

Технічні деталі:
- Міграція: `app/migrations/Version20260208173000.php` додає `btree_gist` і constraint `EXCLUDE USING gist (...)`.
- При збереженні стеку репозиторій ловить SQLSTATE `23P01` і кидає `CollisionException`.
- Контролери API повертають `409 Conflict` з повідомленням про колізію.

## Схема БД

### shelf
- `id` (PK)
- `name`
- `width`, `height`
- `x`, `y` (позиція полиці)

### dish
- `id` (PK)
- `name`
- `type`
- `image`
- `width`, `height`
- `stack_limit`

### stack
- `id` (PK)
- `shelf_id` (FK -> shelf.id, on delete cascade)
- `dish_id` (FK -> dish.id, on delete cascade)
- `x`, `y`
- `width`, `height`
- `count`
- Indexes: `idx_stack_shelf`, `idx_stack_dish`
- Constraint: `stack_no_overlap` (GiST exclusion, забороняє перетин в межах однієї полиці)
