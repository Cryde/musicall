# [MusicAll](https://www.musicall.com)

Community website powered by the Symfony 5 & PHP.  
MusicAll is a platform where people can share video, article, cours, search musician & talk with them.

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development.

### Prerequisites

Some Symfony & VueJS knowledges are recommended.  
You should run this projet with PHP 7.4 
```
php -v
PHP 7.4.8 (cli) (built: Jul 13 2020 16:37:51) ( NTS )
Copyright (c) The PHP Group
Zend Engine v3.4.0, Copyright (c) Zend Technologies
    with Zend OPcache v7.4.8, Copyright (c), by Zend Technologies
```

Be sure to have a least Node.js v12.18.3 (use [nvm](https://github.com/creationix/nvm) to have multiple versions of node)
```
node -v
```
Be sure to also have composer installed (locally or globally) :
```
composer --version
Composer version 1.10.7 2020-06-03 10:03:56
```

### Installing
 
Set up your environment. All of thoses commands have to be done in the projet root.

You will have to initialize your JWT configuration.   
Follow the instructions here (only "Generate the SSH keys" part) : https://github.com/lexik/LexikJWTAuthenticationBundle/blob/master/Resources/doc/index.md#generate-the-ssh-keys


Configure you ```.env.dev.local``` file (I only put important values here) :
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
