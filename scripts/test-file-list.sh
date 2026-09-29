#!/usr/bin/env bash
set -euo pipefail
file_root=$(cd "$(dirname "$0")/.." && pwd)
file_temp=$(mktemp -d /tmp/php-core-file-list.XXXXXX)
cleanup() {
  mysqladmin --no-defaults --socket="$file_temp/mysql.sock" -uroot shutdown >/dev/null 2>&1 || true
  rm -rf -- "$file_temp"
}
trap cleanup EXIT
mkdir "$file_temp/data"
mysqld --no-defaults --initialize-insecure --datadir="$file_temp/data" --log-error="$file_temp/init.log"
mysqld --no-defaults --datadir="$file_temp/data" --socket="$file_temp/mysql.sock" --pid-file="$file_temp/mysql.pid" --skip-networking --mysqlx=0 --log-error="$file_temp/server.log" &
for attempt in $(seq 1 100); do
  if mysqladmin --no-defaults --socket="$file_temp/mysql.sock" -uroot ping >/dev/null 2>&1; then break; fi
  sleep 0.2
done
export FILE_LIST_TEST_DSN="mysql:unix_socket=$file_temp/mysql.sock;dbname=file_list_test;charset=utf8mb4"
php "$file_root/tests/file_list.php"
