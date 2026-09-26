<?php

namespace App\Http\Controllers;

use App\Services\HealthService;
use Illuminate\Http\JsonResponse;

final class HealthController extends Controller
{
    public function __invoke(HealthService $health): JsonResponse
    {
        if (! $health->isAvailable()) {
            return response()->json(['message' => 'Base censitária indisponível.'], 503);
        }

        return response()->json(['data' => ['status' => 'ok']]);
    }
}
