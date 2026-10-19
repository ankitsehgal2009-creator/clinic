# Put SmileDesk on your hosting

This takes about 20 minutes. You need your hosting panel login and SSH access. Nothing has to be installed first: the zip already contains everything SmileDesk needs.

**What you need:** PHP 8.2 or newer (8.3 is ideal), one MySQL database, and a subdomain such as `app.yourclinic.in`.

## 1. Make a subdomain

In the hosting panel, open **Domains** or **Subdomains** and add one, for example `app.yourclinic.in`.

If the panel lets you choose the subdomain's folder (it may be called "Document root"), set it to:

```
smiledesk/public
```

This is **Option A**, and it's the best choice. If you can't choose the folder, that's fine. Note which folder the panel made, for example `app.yourclinic.in` or `public_html/app`, and follow **Option B** in step 3.

## 2. Make the database

Open **MySQL Databases**:

1. Create a database, for example `yourname_smiledesk`.
2. Create a database user with a strong password.
3. Add the user to the database and tick **All privileges**.

Write down the database name, the user and the password. The host is usually `localhost`.

## 3. Upload and unzip

Open **File Manager**.

- **Option A:** go to your home folder, the one that contains `public_html`. Upload `SmileDesk-upload.zip` there and choose **Extract**. You'll get a folder called `smiledesk`.
- **Option B:** go into the subdomain's folder. Upload `SmileDesk-upload.zip` there and choose **Extract**. Then open the new `smiledesk` folder, select everything inside it, including hidden files such as `.htaccess`, and move it up one level into the subdomain's folder. The file `artisan` should now sit directly in the subdomain's folder. The included `.htaccess` keeps the app's private files hidden from the web.

## 4. Run the installer (SSH)

Connect with SSH (your panel's **Terminal**, or PuTTY on Windows), then run:

```
cd smiledesk
php -v
php artisan smiledesk:install
```

For Option B, use `cd` with the subdomain's folder instead of `cd smiledesk`.

`php -v` must show 8.2 or higher. If it shows an older version, ask Serverbyt support for "the PHP 8.3 command for SSH". It's often something like `/opt/alt/php83/usr/bin/php`. Use that in place of `php` in every command.

The installer asks for:

- Your website address, such as `https://app.yourclinic.in`
- The database host, name, user and password from step 2

It then creates the tables. Answer **no** to the demo clinic for a real clinic.

## 5. Turn on HTTPS

In the panel, open **SSL** or **Let's Encrypt** and issue a free certificate for the subdomain.

## 6. Set up the clinic

Open `https://app.yourclinic.in/setup` and fill in the clinic, admin, doctor and receptionists. Everyone then signs in at `https://app.yourclinic.in` with their mobile number.

To add another clinic later, open `/setup` again while signed out. Each clinic only ever sees its own data.

## 7. Daily backup

Open **Cron Jobs** and add one that runs **once a day**, for example at 2:00 AM:

```
cd /home/YOURUSER/smiledesk && php artisan smiledesk:backup
```

Replace `/home/YOURUSER/smiledesk` with the full path to the folder from step 3. File Manager shows it at the top.

Backups are saved in `storage/app/backups`, one file per day. The last 14 days are kept. To keep a copy on your computer, download one from File Manager now and then.

**To restore a backup:** unzip the `.sql.gz` file, then in **phpMyAdmin** open the database, choose **Import** and pick the `.sql` file.

## Updating SmileDesk later

1. Upload the new zip and extract it over the old files. Keep your `.env` file and the `storage` folder.
2. Run these commands over SSH:

```
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## If something goes wrong

- **"500 Server Error":** open `storage/logs` in File Manager and send the newest log file. Also check that the `storage` and `bootstrap/cache` folders have permission 755.
- **"Could not connect to the database" in the installer:** the user isn't added to the database, or the password is wrong. Redo step 2 and run the installer again.
- **The page shows a list of files or "Index of":** you used Option B but the `.htaccess` file wasn't moved. Turn on "Show hidden files" in File Manager and move it too.
