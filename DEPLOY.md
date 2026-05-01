# Деплой Recipe Manager на Ubuntu 24.04 VPS

**Сервер:** `178.104.227.19:8080` · **Пользователь приложения:** `lab` · **Путь:** `/home/lab/app`

---

## Префлайт-чек на сервере (под root)

Выполнить перед деплоем. Всё должно быть зелёным.

```bash
# PHP 8.1+
php --version

# Composer
composer --version

# MySQL доступен и recipe_manager существует (или будет создан миграцией)
mysql -u root -e "SELECT VERSION();"

# Redis слушает на 127.0.0.1:6379
redis-cli -h 127.0.0.1 ping
# ожидаем: PONG

# Пользователь lab существует
id lab

# Домашняя папка доступна
ls -la /home/lab/

# Порт 8080 свободен
ss -tlnp | grep 8080
# (вывод должен быть пустым)
```

Если PHP или Composer не установлены:
```bash
apt update && apt install -y php8.3-cli php8.3-mysql php8.3-redis composer
```

---

## Шаг 1. Залить код

### Вариант A — rsync с локальной машины (рекомендуется)
> Выполняется **локально** на своём компьютере, не на сервере.

```bash
rsync -avz --exclude='.env' --exclude='vendor/' --exclude='storage/' \
  /path/to/lab6/ lab@178.104.227.19:/home/lab/app/
```

### Вариант B — scp архива
> Выполняется **локально**.

```bash
# Запаковать (исключая vendor и .env)
tar -czf lab6.tar.gz --exclude='vendor' --exclude='.env' --exclude='storage' lab6/

# Загрузить
scp lab6.tar.gz lab@178.104.227.19:/home/lab/

# На сервере (под lab)
mkdir -p /home/lab/app
tar -xzf /home/lab/lab6.tar.gz -C /home/lab/app --strip-components=1
```

### Вариант C — git clone (если репо приватное)
```bash
# (lab) на сервере
git clone https://github.com/your-repo/lab6.git /home/lab/app
```

---

## Шаг 2. Создать .env

```bash
# (lab) на сервере
cd /home/lab/app
cp deploy/.env.production.example .env
nano .env
```

Что обязательно заполнить:

| Переменная | Значение |
|---|---|
| `MYSQL_PASSWORD` | пароль пользователя `recipe` в MySQL |
| `CHAOS_ADMIN_PASSWORD` | любой пароль ≥ 12 символов, запомни для защиты |

Создать MySQL-пользователя, если ещё не создан (под root):
```bash
mysql -u root <<'SQL'
CREATE DATABASE IF NOT EXISTS recipe_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'recipe'@'127.0.0.1' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON recipe_manager.* TO 'recipe'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
```

Убедиться что .env не читается снаружи:
```bash
chmod 600 /home/lab/app/.env
```

---

## Шаг 3. Установка зависимостей и миграции

```bash
# (lab) на сервере
cd /home/lab/app
bash deploy/install.sh
```

Скрипт выполняет:
1. `composer install --no-dev` — устанавливает PHP-зависимости
2. `mkdir -p storage` — создаёт папку для chaos.json и rate-limit файлов
3. `php migrations/run.php` — создаёт таблицы в MySQL и применяет seed

Ожидаемый вывод:
```
[OK] Database 'recipe_manager' ready.
[OK] schema.sql — N statement(s) applied.
[OK] seed.sql — N statement(s) applied.
Migration complete.
```

---

## Шаг 4. Регистрация systemd-сервиса

```bash
# (root) на сервере
cp /home/lab/app/deploy/recipe-manager.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable recipe-manager
systemctl start recipe-manager

# Проверить статус
systemctl status recipe-manager
```

Ожидаемый вывод:
```
● recipe-manager.service - Recipe Manager PHP Application
     Active: active (running) since ...
```

---

## Шаг 5. Открыть порт 8080 в Hetzner Cloud Firewall

1. Открыть [Hetzner Cloud Console](https://console.hetzner.cloud/)
2. Выбрать проект → **Firewalls**
3. Выбрать firewall, привязанный к серверу `178.104.227.19`
4. Вкладка **Rules** → **Add Rule** (Inbound)
   - Protocol: **TCP**
   - Port: **8080**
   - Sources: `0.0.0.0/0` (или ограничить своим IP для защиты)
5. Сохранить — изменения применяются мгновенно

---

## Шаг 6. Smoke-test

```bash
# С любой машины
curl -sI http://178.104.227.19:8080/
# ожидаем: HTTP/1.1 200 OK

curl -sI http://178.104.227.19:8080/admin/login.php
# ожидаем: HTTP/1.1 200 OK

# Проверить maintenance-режим (chaos MySQL):
# (временно, через панель)
```

Также открыть в браузере:
- `http://178.104.227.19:8080/` — главная страница рецептов
- `http://178.104.227.19:8080/admin/login.php` — вход в Chaos Panel

---

## Шаг 7. Что сказать на защите

| Что | Значение |
|---|---|
| Приложение | `http://178.104.227.19:8080/` |
| Chaos Panel | `http://178.104.227.19:8080/admin/login.php` |
| Пароль | (то, что указали в `CHAOS_ADMIN_PASSWORD` в `.env`) |
| Как включить chaos | Войти в панель → кнопка «Отключить Redis» или «Отключить MySQL» |
| Автовосстановление | Через 30 секунд без каких-либо действий |

**Сценарий для преподавателя:**
1. Открыть главную → зарегистрироваться → добавить рецепт в избранное
2. Открыть Chaos Panel → «Отключить Redis» → вернуться на главную
3. Показать: красная плашка, сессия сброшена, избранное недоступно, `/stats.php` отдаёт нули
4. Нажать «Включить обратно» → всё восстановилось
5. «Отключить MySQL» → показать страницу maintenance (HTTP 503)
6. «Включить обратно» → сайт снова работает

---

## Откат / траблшутинг

### Остановить / перезапустить сервис
```bash
# (root)
systemctl stop recipe-manager
systemctl restart recipe-manager
```

### Просмотр логов в реальном времени
```bash
# (root или lab)
journalctl -u recipe-manager -f

# Последние 50 строк
journalctl -u recipe-manager -n 50
```

### Перечитать .env без перезапуска сервиса
Dotenv читает файл при каждом запросе — достаточно изменить `.env`, следующий запрос подхватит новые значения. Но **новые сессии PHP-сервера** запускаются только при рестарте юнита:
```bash
# (root)
systemctl restart recipe-manager
```

### Chaos-флаги застряли
Если флаги не сброшены автоматически:
```bash
# (lab)
echo '{"redis_disabled":false,"redis_disabled_until":null,"mysql_disabled":false,"mysql_disabled_until":null}' \
  > /home/lab/app/storage/chaos.json
```

### MySQL не отвечает
```bash
systemctl status mysql
mysql -u recipe -h 127.0.0.1 -p recipe_manager -e "SELECT 1"
```

### Redis не отвечает
```bash
systemctl status redis
redis-cli ping
```

### Приложение стартует, но 404 на всех страницах
Проверить что document root указывает на `public/`:
```bash
systemctl cat recipe-manager | grep ExecStart
# должно быть: -t /home/lab/app/public
```
