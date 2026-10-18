#!/usr/bin/env bash
# SPDX-License-Identifier: MIT
set -euo pipefail
address="127.0.0.1:8003"
url="http://${address}"
script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
php -S "$address" "$script_dir/index.php" &
server_pid=$!
cleanup() { kill "$server_pid" 2>/dev/null || true; wait "$server_pid" 2>/dev/null || true; }
trap cleanup EXIT INT TERM
for _ in {1..50}; do
  if curl --silent --fail --output /dev/null "$url"; then break; fi
  if ! kill -0 "$server_pid" 2>/dev/null; then wait "$server_pid"; exit 1; fi
  sleep 0.1
done
if command -v librewolf >/dev/null 2>&1; then librewolf --new-window "$url" >/dev/null 2>&1 &
elif command -v xdg-open >/dev/null 2>&1; then xdg-open "$url" >/dev/null 2>&1 &
fi
echo "EÜR läuft unter $url (Strg+C beendet den Server)."
wait "$server_pid"
