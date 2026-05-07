# Recipe Manager

Веб-приложение для управления кулинарными рецептами — лабораторная работа по дисциплине «Базы данных».

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php)
![MySQL](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql)
![Redis](https://img.shields.io/badge/Redis-7-DC382D?logo=redis)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?logo=bootstrap)

---

## Содержание

- [Описание лабораторной работы](#описание-лабораторной-работы)
- [Инструкции по запуску](#инструкции-по-запуску)
- [Функциональные возможности](#функциональные-возможности)
- [Сценарии взаимодействия пользователей](#сценарии-взаимодействия-пользователей)
- [Структура базы данных](#структура-базы-данных)
- [Примеры использования](#примеры-использования)
- [Ответы на контрольные вопросы](#ответы-на-контрольные-вопросы)
- [Список использованных источников](#список-использованных-источников)

---

## Описание лабораторной работы

**Цель:** разработать многопользовательское веб-приложение с реляционной базой данных, реализовав полный CRUD, аутентификацию пользователей, разграничение прав доступа и защиту от типичных веб-уязвимостей.

**Проект:** Recipe Manager — сервис хранения, поиска и управления кулинарными рецептами.

**Технологический стек:**

| Технология | Назначение |
|---|---|
| PHP 8.x | Серверная логика, маршрутизация, шаблоны |
| MySQL 8 | Реляционное хранилище: пользователи, рецепты, категории, теги |
| Redis 7 | Сессии, счётчики просмотров, избранное, CSRF-токены, rate limiting |
| PDO | Безопасное подключение к MySQL через prepared statements |
| Predis 2.x | PHP-клиент для Redis |
| vlucas/phpdotenv | Загрузка конфигурации из `.env` |
| Bootstrap 5.3 | Адаптивный интерфейс, валидация форм |
| Composer | Автозагрузка классов (PSR-4), управление зависимостями |

**Архитектура** — трёхслойная (Presentation / Application / Data) без фреймворка:

```
public/      ← точки входа (PHP-контроллеры)
src/         ← классы: Models, Services, Storage, Validators, Support
templates/   ← переиспользуемые HTML-шаблоны
migrations/  ← schema.sql, seed.sql, run.php
config/      ← config.php (читает .env)
```

---

## Инструкции по запуску

**Требования:** PHP 8.1+, MySQL 8, Redis 7, Composer 2.

### Вариант А — нативно (локально)

```bash
# 1. Клонировать репозиторий
git clone https://github.com/DanielRusnak2025/recipe-manager.git
cd recipe-manager

# 2. Установить PHP-зависимости
composer install

# 3. Создать файл окружения
cp .env.example .env
# Открыть .env и указать параметры MySQL и Redis

# 4. Применить миграции и seed-данные
php migrations/run.php

# 5. Запустить встроенный веб-сервер PHP
php -S localhost:8000 -t public
```

Открыть: **http://localhost:8000**

Учётные данные администратора (из seed.sql):
- Email: `admin@recipe.local`
- Пароль: `admin123`

### Вариант Б — через Docker

```bash
# 1. Запустить MySQL
docker run -d --name recipe-mysql \
  -e MYSQL_ROOT_PASSWORD=root \
  -e MYSQL_DATABASE=recipe_manager \
  -p 3307:3306 mysql:8

# 2. Запустить Redis
docker run -d --name recipe-redis \
  -p 6379:6379 redis:alpine

# 3. Установить зависимости и настроить .env
composer install
cp .env.example .env
# Установить MYSQL_PORT=3307 в .env

# 4. Применить миграции
php migrations/run.php

# 5. Запустить сервер
php -S localhost:8000 -t public
```

### Переменные окружения (`.env`)

```env
MYSQL_HOST=127.0.0.1
MYSQL_PORT=3306
MYSQL_DATABASE=recipe_manager
MYSQL_USER=root
MYSQL_PASSWORD=ваш_пароль

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

APP_ENV=dev
APP_URL=http://localhost:8000
COOKIE_SECURE=false
```

---

## Функциональные возможности

### Для обычных пользователей

- **Регистрация и вход** — форма регистрации с валидацией (логин, email, пароль ≥ 6 символов); вход по email и паролю; автоматический выход при блокировке аккаунта
- **Просмотр рецептов** — главная страница со списком всех рецептов, доступна без авторизации
- **Поиск и фильтрация** — полнотекстовый поиск по названию и ингредиентам; фильтр по категории
- **Создание рецепта** — форма с 8 полями: название, автор, категория, сложность, время, дата, ингредиенты, инструкции; поддержка тегов
- **Редактирование и удаление** — только своих рецептов; кнопки в карточке списка и на странице рецепта
- **Избранное** — добавление/удаление рецептов в личный список; хранится в Redis SET
- **Статистика просмотров** — счётчик просмотров каждого рецепта через Redis

### Для администраторов

- **Дашборд** — сводная статистика: пользователи, рецепты, категории, активные сессии Redis
- **Управление пользователями** — просмотр всех аккаунтов, изменение роли (user ↔ admin), блокировка/разблокировка, удаление, создание нового пользователя с выбором роли
- **Управление рецептами** — просмотр, редактирование и удаление любого рецепта независимо от автора
- **Категории CRUD** — создание, переименование, удаление (запрещено если есть рецепты)
- **Теги CRUD** — создание, переименование, удаление с каскадным удалением из `recipe_tags`
- **Системная статистика** — топ авторов по рецептам (MySQL), топ по просмотрам (Redis), список Redis-ключей
- **Chaos Engineering Panel** — симуляция отключения Redis и MySQL через feature flags; демонстрация graceful degradation

---

## Сценарии взаимодействия пользователей

### Сценарий 1: Регистрация и создание рецепта

1. Пользователь открывает `/register.php` и заполняет форму: логин (буквы/цифры/`_`, 3–30 символов), email, пароль ≥ 6 символов.
2. Браузер проверяет поля через HTML5-атрибуты (`required`, `pattern`, `minlength`) до отправки. При ошибке — подсвечивает поле красным с сообщением.
3. Сервер повторно валидирует данные через `UserValidator`. При дублирующемся email выводит ошибку «Этот email или логин уже занят».
4. После успешной регистрации пользователь перенаправляется на главную страницу уже авторизованным.
5. Нажимает «+ Создать рецепт», заполняет форму: выбирает категорию из списка, отмечает теги чекбоксами, вводит ингредиенты и пошаговые инструкции.
6. Сервер валидирует все поля через `RecipeValidator` и сохраняет рецепт в MySQL. Теги создаются автоматически если их не было.
7. Пользователь перенаправляется на страницу созданного рецепта.

### Сценарий 2: Поиск и просмотр рецепта

1. Гость открывает главную страницу `/index.php` — видит сетку карточек всех рецептов без авторизации.
2. Вводит в поле поиска «борщ» и нажимает «Найти» — страница обновляется, показывает только совпадения по названию и ингредиентам.
3. Дополнительно выбирает категорию «Обед» в выпадающем списке — фильтры объединяются через AND.
4. Кликает на карточку рецепта — открывается `/view.php?id=X` с полным описанием, ингредиентами, инструкциями, тегами и счётчиком просмотров.
5. Счётчик просмотров инкрементируется в Redis при каждом открытии страницы (`INCR recipe:{id}:views`).
6. Авторизованный пользователь нажимает «♡ В избранное» — ID рецепта добавляется в Redis SET `user:{id}:favorites`.

### Сценарий 3: Администратор управляет пользователями

1. Администратор входит через `/login.php` — в навигации появляется ссылка «⚙️ Админ-панель».
2. Открывает `/admin/users.php` — видит таблицу всех пользователей с колонками: ID, логин, email, роль, статус блокировки, количество рецептов.
3. Нажимает «Сделать admin» напротив нужного пользователя — роль меняется через POST-форму с CSRF-токеном.
4. Нажимает «Заблокировать» — пользователь немедленно теряет доступ: при следующем запросе `bootstrap.php` принудительно его разлогинивает.
5. Прокручивает страницу вниз, заполняет форму «Создать пользователя»: логин, email, пароль, роль admin — новый администратор появляется в таблице.
6. Переходит в `/admin/recipes.php`, находит чужой рецепт с ошибкой, нажимает «Редактировать» — администратор может редактировать любой рецепт в системе.

---

## Структура базы данных

### Таблица `users`

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT UNSIGNED PK AUTO_INCREMENT | Уникальный идентификатор |
| `username` | VARCHAR(50) UNIQUE NOT NULL | Логин пользователя |
| `email` | VARCHAR(255) UNIQUE NOT NULL | Электронная почта |
| `password` | VARCHAR(255) NOT NULL | Хэш пароля (bcrypt) |
| `role` | ENUM('user','admin') DEFAULT 'user' | Роль в системе |
| `is_blocked` | TINYINT(1) DEFAULT 0 | Флаг блокировки аккаунта |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | Дата регистрации |

### Таблица `categories`

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT UNSIGNED PK AUTO_INCREMENT | Уникальный идентификатор |
| `name` | VARCHAR(100) UNIQUE NOT NULL | Название категории |

### Таблица `tags`

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT UNSIGNED PK AUTO_INCREMENT | Уникальный идентификатор |
| `name` | VARCHAR(100) UNIQUE NOT NULL | Название тега |

### Таблица `recipes`

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT UNSIGNED PK AUTO_INCREMENT | Уникальный идентификатор |
| `user_id` | INT UNSIGNED FK→users NULL | Владелец рецепта |
| `title` | VARCHAR(255) NOT NULL | Название рецепта |
| `author` | VARCHAR(100) NOT NULL | Имя автора (текстовое поле) |
| `prep_time` | SMALLINT UNSIGNED NOT NULL | Время приготовления, минуты |
| `category_id` | INT UNSIGNED FK→categories NOT NULL | Категория (ON DELETE RESTRICT) |
| `difficulty` | ENUM('Легко','Средне','Сложно') | Уровень сложности |
| `ingredients` | TEXT NOT NULL | Список ингредиентов |
| `instructions` | TEXT NOT NULL | Пошаговые инструкции |
| `created_at` | DATE NOT NULL | Дата создания |
| `updated_at` | TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | Дата последнего изменения |

### Таблица `recipe_tags` (связующая)

| Поле | Тип | Описание |
|---|---|---|
| `recipe_id` | INT UNSIGNED FK→recipes | Составной PK; ON DELETE CASCADE |
| `tag_id` | INT UNSIGNED FK→tags | Составной PK; ON DELETE CASCADE |

### Схема связей

```
users ──────────────────────────────── recipes
(id)  1 ──────────────────────── N  (user_id)
                                         │
categories ──────────────────── recipes  │
(id)       1 ────────────── N (category_id)
                                         │
                                  recipe_tags
recipes ──────────── N (recipe_id)       │
(id)    1 ───────────────────────────────┘

tags ────────────────────────── recipe_tags
(id)  1 ──────────────────── N   (tag_id)
```

**Связи:**
- `users` **1:N** `recipes` — один пользователь может иметь много рецептов
- `categories` **1:N** `recipes` — одна категория объединяет много рецептов
- `recipes` **M:N** `tags` — через таблицу `recipe_tags` (каскадное удаление)

---

## Примеры использования

### Главная страница

![Главная страница](screenshots/index.png)

*Список рецептов с фильтрацией по категориям и полнотекстовым поиском*

### Панель администратора

![Панель администратора](screenshots/admin.png)

*Дашборд со статистикой: пользователи, рецепты, категории, активные Redis-сессии*

### Статистика просмотров

![Статистика](screenshots/stats.png)

*Топ-10 рецептов по просмотрам из Redis Sorted Set*

### Chaos Engineering Panel

![Chaos Panel](screenshots/chaos.png)

*Симуляция отказов Redis и MySQL — демонстрация graceful degradation*

---

### Пример 1 — Prepared statement (защита от SQL-инъекций)

Все запросы с пользовательским вводом используют `prepare()` + `execute()`. Значения передаются
отдельно от SQL-кода — инъекция невозможна на уровне протокола (`EMULATE_PREPARES = false`).

```php
// src/Storage/MySQLRecipeStorage.php — поиск рецепта по ID
public function getById(int $id): ?Recipe
{
    $stmt = $this->pdo->prepare(
        self::BASE_SELECT . ' WHERE r.id = ? GROUP BY r.id'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? Recipe::fromArray($this->mapRow($row)) : null;
}
```

### Пример 2 — Хеширование пароля

Пароль никогда не хранится в открытом виде. При регистрации применяется bcrypt,
при входе — `password_verify()` сравнивает введённый пароль с хешем из БД.

```php
// src/Services/AuthService.php — регистрация
public function register(string $username, string $email, string $password): User
{
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $this->pdo->prepare(
        'INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$username, $email, $hash, 'user']);
    // ...
}

// Вход — проверка пароля
if (!password_verify($password, $row['password'])) {
    return null; // неверный пароль
}
```

### Пример 3 — CSRF-защита

Каждая POST-форма содержит скрытый токен. Сервер проверяет его через `hash_equals()`
(защита от timing-атак). Токен хранится в Redis с TTL 1 час, при недоступном Redis — в `$_SESSION`.

```php
// src/Support/Csrf.php — генерация поля формы
public static function field(): string
{
    return '<input type="hidden" name="_token" value="'
        . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
}

// Проверка при обработке POST-запроса
public static function verify(string $token): bool
{
    $expected = self::token();
    return $expected !== '' && hash_equals($expected, $token);
}
```

---

## Ответы на контрольные вопросы

### Q1: Чем PDO отличается от mysqli?

**PDO (PHP Data Objects)** — абстрактный слой доступа к базам данных с единым интерфейсом
для 12+ СУБД. **mysqli** работает только с MySQL и не позволяет сменить СУБД без переписывания
кода.

Ключевые отличия:

| Критерий | PDO | mysqli |
|---|---|---|
| Поддержка СУБД | 12+ (MySQL, PostgreSQL, SQLite…) | Только MySQL |
| Именованные параметры | `:name` и `?` | Только `?` |
| Режим ошибок | `ERRMODE_EXCEPTION` | Ручная проверка `$result` |
| ООП-интерфейс | Полный | Частичный |

В проекте PDO инициализируется в `MySQLConnection::getInstance()` с отключённой эмуляцией
подготовки — запросы готовятся на стороне MySQL, что исключает SQL-инъекции на уровне протокола:

```php
self::$instance = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);
```

---

### Q2: Что такое prepared statements и зачем они нужны?

**Prepared statement** — запрос с параметрами-плейсхолдерами (`?`), который компилируется СУБД
один раз, а затем выполняется с разными значениями. Параметры передаются отдельно от SQL-кода
через бинарный протокол — пользовательский ввод никогда не интерпретируется как часть запроса.

**Зачем нужны:** предотвращают SQL-инъекции. Без prepared statements строка вида
`'; DROP TABLE users; --` в поле поиска могла бы выполниться как SQL-команда.

Пример из проекта — фильтрация рецептов с динамическими условиями:

```php
// src/Storage/MySQLRecipeStorage.php
public function getFiltered(int $categoryId = 0, string $search = ''): array
{
    $where  = [];
    $params = [];

    if ($categoryId > 0) {
        $where[]  = 'r.category_id = ?';
        $params[] = $categoryId;
    }
    if ($search !== '') {
        $like     = '%' . $search . '%';
        $where[]  = '(r.title LIKE ? OR r.ingredients LIKE ?)';
        $params[] = $like;
        $params[] = $like;
    }

    $sql  = self::BASE_SELECT;
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' GROUP BY r.id ORDER BY r.updated_at DESC';

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return array_map(fn($row) => Recipe::fromArray($this->mapRow($row)), $stmt->fetchAll());
}
```

---

### Q3: Как работает хеширование паролей в PHP?

**`password_hash()`** применяет алгоритм bcrypt с автоматически генерируемой солью и
настраиваемым cost-фактором (по умолчанию 10–12 итераций). Результат — строка вида
`$2y$12$...`, содержащая алгоритм, cost и соль — всё необходимое для последующей проверки.

**Почему bcrypt безопасен:**
- Соль уникальна для каждого пользователя — одинаковые пароли дают разные хеши
- Cost-фактор увеличивает время вычисления — замедляет перебор
- Хеш необратим — из него нельзя восстановить пароль

**`password_verify()`** извлекает алгоритм и соль из хеша, применяет их к введённому паролю
и сравнивает результат — без лишних данных в БД.

```php
// Регистрация — src/Services/AuthService.php:46
$hash = password_hash($password, PASSWORD_BCRYPT);
// Сохраняется в users.password: "$2y$10$randomSalt...hashedValue"

// Вход — src/Services/AuthService.php:86
if (!password_verify($password, $row['password'])) {
    return null; // пароль не совпал
}
```

---

### Q4: Что такое CSRF и как защита реализована в проекте?

**CSRF (Cross-Site Request Forgery)** — атака, при которой вредоносный сайт заставляет браузер
жертвы отправить запрос от её имени на другой сайт, где она авторизована. Например, скрытая
форма на стороннем сайте может удалить рецепт пользователя.

**Защита в проекте** (`src/Support/Csrf.php`):

1. При загрузке страницы генерируется случайный токен `bin2hex(random_bytes(32))` и сохраняется
   в Redis (ключ `csrf:{session_id}`, TTL 3600 с). При недоступном Redis — в `$_SESSION`.
2. Токен вставляется скрытым полем во все POST-формы через `Csrf::field()`.
3. При обработке POST-запроса сервер проверяет токен через `hash_equals()` — сравнение за
   фиксированное время, защита от timing-атак.
4. Сторонний сайт не может прочитать токен из DOM жертвы (Same-Origin Policy), поэтому
   подделать запрос с верным токеном невозможно.

```php
// Вставка в форму (templates/recipe_form.php)
<form method="POST" action="/update.php">
    <?= Csrf::field() ?>
    <!-- поля формы -->
</form>

// Проверка при обработке (public/update.php:21)
if (!Csrf::verify($_POST['_token'] ?? '')) {
    Flash::error('Неверный CSRF-токен.');
    header('Location: /index.php');
    exit;
}
```

---

## Список использованных источников

1. PHP Documentation — Официальная документация PHP: https://www.php.net/docs.php
2. PHP PDO Manual — Работа с базами данных через PDO: https://www.php.net/manual/en/book.pdo.php
3. PHP password_hash — Документация функции хеширования: https://www.php.net/manual/en/function.password-hash.php
4. Redis Documentation — Официальная документация Redis: https://redis.io/docs
5. Predis — PHP-клиент для Redis: https://github.com/predis/predis
6. Bootstrap 5.3 Documentation — UI-фреймворк: https://getbootstrap.com/docs/5.3
7. OWASP SQL Injection Prevention — Защита от SQL-инъекций: https://owasp.org/www-community/attacks/SQL_Injection
8. OWASP CSRF Prevention Cheat Sheet — Защита от CSRF: https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html
