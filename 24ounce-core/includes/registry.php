<?php
/**
 * 24Ounce Core - Service Registry
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TwentyFourOunce_Registry
{
    /**
     * Registered service factories
     *
     * @var array<string, callable>
     */
    private static array $factories = [];

    /**
     * Resolved service instances (singletons)
     *
     * @var array<string, object>
     */
    private static array $instances = [];

    /**
     * Register a service factory.
     *
     * @throws RuntimeException
     */
    public static function register(string $key, callable $factory): void
    {
        if (isset(self::$factories[$key])) {
            throw new RuntimeException(
                "Service '{$key}' is already registered."
            );
        }

        self::$factories[$key] = $factory;
    }

    /**
     * Resolve a service (lazy-loaded singleton).
     *
     * @throws RuntimeException
     */
    public static function get(string $key): object
    {
        if (!isset(self::$factories[$key])) {
            throw new RuntimeException(
                "Service '{$key}' is not registered."
            );
        }

        if (!isset(self::$instances[$key])) {
            $instance = call_user_func(self::$factories[$key]);

            if (!is_object($instance)) {
                throw new RuntimeException(
                    "Factory for service '{$key}' must return an object."
                );
            }

            self::$instances[$key] = $instance;
        }

        return self::$instances[$key];
    }

    /**
     * Check if a service is registered.
     */
    public static function has(string $key): bool
    {
        return isset(self::$factories[$key]);
    }

    /**
     * Reset registry state (intended for testing only).
     */
    public static function reset(): void
    {
        self::$factories = [];
        self::$instances = [];
    }
}
