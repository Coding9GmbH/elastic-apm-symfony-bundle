#!/bin/bash

echo "🚀 Running simple test environment..."

# Build the Docker image
docker build -t elastic-apm-test .

# Run composer install
echo "📦 Installing dependencies..."
docker run --rm -v $(pwd):/app elastic-apm-test composer install --ignore-platform-reqs

# Run PHP CS Fixer
echo "🎨 Checking code style..."
docker run --rm -v $(pwd):/app elastic-apm-test ./vendor/bin/php-cs-fixer fix --dry-run --diff || echo "Code style issues found"

# Run a simple PHP syntax check
echo "✅ Checking PHP syntax..."
docker run --rm -v $(pwd):/app elastic-apm-test find src tests -name "*.php" -exec php -l {} \;

echo "✅ Basic checks completed!"