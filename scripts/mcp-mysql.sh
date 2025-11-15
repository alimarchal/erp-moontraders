#!/usr/bin/env sh
# Lightweight launcher for a MySQL/MariaDB MCP server using Node and stdio.
# - Loads .env (Laravel-style) if present
# - Builds DATABASE_URL from DB_* vars if not already set
# - Execs the MCP server via npx so the calling client connects directly to it

set -e

# cd to workspace root if invoked elsewhere
cd "$(dirname "$0")/.."

# Load .env if present and export variables
if [ -f ./.env ]; then
  # Export variables defined in .env
  set -a
  . ./.env
  set +a
fi

# If DATABASE_URL not set, build it for MySQL/MariaDB from Laravel DB_* vars
if [ -z "${DATABASE_URL:-}" ]; then
  DB_HOST="${DB_HOST:-127.0.0.1}"
  DB_PORT="${DB_PORT:-3306}"
  DB_NAME="${DB_DATABASE:-moontrader}"
  DB_USER="${DB_USERNAME:-root}"
  DB_PASS="${DB_PASSWORD:-}"
  # NOTE: If your password contains special characters, URL-encode it.
  DATABASE_URL="mysql://${DB_USER}:${DB_PASS}@${DB_HOST}:${DB_PORT}/${DB_NAME}"
  export DATABASE_URL
fi

# Allow package override via env var; default to MySQL MCP package
PKG="${MYSQL_MCP_PACKAGE:-@berthojoris/mcp-mysql-server}"

# Log startup info (mask password) for debugging
MASKED_PASS="****"
printf "[mcp] package=%s\n" "$PKG" 1>&2
if [ -n "${DB_HOST:-}" ] && [ -n "${DB_PORT:-}" ] && [ -n "${DB_NAME:-}" ] && [ -n "${DB_USER:-}" ]; then
  printf "[mcp] url=mysql://%s:%s@%s:%s/%s\n" "$DB_USER" "$MASKED_PASS" "$DB_HOST" "$DB_PORT" "$DB_NAME" 1>&2
else
  printf "[mcp] DATABASE_URL is set (hidden)\n" 1>&2
fi

# Optional: DB connectivity ping (uses mysql if available)
if command -v mysql >/dev/null 2>&1; then
  # Limit connect time so ping doesn't hang the startup
  if mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "${DB_USER:-root}" ${DB_PASS:+-p"${DB_PASS}"} -D "${DB_NAME:-moontrader}" -e "SELECT 1" >/dev/null 2>&1; then
    printf "[mcp] db ping: OK\n" 1>&2
  else
    printf "[mcp] db ping: FAILED (check MySQL/MariaDB is running and credentials)\n" 1>&2
  fi
else
  printf "[mcp] mysql client not found; skipping db ping\n" 1>&2
fi

# Exec the server via npx, passing DATABASE_URL as a positional argument
exec npx -y "$PKG" "$DATABASE_URL"
