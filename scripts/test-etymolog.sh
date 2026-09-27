#!/usr/bin/env bash
set -euo pipefail
ety_root=$(cd "$(dirname "$0")/.." && pwd)
ety_temp=$(mktemp -d /tmp/etymolog-test.XXXXXX)
cleanup() {
  mysqladmin --no-defaults --socket="$ety_temp/mysql.sock" -uroot shutdown >/dev/null 2>&1 || true
  rm -rf -- "$ety_temp"
}
trap cleanup EXIT
mkdir "$ety_temp/data"
mysqld --no-defaults --initialize-insecure --datadir="$ety_temp/data" --log-error="$ety_temp/init.log"
mysqld --no-defaults --datadir="$ety_temp/data" --socket="$ety_temp/mysql.sock" --pid-file="$ety_temp/mysql.pid" --skip-networking --mysqlx=0 --log-error="$ety_temp/server.log" &
for attempt in $(seq 1 100); do
  if mysqladmin --no-defaults --socket="$ety_temp/mysql.sock" -uroot ping >/dev/null 2>&1; then break; fi
  sleep 0.2
done
export ETYMOLOG_TEST_DSN="mysql:unix_socket=$ety_temp/mysql.sock;dbname=etymolog_test;charset=utf8mb4"
export ETYMOLOG_TEST_DIR="$ety_temp"
php "$ety_root/src/Modules/Etymolog/tests/integration.php"
