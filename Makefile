# Diaré Groupe Industrie — commandes Docker

.PHONY: up down stop restart ps bash logs install db-create db-migrate fixtures assets assets-watch cache-clear lint test export-chatgpt help

up:
	docker compose up -d --build

down:
	docker compose down

stop:
	docker compose stop

restart: stop up

ps:
	docker compose ps

bash:
	docker compose exec php-apache bash

logs:
	docker compose logs -f

install: up
	docker compose exec php-apache php bin/console doctrine:migrations:migrate -n
	docker compose exec php-apache php bin/console doctrine:fixtures:load -n
	docker compose run --rm node npm install
	docker compose run --rm node npm run build

db-create:
	docker compose exec php-apache php bin/console doctrine:database:create --if-not-exists

db-migrate:
	docker compose exec php-apache php bin/console doctrine:migrations:migrate -n

fixtures:
	docker compose exec php-apache php bin/console doctrine:fixtures:load -n

assets:
	docker compose run --rm node npm run build

assets-watch:
	docker compose run --rm node npm run watch

cache-clear:
	docker compose exec php-apache php bin/console cache:clear

lint:
	docker compose exec php-apache php bin/console lint:twig templates
	docker compose exec php-apache php bin/console lint:yaml config
	docker compose exec php-apache php bin/console doctrine:schema:validate
	docker compose exec php-apache php bin/console cache:clear

test:
	docker compose exec php-apache php vendor/bin/phpunit

export-chatgpt:
	chmod +x scripts/export-site-public-chatgpt.sh
	./scripts/export-site-public-chatgpt.sh

help:
	@echo "Diaré Groupe Industrie — commandes disponibles"
	@echo "  make up              Démarrer Docker"
	@echo "  make down            Arrêter et supprimer les conteneurs"
	@echo "  make stop            Arrêter les conteneurs"
	@echo "  make restart         Redémarrer"
	@echo "  make ps              État des conteneurs"
	@echo "  make bash            Shell PHP"
	@echo "  make logs            Logs Docker"
	@echo "  make install         Migrations, fixtures et build des assets"
	@echo "  make db-create       Créer la base si elle n'existe pas"
	@echo "  make db-migrate      Migrations Doctrine"
	@echo "  make fixtures        Recharger les fixtures"
	@echo "  make assets          Compiler Encore"
	@echo "  make assets-watch    Compiler Encore en continu"
	@echo "  make cache-clear     Vider le cache Symfony"
	@echo "  make lint            Twig, YAML, schéma Doctrine, cache"
	@echo "  make test            PHPUnit"
	@echo "  bin/install.sh       Préparation production sur le VPS (pas les fixtures)"
	@echo "  bin/deploy.sh        Déploiement production sur le VPS"
	@echo "  make export-chatgpt  Archive légère sur le Bureau"
