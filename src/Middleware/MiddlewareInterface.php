<?php

declare(strict_types=1);

namespace BebelRouter\Middleware;

use BebelRouter\Request;
use BebelRouter\Response;

interface MiddlewareInterface
{
    public function process(Request $request, callable $next): Response;
}
