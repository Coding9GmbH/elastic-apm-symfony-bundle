# Makefile für einfache Docker-Befehle

.PHONY: help up down test logs shell clean

help: ## Zeigt diese Hilfe
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

up: ## Startet die komplette APM-Umgebung
	docker compose up -d
	@echo "Warte auf Services..."
	@sleep 10
	@echo "✅ APM Stack läuft!"
	@echo "📊 Kibana: http://localhost:5601"
	@echo "🔍 Elasticsearch: http://localhost:9200"
	@echo "📡 APM Server: http://localhost:8200"

down: ## Stoppt alle Container
	docker compose down

test: ## Führt die PHPUnit Tests aus
	docker compose run --rm php vendor/bin/phpunit

test-unit: ## Führt nur Unit Tests aus
	docker compose run --rm php vendor/bin/phpunit --testsuite="Unit Tests"

test-integration: ## Führt Integration Tests aus (startet alle Services)
	@echo "Starting services for integration tests..."
	docker compose up -d --wait
	docker compose -f docker-compose.yml -f docker-compose.test.yml up -d symfony-app
	@echo "Waiting for services to be ready..."
	@sleep 10
	docker compose run --rm php vendor/bin/phpunit -c phpunit-integration.xml.dist
	
test-integration-watch: ## Führt Integration Tests im Watch-Modus
	docker compose run --rm php vendor/bin/phpunit -c phpunit-integration.xml.dist --testdox-html reports/integration.html

test-all: ## Führt alle Tests aus (Unit + Integration)
	@make test-unit
	@make test-integration

logs: ## Zeigt Logs aller Services
	docker compose logs -f

logs-apm: ## Zeigt nur APM Server Logs
	docker compose logs -f apm-server

shell: ## Öffnet eine Shell im PHP Container
	docker compose run --rm php bash

clean: ## Entfernt alle Container und Volumes
	docker compose down -v
	rm -rf vendor/
	rm -f composer.lock

install: ## Installiert Composer Dependencies
	docker compose run --rm php composer install

update: ## Updated Composer Dependencies
	docker compose run --rm php composer update

phpstan: ## Führt PHPStan aus
	docker compose run --rm php vendor/bin/phpstan analyse

# Schnell-Test mit curl
test-apm: ## Sendet Test-Daten an APM
	@echo "Sende Test-Transaction..."
	@docker compose run --rm php php tests/send-test-data.php

# Kibana Index Pattern erstellen
setup-kibana: ## Erstellt APM Index Pattern in Kibana
	@echo "Erstelle Index Pattern in Kibana..."
	@curl -X POST "localhost:5601/api/saved_objects/index-pattern" \
		-H "Content-Type: application/json" \
		-H "kbn-xsrf: true" \
		-d '{"attributes":{"title":"apm-*","timeFieldName":"@timestamp"}}'