# Flysystem Copy-On-Write (COW) Adapter

[![Latest Stable Version](https://poser.pugx.org/ajgl/flysystem-cow/v/stable)](https://packagist.org/packages/ajgl/flysystem-cow)
[![Total Downloads](https://poser.pugx.org/ajgl/flysystem-cow/downloads)](https://packagist.org/packages/ajgl/flysystem-cow)
[![License](https://poser.pugx.org/ajgl/flysystem-cow/license)](LICENSE)
![QA checks](https://github.com/ajgl/flysystem-cow/actions/workflows/qa.yml/badge.svg)


Flysystem Copy-On-Write (COW) Adapter is a library that provides a copy-on-write mechanism for [Flysystem](https://flysystem.thephpleague.com/) adapters. It allows you to manage a layered filesystem where changes are written to a "top" layer while preserving the integrity of a "base" layer.

## Features

- **Copy-On-Write Mechanism**: Changes are written to a top layer, leaving the base layer untouched.
- **Integration with Flysystem**: Fully compatible with Flysystem v3.
- **Public and Temporary URLs**: Supports generating public and temporary URLs when the underlying adapters support it.

## Installation

Install the library using [Composer](https://getcomposer.org/):

```bash
composer require ajgl/flysystem-cow
```

## Usage

```php
<?php

require 'vendor/autoload.php';

use Ajgl\Flysystem\Cow\CowFilesystemAdapter;
use League\Flysystem\Config;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;

// Base adapter (read-only)
$baseAdapter = new InMemoryFilesystemAdapter();
$baseAdapter->write('example.txt', 'Hello, World!', new Config());

// Optional top adapter (write layer)
$topAdapter = new InMemoryFilesystemAdapter();

// Create the COW adapter
$cowAdapter = new CowFilesystemAdapter($baseAdapter, $topAdapter);

// Base file overwriting
$cowAdapter->write('example.txt', 'Hello, Planet!', new Config());
echo $cowAdapter->read('example.txt'); // Outputs: Hello, Planet!
echo PHP_EOL;

echo $baseAdapter->read('example.txt'); // Outputs: Hello, World!
echo PHP_EOL;

echo $topAdapter->read('example.txt'); // Outputs: Hello, Planet!
echo PHP_EOL;

// Base file deletion
$cowAdapter->delete('example.txt');
echo $cowAdapter->fileExists('example.txt') ? 'Yes' : 'No'; //Outputs: No
echo PHP_EOL;

echo $baseAdapter->fileExists('example.txt') ? 'Yes' : 'No'; //Outputs: Yes
echo PHP_EOL;

echo $topAdapter->fileExists('example.txt') ? 'Yes' : 'No'; //Outputs: No
echo PHP_EOL;
```

### License

This project is licensed under the [MIT License](LICENSE).

### Acknowledgments
This library is built on top of the excellent [Flysystem](https://github.com/thephpleague/flysystem) library by [@frankdejonge](https://github.com/frankdejonge).

---

Made with ❤️ by Antonio J. García Lagar.
