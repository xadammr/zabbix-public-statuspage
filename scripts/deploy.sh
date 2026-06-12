#!/usr/bin/env bash

set -euo pipefail

cd "$(dirname "$0")/.."

echo "Pulling latest code..."
git pull --ff-only

echo "Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

echo "Running database migrations..."
php artisan migrate --force

echo "Installing frontend dependencies..."
npm ci

echo "Building frontend assets..."
npm run build

echo "Refreshing Laravel caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Refreshing status page snapshot..."
php artisan statuspage:poll --force

echo "Deployment complete."
