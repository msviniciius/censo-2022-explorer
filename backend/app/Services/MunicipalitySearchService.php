<?php

namespace App\Services;

use App\Queries\MunicipalitySearchQuery;

final class MunicipalitySearchService
{
    public function __construct(private MunicipalitySearchQuery $query) {}

    /** @return list<array{cd_mun: string, nm_mun: string, cd_uf: string, nm_uf: string}> */
    public function search(string $term): array
    {
        return $this->query->search($term);
    }
}
