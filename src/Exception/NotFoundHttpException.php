<?php

declare(strict_types=1);

namespace BebelRouter\Exception;

class NotFoundHttpException extends HttpException
{
    public function __construct(string $message = 'Not Found', ?\Exception $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
