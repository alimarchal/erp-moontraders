# Contributing to MoonTrader ERP

Thank you for your interest in contributing to MoonTrader ERP! This document provides guidelines and setup instructions for developers.

## Development Environment Setup

We support three ways to set up your development environment:

### 1. GitHub Codespaces (Recommended for New Contributors)

**Best for:** Quick start, no local setup required, consistent environment

1. Click the "Open in GitHub Codespaces" badge in README.md
2. Wait 5-10 minutes for automatic setup
3. Start coding immediately!

See [CODESPACES_QUICKSTART.md](CODESPACES_QUICKSTART.md) for detailed instructions.

**Pros:**
- Zero local installation required
- Consistent environment for all developers
- Pre-configured with all tools and extensions
- Works on any device with a browser

**Cons:**
- Requires internet connection
- Limited by GitHub free tier (120 core-hours/month)

### 2. VS Code Dev Containers (Recommended for Regular Contributors)

**Best for:** Working offline, better performance, unlimited usage

**Prerequisites:**
- Docker Desktop installed
- VS Code with Dev Containers extension

**Setup:**
```bash
git clone https://github.com/alimarchal/erp-moontraders.git
cd erp-moontraders
code .
# Press F1 → "Dev Containers: Reopen in Container"
```

See [.devcontainer/README.md](.devcontainer/README.md) for detailed instructions.

**Pros:**
- Works offline
- Better performance than Codespaces
- No usage limits
- Same consistent environment

**Cons:**
- Requires Docker Desktop (large download)
- Uses local disk space
- Initial setup takes longer

### 3. Manual Setup (For Advanced Users)

**Best for:** Custom configurations, non-Docker environments

**Prerequisites:**
- PHP 8.2 or higher
- Composer
- Node.js 20+ and npm
- MySQL/MariaDB
- Git

**Setup:**
```bash
# Clone repository
git clone https://github.com/alimarchal/erp-moontraders.git
cd erp-moontraders

# Quick setup (all-in-one)
composer setup

# Or manual step-by-step
composer install
npm install
cp .env.example .env
php artisan key:generate
# Edit .env with your database credentials
php artisan migrate --seed
npm run build
```

**Pros:**
- Maximum control and customization
- Use your preferred tools
- No Docker required

**Cons:**
- More setup steps
- Environment may differ from others
- Potential compatibility issues

## Verifying Your Setup

After setup, verify everything works:

```bash
# Check environment (if using devcontainer)
bash .devcontainer/env-check.sh

# Run tests
composer test

# Start development server
composer dev
```

Visit http://localhost:8000 to see the application.

## Development Workflow

### 1. Create a Branch

```bash
git checkout -b feature/your-feature-name
# or
git checkout -b fix/your-bug-fix
```

Branch naming conventions:
- `feature/description` - New features
- `fix/description` - Bug fixes
- `docs/description` - Documentation changes
- `refactor/description` - Code refactoring
- `test/description` - Adding tests

### 2. Make Your Changes

Follow Laravel 11+ conventions:
- Use service layer pattern for business logic
- Follow existing code style
- Add tests for new features
- Update documentation if needed

### 3. Run Tests

```bash
# Run all tests
composer test

# Run specific test file
php artisan test tests/Feature/YourTest.php

# Run specific test method
php artisan test --filter test_method_name
```

### 4. Code Formatting

```bash
# Format code with Laravel Pint
./vendor/bin/pint

# Or check without fixing
./vendor/bin/pint --test
```

### 5. Commit Changes

Follow conventional commit format:

```bash
git add .
git commit -m "feat: add new feature description"
# or
git commit -m "fix: resolve bug description"
# or
git commit -m "docs: update documentation"
```

Commit types:
- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation only
- `style:` - Code style changes (formatting)
- `refactor:` - Code refactoring
- `test:` - Adding tests
- `chore:` - Maintenance tasks

### 6. Push and Create Pull Request

```bash
git push origin feature/your-feature-name
```

Then create a Pull Request on GitHub with:
- Clear description of changes
- Reference any related issues
- Include screenshots for UI changes
- Add test results if applicable

## Coding Standards

### Laravel 11+ Specific Guidelines

1. **Use Artisan Commands** for file generation
   ```bash
   php artisan make:model YourModel
   php artisan make:controller YourController
   php artisan make:migration create_your_table
   ```

2. **Service Layer Pattern** - Business logic in services
   ```php
   // app/Services/YourService.php
   class YourService {
       public function doSomething() {
           // Business logic here
       }
   }
   ```

3. **No Comments** unless explaining non-obvious logic
   - Write self-documenting code
   - Use descriptive variable and method names

4. **Type Hints** - Always use PHP 8.2+ features
   ```php
   public function example(string $name, int $age): bool
   {
       return true;
   }
   ```

5. **Service Providers** - Register in `bootstrap/providers.php`

6. **Scheduled Tasks** - Define in `routes/console.php`

7. **Middleware** - Use class names, register in `bootstrap/app.php`

### Project-Specific Guidelines

1. **Accounting Operations** - Always use `AccountingService`
   ```php
   $result = app(AccountingService::class)->createJournalEntry($data);
   ```

2. **Never Edit Posted Entries** - Create reversing entries
   ```php
   $service->reverseJournalEntry($entryId);
   ```

3. **File Uploads** - Use `FileStorageHelper`
   ```php
   FileStorageHelper::storeFiles($files, $model);
   ```

4. **Validation** - Use Form Requests
   ```bash
   php artisan make:request YourRequest
   ```

5. **Testing** - Use Pest PHP
   ```php
   test('feature works correctly', function () {
       // Your test
   });
   ```

## Testing Guidelines

### Writing Tests

1. **Feature Tests** - Test user-facing features
   ```php
   test('user can create GRN', function () {
       $this->actingAs($user)
           ->post('/goods-receipt-notes', $data)
           ->assertSuccessful();
   });
   ```

2. **Unit Tests** - Test individual classes/methods
   ```php
   test('calculates balance correctly', function () {
       $service = new BalanceService();
       expect($service->calculate($account))->toBe(1000.00);
   });
   ```

3. **Database Tests** - Use `RefreshDatabase`
   ```php
   uses(RefreshDatabase::class);
   
   test('creates record in database', function () {
       // Test
   });
   ```

### Running Tests

```bash
# All tests
composer test

# Specific file
php artisan test tests/Feature/GoodsReceiptNoteTest.php

# With coverage (requires Xdebug)
php artisan test --coverage

# Parallel execution (faster)
php artisan test --parallel
```

## Database Migrations

### Creating Migrations

```bash
# Create table
php artisan make:migration create_your_table

# Add column
php artisan make:migration add_column_to_table --table=your_table

# Run migrations
php artisan migrate

# Rollback
php artisan migrate:rollback
```

### Important Notes

- Never edit existing migrations that have been committed
- Always test migrations on both MySQL and PostgreSQL if possible
- The accounting schema has database triggers - be careful modifying it
- Use `--force` flag in production: `php artisan migrate --force`

## Documentation

When adding new features, update:

1. **Code Comments** (sparingly, only for non-obvious logic)
2. **README.md** - If adding major feature
3. **API Documentation** - If adding API endpoints
4. **Migration Guides** - If breaking changes
5. **Inline DocBlocks** - For public methods

Example DocBlock:
```php
/**
 * Calculate the account balance for a given date.
 *
 * @param  ChartOfAccount  $account
 * @param  string  $date
 * @return float
 */
public function calculateBalance(ChartOfAccount $account, string $date): float
{
    // Implementation
}
```

## Getting Help

- **Bug Reports** - Open an issue with reproduction steps
- **Feature Requests** - Open an issue describing the feature
- **Questions** - Check existing issues or ask in discussions
- **Urgent Issues** - Tag as "urgent" in issue

## Code Review Process

All pull requests require:

1. **Passing Tests** - All tests must pass
2. **Code Review** - At least one approval
3. **No Merge Conflicts** - Rebase if needed
4. **Documentation** - Updated if applicable
5. **Clean Commits** - Squash if messy history

## Release Process

We follow semantic versioning:
- `MAJOR.MINOR.PATCH`
- Example: `1.2.3`

Version increments:
- **MAJOR** - Breaking changes
- **MINOR** - New features (backward compatible)
- **PATCH** - Bug fixes

## License

By contributing, you agree that your contributions will be licensed under the same license as the project.

## Questions?

Feel free to:
- Open an issue for questions
- Start a discussion
- Contact the maintainers

Thank you for contributing! 🎉
