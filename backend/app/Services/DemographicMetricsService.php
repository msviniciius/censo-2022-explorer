<?php

namespace App\Services;

final class DemographicMetricsService
{
    /**
     * @param array{total_setores: int, homens_sum: int|null, mulheres_sum: int|null, homens_com_valor: int, mulheres_com_valor: int} $totals
     * @return array{homens: array{valor: int|null, percentual: float|null, setores_com_valor: int, total_setores: int, estado: string}, mulheres: array{valor: int|null, percentual: float|null, setores_com_valor: int, total_setores: int, estado: string}}
     */
    public function compose(array $totals): array
    {
        $men = $totals['homens_sum'];
        $women = $totals['mulheres_sum'];
        // Preserve SQL NULL semantics: PHP would otherwise treat null as zero in addition.
        $sexTotal = $men === null || $women === null ? null : $men + $women;
        $count = $totals['total_setores'];

        return [
            'homens' => $this->metric($men, $totals['homens_com_valor'], $count, $sexTotal),
            'mulheres' => $this->metric($women, $totals['mulheres_com_valor'], $count, $sexTotal),
        ];
    }

    /** @return array{valor: int|null, percentual: float|null, setores_com_valor: int, total_setores: int, estado: string} */
    private function metric(?int $value, int $known, int $total, ?int $sexTotal): array
    {
        $available = $total > 0 && $known > 0;

        return [
            'valor' => $available ? $value : null,
            'percentual' => ! $available || $sexTotal === null || $sexTotal === 0
                ? null : $value / $sexTotal * 100.0,
            'setores_com_valor' => $known,
            'total_setores' => $total,
            'estado' => ! $available ? 'indisponivel' : ($known === $total ? 'completo' : 'parcial'),
        ];
    }
}
