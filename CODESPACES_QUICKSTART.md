# GitHub Codespaces Quick Start Guide

Welcome to MoonTrader ERP! This guide will help you get started with GitHub Codespaces in minutes.

## What is GitHub Codespaces?

GitHub Codespaces provides a complete, configurable development environment in the cloud. You can code, build, test, and deploy without installing anything on your local machine.

## Getting Started

### 1. Create a Codespace

1. Navigate to the [repository](https://github.com/alimarchal/erp-moontraders) on GitHub
2. Click the **Code** button (green button)
3. Select the **Codespaces** tab
4. Click **Create codespace on main** (or your preferred branch)

### 2. Wait for Setup

The first time you create a Codespace, it will:

- Build the Docker container (2-3 minutes)
- Install PHP, Composer, Node.js, and MySQL
- Run the post-create script which:
  - Installs Composer dependencies (~2 minutes)
  - Installs npm packages (~1-2 minutes)
  - Creates and configures .env file
  - Generates application key
  - Waits for MySQL to be ready
  - Runs database migrations
  - Builds frontend assets (~1 minute)

**Total setup time: 5-10 minutes** (only on first creation)

### 3. Access Your Application

Once setup is complete, you have several options:

#### Option A: Use Composer Dev Script (Recommended)

In the terminal, run:

```bash
composer dev
```

This starts:
- Laravel development server (port 8000)
- Queue worker
- Log viewer (pail)
- Vite dev server (port 5173)

#### Option B: Start Services Manually

```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Vite dev server
npm run dev

# Terminal 3 (optional): Queue worker
php artisan queue:listen

# Terminal 4 (optional): Logs
php artisan pail
```

### 4. Open the Application

When you run `php artisan serve` or `composer dev`, Codespaces will automatically detect port 8000 and show a notification. Click **Open in Browser** to access your application.

Or manually:
1. Click the **PORTS** tab in VS Code (bottom panel)
2. Find port 8000
3. Click the globe icon to open in browser

## Pre-installed VS Code Extensions

Your Codespace comes with these extensions ready to use:

**PHP & Laravel:**
- PHP Intelephense (code intelligence)
- Laravel Extra Intellisense
- Laravel Artisan
- Laravel Blade
- Blade Formatter

**Frontend:**
- Tailwind CSS IntelliSense
- Volar (Vue support)
- ESLint
- Prettier

**Developer Tools:**
- GitHub Copilot (if you have access)
- GitLens
- Docker
- DotEnv syntax highlighting

## Database Access

Your Codespace includes both MySQL 8.0 and PostgreSQL 16 databases pre-configured and ready to use. You can choose which one to use by configuring your `.env` file.

### Database Credentials

**MySQL:**
```
Host: mysql
Port: 3306
Database: moontrader
Username: moontrader
Password: secret

Root password: root
```

**PostgreSQL:**
```
Host: postgres
Port: 5432
Database: moontrader
Username: moontrader
Password: secret
```

### Accessing the Database

**MySQL from Terminal:**

```bash
# Connect to database
# Note: Password in command line is acceptable only in development environments
mysql -h mysql -u moontrader -psecret moontrader

# Run migrations
php artisan migrate

# Run seeders
php artisan db:seed

# Open Tinker
php artisan tinker
```

**PostgreSQL from Terminal:**

```bash
# Connect to database
PGPASSWORD=secret psql -h postgres -U moontrader moontrader

# Run migrations (after configuring .env for PostgreSQL)
php artisan migrate

# Run seeders
php artisan db:seed

# Open Tinker
php artisan tinker
```

### Switching Between Databases

Edit your `.env` file:

**For MySQL:**
```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
```

**For PostgreSQL:**
```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
```

Then clear config and run migrations:
```bash
php artisan config:clear
php artisan migrate
```

**From Host Machine (optional):**

If you want to connect from your local machine using a database client:

1. Go to the **PORTS** tab
2. Right-click on port 3306 (MySQL) or 5432 (PostgreSQL)
3. Select **Port Visibility** → **Public** (if needed)
4. Use the forwarded URL with the credentials above

## Common Commands

```bash
# Run tests
composer test
# or
php artisan test

# Run specific test
php artisan test --filter TestName

# Code formatting (Laravel Pint)
./vendor/bin/pint

# Clear caches
php artisan optimize:clear

# View logs
php artisan pail

# Check environment
bash .devcontainer/env-check.sh
```

## File Structure

```
/workspace/
├── app/                 # Application code
├── database/           # Migrations, seeders, factories
├── resources/          # Views, JS, CSS
├── routes/             # Route definitions
├── tests/              # Test files
├── .devcontainer/      # Codespace configuration
├── .env                # Environment variables (auto-generated)
├── composer.json       # PHP dependencies
└── package.json        # JavaScript dependencies
```

## Troubleshooting

### Setup Failed or Stuck

If the post-create script fails:

1. Check the terminal output for errors
2. Run the setup manually:
   ```bash
   composer install
   npm install
   cp .env.example .env
   php artisan key:generate
   php artisan migrate
   npm run build
   ```

### Database Connection Errors

If you get database connection errors:

1. Check if MySQL is running:
   ```bash
   mysql -h mysql -u moontrader -psecret -e "SELECT 1"
   ```

2. If MySQL is not ready, wait 30-60 seconds for it to start

3. Verify .env configuration:
   ```bash
   cat .env | grep DB_
   ```

### Port Already in Use

If you get "port already in use" errors:

1. Check what's running:
   ```bash
   # Check if lsof is available, otherwise use ss or netstat
   if command -v lsof >/dev/null 2>&1; then
       lsof -i :8000
   elif command -v ss >/dev/null 2>&1; then
       ss -ltnp | grep ':8000'
   elif command -v netstat >/dev/null 2>&1; then
       netstat -ltnp | grep ':8000'
   else
       echo "No suitable command found. Try: docker ps"
   fi
   ```

2. Stop the process or use a different port:
   ```bash
   php artisan serve --port=8001
   ```

### Running Environment Check

Run the environment check script to diagnose issues:

```bash
bash .devcontainer/env-check.sh
```

This will verify:
- PHP and required extensions
- Composer and npm
- Database connectivity
- .env configuration
- Application key
- Installed dependencies

## Tips & Tricks

### 1. Speed Up Rebuilds

Codespaces caches your container. To rebuild:
- Press `F1`
- Type "Rebuild Container"
- Select **Dev Containers: Rebuild Container**

### 2. Save Your Work

Codespaces auto-saves and syncs with GitHub. To manually commit:

```bash
git add .
git commit -m "Your message"
git push
```

### 3. Access from Multiple Devices

Your Codespace persists in the cloud. You can:
- Access it from any device with a browser
- Resume where you left off
- Close your browser and come back later

### 4. Stop Codespace to Save Resources

When you're done:
1. Go to https://github.com/codespaces
2. Find your codespace
3. Click the "..." menu
4. Select **Stop codespace**

Stopped Codespaces don't count against your usage quota.

### 5. Use VS Code Desktop

For better performance, use VS Code Desktop:
1. Install VS Code on your computer
2. Install the **GitHub Codespaces** extension
3. Connect to your Codespace from VS Code

## Resource Limits

Free tier includes:
- 120 core-hours per month
- 15 GB storage per month

Your Codespace uses:
- 2 cores by default
- Stops after 30 minutes of inactivity

**Tip:** Always stop your Codespace when not in use!

## Default Credentials

If you run the database seeder (`php artisan db:seed`), you may get default users:

```
Email: admin@example.com
Password: password
```

**Change these immediately in production!**

## Next Steps

Now that your environment is ready:

1. **Explore the codebase** - Start in `routes/web.php` to see available routes
2. **Run tests** - `composer test` to verify everything works
3. **Check documentation** - See README.md and other docs in the repo
4. **Start coding** - Make your changes and test them live

## Getting Help

- **Environment issues**: Check `.devcontainer/README.md`
- **Laravel questions**: See [Laravel Docs](https://laravel.com/docs)
- **Application features**: See main README.md
- **Codespaces issues**: See [GitHub Codespaces Docs](https://docs.github.com/en/codespaces)

## Clean Up

When you're completely done with a Codespace:

1. Go to https://github.com/codespaces
2. Find your codespace
3. Click the "..." menu
4. Select **Delete**

This frees up your storage quota.

---

**Happy Coding! 🚀**

Need help? Open an issue on GitHub or check the project documentation.
