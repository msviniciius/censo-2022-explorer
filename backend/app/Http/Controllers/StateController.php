<?php

namespace App\Http\Controllers;

use App\Services\StateCensusService;
use App\Services\StateListService;
use Illuminate\Http\JsonResponse;

final class StateController extends Controller
{
    public function index(StateListService $states): JsonResponse
    {
        return response()->json(['data' => $states->all()]);
    }

    public function show(string $cd_uf, StateCensusService $census): JsonResponse
    {
        $state = $census->find($cd_uf);
        abort_if($state === null, 404);

        return response()->json(['data' => $state]);
    }
}
