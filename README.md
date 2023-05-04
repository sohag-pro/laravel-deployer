<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<h1 align="center">
Laravel Deployer
</h1>

<p align="center"><a href="https://notes.sohag.pro/laravel-deployer-the-ultimate-deployment-tool-for-your-laravel-application" target="_blank"><img src="https://cdn.hashnode.com/res/hashnode/image/upload/v1683198012948/779395a1-89e6-44be-9a95-3d32322a8504.jpeg?auto=compress,format&format=webp" width="900" alt="Laravel Logo">
</a></p>
<p align="center"><a href="https://notes.sohag.pro/laravel-deployer-the-ultimate-deployment-tool-for-your-laravel-application" target="_blank">
<h2 align="center">Documentation</h2>
</a></p>


## Note to Contributors: We're actively seeking your contributions!

Thank you for your interest in our project. We welcome contributions from anyone and everyone! Whether you're a developer, designer, or just someone who wants to help out, there are many ways you can contribute.

We're currently seeking contributions in the following areas:
- Feature requests
- Bug reports
- Code contributions
- Documentation improvements

If you're interested in contributing, please feel free to open an issue.

We value all contributions and appreciate your help in making our project better. Thank you!


## About Laravel Deployer
It's a no downtime deployer for laravel. You just need to set it up on your server, you will get a UI to deploy your laravel application.



How to setup locally

-  Clone the repository
```bash
git clone https://github.com/sohag-pro/laravel-deployer.git
```

- Install dependencies
```bash
composer install
```

- Create a copy of your .env file
```bash
cp .env.example .env
```

- Generate an app encryption key
```bash
php artisan key:generate
```

- put the env variable in .env file
```bash
GIT_REMOTE_URL=https://github.com/sohag-pro/laravel-deployer.git
# full path from root from where the project will be server
# with a trailing slash
SERVE_DIR=/Users/sohag/Projects/deployer-test/www/

# full path from root from where the project will be cloned and all other operations will be done
# Should be different from SERVE_DIR and should be created manually before running the script
# with a trailing slash
BASE_DIR=/Users/sohag/Projects/deployer-test/

# where the backups will be stored
VERSION_DIR=backups

# where the storage will be linked
STORAGE_DIR=storage

# Where the dump SQL will be stored
DB_DIR=database

# which db to dump
DEPLOYER_DB_NAME=

# user to dump db
DEPLOYER_DB_USER=

# password to dump db
DEPLOYER_DB_PASSWORD=
```

- Run the local development server
```bash
php artisan serve
```

- Visit http://localhost:8000 in your browser

## License
The Laravel Deployer is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
