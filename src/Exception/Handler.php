<?php

declare(strict_types=1);

namespace BebelRouter\Exception;

use BebelRouter\Response;

class Handler
{
    public function handle(\Throwable $e, bool $debug = false): Response
    {
        $status = 500;
        if ($e instanceof HttpException) {
            $status = $e->getStatusCode();
        }

        if ($debug) {
            error_log(sprintf(
                "[BebelRouter] %s: %s\n%s",
                $e::class,
                $e->getMessage(),
                $e->getTraceAsString()
            ));
        }

        $messages = [
            400 => 'Requisição inválida',
            401 => 'Não autorizado',
            403 => 'Proibido',
            404 => 'Página não encontrada',
            405 => 'Método não permitido',
            413 => 'Conteúdo muito grande',
            414 => 'URI muito longa',
            422 => 'Entidade não processável',
        ];

        return Response::text($messages[$status] ?? 'Erro interno do servidor', $status);
    }
}
