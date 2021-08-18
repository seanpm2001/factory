<?php

declare(strict_types=1);

namespace Yiisoft\Factory\Tests\Unit\Definition;

use PHPUnit\Framework\TestCase;
use stdClass;
use Yiisoft\Factory\Definition\ClassDefinition;
use Yiisoft\Factory\Exception\InvalidConfigException;
use Yiisoft\Factory\Tests\TestHelper;
use Yiisoft\Test\Support\Container\SimpleContainer;

final class ClassDefinitionTest extends TestCase
{
    public function t1estResolveWithIncorrectTypeInContainer(): void
    {
        $definition = ClassDefinition::withDefaultValue(stdClass::class, true);

        $container = new SimpleContainer([stdClass::class => 42]);
        $dependencyResolver = TestHelper::createDependencyResolver($container);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(
            'Container returned incorrect type "integer" for service "' . stdClass::class . '".'
        );
        $definition->resolve($dependencyResolver);
    }
}
