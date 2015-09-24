<?php
namespace Shmock;

/**
 * :D
 */
class ShmockTest extends \PHPUnit_Framework_TestCase
{
    public function testFooClassShouldBeAbleToStaticallyMockWeeweeFromWithinLala()
    {
        $foo = Shmock::create($this, '\Shmock\Shmock_Foo', function ($foo) {
            $foo->shmock_class(function ($Shmock_Foo) {
                $Shmock_Foo->weewee()->return_value(3);
            });
        });
        $this->assertEquals(3, $foo->lala());
    }

    public function testFooClassCanBeStaticallyMockedWithOrderMatters()
    {
        $foo = Shmock::create($this, '\Shmock\Shmock_Foo', function ($foo) {
            $foo->order_matters();
            $foo->shmock_class(function ($Shmock_Foo) {
                $Shmock_Foo->weewee()->return_value(3);
            });
            $foo->bar(3)->return_value(7);
            $foo->for_value_map(7,3)->return_value(23);
        });
        $this->assertEquals(23, $foo->staticSequentialCalls());
    }

    public function testFooClassShouldBeAbleToStaticallyMockWeewee()
    {
        $Shmock_Foo = Shmock::create_class($this, '\Shmock\Shmock_Foo', function ($Shmock_Foo) {
            $Shmock_Foo->weewee()->return_value(6);
        });
        $this->assertEquals(6, $Shmock_Foo::weewee());
    }

    public function ignoreTestReturnValueMapStubbedTwiceCalledOnce()
    {
        $this->markTestSkipped('Not sure how to find out that it should have thrown an exception');
        $foo = Shmock::create($this, '\Shmock\Shmock_Foo', function ($foo) {
            $foo->for_value_map()->return_value_map(array(
                array(1, 2, 3),
                array(2, 2, 3),
            ));
        });

        $this->assertEquals(3, $foo->for_value_map(2, 2), 'value map busted');
    }

    public function testReturnValueMapStubbedAndCalledTwice()
    {
        $foo = Shmock::create($this, '\Shmock\Shmock_Foo', function ($foo) {
            $foo->for_value_map()->return_value_map(array(
                array(1, 2, 3),
                array(2, 2, 3),
            ));
        });

        $this->assertEquals(3, $foo->for_value_map(1, 2), 'value map busted');
        $this->assertEquals(3, $foo->for_value_map(2, 2), 'value map busted');
    }

    public function testMockingNoMethodsAtAllShouldPreserveOriginalMethods()
    {
        $foo = Shmock::create($this, '\Shmock\Shmock_Foo', function ($foo) {
            $foo->disable_original_constructor();
        });
        $this->assertEquals(5, $foo::weewee());
    }

    public function testMockCanBeMadeOfAbstractClassIfAllMethodsAreDefined()
    {
        $foo = Shmock::create($this, '\Shmock\AbstractFoo', function ($foo) {
            $foo->bar()->return_value(1);
        });

        $this->assertSame(1, $foo->bar());
    }

    public function testClassMocksAndBeCreatedUsingCreateClassMethod()
    {
        $fooClass = Shmock::create_class($this, '\Shmock\Shmock_Foo', function ($fooClass) {
            $fooClass->weewee()->return_value(10);
        });

        $this->assertEquals(10, $fooClass::weewee());
    }

    public function testShmockVerifyWillAssertThatAllMocksCreatedHaveMetExpectations()
    {
        $fooClass = Shmock::create_class($this, '\Shmock\Shmock_Foo', function ($fooClass) {
            $fooClass->weewee()->twice()->return_value(10);
        });

        try {
            Shmock::verify();
            $this->fail("Expected verify to fail after failing to call the mock method");
        } catch (\PHPUnit_Framework_AssertionFailedError $e) {
        }

    }

    public function testClassInstanceFieldIsAlwaysSetOnShmocksWithCtorDisabled()
    {
        $foo = Shmock::create($this, '\Shmock\Shmock_Foo', function ($mock) {
            $mock->disable_original_constructor();
        });

        $this->assertFalse(property_exists($foo, 'foo'));
        $this->assertTrue(is_string($foo->class));
    }

    public function testWillClosureGetsProperParamsOnIt()
    {
        $foo = Shmock::create($this, '\Shmock\Shmock_Foo', function ($mock) {
            $mock->bar()->any()->will(function ($invocation) {
                $args = $invocation->parameters;
                return $args[0] * 4;
            });
        });

        $this->assertSame($foo->barPlusTwo(4), 18);
        $this->assertSame($foo->barPlusTwo(100), 402);
    }

    public function testUsersCanShmockNonExistentMagicMethods()
    {
        $foo = Shmock::create($this, '\Shmock\Shmock_Foo', function ($mock) {
            $mock->magic()->return_value(2);
        });
        $this->assertSame($foo->doMagic(), 84);
    }

    public function testShmockCanMockInterfaces()
    {
        $foo = Shmock::create($this, '\Shmock\Empty_Foo', function ($mock) {
            $mock->foo()->return_value(42);
        });
        $this->assertSame($foo->foo(), 42);
    }

    public function testShmockCanHandleOrderMattersNotFirst()
    {
        $foo = Shmock::create($this, '\Shmock\Shmock_Foo', function ($mock) {
            $mock->doMagic();
            $mock->magic();
            $mock->bar(42);
            $mock->lala();
            $mock->order_matters();
        });
        $foo->sequentialCalls();
    }

    public function testShmockCanHandleOrderMattersAndThrowExceptionWhenWrong()
    {
        $foo = Shmock::create($this, '\Shmock\Shmock_Foo', function ($mock) {
            $mock->magic();
            $mock->doMagic();
            $mock->bar(42);
            $mock->lala();
            $mock->order_matters();
        });
        $thrown = false;
        try {
            $foo->sequentialCalls();
        } catch (\PHPUnit_Framework_AssertionFailedError $e) {
            $thrown = true;
        }
        $this->assertTrue($thrown);

        $thrown = false;
        try {
            Shmock::verify();
        } catch (\Exception $e) {
            $thrown = true;
        }
        $this->assertTrue($thrown);
    }

    public function testShmockCannotMockStaticPrivateMethodsNormally()
    {
        $testCaseShmock = Shmock::create(
                $this,
                '\PHPUnit_Framework_TestCase',
                function ($mock) {
                    $mock->shmock_class(function ($smock) {
                        $smock->assertFalse(true); // this would normally cause the test to fail, this ensures that it normally would happen
                    });
                }
        );

        $foo = Shmock::create_class(
                $testCaseShmock,
                '\Shmock\Shmock_Foo',
                function ($mock) {
                    $mock->sBar()->return_value(2);
                }
        );

        $foo::sFoo();
    }

    public function testShmockCanMockStaticPrivateMethodsWithEscapeFlag()
    {
        Shmock::$disable_strict_method_checks_for_static_methods = true;
        $foo = Shmock::create_class(
                $this,
                '\Shmock\Shmock_Foo',
                function ($mock) {
                    $mock->sBar()->return_value(2);
                }
        );
        Shmock::$disable_strict_method_checks_for_static_methods = false;

        $this->assertEquals($foo::sFoo(), 2);
    }

    public function tearDown()
    {
        Shmock::verify();
    }
}

class Shmock_Foo
{
    public function __construct()
    {
        $this->foo = "42";
    }

    public static function sFoo()
    {
        return static::sBar();
    }

    private static function sBar()
    {
        return 42;
    }

    public function lala()
    {
        return static::weewee();
    }

    public function for_value_map($a, $b)
    {
        return $a * $b;
    }

    public static function weewee()
    {
        return 5;
    }

    public function barPlusTwo($a)
    {
        return $this->bar($a) + 2;
    }

    public function bar($a)
    {
        return $a * 2;
    }

    public function __call($name, $args)
    {
        if ($name == "magic") {
            return 42;
        }
        return null;
    }

    public function doMagic()
    {
        return 42*$this->magic();
    }

    public function sequentialCalls()
    {
        $this->doMagic();
        $this->magic();
        $this->bar(42);
        $this->lala();
    }

    public function staticSequentialCalls()
    {
        $a = $this->bar(static::weewee());
        return $this->for_value_map($a, 3);
    }
}

abstract class AbstractFoo
{
    abstract public function bar();
}

interface Empty_Foo
{
    public function foo();
}
