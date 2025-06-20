#!/bin/bash

# Configure the APM bundle as a local repository
composer config repositories.apm-bundle path /bundle

# Install the APM bundle
composer require coding9/elastic-apm-symfony-bundle:@dev --no-interaction

# Start PHP built-in server
php -S 0.0.0.0:8000 -t public