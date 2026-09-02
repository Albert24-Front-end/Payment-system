<?php

namespace App\Http\Controllers;

use App\Data\Terminals\TerminalData;
use App\Http\Requests\Terminal\TerminalCreationRequest;
use App\Models\Terminal;
use App\Services\TerminalService;

class TerminalController extends Controller
{
    // преобразование коллекции экземпляров Terminal в массив и далее JSON
    public function index(TerminalService $terminalService)
    {
        return [
            "data" => $terminalService->getUserTerminals(auth()->user())
        ];
    }

    public function create(TerminalCreationRequest $request, TerminalService $terminalService)
    {
        $terminalService->createAuditLog(auth()->user(), $request->toDTO());
        return response()->json(["success" => true], 201);
    }

    public function update(TerminalCreationRequest $request, Terminal $terminal, TerminalService $terminalService)
    {
        $terminalService->updateTerminal(auth()->user(), $terminal, $request->toDTO());
        return ["success" => true];
    }

    public function delete(Terminal $terminal, TerminalService $terminalService)
    {
        $terminalService->deleteTerminal(auth()->user(), $terminal);
        return ["success" => true];
    }

    public function getSecretKey(TerminalService $terminalService, Terminal $terminal)
    {
        return response()->json([
            'secret_key' => $terminalService->getTerminalSecretKey($terminal),
        ]);
    }
}
