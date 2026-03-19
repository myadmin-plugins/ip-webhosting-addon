# Dedicated IP Addon for Webhosting Module in MyAdmin

[![Tests](https://github.com/detain/myadmin-ip-webhosting-addon/actions/workflows/tests.yml/badge.svg)](https://github.com/detain/myadmin-ip-webhosting-addon/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/detain/myadmin-ip-webhosting-addon/version)](https://packagist.org/packages/detain/myadmin-ip-webhosting-addon)
[![Total Downloads](https://poser.pugx.org/detain/myadmin-ip-webhosting-addon/downloads)](https://packagist.org/packages/detain/myadmin-ip-webhosting-addon)
[![License](https://poser.pugx.org/detain/myadmin-ip-webhosting-addon/license)](https://packagist.org/packages/detain/myadmin-ip-webhosting-addon)

A MyAdmin plugin addon that provides dedicated IP address management for the webhosting module. This package enables the purchase, assignment, and revocation of dedicated IP addresses on cPanel/WHM-based webhosting servers through the MyAdmin service management platform.

## Features

- Sells dedicated IP addresses as an addon for webhosting services
- Automatically assigns available IPs from the WHM server pool when enabled
- Reverts to shared IP when the addon is disabled or canceled
- Sends administrative email notifications for IP assignment events and errors
- Integrates with the MyAdmin settings system for configurable IP pricing
- Supports Symfony EventDispatcher hooks for seamless plugin integration

## Installation

Install via Composer:

```sh
composer require detain/myadmin-ip-webhosting-addon
```

## Requirements

- PHP >= 5.0
- ext-soap
- symfony/event-dispatcher ^5.0
- detain/myadmin-plugin-installer

## Usage

The plugin registers itself through the MyAdmin plugin system using Symfony EventDispatcher hooks. Once installed, it automatically integrates with the webhosting module to provide dedicated IP addon functionality.

The plugin registers two event hooks:

- `webhosting.load_addons` -- registers the dedicated IP addon with the service handler
- `webhosting.settings` -- adds the IP cost configuration to the module settings

## Testing

```sh
composer install
vendor/bin/phpunit
```

## License

This package is licensed under the [LGPL-2.1-only](https://opensource.org/licenses/LGPL-2.1) license.
