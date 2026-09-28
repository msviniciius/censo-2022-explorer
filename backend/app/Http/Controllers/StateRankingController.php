<?php

namespace App\Http\Controllers;

use App\Services\StateRankingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class StateRankingController extends Controller
{
    public function __invoke(string $cd_uf, Request $request, StateRankingService $ranking): JsonResponse
    {
        abort_if(preg_match('/\A[0-9]{2}\z/', $cd_uf) !== 1, 404);

        $parameters = $request->query();
        $input = [
            'page' => array_key_exists('page', $parameters) ? $parameters['page'] : 1,
            'per_page' => array_key_exists('per_page', $parameters) ? $parameters['per_page'] : 25,
        ];
        $validated = validator($input, [
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', Rule::in([25, 50, 100])],
        ])->validate();

        $result = $ranking->forState($cd_uf, (int) $validated['page'], (int) $validated['per_page']);
        abort_if($result === null, 404);

        $perPage = (int) $validated['per_page'];

        return response()->json([
            'data' => $result['items'],
            'meta' => [
                'current_page' => (int) $validated['page'],
                'per_page' => $perPage,
                'total' => $result['total'],
                'last_page' => max(1, (int) ceil($result['total'] / $perPage)),
            ],
        ]);
    }
}
