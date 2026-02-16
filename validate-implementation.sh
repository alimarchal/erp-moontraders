#!/bin/bash

# Product Recall Implementation Validation Script
# This script validates the implementation against the plan

echo "=================================="
echo "Product Recall Implementation Validation"
echo "=================================="
echo ""

# Check Phase 1: Stock Adjustment Foundation
echo "Phase 1: Stock Adjustment Foundation"
echo "-------------------------------------"

# Models
if [ -f "app/Models/StockAdjustment.php" ]; then
    echo "✓ StockAdjustment model exists"
else
    echo "✗ StockAdjustment model missing"
fi

if [ -f "app/Models/StockAdjustmentItem.php" ]; then
    echo "✓ StockAdjustmentItem model exists"
else
    echo "✗ StockAdjustmentItem model missing"
fi

# Migrations
if [ -f "database/migrations/2026_02_16_000005_extend_stock_adjustments_table.php" ]; then
    echo "✓ Stock adjustments migration exists"
else
    echo "✗ Stock adjustments migration missing"
fi

if [ -f "database/migrations/2026_02_16_000006_extend_stock_adjustment_items_table.php" ]; then
    echo "✓ Stock adjustment items migration exists"
else
    echo "✗ Stock adjustment items migration missing"
fi

# Service
if [ -f "app/Services/StockAdjustmentService.php" ]; then
    echo "✓ StockAdjustmentService exists"
else
    echo "✗ StockAdjustmentService missing"
fi

# Controller
if [ -f "app/Http/Controllers/StockAdjustmentController.php" ]; then
    echo "✓ StockAdjustmentController exists"
else
    echo "✗ StockAdjustmentController missing"
fi

# Seeder
if [ -f "database/seeders/RecallAccountsSeeder.php" ]; then
    echo "✓ RecallAccountsSeeder exists"
else
    echo "✗ RecallAccountsSeeder missing"
fi

# Tests
if [ -f "tests/Feature/StockAdjustmentTest.php" ]; then
    echo "✓ StockAdjustmentTest exists"
else
    echo "✗ StockAdjustmentTest missing"
fi

# Factory
if [ -f "database/factories/StockAdjustmentFactory.php" ]; then
    echo "✓ StockAdjustmentFactory exists"
else
    echo "✗ StockAdjustmentFactory missing"
fi

echo ""
echo "Phase 2: Product Recall Feature"
echo "--------------------------------"

# Models
if [ -f "app/Models/ProductRecall.php" ]; then
    echo "✓ ProductRecall model exists"
else
    echo "✗ ProductRecall model missing"
fi

if [ -f "app/Models/ProductRecallItem.php" ]; then
    echo "✓ ProductRecallItem model exists"
else
    echo "✗ ProductRecallItem model missing"
fi

# Migrations
if [ -f "database/migrations/2026_02_16_000003_create_product_recalls_table.php" ]; then
    echo "✓ Product recalls migration exists"
else
    echo "✗ Product recalls migration missing"
fi

if [ -f "database/migrations/2026_02_16_000004_create_product_recall_items_table.php" ]; then
    echo "✓ Product recall items migration exists"
else
    echo "✗ Product recall items migration missing"
fi

# Service
if [ -f "app/Services/ProductRecallService.php" ]; then
    echo "✓ ProductRecallService exists"
else
    echo "✗ ProductRecallService missing"
fi

# Controller
if [ -f "app/Http/Controllers/ProductRecallController.php" ]; then
    echo "✓ ProductRecallController exists"
else
    echo "✗ ProductRecallController missing"
fi

# Tests
if [ -f "tests/Feature/ProductRecallTest.php" ]; then
    echo "✓ ProductRecallTest exists"
else
    echo "✗ ProductRecallTest missing"
fi

# Factory
if [ -f "database/factories/ProductRecallFactory.php" ]; then
    echo "✓ ProductRecallFactory exists"
else
    echo "✗ ProductRecallFactory missing"
fi

# Permission Seeder
if [ -f "database/seeders/RecallPermissionsSeeder.php" ]; then
    echo "✓ RecallPermissionsSeeder exists"
else
    echo "✗ RecallPermissionsSeeder missing"
fi

echo ""
echo "Phase 3: Views & UI"
echo "-------------------"

# Stock Adjustment Views
for view in index show create edit; do
    if [ -f "resources/views/stock-adjustments/${view}.blade.php" ]; then
        echo "✓ stock-adjustments/${view}.blade.php exists"
    else
        echo "✗ stock-adjustments/${view}.blade.php missing"
    fi
done

# Product Recall Views
for view in index show create edit; do
    if [ -f "resources/views/product-recalls/${view}.blade.php" ]; then
        echo "✓ product-recalls/${view}.blade.php exists"
    else
        echo "✗ product-recalls/${view}.blade.php missing"
    fi
done

echo ""
echo "Phase 4: Documentation"
echo "----------------------"

if [ -f "PRODUCT_RECALL_IMPLEMENTATION_COMPLETE.md" ]; then
    echo "✓ Implementation complete documentation exists"
else
    echo "✗ Implementation complete documentation missing"
fi

echo ""
echo "Database Compatibility Checks"
echo "-----------------------------"

# Check for MySQL-specific syntax
offending_migrations=()
for migration in database/migrations/2026_02_16_*.php; do
    [ -f "$migration" ] || continue
    if grep -qE "MODIFY|CAST.*AS UNSIGNED" "$migration"; then
        if ! grep -q "getDriverName" "$migration"; then
            offending_migrations+=("$migration")
        fi
    fi
done

if [ ${#offending_migrations[@]} -gt 0 ]; then
    echo "⚠ Found database-specific SQL without driver checks in:"
    for migration in "${offending_migrations[@]}"; do
        echo "  - $migration"
    done
else
    echo "✓ Migrations are database-agnostic"
fi

# Check for orderByRaw with database-specific functions
if grep -r "orderByRaw.*CAST\|orderByRaw.*SUBSTRING" app/Services/StockAdjustmentService.php app/Services/ProductRecallService.php 2>/dev/null | grep -v "^$" >/dev/null 2>&1; then
    echo "⚠ Found database-specific orderByRaw in new services"
else
    echo "✓ No database-specific raw queries in new services"
fi

echo ""
echo "Code Quality Checks"
echo "-------------------"

# Check PHP syntax
php_files=$(find app/Models app/Services app/Http/Controllers -name "*.php" | grep -E "(StockAdjustment|ProductRecall)")
syntax_errors=0
for file in $php_files; do
    if ! php -l "$file" > /dev/null 2>&1; then
        echo "✗ Syntax error in $file"
        syntax_errors=$((syntax_errors + 1))
    fi
done

if [ $syntax_errors -eq 0 ]; then
    echo "✓ No PHP syntax errors"
fi

echo ""
echo "=================================="
echo "Validation Complete"
echo "=================================="
