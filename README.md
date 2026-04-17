# Lescopr PHP SDK

[![Packagist Version](https://img.shields.io/packagist/v/lescopr/lescopr-php.svg)](https://packagist.org/packages/lescopr/lescopr-php)
[![Packagist Downloads](https://img.shields.io/packagist/dt/lescopr/lescopr-php.svg)](https://packagist.org/packages/lescopr/lescopr-php)
[![PHP versions](https://img.shields.io/packagist/php-v/lescopr/lescopr-php.svg)](https://packagist.org/packages/lescopr/lescopr-php)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

**Lescopr** is a zero-configuration PHP monitoring SDK that automatically captures logs, errors, and exceptions from any PHP project and streams them in real-time to the [Lescopr app](https://app.lescopr.com).

Works out of the box with **Laravel**, **Symfony**, and **vanilla PHP / custom OOP** projects.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Framework Integration](#framework-integration)
  - [Laravel](#laravel)
  - [Symfony](#symfony)
  - [Vanilla PHP / POO](#vanilla-php--poo)
- [Architecture](#architecture)
- [CLI Reference](#cli-reference)
- [Advanced Configuration](#advanced-configuration)
- [Packagist](#packagist)
- [License](#license)

---

## Features

- ✅ **Automatic error capture** — hooks into `set_error_handler`, `set_exception_handler`, `register_shutdown_function`
- ✅ **Framework auto-detection** — detects Laravel, Symfony, Lumen, Slim, and vanilla PHP from `composer.json`
- ✅ **Monolog integration** — drop-in handler for Laravel and Symfony
- ✅ **Background daemon** — runs as a `pcntl_fork` process, completely non-blocking
- ✅ **HTTP batch transport** — logs are batched and flushed every 5 seconds via HTTPS
- ✅ **Zero configuration** — a single CLI command is enough to get started
- ✅ **Works everywhere** — Laravel, Symfony, Lumen, Slim, scripts, custom OOP

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | ≥ 7.4 |
| Composer | ≥ 2.0 |
| `guzzlehttp/guzzle` | `^6.5 \|\| ^7.0` |
| `symfony/console` | `^4.4 \|\| ^5.0 \|\| ^6.0 \|\| ^7.0` |
| `ext-json` | bundled with PHP |
| `ext-pcntl` | Recommended (for daemon) |
| `ext-posix` | Recommended (for daemon) |
| `ext-openssl` | Recommended (for HTTPS) |

> **Note:** `ext-pcntl` is not available on Windows. On those environments the SDK operates in direct HTTP mode (no background daemon).

---

## Installation

```bash
composer require lescopr/lescopr-php
```

---

## Quick Start

**Step 1 — Initialise the SDK in your project directory:**

```bash
./vendor/bin/lescopr init --sdk-key YOUR_SDK_KEY
```

This auto-detects your framework, registers the project with the Lescopr API, writes `.lescopr.json`, and starts the background daemon.

**Step 2 — Integrate into your application** (see [Framework Integration](#framework-integration) below).

**That's it.** All logs and exceptions are forwarded to the Lescopr app automatically.

---

## Framework Integration

### Laravel

The SDK registers automatically via Composer's package auto-discovery. No manual registration needed.

**Publish the config (optional):**

```bash
php artisan vendor:publish --tag=lescopr-config
```

**Add the Lescopr channel to your logging stack in `config/logging.php`:**

```php
'channels' => [
    'stack' => [
        'driver'   => 'stack',
        'channels' => ['single', 'lescopr'],  // ← add 'lescopr'
    ],

    'lescopr' => [
        'driver'  => 'monolog',
        'handler' => \Lescopr\Integrations\Laravel\Logging\LescoprMonologHandler::class,
        'handler_with' => [
            'sdk' => app(\Lescopr\Core\Lescopr::class),
        ],
    ],
],
```

All `Log::info(...)`, `Log::error(...)` etc. calls are automatically forwarded to Lescopr.

**Exceptions** are captured automatically via the exception handler decorator bundled with the ServiceProvider.

**Environment variables (`.env`):**

```dotenv
LESCOPR_SDK_KEY=lsk_xxxx
LESCOPR_API_KEY=lak_xxxx
LESCOPR_ENVIRONMENT=production
```

---

### Symfony

**Register the bundle in `config/bundles.php`:**

```php
return [
    // ... other bundles
    Lescopr\Integrations\Symfony\LescoprBundle::class => ['all' => true],
];
```

**Wire the Monolog handler in `config/packages/monolog.yaml`:**

```yaml
monolog:
  handlers:
    lescopr:
      type:     service
      id:       Lescopr\Integrations\Symfony\LescoprMonologHandler
      channels: ['!event', '!doctrine']
```

**Register services in `config/services.yaml`:**

```yaml
services:
  Lescopr\Core\Lescopr:
    factory: ['Lescopr\Core\Lescopr', 'fromConfig']
    public: true

  Lescopr\Integrations\Symfony\LescoprMonologHandler:
    arguments: ['@Lescopr\Core\Lescopr']

  Lescopr\Integrations\Symfony\EventSubscriber\KernelExceptionSubscriber:
    arguments: ['@Lescopr\Core\Lescopr']
    tags:
      - { name: kernel.event_subscriber }
```

Kernel exceptions are automatically captured via `KernelExceptionSubscriber`.

---

### Vanilla PHP / POO

Add **one line** at the top of your entry point (`index.php` / `bootstrap.php`):

```php
<?php
require 'vendor/autoload.php';

\Lescopr\Integrations\Vanilla\LescoprBootstrap::init();

// Your existing code continues unchanged
```

The bootstrap installs:
- `set_error_handler` — captures all PHP errors (E_WARNING, E_NOTICE, etc.)
- `set_exception_handler` — captures uncaught exceptions
- `register_shutdown_function` — captures fatal errors (E_ERROR, E_PARSE, etc.)

**Manual configuration (no `.lescopr.json`):**

```php
\Lescopr\Integrations\Vanilla\LescoprBootstrap::init([
    'sdk_key'     => 'lsk_xxxx',
    'api_key'     => 'lak_xxxx',
    'environment' => 'production',
    'debug'       => false,
]);
```

---

## Architecture

```
Your PHP Application
        │
        │  Log::error() / throw Exception / trigger_error()
        ▼
┌─────────────────────────────────┐
│   LescoprMonologHandler         │  (Monolog — Laravel / Symfony)
│   OR LescoprBootstrap handlers  │  (set_error_handler — Vanilla)
└──────────────┬──────────────────┘
               │ HTTP POST (batched)
               ▼
┌─────────────────────────────────┐
│      Lescopr Daemon             │  (background process, .lescopr.pid)
│  pcntl_fork → DaemonRunner      │  flushes every 5 seconds
└──────────────┬──────────────────┘
               │ HTTPS batch
               ▼
     https://api.lescopr.com
               │
               ▼
     Lescopr App
  https://app.lescopr.com
```

**Key components:**

| Component | Path | Role |
|---|---|---|
| `Lescopr` (core) | `src/Core/Lescopr.php` | Central SDK object, config, log queue, daemon lifecycle |
| `DaemonRunner` | `src/Core/DaemonRunner.php` | Background process — flushes logs, sends heartbeats |
| `ProjectAnalyzer` | `src/Filesystem/Analyzers/ProjectAnalyzer.php` | Scans project, detects frameworks |
| `FrameworkAnalyzer` | `src/Filesystem/Analyzers/FrameworkAnalyzer.php` | Detects Laravel, Symfony, Lumen, Slim, POO |
| `HttpClient` | `src/Network/HttpClient.php` | HTTPS transport via Guzzle |
| `LescoprServiceProvider` | `src/Integrations/Laravel/` | Auto-registers in Laravel |
| `LescoprBundle` | `src/Integrations/Symfony/` | Registers in Symfony DI container |
| `LescoprBootstrap` | `src/Integrations/Vanilla/` | One-line bootstrap for plain PHP |
| CLI | `bin/lescopr` | `init`, `start`, `stop`, `status`, `diagnose`, `reset` |

---

## CLI Reference

After installation, the `lescopr` command is available via Composer's `vendor/bin`:

```bash
./vendor/bin/lescopr [COMMAND] [OPTIONS]
```

| Command | Description |
|---|---|
| `init --sdk-key KEY` | Initialise the SDK in the current project |
| `start` | Start the monitoring daemon |
| `stop` | Stop the monitoring daemon |
| `status` | Show daemon status and project info |
| `diagnose [--details] [--check-server]` | Run a full diagnostic |
| `reset [--force] [--keep-config]` | Remove SDK configuration and stop daemon |

### `init`

```bash
./vendor/bin/lescopr init --sdk-key YOUR_SDK_KEY [--environment production] [--no-start-daemon]
```

- Auto-detects your project framework
- Registers the project with the Lescopr API
- Writes `.lescopr.json`
- Starts the background daemon (use `--no-start-daemon` to skip)

### `diagnose`

```bash
./vendor/bin/lescopr diagnose --details --check-server
```

Prints PHP environment, configuration validity, daemon state, and optionally tests HTTPS connectivity to `api.lescopr.com`.

---

## Advanced Configuration

`.lescopr.json` is generated automatically by `lescopr init`:

```json
{
    "sdk_id": "proj_xxxx",
    "sdk_key": "lsk_xxxx",
    "api_key": "lak_xxxx",
    "environment": "development",
    "project_name": "my-app",
    "project_stack": ["laravel"]
}
```

> **Security:** Add `.lescopr.json` to your `.gitignore`.

### Environment variables

| Variable | Description |
|---|---|
| `LESCOPR_SDK_KEY` | SDK key (overrides `.lescopr.json`) |
| `LESCOPR_API_KEY` | API key (overrides `.lescopr.json`) |
| `LESCOPR_ENVIRONMENT` | `development` or `production` |
| `LESCOPR_DAEMON_MODE=true` | Prevents the SDK from forking inside its own daemon |
| `LESCOPR_DEBUG=true` | Enables verbose output to `.lescopr.log` |

---

## Packagist

The package is available on Packagist: **[lescopr/lescopr-php](https://packagist.org/packages/lescopr/lescopr-php)**

To publish a new release, use the automated release script:

```bash
# From the sdk/php directory
./scripts/release.sh 0.2.0
```

This will update `CHANGELOG.md`, commit, tag and push — GitHub Actions will create the GitHub Release and notify Packagist automatically.

For the full publishing guide (Symfony Flex recipe, Laravel News, SemVer rules), see [PUBLISHING.md](PUBLISHING.md).

---

## Support

| Channel | Link |
|---|---|
| 📖 Documentation | <https://lescopr.com/docs> |
| 🌐 App | <https://app.lescopr.com> |
| 📧 Email | <support@lescopr.com> |

---

## License

[MIT](LICENSE) © 2024-present [SonnaLab](https://sonnalab.com). All rights reserved.

