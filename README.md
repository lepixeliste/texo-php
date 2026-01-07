# Texo PHP Framework

A lightweight PSR-compliant PHP framework for building modern API applications with built-in routing, ORM, authentication, events, and CLI tools.

The framework is built on explicit dependency injection principles without relying on singleton patterns, magic functions, or magic methods. All dependencies are resolved through the container, ensuring transparent, testable, and maintainable code.

## Features

- **PSR Standards Compliant**: Full implementation of PSR-3 (Logger), PSR-4 (Autoloading), PSR-7 (HTTP Messages), PSR-11 (Container), PSR-14 (Event Dispatcher), and PSR-18 (HTTP Client)
- **Dependency Injection**: Reflection-based container with automatic dependency resolution
- **RESTful Routing**: Flexible router with middleware support and route parameters
- **Database ORM**: Active Record pattern with query builder, relationships, and soft deletes
- **JWT Authentication**: Secure token-based authentication with configurable algorithms
- **Event System**: Event dispatcher for decoupled, event-driven architecture
- **CLI Tools**: Built-in commands for migrations, scaffolding, and task management
- **Minimal Dependencies**: Self-contained framework with no external requirements

## Requirements

- PHP >= 8.0
- MySQL/MariaDB database
- Composer (optional)
- Node.js & npm (for Tailwind CSS, optional)

## Installation

### Using Docker (Recommended)

```bash
docker compose up -d
```

### Manual Installation

1. Clone the repository:

```bash
git clone https://github.com/lepixeliste/texo-php texo.dev
cd texo.dev
```

2. Configure environment:

```bash
php cli setup
```

3. Set up the database:

```bash
php cli db:import <file.sql>
```

4. Start PHP development server:

```bash
php -S localhost:8000
```

## Project Structure

```
texo.dev/
├── index.php              # Application entry point
├── main/
│   ├── autoload.php      # PSR-4 autoloader
│   ├── bootstrap.php     # Framework bootstrap
│   ├── functions.php     # Global helper functions
│   ├── core/             # Framework core (104+ files)
│   │   ├── App.php
│   │   ├── Container.php
│   │   ├── Context.php
│   │   ├── Controller.php
│   │   ├── Auth/         # Authentication system
│   │   ├── Cli/          # CLI commands
│   │   ├── Events/       # Event dispatcher
│   │   ├── Http/         # HTTP request/response
│   │   ├── Mail/         # Email support
│   │   ├── Pdo/          # Database ORM
│   │   ├── Psr/          # PSR interfaces
│   │   └── Routing/      # Router implementation
│   ├── app/              # Application code
│   │   ├── Controllers/  # HTTP controllers
│   │   ├── Models/       # Database models
│   │   ├── Middlewares/  # Route middlewares
│   │   └── Casts/        # Model attribute casts
│   ├── routes/           # Route definitions
│   └── tasks/            # Database migrations/seeds
├── views/                # Template files
├── assets/               # Static assets
├── logs/                 # Application logs
└── db/                   # Database schemas

```

## Core Components

### Application Kernel

The `Core\App` class manages the request lifecycle:

```php
// index.php
require_once __DIR__ . '/main/functions.php';
require_once __DIR__ . '/main/autoload.php';

/** @var \Core\App $app */
$app = require_once __DIR__ . '/main/bootstrap.php';
$app->send(); // Process and send response
```

### Container (PSR-11)

Dependency injection container with automatic resolution:

```php
use Core\Container;

$container = new Container();
$app = $container->get('Core\App');
```

### Routing

Define routes in [main/routes/](main/routes/):

```php
use App\Controllers\AuthController;
use App\Controllers\HelloController;
use App\Controllers\UserController;
use Core\Routing\Router;

return function (Router $router) {
    $router
        ->get('/changelog', function () {
            $contents = file_get_contents(path_root('logs', 'changes.log'));
            return response()->text($contents);
        })
        ->get('/hello/?:name?', [HelloController::class, 'wave'])
        ->post('/auth/login', [AuthController::class, 'login'])
}
```

### Database ORM

Active Record pattern with query builder:

```php
use Core\Pdo\Model;

class User extends Model
{
    protected string $table = 'users';
    protected string $primaryKey = 'user_id';

    public function posts()
    {
        return $this->hasMany(Post::class, 'post_userid')->orderBy('post_date', 'desc');
    }
}

// Usage in controller
$db = $this->db();
$user = User::find($db, 1); // \App\Models\User
$users = User::load($db)->where('user_email', 'LIKE', '%@example.com')->get(); // \Core\Collection

// Create
$user = new User($db);
$user->save(); // ->isPersistent() returns true

// Query builder
use Core\Pdo\SqlQuery;
$results = SqlQuery::table('posts')
                ->select('post_id', 'post_date', 'post_title')
                ->join('users', 'users.user_id', 'posts.post_userid')
                ->where('users.user_id', '=', 1)
                ->run($db);
```

### Authentication

JWT-based authentication system:

```php
use Core\Auth\Authenticable;

class User extends Authenticable
{
    const USERNAME_COLUMN = 'user_email'; // by default 'email'
    const PASSWORD_COLUMN = 'user_pass'; // by default 'password'
    const TOKEN_COLUMN = 'user_token'; // by default 'token'
}

// Login attempt in a controller
protected function sign(Request $request)
{
    $auth = new Auth($request);
    $user = $auth->user($this->db(), User::class);
    if (null === $user) {
        return response(401)->json(['status' => 401, 'message' => 'Unauthorized.']);
    }

    $duration = 60 * 60 * 2; // 2 hours
    $key_id = 'USER'; // JWT_KEY_{$key_id}
    $credentials = [$request->get('user', ''), $request->get('pass', '')];
    try {
        $token = $user->attempt($auth, $credentials, $duration, $key_id);
        $payload = $auth->payload();
        setcookie('jwt', $token, isset($payload->exp) ? $payload->exp : time() + $duration, '/', '', true, true);
        return response()->json(['token' => $token]);
    } catch (AuthException $e) {
        return response(401)->json(['status' => 401, 'message' => $e->getMessage()]);
    }
}

// Middleware protection
use Core\Auth\AuthGuard;

$router->get('/protected', AuthGuard::class, [Controller::class, 'method']);
```

### HTTP Request/Response (PSR-7)

```php
use App\Models\Post;
use Core\Http\Request;

// In controller
// response() is an utility shortcut for new Core\Http\Response($code = 200)
public function get($id, Request $request)
{
    $db = $this->db();
    $existing_post = Post::find($db, $id);
    return null !== $existing_post ? response()->json($existing_post) : response(404)->json(['message' => 'No post found.']);
}
```

## API Response Types

The framework supports multiple response types:

```php
// response() is an utility shortcut for new Core\Http\Response($code = 200)

// JSON response
response()->json(['data' => $data]);
response(400)->json(['message' => 'Bad Request.']);

// HTML response
response()->html('<h1>Hello World</h1>');

// Redirect
response(302)->redirect('/dashboard');

// Plain text
response()->text('Hello World');

// Stream
response()->stream($resource, $filename);

// Transfer
response()->transfer($filepath);

// View
use Core\View;
response()->view(new View('pages/welcome.html'));
```

### Logging (PSR-3)

```php
use Core\Logger;

$logger = new Logger();
$logger->info('User logged in', ['user_id' => 123]);
$logger->error('Database error', ['exception' => $e]);
```

### Event System (PSR-14)

Event-driven architecture:

```php
use Core\Events\EventDispatcher;
use Core\Events\ListenerProvider;

$provider = new ListenerProvider();
$provider->register(UserCreated::class, UserCreatedListener::class);

$dispatcher = new EventDispatcher($provider);
$dispatcher->dispatch(new UserCreated($user));
```

## CLI Commands

Run commands using the CLI interpreter:

```bash
php cli <command>
```

Available commands (non-exhaustive):

- `setup` - Initial application setup
- `config:env` - Add a new .env file
- `task:create` - Create a new task operation file
- `task:run` - Launch task operations (-l for latest task only)
- `mail:setup` - Setup the SMTP configuration
- `mail:test` - Test the current SMTP configuration
- `db:create` - Create a new database if not exists
- `db:use` - Switch to database
- `db:dump` - Backup database utility
- `db:import` - Import SQL file into any new or existing database
- `db:schema` - Build/rebuild the database schema file
- `db:ddl` - Build/rebuild the database Data Definition Language file
- `db:build` - Build the database from the latest DDL file
- `db:collate` - Alter database to current charset and collate
- `make:controller <name>` - Generate controller
- `make:model <name>` - Generate model
- `make:resource <name>` - Generate resource
- `make:middleware <name>` - Generate middleware
- `route:add` - Create a new route list file
- `key:ssl` - Generate a new OpenSSL private/public key

List all commands

```bash
php cli <command>
```

## Configuration

Environment variables in [.env](.env):

```env
# Application
APP_NAME=texo-php
APP_ENV=development
APP_MEMORY_LIMIT=256M
APP_VERSION=1.0.0
APP_TIMEZONE=Europe/Paris
APP_LOCALE=fr_FR
APP_BASE_URL=/
APP_STORAGE=/files

# Database
SQL_DB=sandbox
SQL_HOST=mariadb
SQL_PORT=3306
SQL_USER=root
SQL_PASS=root

# JWT Authentication
JWT_ALGO=HS512
JWT_KEY_USER=<your-secret-key>

# Email (SMTP)
SMTP_HOST=
SMTP_PORT=465
SMTP_EMAIL=
SMTP_USER=
SMTP_PASS=
```

## Helper Functions

Global utility functions available throughout the application in [main/functions.php](main/functions.php):

```php
// Security
get_csrf_token();
random_string(32);
get_auth_bearer(); // JWT bearer string token if any

// Environment
is_env_prod();
is_secure();
get_ip();
get_referer();

// Utility
unique_id();
convert_bytes(1024); // "1.00 KB"
current_timestamp(); // Y-m-d H:i:s
array_index($items, fn($item) => $item === 'found'); // (int)$index if found, -1 if not

// Debug
debug('Debug start', ['message' => 'OK'], $data, 'Debug end');
```

## Security Features

- **JWT Authentication**: HS256/HS512 algorithms with configurable secret keys
- **Password Hashing**: PHP's native `password_hash()` and `password_verify()`
- **CSRF Protection**: Timing-safe token validation
- **SQL Injection Prevention**: Parameterized queries via PDO
- **CORS Support**: Configurable CORS headers
- **Secure Cookies**: SameSite policy enforcement

## Middleware

Protect routes with custom middleware:

```php
use Closure;
use Core\Auth\Auth;
use Core\Http\Request;
use Core\Routing\MiddlewareInterface;
use Core\Psr\Http\Message\ResponseInterface;

class AppUserGuard implements MiddlewareInterface
{
    public function process(Request $request, Closure $next): ResponseInterface
    {
        $auth = new Auth($request);
        if (!$auth->isAuth() || $auth->keyId() !== 'USER') {
            return response(401)->json(['message' => 'Unauthorized.']);
        }
        if ($auth->csrf() != get_csrf_token()) {
            return response(401)->json(['message' => 'Invalid or missing CSRF token.']);
        }
        return $next($request);
    }
}
```

## Database Migrations

Create migrations in [main/tasks/](main/tasks/):

```php
use Core\Cli\Printer;
use Core\Context;

/**
 * 20250101Txxxxxx_my_new_table.php
 *
 * @version 1.0.0
 * @return boolean `true` if task is recurring
 */

return function (Context $context) {
    $printer = new Printer();
    $db = $context->db();
    $db_name = $db->name();

    $table_name = "my_new_table";
    $defs = [
        "`table_id` int unsigned NOT NULL AUTO_INCREMENT",
        "`table_key` varchar(128) NOT NULL",
        "`table_value` varchar(32) DEFAULT NULL",
        "PRIMARY KEY (`table_id`)"
    ];
    $ddl_query = implode(', ', $defs);

    $create_table_query = [
        'SET FOREIGN_KEY_CHECKS=0',
        "CREATE TABLE IF NOT EXISTS `{$table_name}` ($ddl_query)",
        'SET FOREIGN_KEY_CHECKS=1'
    ];
    try {
        $db->execute(implode(";\r\n", $create_table_query));
        $printer->out("{green}task:done{nc} > New table `{cyan;italic}$db_name{nc}`.`{cyan;italic}$table_name{nc}` created.");
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        $printer->out("{red}task:error{nc} > $error_message");
    }

    return false;
};
```

Run with:

```bash
php cli task:run
```

## Email Support

Send emails via SMTP:

```php
use Core\Mail\Mailer;
use Core\Mail\MailMessage;
use Core\Mail\MailRecipient;

$mailer = new Mailer();
$message = new MailMessage("You've got a new message!");
$message->setFrom(getenv('SMTP_EMAIL'), getenv('APP_NAME'))
        ->addRecipient(new MailRecipient('recipient@example.com'))
        ->setView(
            new View('mails/message.html', [
                'data' => 'Custom data',
                'contact' => getenv('SMTP_EMAIL'),
                'year' => date('Y')
            ])
        );

$mailer->send($message);
```

## Testing

Currently, the framework does not include a built-in testing suite. You can integrate PHPUnit or Pest:

```bash
composer require --dev phpunit/phpunit
```

## Docker Support

The project includes Docker configuration via [compose.yaml](compose.yaml):

```yaml
services:
  mariadb:
    image: mariadb:latest
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: sandbox
```

## Performance Considerations

- **Autoloading**: Custom PSR-4 autoloader with lowercase-first-segment convention
- **Query Buffering**: Disabled by default for memory efficiency on large result sets
- **Event System**: Lazy listener instantiation

## Architecture Patterns

- **MVC Pattern**: Separation of models, views, and controllers
- **Service Container**: Centralized dependency management
- **Active Record**: Database models with built-in CRUD operations
- **Event-Driven**: Decoupled components via event dispatcher
- **Factory Pattern**: HTTP message object creation
- **Middleware Pipeline**: Request filtering and transformation

## Contributing

This is a custom framework. Contributions should follow:

1. PSR coding standards (PSR-1, PSR-12)
2. Use type hints for parameters and return values
3. Document public methods with PHPDoc
4. Follow existing architectural patterns

## License

Apache License 2.0

## Author

**Charles-André Leduc**
Website: [https://pixeliste.fr](https://pixeliste.fr)

## Version

1.0.0

---

For more information, explore the [core framework documentation](main/core/).
