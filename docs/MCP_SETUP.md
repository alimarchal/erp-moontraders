# MCP setup for PostgreSQL (Node)

This workspace includes a `.vscode/settings.json` that auto-starts a PostgreSQL MCP server for both Claude and Continue VS Code extensions using Node (stdio transport). It launches `scripts/mcp-postgres.sh`, which reads your `.env` and execs the server so no secrets live in settings.

## 1) Choose an MCP Postgres server package

Use a maintained Node-based Postgres MCP server. The launcher defaults to:

- `@modelcontextprotocol/server-postgres` (example/placeholder name)

If you use a different package, set env var `POSTGRES_MCP_PACKAGE` (user-level settings or terminal) or edit the launcher to your preferred package.

## 2) Configure your database URL

No change needed if your `.env` already has the standard Laravel DB variables:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=moontrader
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

The launcher reads `.env` and, if `DATABASE_URL` is not set, builds it as:

```
postgres://DB_USERNAME:DB_PASSWORD@DB_HOST:DB_PORT/DB_DATABASE
```

Tip: If your password contains special characters, URL-encode it or set `DATABASE_URL` explicitly in `.env`.

## 3) Select/override the server package (optional)

The launcher defaults to `@modelcontextprotocol/server-postgres`. To use a different package, set:

```
POSTGRES_MCP_PACKAGE=your-package-name
```

You can export this in your shell or user settings environment if your client supports it.

## 4) Optional: run manually

You can also run the server directly in a terminal for troubleshooting:

```bash
# Uses .env automatically and execs the configured package
sh ./scripts/mcp-postgres.sh
```

## 5) Verify connection in VS Code

- Claude for VS Code:
  - Command Palette → "Claude: List MCP Servers" (should show db-postgres as connected)
  - View → Output → select "Claude"; look for handshake success
- Continue:
  - Command Palette → "Continue: Open Config"; review Servers section
  - View → Output → select "Continue"; look for connection logs

## Notes
- Do not commit real credentials. Your `.env` remains untracked by git.
- If your password contains special characters, consider using `DATABASE_URL` directly in `.env` with proper URL-encoding.
- If you need help selecting a specific MCP Postgres server package, ping in chat and I’ll wire it up precisely.
