<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Statistics\AdminStatisticsRequest;
use App\Services\Statistics\AdminStatisticsService;
use Illuminate\Http\JsonResponse;

class StatisticsController extends Controller
{
    public function __invoke(AdminStatisticsRequest $request, AdminStatisticsService $service): JsonResponse
    {
        return response()->json($service->getStatistics($request->toDTO()));
    }
}
