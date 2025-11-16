#!/bin/bash
set -e

echo "🚀 Starting post-create setup for MoonTrader ERP..."

# Navigate to workspace
cd /workspace

# Install Composer dependencies
echo "📦 Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader

# Install npm dependencies
echo "📦 Installing npm dependencies..."
npm install

# Setup environment file
if [ ! -f .env ]; then
    echo "⚙️  Creating .env file from .env.example..."
    cp .env.example .env
    
    # Note: Database configuration is left as-is in .env.example
    # Users can manually configure their preferred database (MySQL, MariaDB, or PostgreSQL)
    echo "📝 .env created from .env.example"
    echo "   You can manually configure database settings:"
    echo "   - For MySQL: DB_CONNECTION=mysql, DB_HOST=mysql, DB_PORT=3306"
    echo "   - For PostgreSQL: DB_CONNECTION=pgsql, DB_HOST=postgres, DB_PORT=5432"
else
    echo "✅ .env file already exists"
fi

# Generate application key
echo "🔑 Generating application key..."
php artisan key:generate --ansi

# Detect database configuration from .env
DB_CONNECTION=$(grep "^DB_CONNECTION=" .env | cut -d '=' -f2 | tr -d '\r')
DB_HOST=$(grep "^DB_HOST=" .env | cut -d '=' -f2 | tr -d '\r')
DB_PORT=$(grep "^DB_PORT=" .env | cut -d '=' -f2 | tr -d '\r')
DB_USERNAME=$(grep "^DB_USERNAME=" .env | cut -d '=' -f2 | tr -d '\r')
DB_PASSWORD=$(grep "^DB_PASSWORD=" .env | cut -d '=' -f2 | tr -d '\r')

echo "📊 Detected database configuration:"
echo "   Connection: $DB_CONNECTION"
echo "   Host: $DB_HOST"
echo "   Port: $DB_PORT"

# Wait for database to be ready
echo "⏳ Waiting for database to be ready..."
max_attempts=30
attempt=0

if [[ "$DB_CONNECTION" == "pgsql" ]]; then
    # Wait for PostgreSQL
    # Note: PGPASSWORD environment variable is acceptable for development containers only
    until PGPASSWORD=$DB_PASSWORD psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d postgres -c "SELECT 1" &> /dev/null || [ $attempt -eq $max_attempts ]; do
        attempt=$((attempt + 1))
        echo "   Attempt $attempt/$max_attempts..."
        sleep 2
    done
elif [[ "$DB_CONNECTION" == "mysql" || "$DB_CONNECTION" == "mariadb" ]]; then
    # Wait for MySQL/MariaDB
    # Note: Password in command line is acceptable for development containers only
    until mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "SELECT 1" &> /dev/null || [ $attempt -eq $max_attempts ]; do
        attempt=$((attempt + 1))
        echo "   Attempt $attempt/$max_attempts..."
        sleep 2
    done
else
    echo "⚠️  Unknown database connection type: $DB_CONNECTION"
    echo "   Skipping database connectivity check"
fi

if [ $attempt -eq $max_attempts ]; then
    echo "⚠️  Warning: Could not connect to database. You may need to:"
    echo "   1. Check your .env database settings"
    echo "   2. Ensure the database service is running"
    echo "   3. Run migrations manually: php artisan migrate"
else
    echo "✅ Database is ready!"
    
    # Run migrations
    echo "🗄️  Running database migrations..."
    php artisan migrate --force --ansi || echo "⚠️  Migrations failed - may need to run manually"
    
    # Run seeders (optional - uncomment if needed)
    # echo "🌱 Running database seeders..."
    # php artisan db:seed --force --ansi || echo "⚠️  Seeders failed - may need to run manually"
fi

# Create storage symlink
echo "🔗 Creating storage symlink..."
php artisan storage:link --ansi || echo "✅ Storage link already exists"

# Set proper permissions
echo "🔒 Setting proper permissions..."
chmod -R 775 storage bootstrap/cache

# Build assets
echo "🎨 Building frontend assets..."
npm run build

echo ""
echo "✨ Setup complete! You can now run:"
echo "   composer dev       - Start development server with queue, logs, and Vite"
echo "   composer test      - Run tests"
echo "   php artisan serve  - Start Laravel server only"
echo ""
echo "📝 Database services available:"
echo "   MySQL:      mysql:3306 (user: moontrader, password: secret)"
echo "   PostgreSQL: postgres:5432 (user: moontrader, password: secret)"
echo ""
echo "💡 Configure your preferred database in .env file"
echo ""

