#!/usr/bin/env bash
set -euo pipefail
tram_root=$(cd "$(dirname "$0")/.." && pwd)
tram_temp=$(mktemp -d /tmp/transport-test.XXXXXX)
cleanup() {
  mysqladmin --no-defaults --socket="$tram_temp/mysql.sock" -uroot shutdown >/dev/null 2>&1 || true
  rm -rf -- "$tram_temp"
}
trap cleanup EXIT
mkdir "$tram_temp/data"
mysqld --no-defaults --initialize-insecure --datadir="$tram_temp/data" --log-error="$tram_temp/init.log"
mysqld --no-defaults --datadir="$tram_temp/data" --socket="$tram_temp/mysql.sock" --pid-file="$tram_temp/mysql.pid" --skip-networking --mysqlx=0 --log-error="$tram_temp/server.log" &
for attempt in $(seq 1 100); do
  if mysqladmin --no-defaults --socket="$tram_temp/mysql.sock" -uroot ping >/dev/null 2>&1; then break; fi
  sleep 0.2
done
export TRANSPORT_TEST_DSN="mysql:unix_socket=$tram_temp/mysql.sock;dbname=transport_test;charset=utf8mb4"
export TRANSPORT_TEST_DIR="$tram_temp"
php "$tram_root/src/Modules/Transport/tests/integration.php"
