<?php

declare(strict_types=1);

namespace BebelRouter\Exception;

class MethodNotAllowedException extends HttpException
{
    private ?string $allow;

    public function __construct(string $message = 'Method Not Allowed', ?string $allow = null, ?\Exception $previous = null)
    {
        parent::__construct($message, 405, $previous);
        $this->allow = $allow;
    }

    public function getAllow(): ?string
    {
        return $this->allow;
    }
}
