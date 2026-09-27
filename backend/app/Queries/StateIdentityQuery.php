<?php

namespace App\Queries;

use Illuminate\Database\DatabaseManager;

final class StateIdentityQuery
{
    public function __construct(private DatabaseManager $database) {}

    /** @return array{cd_uf: string, nm_uf: string}|null */
    public function find(string $code): ?array
    {
        $identity = $this->database->connection('census')
            ->table('uf')
            ->where('cd_uf', $code)
            ->first(['cd_uf', 'nm_uf']);

        return $identity === null ? null : [
            'cd_uf' => (string) $identity->cd_uf,
            'nm_uf' => $identity->nm_uf,
        ];
    }
}
