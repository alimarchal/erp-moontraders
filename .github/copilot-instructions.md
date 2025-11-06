# Laravel 12 Development Instructions for VS Code

## General Code Instructions
- Don't generate code comments above methods or code blocks if they are obvious. Generate comments only for something that needs extra explanation for the reasons why that code was written
- When changing code, don't comment it out unless specifically instructed. Assume the old code will stay in Git history
- Use type hints for method parameters and return types where applicable
- Follow PSR-12 coding standards

## General Laravel Instructions
- If you need to generate a Laravel file, don't create the folder with `mkdir`, instead run command `php artisan make` whenever possible, and that Artisan command will create the folder itself
- When generating migrations for pivot tables, use correct alphabetical order, like "create_project_role_table" instead of "create_role_project_table"
- Always run `php artisan optimize:clear` after making configuration changes

## Laravel 12 Skeleton Structure

### Service Providers
- There are no other service providers except `AppServiceProvider`. Don't create new service providers unless absolutely necessary
- Use Laravel 12 new features instead of creating providers
- If you really need to create a new service provider, register it in `bootstrap/providers.php` (NOT in `config/app.php` which no longer exists)

### Event Listeners
- Since Laravel 11+, Listeners auto-listen for events if they are type-hinted correctly
- No need to manually register listeners in service providers

### Console Scheduler
- Scheduled commands should be in `routes/console.php`
- Don't create or reference `app/Console/Kernel.php` (doesn't exist since Laravel 11)

### Middleware
- Use Middleware by class name in routes whenever possible
- If you need to register Middleware aliases, register them in `bootstrap/app.php`
- Don't reference `app/Http/Kernel.php` (doesn't exist since Laravel 11)

### Views
- Use Artisan command `php artisan make:view` to create new Blade files instead of `mkdir` or `touch`
- Use Tailwind CSS for styling (not Bootstrap) unless instructed otherwise
- Tailwind is pre-configured with Vite in Laravel 12

### Policies
- Laravel automatically auto-discovers Policies
- No need to register them in Service Providers

### Factories
- Use `fake()` helper instead of `$this->faker` in factories
- Example: `fake()->name()` instead of `$this->faker->name`

### UUIDs
- `HasUuids` trait now generates UUIDv7 by default (not v4)
- If you need UUIDv4, use `HasVersion4Uuids` trait instead

## Laravel 12 Specific Features

### Native Health Checks
- Laravel 12 includes built-in health check endpoints
- Available at `/up` by default for monitoring tools
- No external packages needed for basic health checks

### Route Attributes (PHP 8+)
- You can now use PHP attributes for route definitions directly on controller methods
- Example:
```php
#[Route(method: 'GET', path: '/users')]
public function index() {}
```
- However, traditional route definitions in `routes/web.php` and `routes/api.php` still work fine

### Improved Batch Job Handling
- Better control over queued job batches with improved failure handling
- Use `Bus::batch()` with enhanced retry logic

### Performance Improvements
- Faster route resolution and service container loading
- Optimized boot time and provider registration
- Better lazy collection handling for large datasets

## Project-Specific Configuration

### Stack
- **Framework**: Laravel 12
- **Frontend**: React with Inertia 2
- **Styling**: Tailwind CSS + shadcn/ui components
- **TypeScript**: Enabled
- **Authentication**: Laravel's native authentication system
- **Permissions**: spatie/laravel-permission
- **Query Builder**: spatie/laravel-query-builder

### Starter Kit Notes
- Laravel 12 uses new starter kits (React/Vue/Livewire)
- Breeze and Jetstream are no longer actively maintained
- Your project uses the React starter kit with Inertia 2

### React/Inertia Development
- Components are located in `resources/js/`
- Use Inertia's `usePage()` hook for shared data
- Use `route()` helper for generating URLs
- Always use TypeScript for type safety
- Leverage shadcn/ui components for consistent UI

### Spatie Packages

#### spatie/laravel-permission
- Define roles and permissions in database seeders
- Assign permissions using: `$user->givePermissionTo('permission-name')`
- Check permissions using: `$user->can('permission-name')` or `@can` in Blade
- Use middleware: `middleware(['permission:manage-users'])`

#### spatie/laravel-query-builder
- Use for API endpoints with filtering/sorting capabilities
- Example:
```php
use Spatie\QueryBuilder\QueryBuilder;

QueryBuilder::for(User::class)
    ->allowedFilters(['name', 'email'])
    ->allowedSorts('created_at')
    ->get();
```

## Development Workflow

### Creating New Features
1. Create migration: `php artisan make:migration create_table_name`
2. Create model: `php artisan make:model ModelName`
3. Create controller: `php artisan make:controller ModelController`
4. Create React component in `resources/js/Pages/`
5. Define routes in `routes/web.php` or `routes/api.php`
6. Run migrations: `php artisan migrate`

### Asset Building
- Development: `npm run dev`
- Production build: `npm run build`
- Vite is pre-configured in Laravel 12

### Testing
- Run tests: `php artisan test` or `vendor/bin/phpunit`
- Create tests: `php artisan make:test TestName`

## Best Practices

### Database
- Always use migrations for schema changes
- Use factories for test data
- Index foreign keys and frequently queried columns
- Use proper relationship methods in models

### Security
- Use Laravel's CSRF protection (enabled by default)
- Validate all user input
- Use authorization policies for access control
- Keep dependencies updated: `composer update` and `npm update`

### Performance
- Use eager loading to prevent N+1 queries: `Model::with('relation')`
- Cache frequently accessed data
- Use queues for time-consuming tasks
- Enable route/config caching in production

### Code Organization
- Keep controllers thin, move business logic to services or actions
- Use form requests for validation
- Use resources for API responses
- Follow single responsibility principle

## PHP & Dependencies

### Requirements
- **PHP**: 8.2 - 8.4
- **Composer**: Latest version
- **Node.js**: 18+ recommended
- **npm/yarn**: Latest version

### Package Updates
- Ensure all packages are compatible with Laravel 12
- Check for major version updates in Spatie packages
- Update regularly: `composer update` and `npm update`

## Common Commands Reference

```bash
# Clear all caches
php artisan optimize:clear

# Create model with migration, factory, and controller
php artisan make:model ModelName -mfc

# Generate IDE helper files (if using laravel-ide-helper)
php artisan ide-helper:generate
php artisan ide-helper:models
php artisan ide-helper:meta

# Run database migrations
php artisan migrate
php artisan migrate:fresh --seed  # Fresh migration with seeding

# Queue management
php artisan queue:work
php artisan queue:restart

# View routes
php artisan route:list

# Generate application key
php artisan key:generate
```

## Additional Notes

### Laravel Breeze/Jetstream
- These are NO LONGER maintained as of Laravel 12
- Use Laravel 12's native starter kits instead
- If migrating from Breeze/Jetstream, plan accordingly

### Authentication
- Laravel 12 starter kits include built-in authentication
- Login, registration, password reset, email verification all included
- Optional: WorkOS AuthKit variant for social auth, passkeys, SSO

### TypeScript
- Strongly typed React components
- Use interfaces for props
- Configure in `tsconfig.json`
- Inertia provides TypeScript definitions

### Development Tips
- Use Laravel Telescope for debugging (development only)
- Use Laravel Pint for code formatting: `./vendor/bin/pint`
- Enable strict types in PHP files: `declare(strict_types=1);`
- Use VS Code extensions: Laravel Extension Pack, Intelephense, ESLint, Tailwind CSS IntelliSense
