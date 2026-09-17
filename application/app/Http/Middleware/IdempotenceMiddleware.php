<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use function PHPUnit\Framework\throwException;

class IdempotenceMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    // ответ backend по запросу должен быть сначала закэширован в памяти (в Redis, кэш БД или где-то еще с возможностью переноса данных ответа на диск)
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isMethod(Request::METHOD_POST)) return $next($request); // если запрос не put - переходим к следующему обработчику запроса (действию - другому мидлвару или контроллеру)

        if ($request->header("Idempotence-Key") === null) {
            return response()->json(["error" => "Idempotence-Key header is required"], Response::HTTP_NOT_FOUND);
        } // запросы, проходящие через этот миддлвар, должны содержать в заголовке Idempotence-Key

        // внедряем Atomic Lock для предотвращения race-condition - ошибки незаписи в кэш при одновременном прибытии двух запросов, в обработку берется 1 запрос, происходит блокировка на 60 сек, затем обрабатывается другой запрос
        $lock = Cache::lock("id-" . $request->header("Idempotence-Key"), 60);
        if (!$lock->get()) {
            return response()->json(["error" => "Request is already in progress"], status: 409); // если lock пытается активировать другой запрос, то будет ошибка
        }

        try {
        // обращение к кэшу
        $cached = Cache::get($request->header("Idempotence-Key"));
        if ($cached) {
            return response()->json($cached, Response::HTTP_OK); // возвращаем закэшированное
        }

        $response = $next($request); // переходим к след действию - контроллеру, если из кэша ничего не получили
        if (!$response->isSuccessful()) {
            return $response;
        } // не кэшируем неудачный ответ

        // кэшируем полученные данные
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            $data["duplicated"] = true; // дополняем, что если идет возврат из кэша, то это точно retry - инф-ия для фронта
            Cache::put($request->header("Idempotence-Key"), $data, now()->addDay()); // сохраняем ключ в кэш на сутки
        } else {
            throw new \Exception("Response is not a JsonResponse");
        }
        } finally {
            $lock->release(); // снимаем этот lock в любом случае после 60 сек
        }
        return $response;
    }
}
