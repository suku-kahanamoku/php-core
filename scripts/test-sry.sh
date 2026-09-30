#!/usr/bin/env bash
set -euo pipefail
sry_root=$(cd "$(dirname "$0")/.." && pwd)
sry_temp=$(mktemp -d /tmp/sry-db.XXXXXX)
cleanup() {
  mysqladmin --no-defaults --socket="$sry_temp/mysql.sock" -uroot shutdown >/dev/null 2>&1 || true
  rm -rf -- "$sry_temp"
}
trap cleanup EXIT
mkdir "$sry_temp/data"
mysqld --no-defaults --initialize-insecure --datadir="$sry_temp/data" --log-error="$sry_temp/init.log"
mysqld --no-defaults --datadir="$sry_temp/data" --socket="$sry_temp/mysql.sock" --pid-file="$sry_temp/mysql.pid" --skip-networking --mysqlx=0 --log-error="$sry_temp/server.log" &
for attempt in $(seq 1 100); do
  if mysqladmin --no-defaults --socket="$sry_temp/mysql.sock" -uroot ping >/dev/null 2>&1; then break; fi
  sleep 0.2
done
export SRY_TEST_DSN="mysql:unix_socket=$sry_temp/mysql.sock;dbname=sry_test;charset=utf8mb4"
php "$sry_root/src/Modules/Sry/tests/provision.php"
php "$sry_root/src/Modules/Sry/tests/integration.php"
export SRY_TEST_LOG="$sry_temp/http.log"
php "$sry_root/src/Modules/Sry/tests/http-contract.php"
