<?php

declare(strict_types=1);

namespace Yiisoft\Factory\Definition;

use ReflectionClass;
use ReflectionException;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use Yiisoft\Factory\Exception\NotFoundException;
use Yiisoft\Factory\Exception\NotInstantiableClassException;
use Yiisoft\Factory\Exception\NotInstantiableException;

/**
 * This class resolves dependencies by using class type hints.
 * Note that service names need not match the parameter names, parameter names are ignored
 *
 * @internal
 */
final class DefinitionExtractor
{
    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @psalm-param class-string $class
     *
     * @throws NotFoundException
     * @throws NotInstantiableException
     *
     * @return DefinitionInterface[]
     * @psalm-return array<string, DefinitionInterface>
     */
    public function fromClassName(string $class): array
    {
        try {
            $reflectionClass = new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new NotFoundException($class);
        }

        if (!$reflectionClass->isInstantiable()) {
            throw new NotInstantiableClassException($class);
        }

        $constructor = $reflectionClass->getConstructor();
        return $constructor === null ? [] : $this->fromFunction($constructor);
    }

    /**
     * @return DefinitionInterface[]
     * @psalm-return array<string, DefinitionInterface>
     */
    private function fromFunction(ReflectionFunctionAbstract $reflectionFunction): array
    {
        $result = [];
        foreach ($reflectionFunction->getParameters() as $parameter) {
            $result[$parameter->getName()] = $this->fromParameter($parameter);
        }
        return $result;
    }

    private function fromParameter(ReflectionParameter $parameter): DefinitionInterface
    {
        // Our parameter does have a built-in type hint
        return new ParameterDefinition($parameter);
    }
}
