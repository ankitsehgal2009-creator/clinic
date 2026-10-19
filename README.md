# SmileDesk (Laravel)

Dental clinic software for India, as one shared installation that serves many clinics. Each clinic's data is separated by `clinic_id`.

- **Stack:** Laravel 12, PHP 8.2 or newer, MySQL or MariaDB. Plain Blade views with one CSS file and one JavaScript file, so there's no Node or build step and it runs on ordinary shared hosting.
- **Putting it live:** see [INSTALL.md](INSTALL.md).

## Run on a computer

```
composer install
cp .env.example .env    # set DB_CONNECTION=sqlite for a quick try, or your MySQL details
php artisan key:generate
php artisan migrate --seed   # --seed adds the demo clinic
php artisan serve            # http://localhost:8000
```

The demo logins all use the password `demo1234`:

| Role | Mobile |
| --- | --- |
| Admin | 9820077134 |
| Doctor | 9819022417 |
| Receptionist | 9004018822 |

## Where things are

| Path | What |
| --- | --- |
| `database/migrations` | All tables: clinics, users, patients, chairs, appointments, activities |
| `app/Http/Controllers` | One controller per area: Auth (login and setup), Patient, Import, Appointment, Staff, Settings, Activity, Dashboard, Api |
| `app/Http/Middleware/EnsurePage.php` | Blocks pages a person isn't allowed to open, and signs out turned-off accounts |
| `app/Support/Pages.php` | Page list, role defaults, "coming soon" list |
| `app/Support/SheetReader.php` | Reads CSV and .xlsx imports without extra libraries |
| `app/Console/Commands` | `smiledesk:install` (first setup on a server) and `smiledesk:backup` (daily SQL backup, keeps 14) |
| `resources/views` | Screens |
| `public/js/app.js` | Date picker with bold appointment dates, 15-minute slot picker, name suggestions, import mapping, toggles |

## Rules the code enforces

- **Permanent delete:** only the Admin can delete patients or appointments permanently. Others disable patients or cancel appointments.
- **Turning staff on or off:** the Admin and doctors can do it. Nobody can turn off their own account, and only the Admin can change the Admin.
- **Page access:** the Admin chooses which pages each person can open. Receptionists get Dashboard, Appointments and Patients by default.
- **Booking checks on the server:** the slot must be inside clinic hours, not in the past (India time), and not already taken on that chair.
- **Activity log:** every change writes one line saying who did what, and when.
