#!/usr/bin/env bash
set -euo pipefail
tram_graph_dir=$(realpath "${1:?Usage: transport-serve-graph.sh <graph-directory> <port> <container-name>}")
tram_port=${2:?Supply a distinct local port for this graph version}
tram_container=${3:?Supply a distinct container name for this graph version}
if [[ ! "$tram_port" =~ ^[1-9][0-9]{0,4}$ ]] || (( tram_port > 65535 )); then
  echo "Invalid TCP port." >&2
  exit 2
fi
if [[ ! "$tram_container" =~ ^[a-zA-Z0-9][a-zA-Z0-9_.-]+$ ]]; then
  echo "Invalid container name." >&2
  exit 2
fi
test -f "$tram_graph_dir/manifest.json"
test -f "$tram_graph_dir/graph.obj"
test -f "$tram_graph_dir/build-receipt.json"
docker run -d --name "$tram_container" --user "$(id -u):$(id -g)" \
  --restart unless-stopped -e "JAVA_TOOL_OPTIONS=-Xmx${OTP_HEAP:-4G}" \
  -p "127.0.0.1:$tram_port:8080" -v "$tram_graph_dir:/var/opentripplanner:ro" \
  opentripplanner/opentripplanner:2.9.0 --load --serve
