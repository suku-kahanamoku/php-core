#!/usr/bin/env bash
set -euo pipefail
auth_root=$(cd "$(dirname "$0")/.." && pwd)
php "$auth_root/src/Modules/Transport/Admin/tests/online-planners.php"
