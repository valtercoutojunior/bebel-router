<?php

declare(strict_types=1);

namespace BebelRouter\Exception;

class PayloadTooLargeException extends HttpException
{
    public function __construct(string $message = 'Payload Too Large', ?\Exception $previous = null)
    {
        parent::__construct($message, 413, $previous);
    }
}
