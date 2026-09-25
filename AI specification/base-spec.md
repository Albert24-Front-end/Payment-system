# Training Payment System project description

The project represents a mock payment system for training made on PHP/Laravel framework.
No external services for real money transactions are used. Instead, the project
uses a form with two buttons: success and failure.

Money units are in tiyins (currency - UZS) and stored as an integer.

## The project architecture
The project uses a basic structure of the Laravel application presuming that controllers' code
is moved to services. The repository layer is absent, the services directly call Eloquent.

## The project tests
The project is implemented with the TDD approach and tested basically by Feature tests. 
Unit-tests are used for the code that can be run isolated from the rest of the system, 
including the framework (for example, signature check).

The tests use Refresh Database.

Some tests in the project are written by hand and are not full due to time constraints.
But now full tests are necessary while generating.

## Launching PHP
PHP is to be launched by docker compose, php container on the docker container for development, located in the project root,
in the docker directory. For example,
```
docker compose exec -u www-data php php artisan route:list
```
