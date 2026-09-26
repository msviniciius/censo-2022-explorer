<?php

require '/var/www/html/vendor/autoload.php';

$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (! $app->make(App\Services\HealthService::class)->isAvailable()) {
    fwrite(STDERR, "Base censitária indisponível: verifique o arquivo e o esquema registrados nos logs.\n");
    exit(1);
}
