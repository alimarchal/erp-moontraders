# MCP Database Server Setup

This directory contains scripts to run Model Context Protocol (MCP) database servers for MoonTrader.

## Available Scripts

### MySQL/MariaDB Server
```bash
npm run mcp:mysql
# or
sh ./scripts/mcp-mysql.sh
```

**Package**: `@berthojoris/mcp-mysql-server@1.4.5`
- ✅ Active maintenance (updated Nov 2025)
- ✅ Claude-specific support
- ✅ Dynamic per-project permissions
- ✅ Data export capabilities

### PostgreSQL Server
```bash
npm run mcp:postgres
# or
sh ./scripts/mcp-postgres.sh
```

**Package**: `@modelcontextprotocol/server-postgres@0.6.2`
- ⚠️ Deprecated but still functional
- Works with PostgreSQL databases

## Configuration

### Current Database (from .env)
The scripts automatically read your `.env` file and connect to:
- **Connection**: `${DB_CONNECTION}` (mariadb/mysql/pgsql)
- **Host**: `${DB_HOST}:${DB_PORT}`
- **Database**: `${DB_DATABASE}`
- **User**: `${DB_USERNAME}`

### Claude Code Configuration

Location: `~/.config/claude-code/config.json`

```json
{
  "mcpServers": {
    "moontrader-mysql": {
      "command": "sh",
      "args": ["/Users/alirazamarchal/Herd/moontrader/scripts/mcp-mysql.sh"]
    },
    "moontrader-postgres": {
      "command": "sh",
      "args": ["/Users/alirazamarchal/Herd/moontrader/scripts/mcp-postgres.sh"]
    }
  }
}
```

## Testing Connection

### Test MySQL Connection
```bash
mysql -h 127.0.0.1 -P 3306 -u root -p moontrader -e "SELECT 1"
```

### Test PostgreSQL Connection
```bash
psql -h 127.0.0.1 -p 5432 -U postgres -d moontrader -c "SELECT 1"
```

## Alternative MySQL MCP Servers

If you want to use a different MySQL server, set the environment variable:

```bash
# Use alternative server
export MYSQL_MCP_PACKAGE="@liangshanli/mcp-server-mysql"
npm run mcp:mysql
```

**Available options**:
- `@liangshanli/mcp-server-mysql` (v3.0.0) - DDL support, permission control
- `@sajithrw/mcp-mysql` (v1.0.0) - AWS RDS support
- `@nam088/mcp-database-server` (v1.0.10) - Multi-database support
- `@f4ww4z/mcp-mysql-server` (v0.1.0) - Basic operations

## What You Can Do With MCP

Once connected, Claude Code can:

1. **Query Database**
   - SELECT queries on any table
   - JOIN operations across tables
   - Aggregate queries (COUNT, SUM, AVG, etc.)

2. **Inspect Schema**
   - List all tables
   - Show table structure
   - View indexes and constraints
   - Check foreign key relationships

3. **Debug Data**
   - Check current stock levels
   - View recent GRNs
   - Inspect journal entries
   - Validate data integrity

4. **Generate Reports**
   - Inventory valuations
   - Outstanding supplier payments
   - Stock movement history
   - Promotional item tracking

## Troubleshooting

### Database Connection Failed

1. **Check database is running**:
   ```bash
   # For MySQL/MariaDB
   mysql -u root -p -e "STATUS"

   # For PostgreSQL
   pg_isready
   ```

2. **Verify credentials in .env**:
   ```bash
   cat .env | grep DB_
   ```

3. **Test connection manually** (see Testing Connection above)

### Package Installation Issues

The MCP servers are installed via `npx -y` which auto-installs on first run. If you encounter issues:

```bash
# Clear npm cache
npm cache clean --force

# Test manual installation
npx -y @berthojoris/mcp-mysql-server --help
```

### Permission Issues

Make scripts executable:
```bash
chmod +x scripts/*.sh
```

## Restart Required

After modifying `~/.config/claude-code/config.json`, **restart Claude Code** to load the MCP servers.

## Security Notes

- Scripts mask passwords in logs
- DATABASE_URL contains credentials - keep it secure
- MCP servers run locally and don't expose ports
- Communication happens via stdio (standard input/output)

## Support

For MCP server issues, check:
- MySQL Server: https://github.com/berthojoris/mcp-mysql-server
- PostgreSQL Server: https://modelcontextprotocol.io
- MCP Specification: https://modelcontextprotocol.io/docs
