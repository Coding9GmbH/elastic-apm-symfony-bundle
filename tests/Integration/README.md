# Integration Tests

These tests verify that the APM bundle correctly sends data to Elasticsearch via APM Server.

## Running Integration Tests

### Quick Start

```bash
# Start all services and run integration tests
make test-integration

# Run all tests (unit + integration)
make test-all
```

### Manual Testing

1. Start the APM stack:
```bash
docker compose up -d
```

2. Wait for services to be ready (check health):
```bash
docker compose ps
```

3. Run integration tests:
```bash
docker compose run --rm php vendor/bin/phpunit -c phpunit-integration.xml.dist
```

4. Verify APM data:
```bash
docker compose run --rm php php tests/verify-apm-data.php
```

## Test Coverage

### ApmIntegrationTest
- ✅ Transactions are stored in Elasticsearch
- ✅ Spans are linked to transactions
- ✅ Errors are captured
- ✅ Metrics are collected
- ✅ Distributed tracing headers work
- ✅ High volume transactions are handled

### SymfonyIntegrationTest
- ✅ Controller requests create transactions
- ✅ Slow requests have correct duration
- ✅ Exceptions are captured
- ✅ Different HTTP status codes are tracked
- ✅ Request headers are captured
- ✅ Concurrent requests are handled
- ✅ APM overhead is acceptable

## Environment Variables

- `APM_SERVER_URL`: APM Server URL (default: http://localhost:8200)
- `ELASTICSEARCH_URL`: Elasticsearch URL (default: http://localhost:9200)
- `SYMFONY_APP_URL`: Symfony test app URL (default: http://localhost:8080)

## Debugging Failed Tests

1. Check service logs:
```bash
docker compose logs apm-server
docker compose logs elasticsearch
```

2. Verify data in Elasticsearch:
```bash
# List indices
curl localhost:9200/_cat/indices/apm-*

# Search for transactions
curl -X POST localhost:9200/apm-*/_search -H "Content-Type: application/json" -d '{
  "query": {"exists": {"field": "transaction"}}
}'
```

3. Check APM Server status:
```bash
curl localhost:8200/
```

## Performance Tests

Run performance tests separately:
```bash
docker compose run --rm php vendor/bin/phpunit -c phpunit-integration.xml.dist --group performance
```

## CI/CD Integration

Integration tests run automatically on:
- Pull requests
- Pushes to main branch

See `.github/workflows/tests.yml` for configuration.