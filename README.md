# MoonTrader

[![Open in GitHub Codespaces](https://github.com/codespaces/badge.svg)](https://codespaces.new/alimarchal/erp-moontraders)

A comprehensive inventory management system built with Laravel, designed for businesses to manage stock, suppliers, goods receipt notes (GRN), and promotional campaigns.

## Features

### Inventory Management
- **Goods Receipt Notes (GRN)** - Create, manage, and post GRNs with automatic inventory updates
- **Stock Batch Tracking** - FIFO/LIFO support with batch codes and expiry dates
- **Real-time Inventory** - Current stock levels by product and warehouse
- **Stock Movements** - Complete audit trail of all inventory transactions
- **Stock Valuation** - FIFO costing with valuation layers
- **Stock Ledger** - Running balance tracking for all stock movements

### Promotional Features
- **Promotional Campaigns** - Manage time-bound promotional offers
- **Priority-based Selling** - Urgent stock prioritization (promotional items first)
- **Special Pricing** - Set promotional prices at batch level
- **Must-sell-before Dates** - Track items that need to be sold urgently

### Financial Management
- **Journal Entries** - Automatic accounting integration for inventory transactions
- **Supplier Payments** - Track and allocate payments to GRNs
- **Cost Centers** - Associate transactions with cost centers
- **Accounting Periods** - Manage financial periods and reporting

### Additional Features
- **Multi-warehouse Support** - Manage inventory across multiple locations
- **Product Management** - Complete product catalog with UOM support
- **Supplier Management** - Comprehensive supplier database
- **Employee Management** - User and employee tracking with cost center associations
- **Activity Logging** - Complete audit trail using Spatie Activity Log
- **Role-based Permissions** - Granular access control using Spatie Permissions
- **PDF Generation** - Generate reports and documents with DomPDF

## Tech Stack

- **Framework**: Laravel 12
- **PHP**: 8.2+
- **Database**: MariaDB/MySQL
- **Frontend**:
  - Livewire 3 (reactive components)
  - Alpine.js (lightweight interactions)
  - TailwindCSS 4 (styling)
- **Authentication**: Laravel Jetstream with Sanctum
- **Key Packages**:
  - Spatie Laravel Activity Log
  - Spatie Laravel Permission
  - Spatie Laravel Query Builder
  - Laravel DomPDF
  - Custom ID Generator

## Requirements

- PHP 8.2 or higher
- Composer
- Node.js and npm
- MariaDB/MySQL
- Web server (Apache/Nginx) or Laravel Herd/Valet

## Installation

### Option 1: GitHub Codespaces (Recommended for Quick Start)

The easiest way to get started is using GitHub Codespaces, which provides a fully configured development environment in the cloud:

1. Click the **Code** button on GitHub
2. Select **Codespaces** tab
3. Click **Create codespace on main**
4. Wait for the environment to build (5-10 minutes first time)
5. Everything will be automatically set up and ready to use!

See [.devcontainer/README.md](.devcontainer/README.md) for detailed information about the dev container setup.

### Option 2: VS Code Dev Containers

If you prefer to work locally with Docker:

1. Install [Docker Desktop](https://www.docker.com/products/docker-desktop)
2. Install the [Dev Containers extension](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers)
3. Clone this repository
4. Open in VS Code and select **Reopen in Container**
5. Wait for the container to build and setup to complete

See [.devcontainer/README.md](.devcontainer/README.md) for more details.

### Option 3: Manual Installation

#### 1. Clone the Repository

```bash
git clone <repository-url>
cd moontrader
```

#### 2. Install Dependencies

```bash
composer install
npm install
```

#### 3. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` file and configure your database:

```env
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=moontrader
DB_USERNAME=root
DB_PASSWORD=
```

#### 4. Database Setup

```bash
php artisan migrate --seed
```

This will create all necessary tables and seed initial data including:
- Users and roles
- Sample products and suppliers
- Warehouses
- Cost centers
- Chart of accounts

#### 5. Build Assets

```bash
npm run build
```

#### 6. Start Development Server

#### Using Composer Script (Recommended)
```bash
composer dev
```

This runs Laravel server, queue worker, logs, and Vite concurrently.

#### Manual Start
```bash
php artisan serve
npm run dev
```

Visit `http://localhost:8000` in your browser.

## Quick Setup (One Command)

```bash
composer setup
```

This runs the complete setup: install dependencies, copy .env, generate key, migrate, and build assets.

## Usage

### Creating a Goods Receipt Note (GRN)

1. Navigate to `/goods-receipt-notes/create`
2. Select supplier and warehouse
3. Add products with quantities and costs
4. Set promotional prices and priorities (optional)
5. Save as draft
6. Click "Post to Inventory" to update stock

### Viewing Current Stock

1. Navigate to `/inventory/current-stock`
2. Filter by product, warehouse, or promotional items
3. Click "View Batches" to see detailed batch information

### Managing Promotional Items

During GRN creation, you can:
- Set promotional prices
- Assign priority (1-10 for urgent, 99 for normal)
- Set "Must Sell Before" dates
- Link to promotional campaigns

## Project Structure

```
moontrader/
├── app/
│   ├── Http/Controllers/       # Application controllers
│   ├── Models/                 # Eloquent models
│   ├── Services/               # Business logic services
│   │   └── InventoryService.php
│   └── ...
├── database/
│   ├── migrations/             # Database migrations
│   └── seeders/                # Database seeders
├── resources/
│   ├── views/                  # Blade templates
│   │   ├── goods-receipt-notes/
│   │   ├── inventory/
│   │   └── ...
│   └── js/                     # JavaScript assets
├── routes/
│   └── web.php                 # Application routes
├── scripts/                    # Utility scripts
└── public/                     # Public assets
```

## Key Models

- `GoodsReceiptNote` - GRN headers
- `GoodsReceiptNoteItem` - GRN line items
- `Product` - Product catalog
- `Supplier` - Supplier database
- `StockBatch` - Batch tracking
- `StockMovement` - Inventory transactions
- `StockLedgerEntry` - Running balance
- `StockValuationLayer` - FIFO costing
- `CurrentStock` - Real-time stock summary
- `PromotionalCampaign` - Promotional offers
- `JournalEntry` - Accounting integration

## Documentation

Additional documentation is available:

- [QUICKSTART.md](QUICKSTART.md) - Quick start guide for the inventory system
- [GRN_QUICK_START.md](GRN_QUICK_START.md) - GRN CRUD implementation guide
- [INVENTORY_GUIDE.md](INVENTORY_GUIDE.md) - Detailed inventory user guide
- [INVENTORY_IMPLEMENTATION_SUMMARY.md](INVENTORY_IMPLEMENTATION_SUMMARY.md) - Complete implementation details

## Testing

Run the test suite:

```bash
composer test
```

Or manually:

```bash
php artisan test
```

The project uses Pest PHP for testing.

## Development Commands

### Code Style
```bash
./vendor/bin/pint
```

### Clear Caches
```bash
php artisan optimize:clear
```

### View Logs
```bash
php artisan pail
```

### Queue Worker
```bash
php artisan queue:listen
```

## Database Schema

The system includes the following key tables:

**Inventory Tables:**
- `stock_batches` - Batch tracking with FIFO/LIFO
- `stock_movements` - All inventory transactions
- `stock_ledger_entries` - Running balance audit trail
- `stock_valuation_layers` - FIFO cost tracking
- `current_stock` - Real-time summary by product+warehouse
- `current_stock_by_batch` - Real-time summary by batch

**Core Tables:**
- `goods_receipt_notes` - GRN headers
- `goods_receipt_note_items` - GRN line items
- `products` - Product catalog
- `suppliers` - Supplier database
- `promotional_campaigns` - Promotional offers

**Financial Tables:**
- `journal_entries` - Accounting entries
- `journal_entry_details` - Journal entry lines
- `chart_of_accounts` - Accounting chart
- `accounting_periods` - Financial periods

## Security

- Role-based access control using Spatie Laravel Permission
- Activity logging for audit trails
- CSRF protection on all forms
- Secure authentication with Laravel Sanctum
- Database transaction safety for critical operations

## Important Notes

- GRNs become immutable after posting (cannot be edited or deleted)
- Stock movements maintain complete audit trails
- FIFO costing is automatically calculated
- Journal entries are created automatically when GRNs are posted
- Promotional items are prioritized in stock consumption

## Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details on:

- Setting up your development environment
- Coding standards and conventions
- Testing guidelines
- Pull request process

Quick start for contributors:
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run tests: `composer test`
5. Submit a pull request

## License

[Specify your license here]

## Support

For issues, questions, or contributions, please refer to the documentation files or contact the development team.

## Credits

Built with Laravel 12 and powered by modern web technologies.
