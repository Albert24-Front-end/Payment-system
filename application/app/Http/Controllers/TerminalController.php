<?php

namespace App\Http\Controllers;

use App\Data\Terminals\TerminalData;
use App\Http\Requests\Terminal\TerminalCreationRequest;
use App\Services\TerminalService;

class TerminalController extends Controller
{
    public function create(TerminalCreationRequest $request, TerminalService $terminalService)
    {
        $terminalService->createAuditLog(auth()->user(), $request->toDTO());
        return response()->json(["success" => true], 201);
    }
}
