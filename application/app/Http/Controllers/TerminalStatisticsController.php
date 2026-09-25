<?php

namespace App\Http\Controllers;

use App\Http\Requests\Statistics\TerminalStatisticsRequest;
use App\Models\Terminal;
use App\Services\Statistics\MerchantStatisticsService;
use Illuminate\Http\JsonResponse;

class TerminalStatisticsController extends Controller
{
    public function __invoke(TerminalStatisticsRequest $request, Terminal $terminal, MerchantStatisticsService $service): JsonResponse
    {
        return response()->json($service->getStatistics($terminal, $request->toDTO()));
    }
}
