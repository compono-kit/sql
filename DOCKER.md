## Docker

**Initial:**

* `docker compose build`
* `docker compose run --rm sql_composer install`

**Tests ausführen:**

* `docker compose up -d sql_mariadb`
* `docker compose run --rm sql_php vendor/bin/phpunit -c build/phpunit.xml`

**Composer-Abhängigkeiten aktualisieren:**

* `docker compose run --rm sql_composer update`
