#!/usr/bin/env bash
# Run as user: lab
# Usage: bash deploy/install.sh
set -euo pipefail

APPDIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APPDIR"

echo "=== Recipe Manager — Install ==="
echo "App dir: $APPDIR"
echo ""

# 1. Composer dependencies (production only)
echo "[1/3] Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# 2. Ensure storage directory exists with correct permissions
echo "[2/3] Preparing storage directory..."
mkdir -p storage/admin_rl
touch storage/.gitkeep
chmod 755 storage

# 3. Run database migrations
echo "[3/3] Running database migrations..."
php migrations/run.php

echo ""
echo "✓ Install complete."
echo ""
echo "Next steps (as root):"
echo "  sudo cp deploy/recipe-manager.service /etc/systemd/system/"
echo "  sudo systemctl daemon-reload"
echo "  sudo systemctl enable recipe-manager"
echo "  sudo systemctl start recipe-manager"
