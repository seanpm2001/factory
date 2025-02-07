<?php

declare(strict_types=1);

namespace Yiisoft\Factory\Tests\Unit;

use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;
use Yiisoft\Factory\FactoryInternalContainer;
use Yiisoft\Test\Support\Container\SimpleContainer;

final class FactoryInternalContainerTest extends TestCase
{
    public function dataHas(): array
    {
        return [
            'no "test" in the factory with empty container' => [
                false,
                new SimpleContainer(),
            ],
            '"test" is in the factory with container that has "test"' => [
                true,
                new SimpleContainer(['test' => new stdClass()]),
            ],
        ];
    }

    /**
     * @dataProvider dataHas
     */
    public function testHas(bool $expected, ?ContainerInterface $container): void
    {
        $factoryContainer = new FactoryInternalContainer($container);
        $this->assertSame($expected, $factoryContainer->has('test'));
    }

    public function testGetNonExistingDefinition(): void
    {
        $factoryContainer = new FactoryInternalContainer(new SimpleContainer());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No definition found for "non-exists".');
        $factoryContainer->getDefinition('non-exists');
    }
}
