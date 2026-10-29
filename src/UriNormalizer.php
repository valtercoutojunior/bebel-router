<?php

declare(strict_types=1);

namespace BebelRouter;

use BebelRouter\Exception\HttpException;

/**
 * Single source of truth for URI normalization used by both Request and RouteMatcher.
 */
final class UriNormalizer
{
    private const MAX_DECODE_PASSES = 3;
    private const HARD_MAX_LENGTH = 1048576;

    public static function normalize(string $uri): string
    {
        if (strlen($uri) > self::HARD_MAX_LENGTH) {
            throw new HttpException('URI muito longa', 414);
        }

        if (self::hasControlChars($uri)) {
            return '/';
        }

        $uri = self::decode($uri);
        if (self::hasControlChars($uri)) {
            return '/';
        }

        $uri = str_replace('\\', '/', $uri);
        $uri = preg_replace('#/+#', '/', $uri) ?? $uri;
        if ($uri === '') {
            return '/';
        }
        if ($uri[0] !== '/') {
            $uri = '/' . $uri;
        }

        foreach (explode('/', $uri) as $segment) {
            if ($segment === '..') {
                return '/';
            }
        }

        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        return $uri;
    }

    public static function normalizePattern(string $pattern): string
    {
        $pattern = str_replace('\\', '/', $pattern);
        $pattern = preg_replace('#/+#', '/', $pattern) ?? $pattern;
        if ($pattern === '') {
            $pattern = '/';
        }
        if (!str_starts_with($pattern, '/')) {
            $pattern = '/' . $pattern;
        }
        if ($pattern !== '/' && str_ends_with($pattern, '/')) {
            $pattern = rtrim($pattern, '/');
        }

        return $pattern;
    }

    private static function decode(string $uri): string
    {
        for ($i = 0; $i < self::MAX_DECODE_PASSES; $i++) {
            $decoded = rawurldecode($uri);
            if ($decoded === $uri) {
                break;
            }
            $uri = $decoded;
        }

        return $uri;
    }

    private static function hasControlChars(string $value): bool
    {
        return preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
    }
}
