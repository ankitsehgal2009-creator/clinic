<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Writes every SmileDesk table to storage/app/backups/smiledesk-YYYY-MM-DD.sql.gz and keeps the newest 14.
 * Pure PHP, so it works on shared hosting where mysqldump is not allowed. Restore by importing the .sql in phpMyAdmin.
 */
class BackupDatabase extends Command
{
    protected $signature = 'smiledesk:backup {--keep=14}';

    protected $description = 'Save a compressed SQL backup of all SmileDesk tables';

    private const TABLES = ['clinics', 'users', 'patients', 'chairs', 'appointments', 'activities'];

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $file = $dir.'/smiledesk-'.date('Y-m-d').'.sql.gz';
        $gz = gzopen($file.'.part', 'wb6');
        $pdo = DB::connection()->getPdo();
        gzwrite($gz, "-- SmileDesk backup ".date('Y-m-d H:i')." IST\n-- Import into an empty database that already has the SmileDesk tables (php artisan migrate).\nSET FOREIGN_KEY_CHECKS=0;\n");
        $rows = 0;
        foreach (array_reverse(self::TABLES) as $t) {
            gzwrite($gz, "DELETE FROM `$t`;\n");
        }
        foreach (self::TABLES as $t) {
            foreach (DB::table($t)->orderBy('id')->lazy(500) as $row) {
                $row = (array) $row;
                $vals = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row);
                gzwrite($gz, "INSERT INTO `$t` (`".implode('`,`', array_keys($row)).'`) VALUES ('.implode(',', $vals).");\n");
                $rows++;
            }
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);
        rename($file.'.part', $file);

        $all = glob($dir.'/smiledesk-*.sql.gz');
        rsort($all);
        foreach (array_slice($all, (int) $this->option('keep')) as $old) {
            unlink($old);
        }
        $this->info("Saved $rows rows to ".basename($file));
        return self::SUCCESS;
    }
}
