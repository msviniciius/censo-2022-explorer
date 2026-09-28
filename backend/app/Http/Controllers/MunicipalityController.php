<?php

namespace App\Http\Controllers;

use App\Services\MunicipalCensusService;
use App\Services\MunicipalitySearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class MunicipalityController extends Controller
{
    public function index(Request $request, MunicipalitySearchService $search): JsonResponse
    {
        $term = $request->query('q') ?? '';
        $input = ['q' => is_string($term) ? trim($term) : $term];
        $validated = Validator::make($input, ['q' => ['bail', 'string', 'max:100']], [
            'q.string' => 'O parâmetro q deve ser um texto.',
            'q.max' => 'O parâmetro q deve ter no máximo 100 caracteres.',
        ])->validate();

        return response()->json(['data' => $search->search($validated['q'])]);
    }

    public function show(string $cd_mun, MunicipalCensusService $census): JsonResponse
    {
        $municipality = $census->find($cd_mun);
        abort_if($municipality === null, 404);

        return response()->json(['data' => $municipality]);
    }
}
