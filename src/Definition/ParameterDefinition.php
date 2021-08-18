<?php

declare(strict_types=1);

namespace Yiisoft\Factory\Definition;

use Psr\Container\ContainerExceptionInterface;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Yiisoft\Factory\DependencyResolverInterface;
use Yiisoft\Factory\Exception\InvalidConfigException;
use Yiisoft\Factory\Exception\NotInstantiableException;

final class ParameterDefinition implements DefinitionInterface
{
    private ReflectionParameter $parameter;

    public function __construct(ReflectionParameter $parameter)
    {
        $this->parameter = $parameter;
    }

    public function isVariadic(): bool
    {
        return $this->parameter->isVariadic();
    }

    public function isOptional(): bool
    {
        return $this->parameter->isOptional();
    }

    public function hasValue(): bool
    {
        return $this->parameter->isDefaultValueAvailable() || $this->parameter->allowsNull();
    }

    public function resolve(DependencyResolverInterface $container)
    {
        $type = $this->parameter->getType();

        if ($type === null) {
            return $this->resolveNotObject();
        }

        // PHP 8 union type is used as type hint
        /** @psalm-suppress UndefinedClass, TypeDoesNotContainType */
        if ($type instanceof ReflectionUnionType) {
            $types = [];
            /** @var ReflectionNamedType $unionType */
            foreach ($type->getTypes() as $unionType) {
                if (!$unionType->isBuiltin()) {
                    $typeName = $unionType->getName();
                    if ($typeName === 'self') {
                        // If type name is "self", it means that called class and
                        // $parameter->getDeclaringClass() returned instance of `ReflectionClass`.
                        /** @psalm-suppress PossiblyNullReference */
                        $typeName = $this->parameter->getDeclaringClass()->getName();
                    }

                    $types[] = $typeName;
                }
            }

            if ($types === []) {
                return $this->resolveNotObject();
            }

            /** @psalm-suppress MixedArgument */
            return $this->resolveObject($container, ...$types);
        }

        /** @var ReflectionNamedType $type */

        // Our parameter has a class type hint
        if (!$type->isBuiltin()) {
            $typeName = $type->getName();
            if ($typeName === 'self') {
                // If type name is "self", it means that called class and
                // $parameter->getDeclaringClass() returned instance of `ReflectionClass`.
                /** @psalm-suppress PossiblyNullReference */
                $typeName = $this->parameter->getDeclaringClass()->getName();
            }

            return $this->resolveObject($container, $typeName);
        }

        return $this->resolveNotObject();
    }

    /**
     * @return mixed
     */
    private function resolveObject(DependencyResolverInterface $container, string ...$types)
    {
        foreach ($types as $type) {
            if ($container->has($type)) {
                $result = $container->get($type);
                if (!$result instanceof $type) {
                    $actualType = $this->getValueType($result);
                    throw new InvalidConfigException(
                        "Container returned incorrect type \"$actualType\" for service \"$this->class\"."
                    );
                }
                return $result;
            }
        }

        if ($this->parameter->isDefaultValueAvailable()) {
            return $this->parameter->getDefaultValue();
        }

        $this->throw();
    }

    /**
     * @return mixed
     */
    private function resolveNotObject()
    {
        if ($this->parameter->isDefaultValueAvailable()) {
            return $this->parameter->getDefaultValue();
        }

        if ($this->isOptional()) {
            throw new NotInstantiableException(
                sprintf(
                    'Can not determine default value of parameter "%s" when instantiating "%s" ' .
                    'because it is PHP internal. Please specify argument explicitly.',
                    $this->parameter->getName(),
                    $this->getCallable(),
                )
            );
        }

        $this->throw();
    }

    private function getType(): string
    {
        $type = $this->parameter->getType();

        if ($type === null) {
            return 'undefined';
        }

        /** @psalm-suppress UndefinedClass, TypeDoesNotContainType */
        if ($type instanceof ReflectionUnionType) {
            /** @var ReflectionNamedType[] */
            $namedTypes = $type->getTypes();
            $names = array_map(
                static fn (ReflectionNamedType $t) => $t->getName(),
                $namedTypes
            );
            return implode('|', $names);
        }

        /** @var ReflectionNamedType $type */

        return $type->getName();
    }

    private function getCallable(): string
    {
        $callable = [];

        $class = $this->parameter->getDeclaringClass();
        if ($class !== null) {
            $callable[] = $class->getName();
        }
        $callable[] = $this->parameter->getDeclaringFunction()->getName() . '()';

        return implode('::', $callable);
    }

    /**
     * @param mixed $value
     */
    private function getValueType($value): string
    {
        return is_object($value) ? get_class($value) : gettype($value);
    }

    /**
     * @throws NotInstantiableException
     */
    private function throw()
    {
        throw new NotInstantiableException(
            sprintf(
                'Can not determine value of the "%s" parameter of type "%s" when instantiating "%s". ' .
                'Please specify argument explicitly.',
                $this->parameter->getName(),
                ($this->parameter->allowsNull() ? '?' : '') . $this->getType(),
                $this->getCallable(),
            )
        );
    }
}
