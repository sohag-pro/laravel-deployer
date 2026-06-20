# Contributing to Laravel Deployer

Thanks for your interest in improving Laravel Deployer! Bug reports, feature
ideas, documentation and code are all welcome.

## Ways to contribute

- **Bug reports** — open an issue with steps to reproduce, expected vs actual
  behaviour, and your PHP/Laravel/OS versions.
- **Feature requests** — open an issue describing the use case before building,
  so we can agree on the approach.
- **Security issues** — please report privately (do not open a public issue).
- **Pull requests** — see below.

## Development setup

```bash
git clone https://github.com/sohag-pro/laravel-deployer.git
cd laravel-deployer
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=AdminSeeder
php artisan serve
```

## Before opening a pull request

1. Keep changes focused — one logical change per PR.
2. Format your code:
   ```bash
   vendor/bin/pint
   ```
3. Run the test suite and add tests for new behaviour:
   ```bash
   vendor/bin/phpunit
   ```
4. Write a clear PR description explaining the *why*, not just the *what*.

## Coding guidelines

- Follow the existing style; `pint.json` defines the ruleset.
- This tool runs privileged shell commands — **never** interpolate
  user-controlled input into a shell string. Validate names and use
  `escapeshellarg` / Symfony `Process`.
- Add a feature test for anything that touches routing, auth, or shell
  execution.

By contributing, you agree that your contributions are licensed under the
project's [MIT License](LICENSE).
