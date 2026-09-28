<?php

namespace App\Queries;

use Illuminate\Database\DatabaseManager;

final class StateListQuery
{
    public function __construct(private DatabaseManager $database) {}

    /** @return list<array{cd_uf: string, nm_uf: string}> */
    public function all(): array
    {
        return $this->database->connection('census')
            ->table('uf')
            ->orderByRaw('census_normalize_name(nm_uf) ASC')
            ->orderBy('cd_uf')
            ->get(['cd_uf', 'nm_uf'])
            ->map(fn (object $row): array => [
                'cd_uf' => (string) $row->cd_uf,
                'nm_uf' => $row->nm_uf,
            ])->all();
    }
}
