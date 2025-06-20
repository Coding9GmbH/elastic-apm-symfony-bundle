#!/bin/bash

echo "Starting standalone APM test..."

# Check if containers are running
if ! docker ps | grep -q apm-server; then
    echo "Error: APM containers are not running. Run ./tests/start-test-app.sh first."
    exit 1
fi

# Run standalone test server
echo "Starting test server on http://localhost:8889"
echo "Visit this URL in your browser to test APM functionality."
echo ""

docker run --rm -it \
    --network elastic-apm-symfony-bundle_apm-network \
    -p 8889:8000 \
    -v $(pwd):/app \
    -w /app \
    -e ELASTIC_APM_SERVER_URL=http://apm-server:8200 \
    php:8.2-cli \
    php -S 0.0.0.0:8000 tests/fixtures/test-standalone.php