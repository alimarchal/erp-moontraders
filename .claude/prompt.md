Please install the laravel boost below is the documentation, for laravel boost for database I am using all mysql/mariadb/postgresql you can check from .env file for any changing and add the mcp accordingly 


https://laravel.com/ai/boost
https://github.com/laravel/boost

Introduction
Laravel Boost accelerates AI-assisted development by providing the essential context and structure that AI needs to generate high-quality, Laravel-specific code.

At its foundation, Laravel Boost is an MCP server equipped with over 15 specialized tools designed to streamline AI-assisted coding workflows. The package includes composable AI guidelines specifically crafted for Laravel ecosystem packages, ensuring consistent and framework-appropriate code generation.

Boost also features a powerful Documentation API that combines a built-in MCP tool with an extensive knowledge base containing over 17,000 pieces of Laravel-specific information, all enhanced by semantic search capabilities using embeddings for precise, context-aware results.

Important

Laravel Boost is currently in beta and receives frequent updates as we refine features and expand capabilities.

Installation
Laravel Boost can be installed via Composer:

composer require laravel/boost --dev
Next, install the MCP server and coding guidelines:

php artisan boost:install
Feel free to add the generated MCP configuration file, guideline files (.mcp.json, CLAUDE.md, AGENTS.md, junie/, etc.) and boost.json configuration file to your application's .gitignore as these files are automatically re-generated when running boost:install and boost:update.

Once Laravel Boost has been installed, you're ready to start coding with Cursor, Claude Code, or your AI agent of choice.

Setup Your Code Editors
PhpStorm
Press shift twice to open the command palette
Search "MCP Settings" and press enter
Check the box next to laravel-boost
Click "Apply" at the bottom right
VS Code
Open the command palette (Cmd+Shift+P or Ctrl+Shift+P)
Press enter on "MCP: List Servers"
Arrow to laravel-boost and press enter
Choose "Start server"
Cursor
Open the command palette (Cmd+Shift+P or Ctrl+Shift+P)
Press enter on "/open MCP Settings"
Turn the toggle on for laravel-boost
Claude Code
Claude support is typically enabled automatically, but if you find it isn't
Open a shell in the project's directory
Run claude mcp add -s local -t stdio laravel-boost php artisan boost:mcp
Codex
Codex support is typically enabled automatically, but if you find it isn't
Open a shell in the project's directory
Run codex mcp add -- php artisan boost:mcp
Gemini
Gemini support is typically enabled automatically, but if you find it isn't
Open a shell in the project's directory
Run gemini mcp add -s project -t stdio laravel-boost php artisan boost:mcp
Available MCP Tools
Name	Notes
Application Info	Read PHP & Laravel versions, database engine, list of ecosystem packages with versions, and Eloquent models
Browser Logs	Read logs and errors from the browser
Database Connections	Inspect available database connections, including the default connection
Database Query	Execute a query against the database
Database Schema	Read the database schema
Get Absolute URL	Convert relative path URIs to absolute so agents generate valid URLs
Get Config	Get a value from the configuration files using "dot" notation
Last Error	Read the last error from the application's log files
List Artisan Commands	Inspect the available Artisan commands
List Available Config Keys	Inspect the available configuration keys
List Available Env Vars	Inspect the available environment variable keys
List Routes	Inspect the application's routes
Read Log Entries	Read the last N log entries
Report Feedback	Share Boost & Laravel AI feedback with the team, just say "give Boost feedback: x, y, and z"
Search Docs	Query the Laravel hosted documentation API service to retrieve documentation based on installed packages
Tinker	Execute arbitrary code within the context of the application
Available AI Guidelines
Laravel Boost includes AI guidelines for the following packages and frameworks. The core guidelines provide generic, generalized advice to the AI for the given package that is applicable across all versions.

Package	Versions Supported
Core & Boost	core
Laravel Framework	core, 10.x, 11.x, 12.x
Livewire	core, 2.x, 3.x
Flux UI	core, free, pro
Herd	core
Inertia Laravel	core, 1.x, 2.x
Inertia React	core, 1.x, 2.x
Inertia Vue	core, 1.x, 2.x
Pest	core, 4.x
PHPUnit	core
Pint	core
Tailwind CSS	core, 3.x, 4.x
Livewire Volt	core
Laravel Folio	core
Enforce Tests	conditional
Keeping Guidelines Up-to-Date
You may want to periodically update your local AI guidelines to ensure they reflect the latest versions of the Laravel ecosystem packages you have installed. To do so, you can use the boost:update Artisan command.

php artisan boost:update
You may also automate this process by adding it to your Composer "post-update-cmd" scripts:

{
  "scripts": {
    "post-update-cmd": [
      "@php artisan boost:update --ansi"
    ]
  }
}
Available Documentation
Package	Versions Supported
Laravel Framework	10.x, 11.x, 12.x
Filament	2.x, 3.x, 4.x
Flux UI	2.x Free, 2.x Pro
Inertia	1.x, 2.x
Livewire	1.x, 2.x, 3.x
Nova	4.x, 5.x
Pest	3.x, 4.x
Tailwind CSS	3.x, 4.x
Adding Custom AI Guidelines
To augment Laravel Boost with your own custom AI guidelines, add .blade.php or .md files to your application's .ai/guidelines/* directory. These files will automatically be included with Laravel Boost's guidelines when you run boost:install.

Overriding Boost AI Guidelines
You can override Boost's built-in AI guidelines by creating your own custom guidelines with matching file paths. When you create a custom guideline that matches an existing Boost guideline path, Boost will use your custom version instead of the built-in one.

For example, to override Boost's "Inertia React v2 Form Guidance" guidelines, create a file at .ai/guidelines/inertia-react/2/forms.blade.php. When you run boost:install, Boost will include your custom guideline instead of the default one.

Third-Party Package AI Guidelines
If you maintain a third-party package and would like Boost to include AI guidelines for it, you can do so by adding a resources/boost/guidelines/core.blade.php file to your package. When users of your package run php artisan boost:install, Boost will automatically load your guidelines.

AI guidelines should provide a short overview of what your package does, outline any required file structure or conventions, and explain how to create or use its main features (with example commands or code snippets). Keep them concise, actionable, and focused on best practices so AI can generate correct code for your users. Here is an example:

## Package Name

This package provides [brief description of functionality].

### Features

- Feature 1: [clear & short description].
- Feature 2: [clear & short description]. Example usage:

@verbatim
<code-snippet name="How to use Feature 2" lang="php">
$result = PackageName::featureTwo($param1, $param2);
</code-snippet>
@endverbatim
Manually Registering the Boost MCP Server
Sometimes you may need to manually register the Laravel Boost MCP server with your editor of choice. You should register the MCP server using the following details:

Command	php
Args	artisan boost:mcp
JSON Example:

{
    "mcpServers": {
        "laravel-boost": {
            "command": "php",
            "args": ["artisan", "boost:mcp"]
        }
    }
}
Adding Support for Other IDEs / AI Agents
Boost works with many popular IDEs and AI agents out of the box. If your coding tool isn't supported yet, you can create your own code environment and integrate it with Boost. To do this, create a class that extends Laravel\Boost\Install\CodeEnvironment\CodeEnvironment and implement one or both of the following contracts depending on what you need:

Laravel\Boost\Contracts\Agent - Adds support for AI guidelines.
Laravel\Boost\Contracts\McpClient - Adds support for MCP.
Writing the Code Environment
<?php

declare(strict_types=1);

namespace App;

use Laravel\Boost\Contracts\Agent;
use Laravel\Boost\Contracts\McpClient;
use Laravel\Boost\Install\CodeEnvironment\CodeEnvironment;

class OpenCode extends CodeEnvironment implements Agent, McpClient
{
    // Your implementation...
}
For an example implementation, see ClaudeCode.php.

Registering the Code Environment
Register your custom code environment in the boot method of your application's App\Providers\AppServiceProvider:

use Laravel\Boost\Boost;

public function boot(): void
{
    Boost::registerCodeEnvironment('opencode', OpenCode::class);
}
Once registered, your code environment will be available for selection when running php artisan boost:install.

Contributing
Thank you for considering contributing to Boost! The contribution guide can be found in the Laravel documentation.

Code of Conduct
In order to ensure that the Laravel community is welcoming to all, please review and abide by the Code of Conduct.

Security Vulnerabilities
Please review our security policy on how to report security vulnerabilities.

License
