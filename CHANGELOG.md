# Changelog

All notable changes to `lescopr/lescopr-php` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

---

## [1.0.0] — 2026-04-17

### Added
- **Modes** — nouveau module `src/Modes/` pour configurer finement le comportement du SDK (silent, verbose, strict)
- **Makefile** — workflow de release simplifié (`bump-patch`, `bump-minor`, `bump-major`, `release V=x.y.z`, `test`, `tag`, `push`)

### Changed
- `src/Core/Lescopr.php` — intégration des modes et mise à jour de `SDK_VERSION`

### Fixed
- Script de release : vérification de l'arbre git limitée au répertoire PHP (`git status --porcelain .`) pour éviter les faux positifs dans les setups monorepo

---

## [0.1.0] — 2026-03-07

### Added
- **Core SDK** (`Lescopr\Core\Lescopr`) — central object handling config, log queue, and daemon lifecycle
- **Background daemon** (`DaemonRunner`) — `pcntl_fork` process that flushes logs to `api.lescopr.com` every 5 s with heartbeat every 30 s; graceful fallback to synchronous HTTP when `pcntl` is unavailable
- **HTTP transport** (`HttpClient`) — HTTPS batch delivery via Guzzle 6/7 with automatic retry queuing on failure
- **Framework auto-detection** (`FrameworkAnalyzer`) — detects Laravel, Symfony, Lumen, Slim, CodeIgniter and vanilla PHP/POO from `composer.json` with confidence scoring
- **Project analyzer** (`ProjectAnalyzer`) — full project scan producing the payload for `POST /api/v1/sdk/verify/`
- **Config manager** (`ConfigManager`) — thread-safe read/write of `.lescopr.json`
- **Laravel integration**
  - `LescoprServiceProvider` — auto-registered via Composer package discovery
  - `LescoprMonologHandler` — Monolog 1/2/3 compatible handler
  - `LescoprExceptionHandler` — decorator that captures unhandled exceptions
  - `LescoprFacade` — static-style access `Lescopr::sendLog(...)`
  - Publishable config stub `config/lescopr.php`
- **Symfony integration**
  - `LescoprBundle` — DI bundle (Symfony 4.4 → 7.x)
  - `LescoprMonologHandler` — Monolog handler wired via `services.yaml`
  - `KernelExceptionSubscriber` — captures kernel exceptions automatically
  - `LescoprFactory` — static factory for `services.yaml`
- **Vanilla PHP / POO integration** (`LescoprBootstrap`) — one-line bootstrap installing `set_error_handler`, `set_exception_handler`, `register_shutdown_function`
- **CLI** (`bin/lescopr`) — Symfony Console commands: `init`, `start`, `stop`, `status`, `diagnose`, `reset`
- **Internal logger** (`Monitoring\Logger`) — writes to `.lescopr.log`, never pollutes application output
- **Test suite** — 20 unit tests, 52 assertions (PHPUnit 9/10/11)
- **PHP 7.4+ compatibility** — no PHP 8.0+ syntax; works on PHP 7.4, 8.0, 8.1, 8.2, 8.3
- **Packagist ready** — `composer.json` with Laravel auto-discovery, keywords, support links

### Compatibility matrix

| Framework   | Version      | PHP     |
|-------------|--------------|---------|
| Laravel     | 8, 9, 10, 11 | 7.4–8.3 |
| Symfony     | 4.4–7.x      | 7.4–8.3 |
| Lumen       | 8, 9, 10     | 7.4–8.3 |
| Slim        | 3, 4         | 7.4–8.3 |
| CodeIgniter | 3, 4         | 7.4–8.3 |
| Vanilla PHP | —            | 7.4–8.3 |

---

[Unreleased]: https://github.com/Lescopr/lescopr-php/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/Lescopr/lescopr-php/releases/tag/v1.0.0
[0.1.0]: https://github.com/Lescopr/lescopr-php/releases/tag/v0.1.0

