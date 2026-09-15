#!/bin/sh
set -eu
project="a2a-bundle-postgres-$$"
compose() {
    docker compose -p "$project" -f compose.yml -f compose.postgres.yml "$@"
}
trap 'compose down --remove-orphans' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
compose run --build --rm php vendor/bin/phpunit -c phpunit.postgres.xml
