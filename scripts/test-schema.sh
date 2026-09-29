#!/usr/bin/env bash
set -euo pipefail
schema_root=$(cd "$(dirname "$0")/.." && pwd)
python3 "$schema_root/scripts/build-schemas.py" --check
schema_temp=$(mktemp -d /tmp/php-core-schema-test.XXXXXX)
cleanup() {
  mysqladmin --no-defaults --socket="$schema_temp/mysql.sock" -uroot shutdown >/dev/null 2>&1 || true
  rm -rf -- "$schema_temp"
}
trap cleanup EXIT
mkdir "$schema_temp/data"
mysqld --no-defaults --initialize-insecure --datadir="$schema_temp/data" --log-error="$schema_temp/init.log"
mysqld --no-defaults --datadir="$schema_temp/data" --socket="$schema_temp/mysql.sock" --pid-file="$schema_temp/mysql.pid" --skip-networking --mysqlx=0 --log-error="$schema_temp/server.log" &
for attempt in $(seq 1 100); do
  if mysqladmin --no-defaults --socket="$schema_temp/mysql.sock" -uroot ping >/dev/null 2>&1; then break; fi
  sleep 0.2
done
export SCHEMA_TEST_DSN="mysql:unix_socket=$schema_temp/mysql.sock;dbname=schema_test;charset=utf8mb4"
php "$schema_root/tests/schema.php"
