<?php

namespace App\Services;

use App\Queries\CensusReadinessQuery;
use Psr\Log\LoggerInterface;
use Throwable;

final class HealthService
{
    public function __construct(
        private CensusReadinessQuery $query,
        private LoggerInterface $logger,
    ) {}

    public function isAvailable(): bool
    {
        try {
            $this->query->assertReadable();

            return true;
        } catch (Throwable $exception) {
            $this->logger->warning('Falha ao consultar a base censitária.', [
                'reason' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
