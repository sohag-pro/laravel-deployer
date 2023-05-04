<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<h1 align="center">
Laravel Deployer
</h1>

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

- Run the local development server
```bash
php artisan serve
```


