# sl3-migrations

Framework-agnostic менеджер миграций для PHP (MVP), совместимый по стилю CLI с Phinx для ключевых команд.

## Возможности MVP

- запуск как Composer bin: `vendor/bin/sl3-migrations`
- запуск как PHAR: `./sl3-migrations.phar`
- команды: `init`, `create`, `migrate`, `rollback`, `status`
- reversible migrations:
  - через `change()` с явными up/down SQL для обратимости
  - через отдельные `up()` и `down()` при необходимости
- формат миграций:
  - файл: `migrations/VersionYYYYmmddHHMMSS.php`
  - класс: `final class VersionYYYYmmddHHMMSS extends AbstractMigration`
- хранение состояния только в таблице `db_version`
- preflight-проверка `db_version` в командах `migrate`, `rollback`, `status`

## Установка

```bash
composer require slim3-aftermarket/migrations
```

## Быстрый старт

1. Инициализация:

```bash
vendor/bin/sl3-migrations init
```

Создаст конфиг `sl3-migrations.php` и таблицу `db_version`.

Если нужно загрузить секреты из env-файла:

```bash
vendor/bin/sl3-migrations init --env-file=.env
```

По умолчанию команды автоматически пытаются прочитать `.env` рядом с `sl3-migrations.php`.

1. Создание миграции:

```bash
vendor/bin/sl3-migrations create create_users_table
```

1. Применение миграций:

```bash
vendor/bin/sl3-migrations migrate
```

1. Откат:

```bash
vendor/bin/sl3-migrations rollback --steps=1
```

1. Статус:

```bash
vendor/bin/sl3-migrations status --format=json
```

## Примеры настройки адаптеров

```php
<?php

return [
    'driver' => 'sqlite',
    'database' => '${DB_PATH:-./var/db.sqlite}',
    'migrations_path' => 'migrations',
    'version_table' => 'db_version',
];
```

```php
<?php

return [
    'driver' => 'mysql',
    'host' => '${DB_HOST:-127.0.0.1}',
    'port' => '${DB_PORT:-3306}',
    'database' => '${DB_NAME}',
    'username' => '${DB_USER}',
    'password' => '${DB_PASSWORD}',
    'charset' => 'utf8mb4',
    'migrations_path' => 'migrations',
    'version_table' => 'db_version',
];
```

```php
<?php

return [
    'driver' => 'pgsql',
    'host' => '${DB_HOST:-127.0.0.1}',
    'port' => '${DB_PORT:-5432}',
    'database' => '${DB_NAME}',
    'username' => '${DB_USER}',
    'password' => '${DB_PASSWORD}',
    'migrations_path' => 'migrations',
    'version_table' => 'db_version',
];
```

Поддержка env-переменных:

- `${VAR}` — обязательная переменная
- `${VAR:-default}` — переменная с fallback

Можно явно указать env-файл для любой команды:

```bash
vendor/bin/sl3-migrations migrate --env-file=.env
vendor/bin/sl3-migrations status --env-file=.env --format=json
```

Если в конфиге есть `${VAR}`, но переменная не определена и env-файл не найден (или не содержит переменную), команда завершится с ошибкой.

## Пример миграции

```php
<?php

declare(strict_types=1);

use Sl3Migrations\Migration\AbstractMigration;

final class Version20220718170654 extends AbstractMigration
{
    public function change(): void
    {
        $this->addSql(
            'CREATE TABLE users (id INTEGER PRIMARY KEY, email VARCHAR(255) NOT NULL)',
            'DROP TABLE users'
        );
    }
}
```

Для сложных/нереверсивных операций используйте `up()` и `down()`:

```php
public function up(): void
{
    $this->execute('...');
}

public function down(): void
{
    $this->execute('...');
}
```

## Совместимость CLI с Phinx

Поддержаны:

- `--configuration`
- `--env-file`
- `--target`
- `--dry-run`
- `--format` (для `status`: `text` и `json`)

Ограничения MVP:

- `--environment` не поддерживается (single-runtime config)
- не все edge-case флаги Phinx реализованы, в таких случаях команда сообщает `Not yet supported in MVP`

## Сборка PHAR

```bash
composer install
vendor/bin/box compile
./sl3-migrations.phar --help
```

Важно: в проекте зафиксирована composer-платформа `php=8.3.30`, поэтому lock-файл и PHAR собираются с зависимостями, совместимыми с PHP 8.3.

## Тесты

```bash
composer test
```
