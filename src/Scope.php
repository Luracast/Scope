<?php

declare(strict_types=1);

/**
 * Copyright (c) 2017-2022 Arul Kumaran
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/Luracast/Scope
 */

namespace Luracast\Scope;

final class Scope
{
    public static array $classAliases = [];
    public static $resolver;
    public static $properties = [];
    private static $instances = [];
    private static $registry = [];

    public static function register(string $name, callable $function, bool $singleton = true): void
    {
        self::$registry[$name] = (object) \compact('function', 'singleton');
    }

    public static function set(string $name, object $instance): void
    {
        self::$instances[$name] = (object) ['instance' => $instance];
    }

    public static function get(string $name)
    {
        $r = null;

        if (\array_key_exists($name, self::$instances)) {
            $r = self::$instances[$name]->instance;
        } elseif (!empty(self::$registry[$name])) {
            $function = self::$registry[$name]->function;
            $r = $function();

            if (self::$registry[$name]->singleton) {
                self::$instances[$name] = (object) ['instance' => $r];
            }
        } elseif (\is_callable(self::$resolver) && false === \mb_stristr($name, 'Luracast\Restler')) {
            $fullName = $name;

            if (isset(self::$classAliases[$name])) {
                $fullName = self::$classAliases[$name];
            }

            /** @var callable $function */
            $function = self::$resolver;
            $r = $function($fullName);
            self::$instances[$name] = (object) ['instance' => $r];
            self::$instances[$name]->initPending = true;
        } else {
            $fullName = $name;

            if (isset(self::$classAliases[$name])) {
                $fullName = self::$classAliases[$name];
            }

            if (\class_exists($fullName)) {
                $shortName = self::getShortName($name);
                $r = new $fullName();
                self::$instances[$name] = (object) ['instance' => $r];
            }
        }

        return $r;
    }

    /**
     * Get fully qualified class name for the given scope.
     *
     * @param string $className
     * @param array  $scope     local scope
     *
     * @return bool|string returns the class name or false
     */
    public static function resolve($className, array $scope)
    {
        if (empty($className) || !\is_string($className)) {
            return false;
        }

        if (self::isPrimitiveDataType($className)) {
            return false;
        }

        $divider = '\\';

        if ($className[0] === $divider) {
            $qualified = \trim($className, $divider);
        } elseif (\array_key_exists($className, $scope)) {
            $qualified = $scope[$className];
        } else {
            $qualified = $scope['*'] . $className;
        }

        if (\class_exists($qualified)) {
            return $qualified;
        }

        if (isset(self::$classAliases[$className])) {
            $qualified = self::$classAliases[$className];

            if (\class_exists($qualified)) {
                return $qualified;
            }
        }

        return false;
    }

    public static function getShortName(string $className)
    {
        $className = \explode('\\', $className);

        return \end($className);
    }

    /**
     * @param string $stringName
     *
     * @return bool
     */
    private static function isPrimitiveDataType($stringName)
    {
        $primitiveDataTypes = ['Array', 'array', 'bool', 'boolean', 'float', 'int', 'integer', 'string'];

        return \in_array($stringName, $primitiveDataTypes, true);
    }
}
