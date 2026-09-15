.PHONY: help up down restart logs shell test test-postgres test-symfony test-flex build lint clean ps install verify style package
help:
	@echo 'up down restart logs shell install test test-postgres test-symfony test-flex lint style build verify package clean ps'
test-flex:
	docker compose run --rm php php tools/test-flex.php
test-symfony:
	docker compose run --rm php php tools/test-symfony.php
test-postgres:
	sh tools/test-postgres.sh
up:
	docker compose up -d
down:
	docker compose down
restart:
	docker compose restart
logs:
	docker compose logs -f
shell:
	docker compose run --rm php sh
install:
	docker compose run --rm php composer install --no-interaction --prefer-dist
test:
	docker compose run --rm php composer test
lint:
	docker compose run --rm php composer lint
build:
	docker compose build
	docker compose run --rm php composer build
clean:
	docker compose down --remove-orphans
ps:
	docker compose ps

style:
	docker compose run --rm php composer style
verify:
	docker compose run --rm php composer validate --strict
	docker compose run --rm php composer test
	docker compose run --rm php composer lint
	docker compose run --rm php composer style
	docker compose run --rm php composer build
package:
	docker compose run --rm php composer package
