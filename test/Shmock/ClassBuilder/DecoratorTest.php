<?php

namespace Shmock\ClassBuilder;

class DecoratorTest extends \PHPUnit_Framework_TestCase
{
    public function testDecoratorsShouldWrapObjectMethodReceivers()
    {
        $calculator = new Calculator();
        $joinPoint = new DecoratorJoinPoint($calculator, "multiply");
        $joinPoint->setDecorators([new CalculatorDecorator()]);
        $joinPoint->setArguments([2, 2]);
        $val = $joinPoint->execute();
        $this->assertEquals(12, $val);

    }

    public function testDecoratorShouldWrapStaticMethodReceivers()
    {
        $joinPoint = new DecoratorJoinPoint('\Shmock\ClassBuilder\Calculator', 'subtract');
        $joinPoint->setDecorators([new CalculatorDecorator()]);
        $joinPoint->setArguments([10,3]);
        $val = $joinPoint->execute();
        $this->assertEquals(12, $val);
    }

    public function testJoinPointHandlesArbitraryNumberOfDecorators()
    {
        $calculator = new Calculator();
        $joinPoint = new DecoratorJoinPoint($calculator, "multiply");
        $joinPoint->setDecorators([new CalculatorDecorator(), new CalculatorDecorator(), new CalculatorDecorator()]);
        $joinPoint->setArguments([2, 2]);
        $val = $joinPoint->execute();
        $this->assertEquals(80, $val);

    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidMethodsWillResultInInvalidArgumentException()
    {
        new DecoratorJoinPoint(new CalculatorDecorator(), "doubleIt");
    }

}

class CalculatorDecorator implements Decorator
{
    public function decorate(JoinPoint $joinPoint)
    {
        $args = $joinPoint->arguments();
        $args[1]++;
        $joinPoint->setArguments($args);

        return 2 * $joinPoint->execute();
    }
}

class Calculator
{
    public function multiply($x, $y)
    {
        return $x * $y;
    }

    public static function subtract($x, $y)
    {
        return $x - $y;
    }

}
