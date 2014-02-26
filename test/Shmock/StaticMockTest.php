<?php

namespace Shmock;

/**
 * These tests must be run in separate processes due to the way that PHPUnit
 * generates mock class names. Since a failure in a prior test will get tracked
 * through to the next test case, we must run each in a separate process to prevent
 * collision.
 */
class StaticMockTest extends \PHPUnit_Framework_TestCase
{
    private static $staticClass = null;

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

    public function getPHPUnitStaticClass($clazz)
    {
        self::$staticClass = new PHPUnitStaticClass($this, $clazz);

        return self::$staticClass;
    }

    public function getClassBuilderStaticClass($clazz)
    {
        self::$staticClass = new ClassBuilderStaticClass($this, $clazz);

        return self::$staticClass;

    }

    /**
     * @return callable these callables return instances of Instance that specialize
     * on returning mocked static classes.
     */
    public function instanceProviders()
    {
        return [
          // [[$this, "getPHPUnitStaticClass"]], // requires process isolation
          [[$this, "getClassBuilderStaticClass"]]
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
        self::$staticClass = null;

    }

    private function assertMockObjectsShouldFail($message)
    {
        $threw = true;
        try {
            $this->verifyMockObjects();
            self::$staticClass->verify();
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
        $staticClass = $getClass("\Shmock\ClassToMockStatically");
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
            $staticClass->getAnInt()->will(function ($i) { return 999 + $i->parameters[0]; });
        });

        $this->assertEquals(1000, $mock::getAnInt(1));
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
            ])->any();
        });

        $this->assertEquals(3, $mock::multiply(1,2));
        $this->assertEquals(30, $mock::multiply(10, 20));

        $this->assertFailsMockExpectations(function () use ($mock) {
            $mock::multiply(10, 2);
        }, "Expected no match on the passed arguments");
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
    public function testThrowExceptionWillUseADefaultExceptionTypeIfNonePassed(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->throw_exception();
        });

        try {
            $mock::getAnInt();
            $this->fail("There should have been a logic exception thrown");
        } catch (\Exception $e) {
            if (preg_match('/PHPUnit.*/', get_class($e))) {
                throw $e;
            }
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
        if (strpos("PHPUnit", get_class($getClass[0])) !== 0) {
            $this->markTestSkipped("ordering not supported yet");
        }

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

    /**
     * @dataProvider instanceProviders
     */
    public function testArgumentsShouldBeEnforced($getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->multiply(2,2)->return_value(4);
        });

        $this->assertFailsMockExpectations(function () use ($mock) {
            $mock::multiply(2,3);
        }, "Expected the multiply call to err due to bad args");
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testArrayArgumentsShouldBeEnforced($getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->multiply([2,2],2)->any()->return_value([4,4]);
        });

        $this->assertSame([4,4], $mock::multiply([2,2],2));

        $this->assertFailsMockExpectations(function () use ($mock) {
            $mock::multiply([2,3],3);
        }, "Expected the multiply call to err due to bad args");
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testPHPUnitConstraintsAllowed($getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->multiply($this->isType("integer"), $this->greaterThan(2))->any()->return_value(10);
        });

        // just bare with me here...
        $this->assertSame(10, $mock::multiply(2, 4));
        $this->assertSame(10, $mock::multiply(10, 5));

        $this->assertFailsMockExpectations(function () use ($mock) {
            $mock::multiply(2.0, 1);
        }, "expected the underlying constraints to fail");
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testShmockCanSubclassFunctionsWithReferenceArgs($getClass)
    {
        $a = 5;
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->reference(5)->will(function ($inv) {
                return $inv->parameters[0] + 1;
            });
        });

        $a = $mock::reference($a);
        $this->assertSame(6, $a, "expected shmock to preserve reference semantics");
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testJuggledTypesAreConsideredMatches($getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->multiply("1", "2")->return_value(2);
        });

        $this->assertSame(2, $mock::multiply(1, 2.0));

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

    public static function reference(& $a)
    {
        $a++;
    }
}
