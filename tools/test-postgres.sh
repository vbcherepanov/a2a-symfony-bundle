#!/bin/sh
set -eu
project="a2a-bundle-postgres-$$"
compose() {
    docker compose -p "$project" -f compose.yml -f compose.postgres.yml "$@"
}
trap 'compose down --remove-orphans' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
# The Docker Engine builder can resolve the locally loaded PHP base image.
compose build --builder default php
compose run --rm php vendor/bin/phpunit -c phpunit.postgres.xml
