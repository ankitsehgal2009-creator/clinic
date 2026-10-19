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
| `database/migrations` | All tables: clinics, users, patients, chairs, appointments, activities, then billing (price list, invoices, invoice items, payments, cash payouts, cash closings) |
| `app/Http/Controllers` | One controller per area: Auth (login and setup), Patient, Import, Appointment, Staff, Settings, Activity, Dashboard, Api, Billing (bills, payments, price list), Cash (daily closing) |
| `app/Http/Middleware/EnsurePage.php` | Blocks pages a person isn't allowed to open, and signs out turned-off accounts |
| `app/Support/Pages.php` | Page list, role defaults, "coming soon" list |
| `app/Support/Money.php` | Money in paise, Indian number format (₹1,23,456), amount in words, financial year, GST line maths |
| `app/Support/SheetReader.php` | Reads CSV and .xlsx imports without extra libraries |
| `app/Console/Commands` | `smiledesk:install` (first setup on a server) and `smiledesk:backup` (daily SQL backup, keeps 14) |
| `resources/views` | Screens |
| `public/js/app.js` | Date picker with bold appointment dates, 15-minute slot picker, name suggestions, import mapping, toggles |

## Rules the code enforces

- **Permanent delete:** only the Admin can delete patients or appointments permanently. Others disable patients or cancel appointments.
- **Turning staff on or off:** the Admin and doctors can do it. Nobody can turn off their own account, and only the Admin can change the Admin.
- **Page access:** the Admin chooses which pages each person can open. Receptionists get Dashboard, Appointments and Patients by default.
- **Booking checks on the server:** the slot must be inside clinic hours, not in the past (India time), and not already taken on that chair.
- **Bills:** money is stored in paise. Bill numbers run per financial year, like SC/26-27/0001, and restart on 1 April. Treatment is GST-exempt; a taxed line is split into CGST and SGST. A bill with only exempt lines prints as "Bill of Supply". Bills are never edited or deleted by staff, only cancelled (number kept); a bill with payments can't be cancelled until the admin removes the payments.
- **Cash closing:** expected cash = opening (what was left in the drawer at the last closing) + cash received − paid out. A mismatch needs a reason. Only the admin can reopen a closed day.
- **Activity log:** every change writes one line saying who did what, and when.
