# Runtime and validation

Run PHP, Artisan, Composer, PHPUnit and Pint through the development Docker Compose `php` service as `www-data`, as required by the base specification. Do not fall back to host PHP.

Use `docker/` as the working directory:

```sh
docker compose ps
docker compose exec -u www-data php php artisan route:list
docker compose exec -u www-data php php artisan test --filter=PaymentCreationTest
docker compose exec -u www-data php composer test
docker compose exec -u www-data php php vendor/bin/pint --test app/Services/PaymentCreationService.php
```

Replace the test filter and Pint path with relevant files. Scope formatting checks/fixes to touched PHP files. `composer test` clears Laravel's configuration cache before running the suite; clear it through the container before focused tests when configuration changes require it.

From the repository root, use an explicit Compose-file prefix:

```sh
docker compose -f docker/docker-compose.yml exec -u www-data php php artisan route:list
```

The container working directory `/var/www/html` maps to `application/`, so omit `application/` from container-relative paths. The Dockerfile installs PHP 8.5; Composer declares PHP `^8.3` and Laravel `^13.17`. Respect declared compatibility when adding language features.

When environment setup is part of the task, the README uses `docker compose up -d` and `docker compose exec -u www-data php composer install` from `docker/`. Inspect availability and configuration before testing. Do not reset databases or volumes to resolve routine failures. If infrastructure or dependencies are unavailable, report the blocker and commands still needing execution.
