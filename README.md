# Intercom

A small Slack-style chat app for a company intranet: public and private channels,
direct messages, editing and deleting messages, and registration with email
verification.

Stack: Laravel 10, Livewire 3, Tailwind (loaded from the CDN, so there is no
front-end build step). Live updates are done with polling rather than websockets.

## Requirements

- PHP 8.1+
- Composer
- The `pdo_sqlite` extension

## Setup

```bash
composer install
cp .env.example .env          # copy .env.example .env on Windows
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Then open http://127.0.0.1:8000.

## Demo accounts

The seeder creates a handful of users. The password for all of them is `password`.

| Email                  | Role     |
| ---------------------- | -------- |
| admin@company.test     | admin    |
| korisnik1@company.test | employee |
| korisnik2@company.test | employee |
| korisnik3@company.test | employee |
| korisnik4@company.test | employee |
| korisnik5@company.test | employee |

`#general`, `#random` and `#razvoj` are public channels; `#menadzment` is private.
Anyone who registers is added to every public channel automatically.

## Email

Locally `MAIL_MAILER=log`, so verification emails go to `storage/logs/laravel.log`
and the `/email/verify` page shows a working verification link. For real delivery,
fill in the SMTP block in `.env` (there is a Brevo example in `.env.example`). In
production also set `QUEUE_CONNECTION=database` and run `php artisan queue:work`.

## Notes

- Most of the chat logic is in `app/Livewire/Chat.php`, the UI in
  `resources/views/livewire/chat.blade.php`.
- The interface text is in Macedonian.
