#!/usr/bin/env bash
set -euo pipefail
http_root=$(cd "$(dirname "$0")/.." && pwd)
php "$http_root/src/Modules/Http/tests/integration.php"
for http_test in "$http_root"/src/Modules/OpenAi/tests/*Test.php "$http_root"/src/Modules/FannCatalog/tests/*Test.php "$http_root"/tests/test_internal_auth.php "$http_root"/tests/test_query_policy.php "$http_root"/tests/test_projection.php; do
  php "$http_test"
done
