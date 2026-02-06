# Symfony Template (Docker)

Clean Symfony project template. Stack: **nginx**, **PHP-FPM**, **Xdebug**, **MariaDB**.

## Quick start

```bash
# 1. Create Symfony project (one time)
chmod +x init-symfony.sh
./init-symfony.sh

# 2. Start containers
docker compose up -d

# 3. Open in browser
# http://localhost:8082
```

## Without init script

```bash
docker compose up -d
docker compose exec php composer create-project symfony/skeleton . --no-interaction
docker compose exec php composer require webapp symfony/orm-pack
```

## Database

- **Host:** `mariadb` (from php container) or `127.0.0.1` (from host)
- **Port:** 3306
- **Database:** symfony
- **User:** symfony
- **Password:** symfony

`DATABASE_URL` is set in `.env`.

## Xdebug

- Port: **9003**
- IDE key: **PHPSTORM**
- Enable listener on port 9003 and set server `symfony` with path `/var/www/html`.

## Commands

```bash
docker compose up -d          # Start
docker compose down          # Stop
docker compose exec php sh   # Shell in PHP container
docker compose exec php composer require <package>
```
