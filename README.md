# [MusicAll](https://www.musicall.com)

[![codecov](https://codecov.io/gh/Cryde/musicall/branch/master/graph/badge.svg?token=7RK8UIL2RH)](https://codecov.io/gh/Cryde/musicall)

MusicAll is a platform where people can share videos, articles, courses, search musicians or band and talk with them.

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development.

This project use: 
- PHP 8.2
- Symfony 7.0
- MariaDB version 10.6
- node 20
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

If you need to rebuild image (after an update for instance)
``` 
docker compose up --build
```

#### Setup the project

Add `musicall.localhost` and `musicall.test` to your `/etc/hosts`
```
127.0.0.1 	musicall.localhost musicall.test
```

Install PHP vendor
```
docker compose run --rm php-musicall composer install
```

You will have to initialize your JWT configuration.   
Follow the instructions here (only "Generate the SSH keys" part) : https://github.com/lexik/LexikJWTAuthenticationBundle/blob/2.x/Resources/doc/index.rst#generate-the-ssl-keys  
**Note**: you will have to run some php command inside docker (eg: `docker compose run --rm php-musicall bin/console lexik:jwt:generate-keypair`)

Configure you ```.env.local``` file (I only put important values here) :
```
APP_ENV=dev
APP_SECRET=thisissecretchangeit
DATABASE_URL=mysql://user:password@db.musicall:3306/musicall
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=thepassphrase
```

### Migrations
Run the migrations to have the latest database schema change. Do it every time before working on a MR.

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


You can now access http://musicall.localhost

[Learn how to use the application](doc/index.MD).

## TODO

- [ ] Create fixtures
- [ ] Https
