## Global Architecture Vision

### 1) Мета продукту

Зробити веб-додаток з:

* публічною сторінкою, яка **візуалізує полицю з посудом**
* адмінкою, яка дозволяє **керувати полицями, посудом, розміщенням** і **стопками тарілок**
* позиції предметів **вільні (не клітинки)**, **без ротації**
* підтримка переміщення предметів **клавіатурою (як у грі)**
* колізії: предмети **не можуть перекриватися** в межах однієї полиці та **виходити за межі полиці**

### 2) Основні принципи

* **Local-first UI:** фронтенд завжди реагує миттєво, без очікування сервера.
* **Backend as authority:** бекенд гарантує, що колізії не потраплять у БД.
* **Normalized coordinates:** координати та розміри в **0..1** відносно розміру полиці.
* **Minimal network chatter:** запити на бекенд тільки на завершення дії (keyup / debounce), не на кожен “крок”.
* **Stacks are groups:** стопка = група елементів з одним “якорем” (shared x/y/shelf_id), порядок визначає stack_index.

### 3) Технологічні рамки (для реалізації)

* Frontend: **Twig + vanilla JS** (без React/Vue).
* Backend: **Symfony + Doctrine** (або сумісна MVC-архітектура).
* DB: бажано **PostgreSQL** для GiST/EXCLUDE (але архітектуру зробити так, щоб можна було замінити на MySQL з ручною перевіркою).
* Візуалізація: HTML/CSS absolute positioning; для плавності під час руху застосовувати `transform` + `requestAnimationFrame`.

### 4) Доменна модель (логічно)

Сутності:

* **Shelf**: `{id, name, width, height}`
* **Dish**: `{id, name, type, image, width, height}`
* **Placement**: `{id, shelfId, dishId, x, y, width, height, stackId?, stackIndex?}`

Визначення:

* “Об’єкт колізії” = або **одиночний placement**, або **стопка як один footprint**.
* Стопка: всі placements з одним `stackId` мають однакові `shelfId/x/y` (якір), а `stackIndex` визначає порядок.

### 5) Рівні системи (layered architecture)

#### 5.1 Backend layers

* **Controller/API layer**

  * приймає/віддає JSON, валідує формат
  * не містить бізнес-логіки колізій/стопок
* **Application services**

  * `ShelfService`, `DishService`
  * `PlacementService` (move/resize)
  * `StackService` (merge/unstack/moveStack)
* **Domain services**

  * `CollisionPolicy` / `CollisionChecker` (абстракція)
  * `BoundsPolicy` (перевірка меж полиці)
* **Persistence**

  * репозиторії Doctrine/SQL
  * реалізація колізій:

    * Postgres: GiST + EXCLUDE constraint
    * fallback: SELECT collision + транзакція + lock shelf (FOR UPDATE)

#### 5.2 Frontend modules

* **State store (plain JS)**

  * `placementsById`, `stacksById` (derived), `selectedId`
  * `lastSaved` per item for rollback
* **Renderer**

  * перетворює 0..1 координати у `%` та застосовує стилі
  * під час активного руху використовує `transform` (не `left/top`) + rAF
* **Input / Interaction**

  * keyboard movement (WASD/Arrows, Shift speed)
  * selection (click)
  * stack actions (buttons / context)
* **Collision on frontend**

  * MVP: O(n) AABB
* **Sync**

  * debounce commit to backend
  * handle `409 Conflict` -> rollback + повідомлення

### 6) API контракти (високорівнево)

* `GET /api/shelves/{id}` → shelf + placements (+ dish preview data)
* `PATCH /api/placements/{id}` → `{x,y}` (і за потреби `shelfId`)
* `POST /api/stacks/merge` → `{sourcePlacementId, targetPlacementId, position}`
* `POST /api/stacks/unstack` → `{placementId}`
* `PATCH /api/stacks/{stackId}` → `{shelfId, x, y}`

Поведінка:

* при колізії: бекенд повертає **409 Conflict**
* при виході за межі: **422 Unprocessable Entity**

### 7) Колізії: стратегія “швидко + правильно”

* UI перевіряє колізію локально (миттєвий feedback).
* Бекенд **обов’язково** перевіряє перед збереженням.
* Postgres:
  * використовувати `rect box` (generated) + `GiST` + `EXCLUDE`
  * тоді collision-check = просто UPDATE, а БД відмовляє при конфлікті.
  
### 8) Стопки: стратегія MVP

* У `placements` додати `stackId` nullable та `stackIndex`.
* Merge:

  * якщо target не в стопці → створити `stackId` і додати target
  * перенести source в цей `stackId`, задати `stackIndex`
  * вирівняти `shelfId/x/y` по якорю
* Unstack:

  * прибрати `stackId` з елемента
  * перенумерувати `stackIndex`
  * якщо лишився 1 елемент у стопці → auto-dissolve (stackId = null)

### 9) Нефункціональні вимоги

* Миттєвий UX при русі клавіатурою (60fps target).
* Мінімізувати кількість запитів: commit по debounce/keyup.
* Логувати latency бекенда (особливо collision errors).
* Код повинен бути простим, MVP-орієнтованим, без зайвих залежностей.

### 10) Code style
* Для кожного створенного классу - `declare(strict_types=1);`.
