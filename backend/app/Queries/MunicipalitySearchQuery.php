<?php

namespace App\Queries;

use App\Support\MunicipalityNameNormalizer;
use Illuminate\Database\DatabaseManager;

final class MunicipalitySearchQuery
{
    public function __construct(private DatabaseManager $database) {}

    /** @return list<array{cd_mun: string, nm_mun: string, cd_uf: string, nm_uf: string}> */
    public function search(string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term, 'UTF-8') < 2) {
            return [];
        }

        $normalized = MunicipalityNameNormalizer::normalize($term);
        if ($normalized === '') {
            return [];
        }

        $pattern = '%'.strtr($normalized, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';

        // Normalize at read time: scans names without changing the census snapshot.
        return $this->database->connection('census')
            ->table('municipio as m')
            ->join('uf as u', 'u.cd_uf', '=', 'm.cd_uf')
            ->whereRaw("LENGTH(m.cd_mun) = 7 AND m.cd_mun NOT GLOB '*[^0-9]*'")
            ->whereRaw("TRIM(m.nm_mun) <> ''")
            ->whereRaw("census_normalize_name(m.nm_mun) LIKE ? ESCAPE '!'", [$pattern])
            ->orderByRaw('census_normalize_name(m.nm_mun) ASC')
            ->orderBy('m.cd_mun')
            ->limit(10)
            ->get(['m.cd_mun', 'm.nm_mun', 'u.cd_uf', 'u.nm_uf'])
            ->map(fn (object $row): array => [
                'cd_mun' => (string) $row->cd_mun,
                'nm_mun' => $row->nm_mun,
                'cd_uf' => (string) $row->cd_uf,
                'nm_uf' => $row->nm_uf,
            ])->all();
    }
}
