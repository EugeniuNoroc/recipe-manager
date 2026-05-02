#!/usr/bin/env bash
# Аварийный сброс chaos-флагов. Запускать под пользователем lab.
# Usage: bash deploy/emergency-reset.sh
set -e

cd "$(dirname "$0")/.."

echo '{"redis_disabled":false,"mysql_disabled":false}' > storage/chaos.json
echo "Chaos flags reset. Site should be available now."
echo ""
cat storage/chaos.json
