# [MusicAll](https://www.musicall.com)

[![codecov](https://codecov.io/gh/Cryde/musicall/branch/master/graph/badge.svg?token=7RK8UIL2RH)](https://codecov.io/gh/Cryde/musicall)

MusicAll is a platform where people can share videos, articles, courses, search musicians or band and talk with them.

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development.

This project use: 
- PHP 8.4
- Symfony 7.3
- MariaDB version 10.11
- node 20
- VueJS 3

### Installing

#### Setup local https

The application need to be run in HTTPS (for Facebook logins for instance)
Go to the root project, then launch :  
```
./bin/create_ssl_ca
./bin/create_ssl_cert musicall.local
```
You will only need to run this once

#### Setup Docker

You will need Docker to run this project.
Follow the Docker installation guide (https://docs.docker.com/get-docker/) to have it on your environment.

Go in the project root and run 
```
docker compose up -d
```
It will pull and build all the required images to run MusicAll

If you need to rebuild image (after an update for instance)
``` 
docker compose up --build
```

#### Setup the project

Add `musicall.local` and `musicall.test` to your `/etc/hosts`
```
10.200.200.2 	musicall.local musicall.test
```

Install PHP vendor
```
docker compose run --rm php-musicall composer install
```

Configure you ```.env.local``` file (I only put important values here) :
```
APP_ENV=dev
APP_SECRET=thisissecretchangeit
DATABASE_URL=mysql://user:password@db.musicall:3306/musicall
```

### Apply the fixtures
This will create the database schema and populate it with some random data.  
It will erase all the previous data in the database you had.
```
docker compose run --rm php-cli bin/console foundry:load-fixtures app
```
Run it every time before working on a MR or when you want to start from scratch.

### Migrations
If you applied the fixtures, you can skip this step.
Run the migrations to have the latest database schema change. Do it every time before working on a MR.
Do this step if you want to populate the database yourself.

Run the migrations
```
docker compose run --rm php-musicall bin/console doctrine:migration:migrate
```

### Assets 

You can either run everything through the docker or in your local by installation node via NVM
Install JS deps
```
docker compose run --rm node npm ci 
# or 
npm ci
```


Start the assets watcher
```
docker compose run --rm node npm run dev
# or
npm run dev
```
Or simply build 
```
docker compose run --rm node npm run build
# or
npm run build
```


You can now access https://musicall.local

[Learn how to use the application](doc/README).

### Tests

To run tests on your local : 
```
docker compose run --rm php-cli bin/phpunit
```

## TODO

- [ ] Https
