#!/bin/bash

# Start the test environment
echo "Starting APM test environment..."

# Build and start containers
docker compose -f docker-compose.yml -f docker-compose.test.yml up -d --build

# Wait for services to be ready
echo "Waiting for services to be ready..."
sleep 10

# Run database migrations for Messenger (if needed)
docker compose -f docker-compose.yml -f docker-compose.test.yml exec symfony-app php bin/console doctrine:schema:update --force 2>/dev/null || true

# Start Messenger worker in background
docker compose -f docker-compose.yml -f docker-compose.test.yml exec -d symfony-app php bin/console messenger:consume async -vv

echo ""
echo "Test application is ready!"
echo ""
echo "Access the application at: http://localhost:8888"
echo "Kibana dashboard: http://localhost:5601"
echo ""
echo "Available test endpoints:"
echo "- http://localhost:8888/ - Main page with buttons"
echo "- http://localhost:8888/api/users - Normal API request"
echo "- http://localhost:8888/api/slow - Slow request (2s)"
echo "- http://localhost:8888/api/error - Trigger an error"
echo "- http://localhost:8888/api/send-message - Send message to queue (POST)"
echo ""
echo "To stop the environment: docker compose -f docker-compose.yml -f docker-compose.test.yml down"