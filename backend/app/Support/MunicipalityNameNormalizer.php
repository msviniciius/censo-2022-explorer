<?php

namespace App\Support;

use InvalidArgumentException;
use Normalizer;

final class MunicipalityNameNormalizer
{
    public static function normalize(string $name): string
    {
        $decomposed = Normalizer::normalize($name, Normalizer::FORM_D);
        if ($decomposed === false) {
            throw new InvalidArgumentException('Nome deve conter texto Unicode válido.');
        }

        return mb_strtolower(preg_replace('/\p{M}+/u', '', $decomposed), 'UTF-8');
    }
}
