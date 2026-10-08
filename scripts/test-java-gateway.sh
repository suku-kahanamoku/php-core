#!/usr/bin/env bash
set -euo pipefail
gateway_root=$(cd "$(dirname "$0")/.." && pwd)
gateway_temp=$(mktemp -d /tmp/php-core-java-gateway-test.XXXXXX)
trap 'rm -rf -- "$gateway_temp"' EXIT
export JAVA_GATEWAY_TEST_DIR="$gateway_temp"
php "$gateway_root/src/Modules/Transport/Gateway/tests/integration.php"
php "$gateway_root/src/Modules/Transport/Admin/tests/online-planners.php"
