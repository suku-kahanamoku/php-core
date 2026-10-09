#!/usr/bin/env bash
set -euo pipefail
cleanup_root=$(cd "$(dirname "$0")/.." && pwd)
cleanup_temp=$(mktemp -d /tmp/php-core-tram-cleanup-test.XXXXXX)
cleanup() {
  mysqladmin --no-defaults --socket="$cleanup_temp/mysql.sock" -uroot shutdown >/dev/null 2>&1 || true
  rm -rf -- "$cleanup_temp"
}
trap cleanup EXIT
mkdir "$cleanup_temp/data"
mysqld --no-defaults --initialize-insecure --datadir="$cleanup_temp/data" --log-error="$cleanup_temp/init.log"
mysqld --no-defaults --datadir="$cleanup_temp/data" --socket="$cleanup_temp/mysql.sock" --pid-file="$cleanup_temp/mysql.pid" --skip-networking --mysqlx=0 --log-error="$cleanup_temp/server.log" &
for attempt in $(seq 1 100); do
  if mysqladmin --no-defaults --socket="$cleanup_temp/mysql.sock" -uroot ping >/dev/null 2>&1; then break; fi
  sleep 0.2
done
export TRAM_CLEANUP_TEST_DSN="mysql:unix_socket=$cleanup_temp/mysql.sock;dbname=tram_cleanup_test;charset=utf8mb4"
php "$cleanup_root/tests/tram-cleanup.php"
