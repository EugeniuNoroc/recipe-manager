# Recipe Manager

Веб-приложение для управления рецептами на PHP с MySQL и Redis.  
Лабораторная работа №8 / Курсовой проект по PHP и веб-разработке.

**Демо:** http://178.104.227.19:8080 (если развёрнуто)  
**Стек:** PHP 8.x · MySQL 8 · Redis 7 · Bootstrap 5.3 · Composer

---

## Содержание

- [Описание](#описание)
- [Функциональность](#функциональность)
- [Роли пользователей](#роли-пользователей)
- [Сценарии взаимодействия](#сценарии-взаимодействия)
- [Структура БД](#структура-бд)
- [Запуск](#запуск)
- [Архитектура](#архитектура)
- [Безопасность](#безопасность)
- [PHPDoc](#phpdoc)
- [Контрольные вопросы](#контрольные-вопросы)
- [Источники](#источники)

---

## Описание

Recipe Manager — многоуровневое веб-приложение для хранения, поиска и управления кулинарными рецептами. Реализовано на чистом PHP 8 без фреймворков с применением принципов слоистой архитектуры и классических паттернов проектирования (Singleton, Proxy, Null Object, Repository, Guard, PRG).

Приложение демонстрирует:
- работу с **реляционной СУБД** (MySQL 8, PDO, транзакции, prepared statements, индексы);
- работу с **нереляционным хранилищем** (Redis 7: строки, множества, sorted sets, TTL);
- **отказоустойчивость** через Chaos Engineering Panel — симуляцию сбоев и graceful degradation;
- **безопасность** — bcrypt, CSRF, HttpOnly-cookie, rate limiting, XSS-защита;
- **три роли пользователей** с разными уровнями доступа (гость, пользователь, администратор).

Первый зарегистрированный пользователь автоматически получает роль `admin`. Учётная запись по умолчанию после seed: `admin@recipe.local` / `admin123`.

---

## Функциональность

1. **Регистрация и аутентификация** — bcrypt-хэширование паролей (cost 12), Redis-сессии (токен в httpOnly cookie с TTL 24 ч), блокировка аккаунтов администратором. *(MySQL + Redis)*

2. **CRUD рецептов** — создание, просмотр, редактирование и удаление рецептов. Автор или администратор могут редактировать/удалять. Поля: название, ингредиенты, инструкции, время, сложность, категория, теги. *(MySQL)*

3. **Категории и теги** — каждый рецепт принадлежит одной категории и может иметь несколько тегов (many-to-many через `recipe_tags`). Теги создаются автоматически при сохранении рецепта. *(MySQL)*

4. **Избранное** — авторизованный пользователь добавляет/убирает рецепты в избранное; список хранится в Redis-SET `user:{id}:favorites`. Работает через POST + Redirect (PRG-паттерн). *(Redis)*

5. **Статистика просмотров** — счётчик `recipe:{id}:views` инкрементируется при каждом открытии рецепта. Топ-10 популярных строится через Redis ZSET (`popular:recipes`). *(Redis)*

6. **Rate Limiting** — ограничение частоты запросов через паттерн `INCR + EXPIRE` в Redis. Применяется к регистрации и авторизации. Fail-open при недоступном Redis. *(Redis)*

7. **Административная панель** — 7 страниц: дашборд со сводной статистикой, управление пользователями (роли, блокировка, удаление), рецептами, категориями, тегами, системной статистикой. Защищена `AdminGuard::check()`. *(MySQL + Redis)*

8. **Chaos Engineering Panel** — симуляция сбоев MySQL и Redis для демонстрации отказоустойчивости. Флаги хранятся в `storage/chaos.json` с file-lock. При отключённом Redis `SafeRedis` деградирует к `NullRedisClient`, при отключённом MySQL — выводится страница обслуживания с кодом 503. *(файловое хранилище)*

---

## Роли пользователей

| Действие | Гость | Пользователь | Администратор |
|---|:---:|:---:|:---:|
| Просмотр списка рецептов | ✅ | ✅ | ✅ |
| Просмотр страницы рецепта | ✅ | ✅ | ✅ |
| Фильтрация по категории / поиск | ✅ | ✅ | ✅ |
| Регистрация / вход | ✅ | — | — |
| Создание рецепта | ❌ | ✅ | ✅ |
| Редактирование своего рецепта | ❌ | ✅ | ✅ |
| Редактирование чужого рецепта | ❌ | ❌ | ✅ |
| Удаление своего рецепта | ❌ | ✅ | ✅ |
| Удаление чужого рецепта | ❌ | ❌ | ✅ |
| Добавление в избранное | ❌ | ✅ | ✅ |
| Просмотр своего избранного | ❌ | ✅ | ✅ |
| Доступ к административной панели | ❌ | ❌ | ✅ |
| Управление пользователями (роли, блокировка) | ❌ | ❌ | ✅ |
| Управление категориями и тегами | ❌ | ❌ | ✅ |
| Просмотр системной статистики (Redis-ключи, топы) | ❌ | ❌ | ✅ |
| Включение / выключение Chaos-режима | ❌ | ❌ | ✅ |

---

## Сценарии взаимодействия

### Сценарий 1: Обычный пользователь

1. Открывает `/register.php`, заполняет форму (логин, email, пароль ≥ 6 символов). Браузер валидирует поля HTML5 до отправки.
2. После регистрации перенаправляется на главную `/index.php` — видит список рецептов с фильтрацией по категории и строкой поиска.
3. Нажимает «Создать рецепт», заполняет все обязательные поля, выбирает категорию и теги, нажимает «Сохранить».
4. Открывает свой рецепт, нажимает «Редактировать», исправляет ингредиенты, сохраняет.
5. На странице любого рецепта нажимает «⭐ В избранное» — ID рецепта добавляется в Redis-SET `user:{id}:favorites`.
6. Переходит в «Избранное» — видит только свои рецепты из Redis-SET.
7. Открывает «Топ рецептов» (`/stats.php`) — видит топ-10 по просмотрам из Redis ZSET.

### Сценарий 2: Администратор

1. Входит через `/login.php` под учётной записью `admin@recipe.local` / `admin123`.
2. В навигационной панели появляется ссылка «⚙️ Админ-панель», переходит на `/admin/index.php` — видит 4 карточки со сводной статистикой.
3. Переходит в «Пользователи» — видит таблицу всех пользователей с кнопками «Роль», «Блок», «Удалить». Блокирует пользователя-нарушителя — тот при следующем запросе будет автоматически разлогинен.
4. Переходит в «Категории», создаёт новую категорию «Веганское» через форму.
5. Переходит в «Рецепты», находит чужой рецепт без категории, нажимает «Редактировать», исправляет.
6. Переходит в «Статистика» — видит топ-10 авторов по рецептам (MySQL), топ просмотров (Redis), распределение по категориям с прогресс-барами, все Redis-ключи с типами.

### Сценарий 3: Демонстрация отказоустойчивости (Chaos Engineering)

1. Администратор входит в систему, переходит в `/admin/chaos.php`.
2. Включает «🔴 Отключить Redis» — флаг записывается в `storage/chaos.json` с file-lock.
3. Переходит на главную: избранное и счётчики просмотров не работают, но страница загружается — `SafeRedis` деградирует до `NullRedisClient` (graceful degradation).
4. Включает «🔴 Отключить MySQL» — при следующем запросе отображается страница обслуживания `templates/maintenance.php` с кодом 503.
5. Выключает оба флага через Chaos Panel — приложение полностью восстанавливается без перезапуска.
6. Запускает `php migrations/rebuild_popular_zset.php` для восстановления ZSET популярных рецептов из счётчиков просмотров.

---

## Структура БД

### Таблицы MySQL

#### `users`

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT UNSIGNED PK AUTO_INCREMENT | Первичный ключ |
| `username` | VARCHAR(50) UNIQUE NOT NULL | Уникальный логин |
| `email` | VARCHAR(255) UNIQUE NOT NULL | Email-адрес |
| `password` | VARCHAR(255) NOT NULL | Bcrypt-хэш пароля |
| `role` | ENUM('user','admin') DEFAULT 'user' | Роль пользователя |
| `is_blocked` | TINYINT(1) DEFAULT 0 | Флаг блокировки |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | Дата регистрации |

#### `categories`

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT UNSIGNED PK AUTO_INCREMENT | Первичный ключ |
| `name` | VARCHAR(100) UNIQUE NOT NULL | Название категории |

#### `tags`

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT UNSIGNED PK AUTO_INCREMENT | Первичный ключ |
| `name` | VARCHAR(100) UNIQUE NOT NULL | Название тега |

#### `recipes`

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT UNSIGNED PK AUTO_INCREMENT | Первичный ключ |
| `user_id` | INT UNSIGNED FK→users DEFAULT NULL | Владелец (NULL — без аккаунта) |
| `title` | VARCHAR(255) NOT NULL | Название рецепта |
| `author` | VARCHAR(100) NOT NULL | Текстовое имя автора |
| `prep_time` | SMALLINT UNSIGNED NOT NULL | Время приготовления (мин) |
| `category_id` | INT UNSIGNED FK→categories NOT NULL | Категория (ON DELETE RESTRICT) |
| `difficulty` | ENUM('Легко','Средне','Сложно') | Уровень сложности |
| `ingredients` | TEXT NOT NULL | Список ингредиентов |
| `instructions` | TEXT NOT NULL | Пошаговые инструкции |
| `created_at` | DATE NOT NULL | Дата создания |
| `updated_at` | TIMESTAMP AUTO ON UPDATE | Дата последнего обновления |

**Индексы:** `idx_category (category_id)`, `idx_difficulty (difficulty)`, `idx_created (created_at)`.

#### `recipe_tags` (many-to-many)

| Поле | Тип | Описание |
|---|---|---|
| `recipe_id` | INT UNSIGNED FK→recipes | PK-компонент, ON DELETE CASCADE |
| `tag_id` | INT UNSIGNED FK→tags | PK-компонент, ON DELETE CASCADE |

Составной первичный ключ `(recipe_id, tag_id)` исключает дублирование связей.

### Ключи Redis

| Паттерн ключа | Тип | TTL | Назначение |
|---|---|---|---|
| `session:{token}` | STRING | 86 400 с (24 ч) | User ID для аутентификации по токену |
| `csrf:{session_id}` | STRING | 3 600 с (1 ч) | CSRF-токен для защиты форм |
| `user:{id}:favorites` | SET | без TTL | Множество ID избранных рецептов |
| `recipe:{id}:views` | STRING | без TTL | Счётчик просмотров рецепта |
| `popular:recipes` | ZSET | без TTL | Sorted set: рецепты → количество просмотров |
| `ratelimit:{action}:{ip}` | STRING | окно (сек.) | Счётчик rate limiting (INCR + EXPIRE) |

---

## Запуск

### Вариант 1: Локально с Docker

```bash
# 1. Клонировать репозиторий
git clone <repo-url> && cd lab6

# 2. Создать .env из примера
cp .env.example .env
# Отредактировать .env: задать пароли MySQL

# 3. Запустить MySQL
docker run -d --name recipe-mysql \
  -e MYSQL_ROOT_PASSWORD=secret \
  -e MYSQL_DATABASE=recipe_manager \
  -e MYSQL_USER=recipe_user \
  -e MYSQL_PASSWORD=secret \
  -p 3307:3306 \
  mysql:8

# 4. Запустить Redis
docker run -d --name recipe-redis \
  -p 6379:6379 \
  redis:7-alpine

# 5. Установить зависимости PHP
composer install

# 6. Применить схему и seed-данные
php migrations/run.php

# 7. Запустить встроенный сервер PHP
php -S localhost:8000 -t public

# Приложение доступно по адресу: http://localhost:8000
# Учётная запись admin: admin@recipe.local / admin123
```

**Требования:** PHP 8.1+, Composer, Docker.

### Вариант 2: На Ubuntu VPS (нативно)

```bash
# Установить зависимости ОС
sudo apt update && sudo apt install -y \
  php8.2 php8.2-cli php8.2-mysql php8.2-redis \
  mysql-server redis-server composer nginx

# Настроить MySQL
sudo mysql -e "CREATE DATABASE recipe_manager CHARACTER SET utf8mb4;"
sudo mysql -e "CREATE USER 'recipe'@'localhost' IDENTIFIED BY 'ваш_пароль';"
sudo mysql -e "GRANT ALL ON recipe_manager.* TO 'recipe'@'localhost';"

# Настроить проект
git clone <repo-url> /var/www/recipe && cd /var/www/recipe
cp .env.example .env && nano .env   # задать DB credentials
composer install --no-dev --optimize-autoloader
php migrations/run.php

# Права на storage/
chmod 775 storage/ && chown www-data:www-data storage/

# Настроить Nginx (document root → /var/www/recipe/public)
# Запустить php-fpm и nginx
sudo systemctl restart php8.2-fpm nginx
```

### Переменные окружения (`.env`)

```env
MYSQL_HOST=127.0.0.1
MYSQL_PORT=3307
MYSQL_DATABASE=recipe_manager
MYSQL_USER=recipe_user
MYSQL_PASSWORD=secret

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

APP_ENV=dev
APP_URL=http://localhost:8000
COOKIE_SECURE=false
```

---

## Архитектура

```
public/          ← точки входа (PHP-скрипты, не классы)
├── index.php, create.php, view.php, edit.php, delete.php, ...
└── admin/       ← административный раздел (7 страниц)

src/
├── Database/    ← подключения к БД
│   ├── MySQLConnection.php   (паттерн Singleton)
│   ├── RedisConnection.php   (паттерн Singleton)
│   ├── SafeRedis.php         (паттерн Proxy + graceful degradation)
│   └── NullRedisClient.php   (паттерн Null Object)
├── Models/      ← доменные объекты
│   ├── Recipe.php
│   └── User.php
├── Storage/     ← слой доступа к данным
│   ├── StorageInterface.php
│   └── MySQLRecipeStorage.php  (паттерн Repository)
├── Services/    ← бизнес-логика
│   ├── AuthService.php
│   ├── AdminService.php
│   ├── FavoritesService.php
│   ├── StatsService.php
│   ├── RateLimiter.php
│   ├── SessionStore.php
│   └── ChaosFlags.php
├── Validators/
│   ├── RecipeValidator.php
│   └── UserValidator.php
└── Support/
    ├── Csrf.php
    ├── Flash.php
    ├── View.php
    └── AdminGuard.php  (паттерн Guard)

templates/       ← переиспользуемые шаблоны (header, footer, layout)
migrations/      ← schema.sql, seed.sql, run.php, служебные скрипты
config/          ← config.php (читает переменные из .env)
storage/         ← chaos.json (файловые флаги, file-lock)
```

**Слои:** Presentation (`public/`) → Application (`src/Services`, `src/Storage`) → Data (`MySQL` + `Redis`).

**Паттерны проектирования:**

| Паттерн | Класс | Назначение |
|---|---|---|
| Singleton | `MySQLConnection`, `RedisConnection` | Одно соединение на жизненный цикл запроса |
| Proxy | `SafeRedis` | Перехват исключений Redis, деградация к Null Object |
| Null Object | `NullRedisClient` | Безопасные no-op команды при недоступном Redis |
| Repository | `MySQLRecipeStorage` | Изолирует SQL-запросы от бизнес-логики |
| Guard | `AdminGuard` | Первая строка каждой admin-страницы — проверка роли |
| PRG | все POST-формы | Post-Redirect-Get предотвращает двойной сабмит |

---

## Безопасность

| Мера | Реализация |
|---|---|
| Хэширование паролей | `password_hash($pwd, PASSWORD_BCRYPT)` — bcrypt, cost 12 |
| Защита от SQL-инъекций | PDO prepared statements с `?`-плейсхолдерами во всех запросах |
| CSRF-защита | Скрытое поле `_token`, проверка `Csrf::verify()` на каждый POST |
| Защита сессий | httpOnly + SameSite=Lax cookie, токен в Redis с TTL 24 ч |
| Rate Limiting | `RateLimiter::check()` через Redis INCR + EXPIRE |
| XSS-защита | `htmlspecialchars()` во всех шаблонах при выводе пользовательских данных |
| Проверка прав | Только автор или admin могут редактировать/удалять рецепт |
| Блокировка пользователей | `is_blocked = 1` → принудительный logout при каждом запросе |
| Клиентская валидация | HTML5-атрибуты (`required`, `minlength`, `pattern`) + Bootstrap |
| Скрытие чувствительных данных | Поле `password` не возвращается в `User::toArray()` |

---

## PHPDoc

Все классы в `src/` документированы по стандарту PHPDoc:

- **Классы:** `@package`, описание паттерна и назначения
- **Методы:** `@param`, `@return`, `@throws` где применимо
- **Свойства:** `@var` с типом и описанием

Проверка количества аннотаций:

```bash
grep -r "@param\|@return\|@throws" src/ | wc -l
# Результат: 191  (требование: > 100 ✅)
```

---

## Контрольные вопросы

### 1. Что такое PDO и чем отличается от `mysqli_*`?

**PDO (PHP Data Objects)** — абстрактный слой доступа к базам данных, предоставляющий единый интерфейс для работы с разными СУБД (MySQL, PostgreSQL, SQLite и др.). В отличие от `mysqli_*`, который жёстко привязан только к MySQL, PDO позволяет сменить СУБД, изменив лишь строку DSN. PDO поддерживает именованные и позиционные плейсхолдеры, объектно-ориентированный интерфейс и гибкое управление режимами ошибок через `PDO::ATTR_ERRMODE`.

В проекте PDO инициализируется в `MySQLConnection::getInstance()`:

```php
$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);
```

Отключение `EMULATE_PREPARES` заставляет MySQL выполнять настоящую подготовку запросов на стороне сервера — это повышает безопасность и точность типов данных.

---

### 2. Что такое подготовленные выражения?

**Подготовленное выражение (prepared statement)** — запрос с параметрами-плейсхолдерами, который компилируется СУБД один раз, а затем выполняется многократно с разными значениями. Параметры передаются отдельно от SQL-кода, что исключает SQL-инъекции: пользовательский ввод никогда не интерпретируется как часть запроса.

В проекте все операции с данными пользователя используют prepared statements:

```php
// AuthService::login() — поиск пользователя по email
$stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$row = $stmt->fetch();
```

Значение `$email` передаётся как связанный параметр и никогда не конкатенируется в строку SQL, что гарантирует защиту от инъекций.

---

### 3. Что такое транзакция и когда её использовать?

**Транзакция** — атомарная последовательность операций с базой данных: либо все они выполняются успешно (COMMIT), либо все откатываются (ROLLBACK). Применяется при изменении нескольких связанных таблиц, когда частичное выполнение оставило бы данные в несогласованном состоянии (ACID-гарантии).

Классический кейс в проекте — сохранение рецепта в `MySQLRecipeStorage::save()`: сначала `INSERT` в `recipes`, затем `DELETE + INSERT` в `recipe_tags`. Если вставка тегов завершится ошибкой, рецепт без тегов останется в БД. Транзакция решает это:

```php
$this->pdo->beginTransaction();
try {
    $this->pdo->prepare('INSERT INTO recipes ...')->execute([...]);
    $recipeId = (int) $this->pdo->lastInsertId();
    $this->syncTags($recipeId, $recipe->tags);
    $this->pdo->commit();
} catch (\PDOException $e) {
    $this->pdo->rollBack();
    throw $e;
}
```

Если `syncTags()` бросает исключение — `rollBack()` откатывает и INSERT в `recipes`, сохраняя согласованность данных.

---

### 4. Чем отличается `fetch()` от `fetchAll()`?

**`fetch()`** читает одну строку результата и перемещает внутренний указатель. Используется меньше памяти: строки не загружаются в массив сразу, что важно при больших наборах данных. **`fetchAll()`** считывает все строки в массив PHP за один вызов — удобно для небольших выборок, но требует памяти пропорционально размеру результата.

В проекте `fetch()` применяется для поиска одной записи:

```php
// AuthService::login() — ожидаем одну строку или false
$stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$row = $stmt->fetch();
```

`fetchAll()` — для списков рецептов:

```php
// MySQLRecipeStorage::getAll() — весь список с JOIN-ами
$stmt = $this->pdo->query(self::BASE_SELECT . ' GROUP BY r.id ORDER BY r.updated_at DESC');
return array_map(fn($row) => Recipe::fromArray($this->mapRow($row)), $stmt->fetchAll());
```

---

## Источники

1. **PHP Documentation** — https://www.php.net/docs.php
2. **PDO Manual** — https://www.php.net/manual/en/book.pdo.php
3. **Redis Documentation** — https://redis.io/docs
4. **Predis — PHP Redis client** — https://github.com/predis/predis
5. **Bootstrap 5.3** — https://getbootstrap.com/docs/5.3
6. **OWASP SQL Injection** — https://owasp.org/www-community/attacks/SQL_Injection
7. **OWASP CSRF Prevention Cheat Sheet** — https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html
8. **PHP-FIG PSR-4 Autoloading Standard** — https://www.php-fig.org/psr/psr-4/
