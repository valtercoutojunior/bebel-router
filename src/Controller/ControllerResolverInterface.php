<?php

declare(strict_types=1);

namespace BebelRouter\Controller;

use BebelRouter\Request;

interface ControllerResolverInterface
{
    /**
     * @param array<int|string, mixed>|string $action
     * @param array<string, string> $params
     */
    public function resolve(array|string $action, array $params, Request $request): mixed;
}
