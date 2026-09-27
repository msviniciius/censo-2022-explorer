<?php

namespace App\Queries;

use Illuminate\Database\DatabaseManager;

final class MunicipalityIdentityQuery
{
    public function __construct(private DatabaseManager $database) {}

    /** @return array{cd_mun: string, nm_mun: string, cd_uf: string, nm_uf: string}|null */
    public function find(string $code): ?array
    {
        $identity = $this->database->connection('census')
            ->table('municipio as m')
            ->join('uf as u', 'u.cd_uf', '=', 'm.cd_uf')
            ->where('m.cd_mun', $code)
            ->first(['m.cd_mun', 'm.nm_mun', 'u.cd_uf', 'u.nm_uf']);

        return $identity === null ? null : [
            'cd_mun' => (string) $identity->cd_mun,
            'nm_mun' => $identity->nm_mun,
            'cd_uf' => (string) $identity->cd_uf,
            'nm_uf' => $identity->nm_uf,
        ];
    }
}
