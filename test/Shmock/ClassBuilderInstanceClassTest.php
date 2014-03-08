<?php

namespace Shmock;

require_once 'MockChecker.php';


class ClassBuilderInstanceClassTest extends \PHPUnit_Framework_TestCase
{
    use MockChecker;

    protected function buildMock(callable $fn, $clazz = "\Shmock\ClassToMockAsAnInstance") {
        $instance = new ClassBuilderInstanceClass($this, $clazz);
        $fn($instance);
        return $instance->replay();
    }

    public function testInstancesCanBeMockedAsExpected()
    {
        $mock = $this->buildMock(function($instance) {
            $instance->add(1,2)->return_value(3);
        });

        $this->assertSame(3, $mock->add(1,2));
    }

    public function testConstructorArgumentsCanBeReplaced()
    {
        $mock = $this->buildMock(function($instance) {
            $instance->set_constructor_arguments(2,3);
        }, "\Shmock\ClassToMockAsInstanceWithArgs");

        $this->assertSame(5, $mock->sum());
    }

    public function testConstructorCanBeDisabled()
    {
        $mock = $this->buildMock(function($instance){
            $instance->disable_original_constructor();
        }, "\Shmock\ClassWithEvilConstructor");

        $this->assertTrue(is_a($mock, '\Shmock\ClassWithEvilConstructor'));
    }

    public function testShmockClassWithinShmockInstanceAllowsMocksOfStaticMethods()
    {
        $mock = $this->buildMock(function($instance) {
            $instance->shmock_class(function($clazz) {
                $clazz->multiply(2,3)->return_value(6);
            });
        });
        $clazz = get_class($mock);

        $this->assertSame(6, $clazz::multiply(2,3));
    }

}

class ClassToMockAsAnInstance
{
    public function add($x, $y)
    {

    }

    public static function multiply($a, $b)
    {

    }
}

class ClassToMockAsInstanceWithArgs
{
    private $a;
    private $b;

    public function __construct($a, $b)
    {
        $this->a = $a;
        $this->b = $b;
    }

    public function sum()
    {
        return $this->a + $this->b;
    }
}

class ClassWithEvilConstructor
{
    public function __construct()
    {
        throw new EvilException();
    }
}

class EvilException{}