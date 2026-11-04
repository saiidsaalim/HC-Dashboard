<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Setup dan Deployment

Definisi permission berada di `PermissionRegistry`. `permissions:sync` idempotent, tidak menjalankan seeder secara tersembunyi, dan mempertahankan konfigurasi role yang sudah disimpan. Opsi `--apply-defaults` hanya digunakan secara eksplisit ketika baseline role memang hendak diterapkan.

### Setup development baru

Pastikan `APP_ENV=local` atau `testing` dan database adalah database development lokal, lalu jalankan:

```bash
composer install
php artisan migrate:fresh --seed
php artisan permissions:sync
npm install
npm run build
```

Seeder berisi akun dan password contoh. Jangan gunakan database production atau database eksternal untuk perintah tersebut.

### Reset development setelah perubahan schema ini

Migration awal movement pernah diubah selama fase development. Karena migration yang sudah tercatat tidak dijalankan ulang oleh `php artisan migrate`, lakukan reset satu kali hanya jika data development boleh dihapus:

```bash
php artisan optimize:clear
php artisan migrate:fresh --seed
php artisan permissions:sync
```

Perintah ini menghapus seluruh data pada database aktif. Jangan jalankan jika environment atau target database meragukan.

### Update development biasa

Setelah schema development sudah di-reset satu kali, pembaruan biasa menggunakan:

```bash
composer install
php artisan migrate
php artisan permissions:sync
php artisan optimize:clear
npm install
npm run build
```

Database yang sudah berisi data nyata tidak boleh memakai `migrate:fresh`. Perubahan schema berikutnya wajib menggunakan forward migration yang aman dan telah diuji.

### Deployment production baru

Gunakan database production kosong dan jalankan:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan permissions:sync --apply-defaults
php artisan optimize
npm ci
npm run build
```

Tinjau dan konfirmasi penerapan baseline role oleh `permissions:sync --apply-defaults`. Untuk update production berikutnya, gunakan `permissions:sync` tanpa opsi tersebut agar konfigurasi role tersimpan tidak ditimpa.

Jangan pernah menjalankan `php artisan db:seed`, `php artisan migrate --seed`, atau `php artisan migrate:fresh --seed` di production. Akun Super Admin production harus dibuat melalui prosedur aman terpisah, bukan melalui `DatabaseSeeder`.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
