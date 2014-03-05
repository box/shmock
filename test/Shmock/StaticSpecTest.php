<?php

namespace Shmock;

use Shmock\ClassBuilder\ClassBuilder;
use Shmock\ClassBuilder\Invocation;

class StaticSpecTest extends \PHPUnit_Framework_TestCase
{
    private function buildPolicy($method, callable $fn)
    {
        if (!method_exists('\Shmock\Policy', $method)) {
            throw new \InvalidArgumentException("$method does not exist on policies");
        }

        $builder = new ClassBuilder();
        $builder->setExtends("\Shmock\Policy");

        $builder->addMethod($method, function (Invocation $i) use ($fn) {
            return $i->callWith($fn);
        }, ['$className', '$methodName', '$value', '$static']);

        $clazz = $builder->create();

        return new $clazz();
    }

    private function assertThrowsShmockException(callable $fn, $message)
    {
        try {
            $fn();
            $this->fail($message);
        } catch (FakeShmockException $e) {
            $this->assertTrue(true);
        }
    }

    public function testSpecWillVerifyArgumentsWithPolicies()
    {
        $policy = $this->buildPolicy('check_method_parameters', function ($className, $methodName, array $parameters, $static) {
            $this->assertSame(get_class($this), $className);
            $this->assertSame('aStaticMethod', $methodName);
            $this->assertTrue($static);

            if ([2,3,4] !== $parameters) {
                throw new FakeShmockException();
            }
        });

        $spec = new StaticSpec($this, get_class($this), 'aStaticMethod', [2,3,4], [$policy]);

        $this->assertThrowsShmockException(function () use ($policy) {
            $spec = new StaticSpec($this, get_class($this), 'aStaticMethod', [2,4,4], [$policy]);
        }, 'Should have failed to instantiate a spec with invalid arguments');
    }

    public function testSpecWillVerifyReturnValuesWithPolicies()
    {
        $policy = $this->buildPolicy('check_method_return_value', function ($className, $methodName, $retVal, $static) {
            $this->assertSame(get_class($this), $className);
            $this->assertSame('aStaticMethod', $methodName);
            $this->assertTrue($static);

            if ($retVal !== 10) {
                throw new FakeShmockException();
            }
        });

        $spec = new StaticSpec($this, get_class($this), 'aStaticMethod', [], [$policy]);
        $spec->return_value(10);

        $this->assertThrowsShmockException(function () use ($spec) {
            $spec->return_value(11);
        }, "Should have erred when mocking with an inappropriate value");
    }

    public function testSpecWillVerifyThrowsMatch()
    {
        $policy = $this->buildPolicy('check_method_throws', function ($className, $methodName, $exception, $static) {
            $this->assertSame(get_class($this), $className);
            $this->assertSame('aStaticMethod', $methodName);
            $this->assertTrue($static);

            if (get_class($exception) === 'Exception') {
                throw new FakeShmockException();
            }
        });

        $spec = new StaticSpec($this, get_class($this), 'aStaticMethod', [], [$policy]);
        $spec->throw_exception(new \InvalidArgumentException());

        $this->assertThrowsShmockException(function () use ($spec) {
            $spec->throw_exception(new \Exception());
        }, "Should have erred when mocking with an inappropriate value");

    }

    public static function aStaticMethod()
    {

    }
}

class FakeShmockException extends \Exception{}
