# [MusicAll](https://www.musicall.com)

[![codecov](https://codecov.io/gh/Cryde/musicall/branch/master/graph/badge.svg?token=7RK8UIL2RH)](https://codecov.io/gh/Cryde/musicall)

Community website powered by the Symfony 6.2 & PHP8.1.
MusicAll is a platform where people can share videos, articles, courses, search musicians or band & talk with them.

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development.

This project use: 
- PHP 8.1
- Symfony 6.2
- MariaDB version 10.6
- node 16
- VueJS 2.7

### Installing

#### Setup Docker

You will need Docker to run this project.
Follow the Docker installation guide (https://docs.docker.com/get-docker/) to have it on your environment.

Once it's done, go in the project root and run 
```
docker compose up -d
```
It will pull and build all the required images to run MusicAll

#### Setup the project

Add `musicall.localhost` to your `/etc/hosts`
```
127.0.0.1 	musicall.localhost
```

Install PHP vendor
```
docker compose run --rm php-musical composer install
```

You will have to initialize your JWT configuration.   
Follow the instructions here (only "Generate the SSH keys" part) : https://github.com/lexik/LexikJWTAuthenticationBundle/blob/2.x/Resources/doc/index.rst#generate-the-ssl-keys  
**Note**: you will have to run some php command inside docker (eg: `docker compose run --rm php-musical bin/console lexik:jwt:generate-keypair`)

Configure you ```.env.local``` file (I only put important values here) :
```
APP_ENV=dev
APP_SECRET=thisissecretchangeit
DATABASE_URL=mysql://user:password@db.musicall:3306/musicall
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=thepassphrase
```

Install JS deps
```
npm ci
```

Run the migrations
```
docker compose run --rm php-musical bin/console doctrine:migration:migrate
```

Start the assets watcher
```
npm run dev-server
```

You can now access http://musicall.localhost

## TODO

- [ ] Create fixtures
