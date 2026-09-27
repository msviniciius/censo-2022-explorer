<?php

namespace App\Providers;

use App\Support\MunicipalityNameNormalizer;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use PDO;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            if ($event->connection->getName() === 'census') {
                $pdo = $event->connection->getPdo();
                $pdo->exec('PRAGMA query_only = ON');
                $pdo->sqliteCreateFunction(
                    'census_normalize_name',
                    [MunicipalityNameNormalizer::class, 'normalize'],
                    1,
                    PDO::SQLITE_DETERMINISTIC,
                );
            }
        });
    }
}
