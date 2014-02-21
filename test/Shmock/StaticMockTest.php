<?php

namespace Shmock;

/**
 * These tests must be run in separate processes due to the way that PHPUnit
 * generates mock class names. Since a failure in a prior test will get tracked
 * through to the next test case, we must run each in a separate process to prevent
 * collision.
 * @runTestsInSeparateProcesses
 */
class StaticMockTest extends \PHPUnit_Framework_TestCase
{
    /**
     * plucked from https://github.com/sebastianbergmann/phpunit-mock-objects/blob/master/tests/MockObjectTest.php
     */
    private function resetMockObjects()
    {
        $refl = new \ReflectionObject($this);
        $refl = $refl->getParentClass();
        $prop = $refl->getProperty('mockObjects');
        $prop->setAccessible(true);
        $prop->setValue($this, array());
    }

    public function getPHPUnitStaticClass($tc, $clazz)
    {
        return new PHPUnitStaticClass($tc, $clazz);
    }

    /**
     * @return callable these callables return instances of Instance that specialize
     * on returning mocked static classes.
     */
    public function instanceProviders()
    {
        return [
          [[$this, "getPHPUnitStaticClass"]],
          // [new ClassBuilderStaticClass()]
        ];
    }

    private function assertFailsMockExpectations(callable $fn, $message)
    {
        $threw = true;
        try {
            $fn();
            $threw = false;
        } catch (\PHPUnit_Framework_AssertionFailedError $e) {

        }
        if (!$threw) {
            $this->fail("Expected callable to throw phpunit failure: $message");
        }
        $this->resetMockObjects();

    }

    private function assertMockObjectsShouldFail($message)
    {
        $threw = true;
        try {
            $this->verifyMockObjects();
            $threw = false;
        } catch ( \PHPUnit_Framework_AssertionFailedError $e) {

        }
        if (!$threw) {
            $this->fail("Expected mock objects to fail in PHPUnit: $message");
        }
        $this->resetMockObjects();
    }

    private function buildMockClass(callable $getClass, callable $setup)
    {
        $staticClass = $getClass($this, "\Shmock\ClassToMockStatically");
        $setup($staticClass);

        return $staticClass->replay();
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testMockClassesCanExpectMethodsBeInvoked(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->return_value(5);
        });

        $this->assertEquals(5, $mock::getAnInt());
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testFrequenciesCanBeEnforced(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->times(2)->return_value(10);
        });

        $this->assertEquals(10, $mock::getAnInt());
        $this->assertEquals(10, $mock::getAnInt());

        $this->assertFailsMockExpectations(function () use ($mock) {
            $mock::getAnInt();
        }, "the third invocation of getAnInt should have triggered the frequency check");

    }

    /**
     * @dataProvider instanceProviders
     */
    public function testMockClassesCanHaveAnyNumberOfInvocations(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->any()->return_value(15);
        });

        // should not fail here
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testMockClassesCanHaveExactlyZeroInvocations(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->never();
        });

        $this->assertFailsMockExpectations(function () use ($mock) {
            $mock::getAnInt();
        }, "expected to never invoke the mock object");
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testAtLeastOnceAllowsManyInvocations(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->at_least_once()->return_value(15);
        });

        $mock::getAnInt();
        $mock::getAnInt();
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testAtLeastOnceErrsWhenZeroInvocations(callable $getClass)
    {
        $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->at_least_once()->return_value(15);
        });

        $this->assertMockObjectsShouldFail("at least once should fail when there are no invocations");
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testNoExplicitFrequencyIsImpliedOnce(callable $getClass)
    {
        $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->return_value(4);
        });

        $this->assertMockObjectsShouldFail("implied once should fail when there are no invocations");
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testPassingCallableToWillCausesInvocationWhenMockIsUsed(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->will(function () { return 1000; });
        });

        $this->assertEquals(1000, $mock::getAnInt());
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testReturnValueMapWillRespondWithLastValuesInArrayGivenTheArguments(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->multiply()->return_value_map([
               [10, 20, 30],
               [1, 2, 3],
            ]);
        });

        $this->assertEquals(3, $mock::multiply(1,2));
        $this->assertEquals(30, $mock::multiply(10, 20));
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testReturnThisWillReturnTheClassItself(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->return_this();
        });

        $this->assertSame($mock, $mock::getAnInt());
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testThrowExceptionWillTriggerAnExceptionOnUse(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->throw_exception(new \LogicException());
        });

        try {
            $mock::getAnInt();
            $this->fail("There should have been a logic exception thrown");
        } catch (\LogicException $e) {

        }
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testReturnConsecutivelyReturnsValuesInASequence(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->return_consecutively([1,2,3]);
        });

        $this->assertEquals(1, $mock::getAnInt());
        $this->assertEquals(2, $mock::getAnInt());
        $this->assertEquals(3, $mock::getAnInt());
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testReturnShmockOpensNestedMockingFacility(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->return_shmock('\Shmock\ClassToMockStatically', function ($toMock) {
                // unimportant
            });
        });

        $nestedMock = $mock::getAnInt();
        $this->assertTrue(is_a($nestedMock,'\Shmock\ClassToMockStatically'));
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testUnmockedFunctionsRemainIntact(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
        });

        $this->assertSame(1, $mock::getAnInt());
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testUnmockedFunctionsElidedIfPreservationDisabled($getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->dont_preserve_original_methods();
        });

        $this->assertNull($mock::getAnInt());
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testOrderMattersWillEnforceCorrectOrderingOfCalls($getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->order_matters();
            $staticClass->getAnInt()->return_value(2);
            $staticClass->getAnInt()->return_value(4);
        });

        $this->assertSame(2, $mock::getAnInt());
        $this->assertSame(4, $mock::getAnInt());
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testOrderMattersWillPreventOutOfOrderCalls($getClass)
    {
        $this->markTestSkipped("This does not work on classes");
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->order_matters();
            $staticClass->getAnInt()->return_value(2);
            $staticClass->multiply(2, 2)->return_value(4);
        });

        $this->assertFailsMockExpectations(function () use ($mock) {
            $mock::multiply(2, 2);
        }, "Expected the multiply call to be out of order");
    }

}

class ClassToMockStatically
{
    private $a;

    public function __construct($a = null)
    {
        $this->a = $a;
    }

    public function getA()
    {
        return $this->a;
    }

    public static function getAnInt()
    {
        return 1;
    }

    public static function multiply($a, $b)
    {
        return $a * $b;
    }
}
