---
description: Training Payment System — domain, money, and Docker PHP execution
alwaysApply: true
---

# Project overview

Educational Laravel payment system. No real payment providers — the payment UI is a form with two buttons: success and failure.

Currency is UZS. Store and pass all monetary amounts as **integers in tiyins** (1 UZS = 100 tiyins). Never use floats for money.

Laravel app lives in `application/`. Dev stack is Docker Compose in `docker/`.

# Running PHP

Always run PHP, Composer, Artisan, and PHPUnit via the `php` service as `www-data`, from the `docker/` directory:

```bash
cd docker
docker compose exec -u www-data php php artisan route:list
docker compose exec -u www-data php composer install
docker compose exec -u www-data php php artisan test
```

Do not run host PHP/Composer against the app unless the user explicitly asks.