#!/bin/bash

# MoonTrader ERP - Environment Check Script
# This script verifies that all required tools and services are properly installed

echo "🔍 MoonTrader ERP - Environment Check"
echo "======================================"
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Track if any checks fail
FAILED=0

# Function to check command
check_command() {
    if command -v $1 &> /dev/null; then
        echo -e "${GREEN}✓${NC} $1 is installed"
        $1 --version | head -n 1 | sed 's/^/  /'
        return 0
    else
        echo -e "${RED}✗${NC} $1 is NOT installed"
        FAILED=1
        return 1
    fi
}

# Check PHP
echo "Checking PHP..."
check_command php
if command -v php &> /dev/null; then
    echo "  Checking required PHP extensions..."
    
    for ext in pdo pdo_mysql mbstring xml zip bcmath gd; do
        if php -m | grep -q "^$ext$"; then
            echo -e "  ${GREEN}✓${NC} $ext extension"
        else
            echo -e "  ${RED}✗${NC} $ext extension is missing"
            FAILED=1
        fi
    done
fi
echo ""

# Check Composer
echo "Checking Composer..."
check_command composer
echo ""

# Check Node.js
echo "Checking Node.js..."
check_command node
echo ""

# Check npm
echo "Checking npm..."
check_command npm
echo ""

# Check Git
echo "Checking Git..."
check_command git
echo ""

# Check database clients
echo "Checking Database Clients..."
check_command mysql || echo -e "  ${YELLOW}ℹ${NC} MySQL client not required if using docker"
echo ""

# Check if .env exists
echo "Checking Laravel Configuration..."
if [ -f .env ]; then
    echo -e "${GREEN}✓${NC} .env file exists"
    
    # Check database configuration
    if grep -q "DB_HOST=mysql" .env; then
        echo -e "  ${GREEN}✓${NC} Database host configured for docker"
    elif grep -q "DB_HOST=127.0.0.1" .env; then
        echo -e "  ${YELLOW}ℹ${NC} Database host set to localhost"
    fi
else
    echo -e "${RED}✗${NC} .env file does NOT exist"
    echo "  Run: cp .env.example .env"
    FAILED=1
fi
echo ""

# Check if vendor directory exists
echo "Checking Dependencies..."
if [ -d vendor ]; then
    echo -e "${GREEN}✓${NC} Composer dependencies installed"
else
    echo -e "${RED}✗${NC} Composer dependencies NOT installed"
    echo "  Run: composer install"
    FAILED=1
fi

if [ -d node_modules ]; then
    echo -e "${GREEN}✓${NC} npm dependencies installed"
else
    echo -e "${RED}✗${NC} npm dependencies NOT installed"
    echo "  Run: npm install"
    FAILED=1
fi
echo ""

# Check if we can connect to database (if in docker environment)
echo "Checking Database Connection..."
if command -v mysql &> /dev/null; then
    # Try to determine DB host from .env
    if [ -f .env ]; then
        DB_HOST=$(grep "^DB_HOST=" .env | cut -d '=' -f2)
        DB_USER=$(grep "^DB_USERNAME=" .env | cut -d '=' -f2)
        DB_PASS=$(grep "^DB_PASSWORD=" .env | cut -d '=' -f2)
        DB_NAME=$(grep "^DB_DATABASE=" .env | cut -d '=' -f2)
        
        if [ "$DB_HOST" = "mysql" ]; then
            # Docker environment
            if mysql -h mysql -u "$DB_USER" -p"$DB_PASS" -e "SELECT 1" &> /dev/null; then
                echo -e "${GREEN}✓${NC} Can connect to database"
                mysql -h mysql -u "$DB_USER" -p"$DB_PASS" -e "SELECT VERSION()" | tail -n 1 | sed 's/^/  MySQL: /'
            else
                echo -e "${YELLOW}⚠${NC} Cannot connect to database (may not be started yet)"
                echo "  If using docker-compose, run: docker-compose up -d"
            fi
        else
            echo -e "${YELLOW}ℹ${NC} Skipping database connection test for non-docker host"
        fi
    fi
else
    echo -e "${YELLOW}ℹ${NC} MySQL client not available, skipping database check"
fi
echo ""

# Check if application key is set
echo "Checking Application Key..."
if [ -f .env ]; then
    if grep -q "^APP_KEY=base64:" .env; then
        echo -e "${GREEN}✓${NC} Application key is set"
    else
        echo -e "${RED}✗${NC} Application key is NOT set"
        echo "  Run: php artisan key:generate"
        FAILED=1
    fi
fi
echo ""

# Summary
echo "======================================"
if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All checks passed!${NC}"
    echo ""
    echo "You can now run:"
    echo "  composer dev       - Start development server"
    echo "  php artisan serve  - Start Laravel server only"
    echo "  npm run dev        - Start Vite dev server"
    echo "  composer test      - Run tests"
    exit 0
else
    echo -e "${RED}✗ Some checks failed${NC}"
    echo ""
    echo "Please fix the issues above before continuing."
    echo "For a fresh setup, run: composer setup"
    exit 1
fi
