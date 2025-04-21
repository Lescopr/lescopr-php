# Lescopr/PHP

**Lescopr/PHP** is a proprietary PHP SDK and CLI tool for companies who want to connect their backend PHP application to the Lescopr mobile app. It enables real-time monitoring of your backend, so you can be instantly alerted on your mobile in case of bugs or production errors.

This package is exclusively for use with the Lescopr mobile app.
After installing the package on your backend, you can activate your trial or subscription plan and link your backend to your Lescopr account.

## Features

- Real-time backend monitoring and alerting to your mobile
- Easy CLI setup for API key and project configuration
- Secure local config management with `.lescoprrc.json`
- API key validation and status checking
- Reset and reconfigure your Lescopr integration at any time
- Integration with popular PHP frameworks (via standard Composer autoloading)

## Getting Started

### 1. Install

  ```bash
   composer require sonnalabs/lescopr
  ```

### 2. Setup
After installing the package, just run the command below to config it :

  ```bash
   ./vendor/bin/lescopr config
  ````

## Usage    
Use the CLI tool via ./vendor/bin/lescopr:

- Show current configuration:
  ```bash
  ./vendor/bin/lescopr info
  ```
- Reset configuration (removes .lescoprrc.json)
  ```bash
  ./vendor/bin/lescopr reset
  ```
- Show help for all commands:
  ```bash
  ./vendor/bin/lescopr help
  ```  

## Documentation

See the [Lescopr documentation](https://lescopr.com/docs) for full API usage and advanced integration details.

---
© 2025 SonnaLabs – All rights reserved.

