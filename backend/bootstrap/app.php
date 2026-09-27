<?php

use Illuminate\Database\SQLiteDatabaseDoesNotExistException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(api: __DIR__.'/../routes/api.php')
    ->withMiddleware()
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api', 'api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api', 'api/*')) {
                return null;
            }

            if ($exception instanceof ValidationException) {
                return response()->json([
                    'message' => 'Os parâmetros informados são inválidos.',
                    'errors' => $exception->errors(),
                ], 422);
            }

            if ($exception instanceof SQLiteDatabaseDoesNotExistException || $exception instanceof PDOException) {
                return response()->json(['message' => 'Base censitária indisponível.'], 503);
            }

            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : 500;

            return response()->json([
                'message' => $status === 404 ? 'Recurso não encontrado.' : 'Não foi possível atender à solicitação.',
            ], $status);
        });
    })->create();
