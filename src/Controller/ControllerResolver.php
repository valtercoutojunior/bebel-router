<?php

declare(strict_types=1);

namespace BebelRouter\Controller;

use BebelRouter\ContainerInterface;
use BebelRouter\Request;

final class ControllerResolver
{
    public function __construct(private ?ContainerInterface $container = null)
    {
    }

    /**
     * @param array<int|string, mixed>|string $action
     * @param array<string, string> $params
     */
    public function resolve(array|string $action, array $params, Request $request): mixed
    {
        if (is_array($action) && count($action) === 2) {
            [$class, $methodName] = $action;
            if (!is_string($class) || !is_string($methodName)) {
                throw new \RuntimeException('Ação de controller inválida');
            }
            if (!class_exists($class)) {
                throw new \RuntimeException("Controller não encontrado: {$class}");
            }
            $instance = $this->make($class);
            if (!method_exists($instance, $methodName)) {
                throw new \RuntimeException("Método não encontrado: {$methodName}");
            }
            $ref = new \ReflectionMethod($instance, $methodName);
            if (!$ref->isPublic()) {
                throw new \RuntimeException("Método não público: {$methodName}");
            }
            return $this->callMethod($instance, $methodName, $params, $request);
        }
        if (is_string($action) && str_contains($action, '@')) {
            [$class, $methodName] = explode('@', $action, 2);
            if (!class_exists($class)) {
                throw new \RuntimeException("Controller não encontrado: {$class}");
            }
            $instance = $this->make($class);
            if (!method_exists($instance, $methodName)) {
                throw new \RuntimeException("Método não encontrado: {$methodName}");
            }
            $ref = new \ReflectionMethod($instance, $methodName);
            if (!$ref->isPublic()) {
                throw new \RuntimeException("Método não público: {$methodName}");
            }
            return $this->callMethod($instance, $methodName, $params, $request);
        }
        throw new \RuntimeException('Tipo de ação não suportado');
    }

    private function make(string $class): object
    {
        if ($this->container !== null && $this->container->has($class)) {
            $obj = $this->container->get($class);
            if (is_object($obj)) {
                return $obj;
            }

            throw new \RuntimeException("Container retornou um valor inválido para {$class}");
        }

        if (!class_exists($class)) {
            throw new \RuntimeException("Controller não encontrado: {$class}");
        }

        $ref = new \ReflectionClass($class);
        if (!$ref->isInstantiable() || $ref->isInternal()) {
            throw new \RuntimeException("Classe não instanciável: {$class}");
        }

        $constructor = $ref->getConstructor();
        if ($constructor !== null && !$constructor->isPublic()) {
            throw new \RuntimeException("Construtor não público: {$class}");
        }
        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return new $class();
        }
        // Simple DI: try container for params
        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();
                if ($this->container !== null && $this->container->has($typeName)) {
                    $args[] = $this->container->get($typeName);
                    continue;
                }
                if (class_exists($typeName)) {
                    $args[] = $this->make($typeName);
                    continue;
                }
            }
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }
            throw new \RuntimeException("Não foi possível resolver dependência do construtor: {$param->getName()} em {$class}");
        }
        return $ref->newInstanceArgs($args);
    }

    /** @param array<string, string> $params */
    private function callMethod(object $instance, string $methodName, array $params, Request $request): mixed
    {
        $ref = new \ReflectionMethod($instance, $methodName);
        $paramsToPass = [];
        $routeParamValues = array_values($params);
        $routeParamKeys = array_keys($params);
        foreach ($ref->getParameters() as $i => $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();
                if (is_a($typeName, Request::class, true) || $typeName === Request::class) {
                    $paramsToPass[] = $request;
                    continue;
                }
                if ($this->container !== null && $this->container->has($typeName)) {
                    $paramsToPass[] = $this->container->get($typeName);
                    continue;
                }
            }
            // try route params by position/name
            if (isset($routeParamValues[$i])) {
                $paramsToPass[] = $routeParamValues[$i];
                continue;
            }
            $name = $param->getName();
            if (array_key_exists($name, $params)) {
                $paramsToPass[] = $params[$name];
                continue;
            }
            if ($param->isDefaultValueAvailable()) {
                $paramsToPass[] = $param->getDefaultValue();
                continue;
            }
            throw new \RuntimeException("Parâmetro obrigatório não resolvido: {$name} em {$methodName}");
        }
        return $ref->invokeArgs($instance, $paramsToPass);
    }
}
