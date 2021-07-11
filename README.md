# [MusicAll](https://www.musicall.com)

Community website powered by the Symfony 5 & PHP.  
MusicAll is a platform where people can share videos, articles, courses, search musicians or band & talk with them.

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development.

### Prerequisites

Some Symfony & VueJS knowledges are recommended.  
You should run this projet with PHP 8.0
```
php -v
PHP 8.0.8 (cli) (built: Jul  1 2021 16:14:13) ( NTS )
Copyright (c) The PHP Group
Zend Engine v4.0.8, Copyright (c) Zend Technologies
    with Zend OPcache v8.0.8, Copyright (c), by Zend Technologies
```

Be sure to have a least Node.js v14.17.1 (use [nvm](https://github.com/creationix/nvm) to have multiple versions of node)
```
node -v
```
Be sure to also have composer installed (locally or globally) :
```
composer --version
Composer version 2.0.8 2020-12-03 17:20:38
```

### Installing
 
Set up your environment. All of these commands have to be done in the project root.

You will have to initialize your JWT configuration.   
Follow the instructions here (only "Generate the SSH keys" part) : https://github.com/lexik/LexikJWTAuthenticationBundle/blob/master/Resources/doc/index.md#generate-the-ssh-keys


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
