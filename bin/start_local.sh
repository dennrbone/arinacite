#!/usr/bin/env bash
set -euo pipefail
project_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
port="${1:-8000}"
if [[ ! "$port" =~ ^[0-9]+$ ]] || (( port < 1 || port > 65535 )); then
  echo 'Укажите номер порта от 1 до 65535.' >&2
  exit 2
fi
session_dir="$project_dir/.runtime/sessions"
mkdir -p "$session_dir"
chmod 700 "$project_dir/.runtime" "$session_dir"
cd "$project_dir"
exec php -d "session.save_path=$session_dir" -S "127.0.0.1:$port" -t public
