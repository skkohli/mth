#!/usr/bin/env bash
set -euo pipefail

database="${MARIADB_DATABASE:-u813440994_J22Y4}"
dump_file="/dbdump/u813440994_J22Y4 (3).sql"
local_url="${WP_LOCAL_URL:-http://localhost:8081}"

if [[ ! -f "$dump_file" ]]; then
  echo "Database dump not found: $dump_file" >&2
  exit 1
fi

echo "Importing WordPress dump into ${database}..."
mariadb -uroot -p"${MARIADB_ROOT_PASSWORD}" "${database}" < "$dump_file"

echo "Setting local WordPress URLs to ${local_url}..."
mariadb -uroot -p"${MARIADB_ROOT_PASSWORD}" "${database}" <<SQL
UPDATE wp_options
SET option_value = '${local_url}'
WHERE option_name IN ('siteurl', 'home');
SQL
