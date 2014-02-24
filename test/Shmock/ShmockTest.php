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

    public function tearDown()
    {
        Shmock::verify();
    }
}

class Shmock_Foo
{
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

}

abstract class AbstractFoo
{
    abstract public function bar();
}
