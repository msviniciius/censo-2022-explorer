<?php

namespace App\Services;

use App\Queries\StateListQuery;

final class StateListService
{
    public function __construct(private StateListQuery $query) {}

    /** @return list<array{cd_uf: string, nm_uf: string}> */
    public function all(): array
    {
        return $this->query->all();
    }
}
