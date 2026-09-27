#!/usr/bin/env bash
set -euo pipefail
tram_graph_dir=$(realpath "${1:?Usage: transport-build-graph.sh <exported-directory>}")
test -f "$tram_graph_dir/manifest.json"
test -f "$tram_graph_dir/streets.osm.pbf"
if test -f "$tram_graph_dir/graph.obj"; then
  echo "Graph already exists; export into a new directory." >&2
  exit 1
fi
# Export creates a private directory; run the build under the current UID.
docker run --rm --user "$(id -u):$(id -g)" -e "JAVA_TOOL_OPTIONS=-Xmx${OTP_HEAP:-4G}"   -v "$tram_graph_dir:/var/opentripplanner" opentripplanner/opentripplanner:2.9.0 --build --save
php -r '$d=$argv[1]; if (!is_file($d."/graph.obj")) exit(1); $r=["manifest_sha256"=>hash_file("sha256",$d."/manifest.json"),"graph_sha256"=>hash_file("sha256",$d."/graph.obj"),"built_at"=>gmdate(DATE_RFC3339),"image"=>"opentripplanner/opentripplanner:2.9.0"]; file_put_contents($d."/build-receipt.json",json_encode($r,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));' "$tram_graph_dir"
