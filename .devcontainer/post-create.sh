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
    
    # Update database configuration for docker
    sed -i 's/DB_HOST=127.0.0.1/DB_HOST=mysql/' .env
    sed -i 's/DB_DATABASE=moontrader/DB_DATABASE=moontrader/' .env
    sed -i 's/DB_USERNAME=root/DB_USERNAME=moontrader/' .env
    sed -i 's/DB_PASSWORD=/DB_PASSWORD=secret/' .env
else
    echo "✅ .env file already exists"
fi

# Generate application key
echo "🔑 Generating application key..."
php artisan key:generate --ansi

# Wait for MySQL to be ready
echo "⏳ Waiting for MySQL to be ready..."
max_attempts=30
attempt=0
until mysql -h mysql -u moontrader -psecret -e "SELECT 1" &> /dev/null || [ $attempt -eq $max_attempts ]; do
    attempt=$((attempt + 1))
    echo "   Attempt $attempt/$max_attempts..."
    sleep 2
done

if [ $attempt -eq $max_attempts ]; then
    echo "⚠️  Warning: Could not connect to MySQL. You may need to run migrations manually."
else
    echo "✅ MySQL is ready!"
    
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
echo "📝 Default credentials (if seeded):"
echo "   Email: admin@example.com"
echo "   Password: password"
echo ""
