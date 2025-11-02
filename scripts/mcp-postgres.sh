#!/usr/bin/env sh
# Lightweight launcher for a Postgres MCP server using Node and stdio.
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

# If DATABASE_URL not set, build it for Postgres from Laravel DB_* vars
if [ -z "${DATABASE_URL:-}" ]; then
  DB_HOST="${DB_HOST:-127.0.0.1}"
  DB_PORT="${DB_PORT:-5432}"
  DB_NAME="${DB_DATABASE:-postgres}"
  DB_USER="${DB_USERNAME:-postgres}"
  DB_PASS="${DB_PASSWORD:-}"
  # NOTE: If your password contains special characters, URL-encode it.
  DATABASE_URL="postgres://${DB_USER}:${DB_PASS}@${DB_HOST}:${DB_PORT}/${DB_NAME}"
  export DATABASE_URL
fi

# Allow package override via env var; default to a common package name placeholder
PKG="${POSTGRES_MCP_PACKAGE:-@modelcontextprotocol/server-postgres}"

# Log startup info (mask password) for debugging
MASKED_PASS="****"
printf "[mcp] package=%s\n" "$PKG" 1>&2
if [ -n "${DB_HOST:-}" ] && [ -n "${DB_PORT:-}" ] && [ -n "${DB_NAME:-}" ] && [ -n "${DB_USER:-}" ]; then
  printf "[mcp] url=postgres://%s:%s@%s:%s/%s\n" "$DB_USER" "$MASKED_PASS" "$DB_HOST" "$DB_PORT" "$DB_NAME" 1>&2
else
  printf "[mcp] DATABASE_URL is set (hidden)\n" 1>&2
fi

# Optional: DB connectivity ping (uses psql if available)
if command -v psql >/dev/null 2>&1; then
  # Limit connect time so ping doesn't hang the startup
  if PGCONNECT_TIMEOUT=2 PGPASSWORD="${DB_PASS:-}" psql -h "${DB_HOST:-127.0.0.1}" -p "${DB_PORT:-5432}" -U "${DB_USER:-postgres}" -d "${DB_NAME:-postgres}" -tAc "select 1" >/dev/null 2>&1; then
    printf "[mcp] db ping: OK\n" 1>&2
  else
    printf "[mcp] db ping: FAILED (check Postgres is running and credentials)\n" 1>&2
  fi
else
  printf "[mcp] psql not found; skipping db ping\n" 1>&2
fi

# Exec the server via npx, passing DATABASE_URL as a positional argument for packages that require it
exec npx -y "$PKG" "$DATABASE_URL"
