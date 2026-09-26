<?php

namespace App\Queries;

use Illuminate\Database\DatabaseManager;
use RuntimeException;

final class CensusReadinessQuery
{
    public function __construct(private DatabaseManager $database) {}

    public function assertReadable(): void
    {
        $connection = $this->database->connection('census');

        // Validate the required schema without aggregating or scanning the dataset.
        foreach ([
            'uf' => ['cd_uf', 'nm_uf'],
            'municipio' => ['cd_mun', 'nm_mun', 'cd_uf'],
            'setor' => ['cd_setor', 'cd_mun', 'situacao', 'area_km2', 'populacao'],
            'demografia' => ['cd_setor', 'moradores', 'homens', 'mulheres'],
        ] as $table => $columns) {
            $available = $connection->getSchemaBuilder()->getColumnListing($table);
            if (array_diff($columns, $available) !== []) {
                throw new RuntimeException('Esquema censitário incompatível: '.$table);
            }

            $connection->table($table)->select($columns)->limit(1)->get();
        }
    }
}
