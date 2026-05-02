# Recipe Manager — Lab 6

Веб-приложение для управления рецептами на PHP с MySQL и Redis.

## Стек

- PHP 8.x (встроенный сервер)
- MySQL 8 (Docker, порт 3307)
- Redis 7 (Docker, порт 6379, клиент: Predis)
- Composer, vlucas/phpdotenv, Bootstrap 5

## Функциональность

1. **Авторизация** — регистрация, логин, логаут (POST + CSRF)
2. **CRUD рецептов** — создание, просмотр, редактирование, удаление (только автор)
3. **Категории и теги** — связанные сущности many-to-many
4. **Поиск и фильтрация** — по названию/ингредиентам и категории (AND)
5. **Избранное** — добавление/удаление через Redis SET
6. **Статистика просмотров** — Redis INCR + сортировка для топа
7. **Rate limiting** — защита от спама через Redis INCR + TTL

## Архитектура

- **MySQL** — источник правды: пользователи, рецепты, категории, теги
- **Redis** — эфемерные данные: сессии, CSRF-токены, счётчики, избранное, rate limits
- **Graceful degradation** — при падении Redis приложение работает в режиме чтения из MySQL; сессии, избранное и статистика недоступны, ни одна страница не крашится

## Запуск

### 1. Поднять БД

```bash
docker run -d --name recipe-mysql \
  -e MYSQL_ROOT_PASSWORD=root \
  -e MYSQL_DATABASE=recipe_manager \
  -p 3307:3306 mysql:8

docker run -d --name recipe-redis \
  -p 6379:6379 redis:alpine
```

### 2. Создать `.env`

```dotenv
MYSQL_HOST=127.0.0.1
MYSQL_PORT=3307
MYSQL_DATABASE=recipe_manager
MYSQL_USER=root
MYSQL_PASSWORD=root
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
APP_ENV=dev
```

### 3. Установить зависимости

```bash
composer install
```

### 4. Применить миграции

```bash
php migrations/run.php
php migrations/migrate_from_json.php   # опционально — импорт legacy-рецептов из data.json
```

### 5. Запустить сервер

```bash
php -S localhost:8000 -t public
```

Открыть: http://localhost:8000

---

## Структура проекта

```
lab6/
├── config/
│   └── config.php              # чтение .env → массив конфига
├── migrations/
│   ├── schema.sql              # DDL: все таблицы
│   ├── seed.sql                # Начальные данные (категории, теги, рецепты)
│   ├── run.php                 # Запускает schema.sql + seed.sql
│   └── migrate_from_json.php   # Импорт из data.json → MySQL
├── public/                     # Document root (php -S ... -t public)
│   ├── index.php               # Главная (список + фильтрация)
│   ├── view.php                # Просмотр рецепта + счётчик просмотров
│   ├── create.php              # Форма нового рецепта
│   ├── save.php                # Обработчик создания
│   ├── edit.php                # Форма редактирования
│   ├── update.php              # Обработчик обновления
│   ├── delete.php              # Подтверждение + обработчик удаления
│   ├── stats.php               # Топ-10 по просмотрам
│   ├── favorites.php           # Список избранного
│   ├── favorite_toggle.php     # Добавить/убрать из избранного
│   ├── register.php            # Регистрация
│   ├── login.php               # Вход
│   └── logout.php              # Выход (POST + CSRF)
├── src/
│   ├── Database/
│   │   ├── MySQLConnection.php     # PDO singleton
│   │   ├── SafeRedis.php           # Обёртка Predis с перехватом runtime-ошибок
│   │   └── NullRedisClient.php     # Заглушка (fail-open) когда Redis недоступен
│   ├── Models/
│   │   ├── User.php
│   │   └── Recipe.php
│   ├── Services/
│   │   ├── AuthService.php         # Регистрация, логин, сессия
│   │   ├── SessionStore.php        # Redis-сессии с TTL 24ч
│   │   ├── FavoritesService.php    # Redis SET для избранного
│   │   ├── StatsService.php        # Redis INCR + KEYS для статистики
│   │   └── RateLimiter.php         # INCR + EXPIRE для rate limit
│   ├── Storage/
│   │   ├── StorageInterface.php
│   │   └── MySQLRecipeStorage.php
│   ├── Support/
│   │   ├── Csrf.php                # CSRF-токены (Redis или SESSION)
│   │   ├── Flash.php               # Flash-сообщения через $_SESSION
│   │   └── View.php
│   └── Validators/
│       ├── RecipeValidator.php
│       └── UserValidator.php
├── templates/
│   ├── header.php              # Навигация + динамический Redis-статус
│   ├── footer.php              # Закрытие body/html + Bootstrap JS
│   └── recipe_form.php         # Форма рецепта (create/edit)
├── bootstrap.php               # Инициализация всех сервисов
├── composer.json
└── data.json                   # Legacy-данные (12 рецептов для импорта)
```

---

## Схема MySQL (5 таблиц)

| Таблица       | Назначение                                     | Ключевые связи          |
|---------------|------------------------------------------------|-------------------------|
| `users`       | Пользователи (bcrypt-пароль)                   | —                       |
| `categories`  | Справочник категорий                           | —                       |
| `tags`        | Справочник тегов                               | —                       |
| `recipes`     | Рецепты (FK → categories, опционально → users) | category_id, user_id    |
| `recipe_tags` | Many-to-many: рецепты ↔ теги                  | recipe_id FK, tag_id FK |

---

## Chaos Engineering Mode (для защиты лабораторной)

### Что это

Встроенная система симуляции сбоев для демонстрации graceful degradation вживую. Преподаватель открывает панель, нажимает одну кнопку — сайт переходит в degraded-режим; ещё раз — всё восстанавливается.

**Отключение действует до явного включения.** На каждой странице сайта висит красный sticky-баннер с кнопкой «Включить обратно». Не забывай включить обратно после демонстрации, иначе сайт останется в degraded-режиме.

### Как включить

1. Установить в `.env`:
   ```dotenv
   APP_ENV=demo
   CHAOS_ADMIN_PASSWORD=your_strong_password_here   # мин. 12 символов
   ```
2. Запустить сервер как обычно: `php -S localhost:8000 -t public`

### URL панели

```
http://localhost:8000/admin/login.php   ← вход
http://localhost:8000/admin/chaos.php   ← панель управления
```

### Что симулирует, а что нет

| Действие | Эффект |
|---|---|
| «Отключить Redis» | SafeRedis начинает возвращать NullRedisClient-ответы; сессии, CSRF, избранное, счётчики, rate limit деградируют ровно как при реальном падении |
| «Отключить MySQL» | Каждый запрос к сайту получает HTTP 503 + страницу maintenance.php |
| Реальный Docker-stop | Не происходит — сервисы в контейнерах продолжают работать |

**Почему симуляция, а не настоящий `docker stop`?**  
Реальный stop требует Docker-сокета и прав `sudo` — опасно на shared VPS. Симуляция через feature flag безопасна, эффект для зрителя идентичный.

**Запасной вариант (100% настоящее падение Redis):**
```bash
docker stop recipe-redis   # упадёт SafeRedis → NullRedisClient
docker start recipe-redis  # восстановление
```

### Флаги хранятся в `storage/chaos.json`

Файл выбран намеренно: флаг «Redis отключён» должен переживать отключение Redis.

---

## Схема Redis (ключи)

| Ключ                    | Тип    | TTL | Назначение                      |
|-------------------------|--------|-----|---------------------------------|
| `session:{token}`       | String | 24ч | user_id → авторизованная сессия |
| `csrf:{session_id}`     | String | 1ч  | CSRF-токен формы                |
| `user:{id}:favorites`   | SET    | нет | id рецептов в избранном         |
| `recipe:{id}:views`     | String | нет | счётчик просмотров (INCR)       |
| `rate:create:{user_id}` | String | 60с | rate limit создания рецептов    |
