# Docker Test-Umgebung für Elastic APM Symfony Bundle

Diese Docker-Umgebung bietet eine komplette Test-Infrastruktur für das Elastic APM Symfony Bundle.

## 🚀 Quick Start

```bash
# Starte die komplette APM-Umgebung
make up

# Führe Tests aus
make test

# Starte Test-Symfony-App
make test-integration
```

## 📦 Verfügbare Services

- **Elasticsearch** (Port 9200): Speichert APM-Daten
- **Kibana** (Port 5601): Visualisierung der APM-Daten
- **APM Server** (Port 8200): Empfängt APM-Daten vom Bundle
- **PHP Test Container**: Für PHPUnit Tests
- **Symfony Test App** (Port 8080): Demo-Anwendung mit Bundle

## 🛠️ Makefile Befehle

```bash
make help              # Zeigt alle verfügbaren Befehle
make up                # Startet alle Services
make down              # Stoppt alle Services
make test              # Führt PHPUnit Tests aus
make test-integration  # Startet Symfony Test-App
make logs              # Zeigt Logs aller Services
make shell             # Öffnet Shell im PHP Container
make clean             # Entfernt alle Container und Daten
make test-apm          # Sendet Test-Daten an APM
```

## 🧪 Test-Endpoints der Demo-App

Nach `make test-integration`:

- `http://localhost:8080/` - Homepage
- `http://localhost:8080/api/users` - User Liste (simuliert DB-Query)
- `http://localhost:8080/api/slow` - Langsame Response (2s)
- `http://localhost:8080/api/error` - Wirft Exception
- `http://localhost:8080/api/random-status` - Zufälliger HTTP Status

## 📊 APM-Daten in Kibana anzeigen

1. Öffne Kibana: http://localhost:5601
2. Gehe zu "APM" im Menü
3. Du siehst alle Services und Transaktionen

## 🔧 Eigene Tests schreiben

```php
// tests/send-test-data.php anpassen oder eigene Scripte erstellen
$apm = new ElasticApmInteractor($config);
$transaction = $apm->startTransaction('meine-transaktion', 'custom');
// ... dein Code ...
$apm->stopTransaction($transaction);
```

## 🐛 Troubleshooting

- **Services starten nicht**: `docker compose logs <service-name>`
- **Keine Daten in Kibana**: Warte ~30 Sekunden nach dem Start
- **Port bereits belegt**: Ändere Ports in docker-compose.yml