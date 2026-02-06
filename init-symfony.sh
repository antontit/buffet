#!/bin/sh
set -e
echo "Creating Symfony project..."
docker compose run --rm php sh -c 'composer create-project symfony/skeleton app --no-interaction && cd app && composer require webapp symfony/orm-pack doctrine/doctrine-bundle'
echo "Done. Run: docker compose up -d"
echo "Open: http://localhost:8082"
