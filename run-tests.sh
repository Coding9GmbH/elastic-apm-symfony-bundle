#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "🚀 Building Docker image..."
docker compose build php

echo -e "\n📦 Installing dependencies..."
docker compose run --rm php composer install

echo -e "\n✅ Running PHPUnit tests..."
if docker compose run --rm php composer test; then
    echo -e "${GREEN}✓ Tests passed${NC}"
else
    echo -e "${RED}✗ Tests failed${NC}"
    exit 1
fi

echo -e "\n🎨 Running PHP CS Fixer..."
if docker compose run --rm php composer cs-fix -- --dry-run --diff; then
    echo -e "${GREEN}✓ Code style is correct${NC}"
else
    echo -e "${YELLOW}⚠ Code style issues found${NC}"
    echo "Run 'docker compose run --rm php composer cs-fix' to fix them"
fi

echo -e "\n🔍 Running PHPStan..."
if docker compose run --rm php composer phpstan; then
    echo -e "${GREEN}✓ Static analysis passed${NC}"
else
    echo -e "${RED}✗ Static analysis failed${NC}"
    exit 1
fi

echo -e "\n📊 Running tests with coverage..."
docker compose run --rm php ./vendor/bin/phpunit --coverage-text --coverage-html coverage/

echo -e "\n${GREEN}🎉 All checks completed!${NC}"