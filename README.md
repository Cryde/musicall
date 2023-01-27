# [MusicAll](https://www.musicall.com)

Community website powered by the Symfony 6.1 & PHP8.1.  
MusicAll is a platform where people can share videos, articles, courses, search musicians or band & talk with them.

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development.

### Prerequisites

Some Symfony & VueJS knowledges are recommended.  
You should run this projet with PHP 8.1
```
php -v
PHP 8.1.5 (cli) (built: Apr 21 2022 10:15:06) (NTS)
Copyright (c) The PHP Group
Zend Engine v4.1.5, Copyright (c) Zend Technologies
    with Zend OPcache v8.1.5, Copyright (c), by Zend Technologies
```

Be sure to have at least Node.js v16.16.0 (use [nvm](https://github.com/creationix/nvm) to have multiple versions of node)
```
node -v
```
Be sure to also have composer installed (locally or globally) :
```
composer --version
Composer version 2.3.10 2022-07-13 15:48:23
```

### Installing
 
Set up your environment. All of these commands have to be done in the project root.

You will have to initialize your JWT configuration.   
Follow the instructions here (only "Generate the SSH keys" part) : https://github.com/lexik/LexikJWTAuthenticationBundle/blob/2.x/Resources/doc/index.rst#generate-the-ssl-keys


Configure you ```.env.local``` file (I only put important values here) :
```
APP_ENV=dev
APP_SECRET=thisissecretchangeit
DATABASE_URL=mysql://user:password@127.0.0.1:3306/your-db-name
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=thepassphrase
```

Install PHP vendor
```
composer install
```

Install JS deps
```
npm ci
```

Run the migrations
```
bin/console doctrine:migration:migrate
```

Start the assets watcher
```
npm run dev-server
```

Start the local dev server
```
bin/console server:start
```
You will get a message like : ``` [OK] Server listening on http://127.0.0.1:8000```

## TODO

- [ ] Create fixtures
- [ ] Create a docker 
