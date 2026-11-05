#!/usr/bin/env bash
set -euo pipefail

# A production preflight with deliberately incomplete configuration must fail
# closed, and must never echo database credentials or other secret values.
set +e
output="$(env -i PATH="$PATH" \
  APP_ENV=production \
  APP_DEBUG=false \
  APP_KEY=0123456789abcdef0123456789abcdef \
  DB_HOST=127.0.0.1 \
  DB_DATABASE=preflight_missing_database \
  DB_USERNAME=preflight_user \
  DB_PASSWORD=preflight-secret-must-not-appear \
  php bin/production-preflight.php 2>&1)"
status=$?
set -e

if [[ "$status" -eq 0 ]]; then
  echo "Production preflight unexpectedly passed with incomplete configuration."
  echo "$output"
  exit 1
fi

if grep -Fq 'preflight-secret-must-not-appear' <<<"$output"; then
  echo "Production preflight exposed a secret value."
  exit 1
fi

if ! grep -Fq 'Production preflight failed' <<<"$output"; then
  echo "Production preflight did not return its expected failure summary."
  echo "$output"
  exit 1
fi

echo "Production preflight fails closed without exposing secrets."
