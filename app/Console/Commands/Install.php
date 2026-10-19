<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/** One-time setup on a new server: asks for the MySQL details, writes .env, creates the tables. */
class Install extends Command
{
    protected $signature = 'smiledesk:install';

    protected $description = 'Connect SmileDesk to its MySQL database and create the tables';

    public function handle(): int
    {
        $env = base_path('.env');
        if (! is_file($env)) {
            copy(base_path('.env.example'), $env);
        }
        $this->info('SmileDesk setup. Use the database details from your hosting panel (MySQL Databases).');
        $url = rtrim($this->ask('Website address, e.g. https://app.yourclinic.in'), '/');
        $host = $this->ask('Database host', 'localhost');
        $name = $this->ask('Database name');
        $user = $this->ask('Database user');
        $pass = $this->secret('Database password');

        try {
            new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
        } catch (Throwable $e) {
            $this->error('Could not connect to the database: '.$e->getMessage());
            $this->line('Check the name, user and password, and that the user is added to the database with all privileges.');
            return self::FAILURE;
        }

        $this->setEnv($env, [
            'APP_URL' => $url, 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $host, 'DB_PORT' => '3306',
            'DB_DATABASE' => $name, 'DB_USERNAME' => $user, 'DB_PASSWORD' => '"'.addcslashes($pass, '"\\').'"',
        ]);
        if (! preg_match('/^APP_KEY=base64:/m', file_get_contents($env))) {
            Artisan::call('key:generate', ['--force' => true]);
        }
        Artisan::call('config:clear');
        config(['database.default' => 'mysql', 'database.connections.mysql' => array_merge(config('database.connections.mysql'), ['host' => $host, 'database' => $name, 'username' => $user, 'password' => $pass])]);
        DB::purge('mysql');

        $this->info('Creating tables…');
        Artisan::call('migrate', ['--force' => true], $this->output);
        if ($this->confirm('Add the demo clinic so you can try it? (you can skip this for a real clinic)', false)) {
            Artisan::call('db:seed', ['--force' => true], $this->output);
        }
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');
        $this->info("Done. Open $url/setup to set up the first clinic.");
        return self::SUCCESS;
    }

    private function setEnv(string $file, array $values): void
    {
        $text = file_get_contents($file);
        foreach ($values as $k => $v) {
            $line = "$k=$v";
            $text = preg_match("/^#?\s*$k=.*/m", $text) ? preg_replace("/^#?\s*$k=.*/m", str_replace('$', '\$', $line), $text, 1) : $text."\n$line";
        }
        file_put_contents($file, $text);
    }
}
