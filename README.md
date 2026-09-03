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

## Roles and channel membership

The first account to register is an `admin`; everyone else is an `employee`.

- **Private channels are admin-only** — they don't appear at all for non-admins. Admins see
  and can post in every channel, public or private.
- Admins can promote or demote other admins from the profile popup.
- Admins can add and remove channel members from the "Членови" button in a channel header.
- On a public channel, any user can suggest a person for membership from that panel; an
  admin approves or rejects it (a badge shows on the channel while one is pending).

## Chat

- Messages can carry an image (paperclip in the composer, stored on the `public` disk —
  run `php artisan storage:link`).
- Any message can be reacted to: a quick bar of eight emoji on hover, or the full picker
  behind the `＋`.
- DMs show a "Видено" line once the other person has read your latest message; channels
  show "Видено од N" under the last message.
- Presence is online / away / do-not-disturb / invisible. It goes to *away* after two
  minutes with no activity; you can also set it yourself from the menu under your name
  ("Автоматски" hands control back). Do-not-disturb silences incoming toasts; invisible
  shows you as offline to everyone else.

## Email

Locally set `MAIL_MAILER=log` to write verification emails to
`storage/logs/laravel.log` instead of sending them. For real delivery, fill in the SMTP
block in `.env` (there is a Brevo example in `.env.example`). In production also set
`QUEUE_CONNECTION=database` and run `php artisan queue:work`.

## Notes

- Most of the chat logic is in `app/Livewire/Chat.php`, the UI in
  `resources/views/livewire/chat.blade.php`.
- The interface text is in Macedonian.
