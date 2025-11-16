# GitHub Codespaces / Dev Container Setup

This directory contains the configuration files for GitHub Codespaces and VS Code Dev Containers.

## What's Included

This dev container includes:

- **PHP 8.3** with all required extensions (PDO, MySQL, PostgreSQL, GD, Zip, etc.)
- **Composer** - Latest version
- **Node.js 20** with npm
- **MySQL 8.0** - Pre-configured database server
- **Git & GitHub CLI** - For version control
- **VS Code Extensions** - Laravel, PHP, Tailwind CSS, and more

## Quick Start

### Using GitHub Codespaces

1. Click the **Code** button on GitHub
2. Select **Codespaces** tab
3. Click **Create codespace on [branch]**
4. Wait for the environment to build (first time takes 5-10 minutes)
5. Once ready, the setup script will automatically:
   - Install all Composer dependencies
   - Install all npm packages
   - Create `.env` file with proper database settings
   - Generate application key
   - Run database migrations
   - Build frontend assets

### Using VS Code Dev Containers Locally

1. Install [Docker Desktop](https://www.docker.com/products/docker-desktop)
2. Install the [Dev Containers extension](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers) for VS Code
3. Open this project in VS Code
4. Press `F1` and select **Dev Containers: Reopen in Container**
5. Wait for the container to build and setup to complete

## Configuration Details

### Services

- **app**: Main PHP application container (PHP 8.3)
- **mysql**: MySQL 8.0 database server
- **postgres**: PostgreSQL 16 database server

Both database servers run simultaneously. You can choose which one to use by configuring your `.env` file.

### Database Configuration

The dev container includes both MySQL and PostgreSQL. You can choose your preferred database by editing the `.env` file:

**For MySQL/MariaDB:**
```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=moontrader
DB_USERNAME=moontrader
DB_PASSWORD=secret
```

**For PostgreSQL:**
```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=moontrader
DB_USERNAME=moontrader
DB_PASSWORD=secret
```

**Database Credentials:**
- MySQL root password: `root`
- PostgreSQL credentials: username `moontrader`, password `secret`

### Ports

The following ports are automatically forwarded:

- **8000**: Laravel development server
- **5173**: Vite development server (HMR)
- **3306**: MySQL server
- **5432**: PostgreSQL server

## Running the Application

Once the container is ready, you can run:

```bash
# Start all development services (server, queue, logs, Vite)
composer dev

# Or start services individually:
php artisan serve              # Laravel server on port 8000
npm run dev                    # Vite dev server with HMR
php artisan queue:listen       # Queue worker
php artisan pail              # Log viewer
```

## Database Access

### From Within Container

**MySQL:**
```bash
# Using artisan tinker
php artisan tinker

# Using MySQL CLI
mysql -h mysql -u moontrader -psecret moontrader

# Run migrations
php artisan migrate

# Run seeders
php artisan db:seed
```

**PostgreSQL:**
```bash
# Using artisan tinker
php artisan tinker

# Using PostgreSQL CLI
PGPASSWORD=secret psql -h postgres -U moontrader moontrader

# Run migrations
php artisan migrate

# Run seeders
php artisan db:seed
```

### From Host Machine

**MySQL:**

Connect to `localhost:3306` with credentials:
- **Username**: `moontrader`
- **Password**: `secret`
- **Database**: `moontrader`

Or as root:
- **Username**: `root`
- **Password**: `root`
- **Database**: `moontrader`

**PostgreSQL:**

Connect to `localhost:5432` with credentials:
- **Username**: `moontrader`
- **Password**: `secret`
- **Database**: `moontrader`

## VS Code Extensions

The following extensions are automatically installed:

**PHP Development:**
- PHP Intelephense
- PHP Namespace Resolver
- Laravel Extra Intellisense
- Laravel Artisan
- Laravel Blade
- Blade Formatter

**Frontend:**
- Tailwind CSS IntelliSense
- Volar (Vue)
- ESLint
- Prettier

**General:**
- GitHub Copilot
- GitLens
- Docker
- DotEnv

## Troubleshooting

### Container Won't Start

1. Check Docker is running
2. Try rebuilding the container:
   - `F1` → **Dev Containers: Rebuild Container**

### Database Connection Issues

1. Wait for the database to fully start (takes 30-60 seconds)
2. Check which database you're using in `.env` file
3. Check logs:
   ```bash
   # For MySQL
   docker-compose logs mysql
   
   # For PostgreSQL
   docker-compose logs postgres
   ```
4. Manually test connection:
   ```bash
   # MySQL
   mysql -h mysql -u moontrader -psecret -e "SELECT 1"
   
   # PostgreSQL
   PGPASSWORD=secret psql -h postgres -U moontrader -c "SELECT 1"
   ```

### Switching Databases

To switch between MySQL and PostgreSQL:

1. Edit your `.env` file and change the database configuration:
   ```bash
   # For MySQL
   DB_CONNECTION=mysql
   DB_HOST=mysql
   DB_PORT=3306
   
   # For PostgreSQL
   DB_CONNECTION=pgsql
   DB_HOST=postgres
   DB_PORT=5432
   ```

2. Clear configuration cache:
   ```bash
   php artisan config:clear
   ```

3. Run migrations on the new database:
   ```bash
   php artisan migrate
   ```

### Migrations Fail

If migrations fail during setup, run them manually:

```bash
php artisan migrate --force
```

### Permission Issues

If you encounter permission issues with storage or cache:

```bash
chmod -R 775 storage bootstrap/cache
```

## Manual Setup

If the automatic setup fails, you can run these commands manually:

```bash
# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Configure database in .env (choose MySQL or PostgreSQL)
# For MySQL: DB_CONNECTION=mysql, DB_HOST=mysql, DB_PORT=3306
# For PostgreSQL: DB_CONNECTION=pgsql, DB_HOST=postgres, DB_PORT=5432
# Set DB_USERNAME=moontrader and DB_PASSWORD=secret

# Run migrations
php artisan migrate --force

# Build assets
npm run build
```

## Customization

### Adding PHP Extensions

Edit `.devcontainer/Dockerfile` and add to the `docker-php-ext-install` line:

```dockerfile
RUN docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd zip your-extension
```

### Adding VS Code Extensions

Edit `.devcontainer/devcontainer.json` in the `extensions` array:

```json
"extensions": [
  "your.extension-id"
]
```

### Changing Database

Both MySQL and PostgreSQL are already included and running. To switch databases:

1. Edit your `.env` file:
   - For MySQL: `DB_CONNECTION=mysql`, `DB_HOST=mysql`, `DB_PORT=3306`
   - For PostgreSQL: `DB_CONNECTION=pgsql`, `DB_HOST=postgres`, `DB_PORT=5432`

2. Clear config cache: `php artisan config:clear`

3. Run migrations: `php artisan migrate`

No need to rebuild the container!

## Performance

The dev container uses volume caching for better performance:

```yaml
volumes:
  - ../:/workspace:cached
```

This significantly improves file I/O performance, especially on macOS and Windows.

## Persistent Data

Database data is stored in named volumes that persist even if you rebuild the container:
- `mysql-data` - MySQL database
- `postgres-data` - PostgreSQL database

To reset a database:

```bash
# Stop containers
docker-compose down

# Remove the MySQL volume
docker volume rm devcontainer_mysql-data

# Or remove the PostgreSQL volume
docker volume rm devcontainer_postgres-data

# Restart
docker-compose up -d
```

## Resources

- [VS Code Dev Containers](https://code.visualstudio.com/docs/devcontainers/containers)
- [GitHub Codespaces](https://docs.github.com/en/codespaces)
- [Laravel Documentation](https://laravel.com/docs)
- [Docker Documentation](https://docs.docker.com/)

## Support

For issues specific to the dev container setup, please check:

1. Docker Desktop is running and up-to-date
2. VS Code Dev Containers extension is installed
3. You have enough disk space (at least 10GB free)
4. Your Docker daemon has enough resources allocated (4GB RAM minimum)
