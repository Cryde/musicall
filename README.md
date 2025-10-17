# [MusicAll](https://www.musicall.com)

[![codecov](https://codecov.io/gh/Cryde/musicall/branch/master/graph/badge.svg?token=7RK8UIL2RH)](https://codecov.io/gh/Cryde/musicall)
[![PHP Version](https://img.shields.io/badge/php-8.4-blue)](https://php.net)
[![Symfony](https://img.shields.io/badge/symfony-7.3-black)](https://symfony.com)
[![Node](https://img.shields.io/badge/node-20-green)](https://nodejs.org)

## About

MusicAll is a platform where people can share videos, articles, and courses, search for musicians or bands to collaborate with, and chat directly with them.

## Table of Contents

- [About](#about)
- [Tech Stack](#tech-stack)
- [Prerequisites](#prerequisites)
- [Quick Start](#quick-start)
- [Getting Started](#getting-started)
- [Development](#development)
- [Testing](#testing)
- [Contributing](#contributing)

## Tech Stack

This project uses:
- PHP 8.4
- Symfony 7.3
- MariaDB version 10.11
- Node 20
- Vue.js 3

## Prerequisites

Before you begin, ensure you have the following installed:
- Docker & Docker Compose ([Installation Guide](https://docs.docker.com/get-docker/))
- Git
- (Optional) Node 20+ via NVM for local asset development

## Quick Start

For experienced developers, here's the condensed setup:

```bash
# Add to /etc/hosts
echo "10.200.200.7 musicall.local musicall.test" | sudo tee -a /etc/hosts

# Start services
docker compose up -d

# Install dependencies
docker compose run --rm php-cli composer install
docker compose run --rm node npm ci

# Setup database with fixtures
docker compose run --rm php-cli bin/console foundry:load-fixtures app

# Fix storage permissions
chmod 775 -R public/images/ public/media/

# Build assets
docker compose run --rm node npm run dev

# Visit https://musicall.local
```

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development.

### Installing

#### 1. Setup Docker

Start Docker services:

```bash
docker compose up -d
```

This will pull and build all required images to run MusicAll.

If you need to rebuild images (after an update, for instance):

```bash
docker compose up --build
```

#### 2. Configure Hosts

Add `musicall.local` and `musicall.test` to your `/etc/hosts`:

```bash
10.200.200.7    musicall.local musicall.test
```

#### 3. Install Dependencies

Install PHP dependencies:

```bash
docker compose run --rm php-cli composer install
```

Install JavaScript dependencies:

```bash
docker compose run --rm node npm ci
# or locally if you have Node installed:
npm ci
```

#### 4. Setup Database

**Option A: Load Fixtures (Recommended for Development)**

This will create the database schema and populate it with random data. It will erase all previous data.

```bash
docker compose run --rm php-cli bin/console foundry:load-fixtures app
```

Run this every time before working on a merge request or when you want to start from scratch.

**Option B: Run Migrations **

If you want to populate the database yourself, run migrations instead:

```bash
docker compose run --rm php-cli bin/console doctrine:migration:migrate
```

#### 5. Setup Storage Permissions

Configure permissions for file storage:

```bash
chmod 775 -R public/images/ public/media/
```

If you encounter permission issues, adjust ownership:

```bash
sudo chown -R $USER:www-data public/images/ public/media/
```

#### 6. Build Assets

**Development Mode** (auto-rebuild on changes):

```bash
docker compose run --rm node npm run dev
# or locally:
npm run dev
```

**Production Build**:

```bash
docker compose run --rm node npm run build
# or locally:
npm run build
```

#### 7. Access the Application

You can now access the application at: **https://musicall.local**

[Learn how to use the application](doc/README)

## Development

### Database Management

**Reset database with fresh fixtures**:

```bash
docker compose run --rm php-cli bin/console foundry:load-fixtures app
```

**Run pending migrations**:

```bash
docker compose run --rm php-cli bin/console doctrine:migration:migrate
```

**Create a new migration**:

```bash
docker compose run --rm php-cli bin/console doctrine:migrations:diff
```

### Asset Development

You can run asset commands either through Docker or locally (if you have Node installed via NVM).

**Watch mode** (recommended during development):

```bash
docker compose run --rm node npm run dev
# or locally:
npm run dev
```

**Production build**:

```bash
docker compose run --rm node npm run build
# or locally:
npm run build
```

## Testing

### Run PHP Tests

```bash
docker compose run --rm php-cli bin/phpunit
```

### Generate Code Coverage Report

```bash
docker compose run --rm php-cli bin/phpunit --coverage-html coverage/
```

Then open `coverage/index.html` in your browser.

### Run Specific Test Suite

```bash
docker compose run --rm php-cli bin/phpunit tests/Unit
docker compose run --rm php-cli bin/phpunit tests/Functional
```

## Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run tests to ensure nothing breaks
5. Commit your changes following [Conventional Commits](https://www.conventionalcommits.org/)
6. Push to your branch
7. Open a Pull Request
