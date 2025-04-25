OpenAPI to Postman Collection Generator

A simple CLI tool that reads an OpenAPI v3 JSON specification and generates a Postman Collection v2.1 JSON file. Use it to automate creating Postman collections from your API definitions.

Prerequisites

PHP 8.0 or higher

Composer installed globally: https://getcomposer.org/

A terminal (bash, zsh, PowerShell, etc.)

Installation

1. Global install via Packagist

# Install the package globally so 'postman:generate' is available everywhere

composer global require adexyme/openapi-to-postman

Make sure your global Composer vendor/bin directory is in your PATH. For example on Unix/macOS:

export PATH="$HOME/.composer/vendor/bin:$PATH"

# or if using Composer 2

export PATH="$HOME/.config/composer/vendor/bin:$PATH"

After this, you can run:

postman:generate --help

to verify the command is registered.

2. Per-project install (local)

If you only want to use the tool inside a specific Laravel project:

In your Laravel project root, add a path repository in composer.json:

{
"repositories": [
{
"type": "path",
"url": "./packages/adexyme/openapi-to-postman",
"options": { "symlink": true }
}
],
"require": {
"adexyme/openapi-to-postman": "\*"
}
}

Install via Composer:

composer require adexyme/openapi-to-postman --prefer-source
composer dump-autoload

Verify:

php artisan list | grep postman:generate

Usage

Generate a Postman Collection JSON from your OpenAPI file:

# Basic usage:

postman:generate path/to/openapi.json

# Specify output filename:

postman:generate path/to/openapi.json output/postman_collection.json

# Group all requests under a folder name:

postman:generate openapi.json --folder="My API Endpoints"

input: Path to your OpenAPI v3 JSON file.

output (optional): Where to write the Postman collection (defaults to postman_collection.json in cwd).

--folder: Wraps all items in a Postman folder of the given name.

After running, import the generated JSON file into Postman.

Example

# Assuming openapi.json is in the current directory:

postman:generate openapi.json collections/my-api.postman.json --folder="Wallet & Transactions"

In Postman, go to Import → Upload File, select collections/my-api.postman.json, and your requests appear grouped under Wallet & Transactions.

Contributing

Fork the repository

Clone your fork and create a branch:

git clone https://github.com/your-vendor/openapi-to-postman.git
cd openapi-to-postman
git checkout -b feature/your-feature

3. Make changes and submit a pull request

---

## License

MIT © Your Name
