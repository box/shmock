<?php
namespace Shmock;

use \Shmock\ClassBuilder\ClassBuilder;
use \Shmock\ClassBuilder\Invocation;

use \Shmock\Constraints\CountOfTimes;
use \Shmock\Constraints\AnyTimes;
use \Shmock\Constraints\AtLeastOnce;

class StaticSpec implements Spec
{
    /**
     * @var string The name of the primary class
     * or interface being mocked.
     */
    private $className;

    /**
     * @var string the name of the method being mocked
     */
    private $methodName;

    /**
     * @var mixed|null
     */
    private $returnValue;

    /**
     * @var \Shmock\Constraint\Frequency|null
     */
    private $frequency = null;

    /**
     * @var array arguments
     */
    private $arguments;

    /**
     * @var callable
     */
    private $will;

    /**
     * @var bool
     */
    private $returnThis = false;

    /**
     * @var \PHPUnit_Framework_TestCase
     */
    private $testCase;

    /**
     * @var \Shmock\Policy[] $policies
     */
    private $policies;

    /**
     * @param \PHPUnit_Framework_TestCase $testCase
     * @param string                      $className
     * @param string                      $methodName
     * @param array                       $arguments
     * @param Policy[]                    $policies
     */
    public function __construct($testCase, $className, $methodName, $arguments, $policies)
    {
        $this->testCase = $testCase;
        $this->className = $className;
        $this->methodName = $methodName;
        $this->arguments = $arguments;
        $this->frequency = new CountOfTimes(1, $this->methodName);
        $this->policies = $policies;

        $this->doStrictMethodCheck();

        foreach ($this->policies as $policy) {
            $policy->check_method_parameters($className, $methodName, $arguments, $this->isStatic());
        }
    }

    /**
     * @return bool
     */
    protected function isStatic()
    {
        return true;
    }

    /**
     * @return void
     */
    protected function doStrictMethodCheck()
    {
        $errMsg = "#{$this->methodName} is an instance method on the class {$this->className}, but you expected it to be static.";
        try {
            $reflectionMethod = new \ReflectionMethod($this->className, $this->methodName);
            $this->testCase->assertTrue($reflectionMethod->isStatic(), $errMsg);
            $this->testCase->assertFalse($reflectionMethod->isPrivate(), "#{$this->methodName} is a private method on {$this->className}, but you cannot mock a private method.");
        } catch (\ReflectionException $e) {
            $this->testCase->assertTrue(method_exists($this->className, '__callStatic'), "The method #{$this->methodName} does not exist on the class {$this->className}");
        }
    }

    /**
     * Specify that the method will be invoked $times times.
     * <pre>
     *  // expect notify will be called 5 times
     *  $shmock->notify()->times(5);
     * </pre>
     *
     * @param  int|null     $times the number of times to expect the given call
     * @return \Shmock\Spec
     * @see \Shmock\Spec::at_least_once() See at_least_once()
     */
    public function times($times)
    {
        $this->frequency = new CountOfTimes($times, $this->methodName);

        return $this;
    }

    /**
     * Specify that the method will be invoked once.
     *
     * This is a shorthand for <code>times(1)</code>
     * @return \Shmock\Spec
     * @see \Shmock\Spec::times() See times()
     */
    public function once()
    {
        $this->frequency = new CountOfTimes(1, $this->methodName);

        return $this;
    }

    /**
     * Specify that the method will be invoked twice.
     *
     * This is a shorthand for <code>times(2)</code>
     * @return \Shmock\Spec
     * @see \Shmock\Spec::times() See times()
     */
    public function twice()
    {
        $this->frequency = new CountOfTimes(2, $this->methodName);

        return $this;
    }

    /**
     * Specifies that the number of invocations of this method
     * is not to be verified by Shmock.
     * <pre>
     *  $shmock->notify()->any();
     * </pre>
     * This is a shorthand for <code>times(null)</code>
     * @return \Shmock\Spec
     * @see \Shmock\Spec::times() See times()
     */
    public function any()
    {
        $this->frequency = new AnyTimes();

        return $this;
    }

    /**
     * Specifies that this method is never to be invoked.
     *
     * This is an alias for <code>times(0)</code>
     * @return \Shmock\Spec
     * @see \Shmock\Spec::times() See times()
     */
    public function never()
    {
        $this->frequency = new CountOfTimes(0, $this->methodName);

        return $this;
    }

    /**
     * Specifies that the method is to be invoked at least once
     * but possibly more.
     * This directive is only respected if no other calls to <code>times()</code> have been recorded.
     * <pre>
     *  $shmock->notify()->at_least_once();
     * </pre>
     *
     * @return \Shmock\Spec
     * @see \Shmock\Spec::times() See times()
     */
    public function at_least_once()
    {
        $this->frequency = new AtLeastOnce($this->methodName);

        return $this;
    }

    /**
     * Specifies that the given closure will be executed on invocation.
     * The first argument to the closure is an instance of <code>\PHPUnit_Framework_MockObject_Invocation</code>.
     *
     * <pre>
     *  // custom action with a closure
     *
     *  $shmock->notify()->will(function ($invocation) {
     *    $this->assertTrue(count($invocation->parameters) > 2);
     *  });
     * </pre>
     *
     * @param  callable     $will_closure
     * @return \Shmock\Spec
     */
    public function will($will_closure)
    {
        $this->will = $will_closure;

        return $this;
    }

    /**
    * An order-agnostic set of return values given a set of inputs.
    *
    * @param mixed[][] $map_of_args_to_values an array of arrays of arguments with the final value
    * of the array being the return value.
    * @return \Shmock\Spec
    * For example, if you were simulating addition:
    * <pre>
    * $shmock_calculator->add()->return_value_map([
    * 	[1, 2, 3], // 1 + 2 = 3
    * 	[10, 15, 25],
    * 	[11, 11, 22]
    * ]);
    * </pre>
    */
    public function return_value_map($mapOfArgsToValues)
    {
        if (count($mapOfArgsToValues) < 1) {
            throw new \InvalidArgumentException('Must specify at least one return value');
        };

        $limit = count($mapOfArgsToValues);

        $this->frequency = new CountOfTimes($limit, $this->methodName);

        /*
        * make the mapping a little more sane: if we received
        * [
        *   [1,2,3],
        *   [4,5,9]
        * ]
        * as a map, convert it to:
        * [
        *  [[1,2], 3],
        *  [[4,5], 9],
        * ]
        */
        $mapping = [];
        foreach ($mapOfArgsToValues as $paramsAndReturn) {
            $parameterSet = array_slice($paramsAndReturn, 0, count($paramsAndReturn) - 1);
            $returnVal = $paramsAndReturn[count($paramsAndReturn) - 1];
            $mapping[] = [$parameterSet, $returnVal];
        }

        foreach ($this->policies as $policy) {
            foreach ($mapping as $paramsWithReturn) {
                $policy->check_method_parameters($this->className, $this->methodName, $paramsWithReturn[0], $this->isStatic());
                $policy->check_method_return_value($this->className, $this->methodName, $paramsWithReturn[1], $this->isStatic());
            }
        }

        $this->will = function ($invocation) use ($mapping) {
            $args = $invocation->parameters;
            $differ = new \SebastianBergmann\Diff\Differ();
            $diffSoFar = null;
            foreach ($mapping as $map) {
                list($possibleArgs, $possibleRet) = $map;
                if ($possibleArgs === $args) {
                    return $possibleRet;
                } else {
                    $nextDiff = $differ->diff(print_r($possibleArgs, true), print_r($args, true));
                    if ($diffSoFar === null || strlen($nextDiff) < strlen($diffSoFar)) {
                        $diffSoFar = $nextDiff;
                    }
                }
            }
            throw new \PHPUnit_Framework_AssertionFailedError(sprintf("Did not expect to be called with args %s, diff with closest match is\n%s", print_r($args, true), $diffSoFar));
        };

        return $this;
    }

    /**
     * Specifies that the method will return true.
     * This is a shorthand for <code>return_value(true)</code>
     * @return \Shmock\Spec
     */
    public function return_true()
    {
        return $this->return_value(true);
    }

    /**
     * Specifies that the method will return false.
     * This is a shorthand for <code>return_value(false)</code>
     * @return \Shmock\Spec
     */
    public function return_false()
    {
        return $this->return_value(false);
    }

    /**
    * Specifies that the method will return null.
    * This is a shorthand for <code>return_value(null)</code>
    * @return \Shmock\Spec
    */
    public function return_null()
    {
        return $this->return_value(null);
    }

    /**
     * Specifies that the method will return the given value on invocation.
     * <pre>
     *  $shmock->notify()->return_value("notification!");
     * </pre>
     * @param  mixed|null   $value The value to return on invocation
     * @return \Shmock\Spec
     * @see \Shmock\Instance::order_matters() If you wish to specify multiple return values and the order is important, look at Instance::order_matters()
     * @see \Shmock\Spec::return_value_map() If you wish to specify multiple return values contingent on the parameters, but otherwise insensitive to the order, look at return_value_map()
     */
    public function return_value($value)
    {
        $this->returnValue = $value;
        foreach ($this->policies as $policy) {
            $policy->check_method_return_value($this->className, $this->methodName, $value, $this->isStatic());
        }

        return $this;
    }

    /**
     * Specifies that the method will return the invocation target. This is
     * useful for mocking other objects that have fluent interfaces.
     * <pre>
     *  $latte->add_foam()->return_this();
     *  $latte->caffeine_free()->return_this();
     * </pre>
     * @return \Shmock\Spec
     */
    public function return_this()
    {
        $this->returnThis = true;

        return $this;
    }

    /**
     * Throws an exception on invocation.
     * @param \Exception|void $e the exception to throw. If not specified, Shmock will provide an instance of
     * the base \Exception.
     * @return \Shmock\Spec
     */
    public function throw_exception($e=null)
    {
        $e = $e ?: new \Exception();

        foreach ($this->policies as $policy) {
            $policy->check_method_throws($this->className, $this->methodName, $e, true);
        }

        $this->will(function () use ($e) {
            throw $e;
        });

        return $this;
    }

    /**
     * Specifies that each subsequent invocation of this method will take the subsequent value from the array as the return value.
     *
     * The sequence of values to return is not affected by ordering constraints on the mock (ie, order_matters()).
     *
     * <pre>
     *  $shmock->notify()->return_consecutively(["called!", "called again!", "called a third time!"]);
     *
     *  $mock = $shmock->replay(); // replay is automatically called at the end of \Shmock\Shmock::create()
     *
     *  $mock->notify(); // called!
     *  $mock->notify(); // called again!
     *  $mock->notify(); // called a third time!
     * </pre>
     * @param mixed[]      $array_of_values           the sequence of values to return.
     * @param boolean|void $keep_returning_last_value whether to continue returning the last element in the sequence
     * or to fail the count expectation after every sequence element has been used. Defaults to false.
     * @return \Shmock\Spec
     */
    public function return_consecutively($array_of_values, $keep_returning_last_value=false)
    {
        foreach ($this->policies as $policy) {
            foreach ($array_of_values as $value) {
                $policy->check_method_return_value($this->className, $this->methodName, $value, true);
            }
        }

        // $this->returned_values = array_merge($this->returned_values, $array_of_values);
        $this->will = function () use ($array_of_values, $keep_returning_last_value) {
            static $counter = -1;
            $counter++;
            if ($counter == count($array_of_values)) {
                if ($keep_returning_last_value) {
                    return $array_of_values[count($array_of_values)-1];
                }
            } else {
                return $array_of_values[$counter];
            }
        };
        if (!$keep_returning_last_value) {
            $this->times(count($array_of_values));
        }

        return $this;
    }

    /**
     * Specifies that the return value from this function will be a new mock object, which is
     * built and replayed as soon as the invocation has occurred.
     * The signature of <code>return_shmock()</code> is similar to <code>\Shmock\Shmock::create()</code>
     * except that the test case argument is omitted
     *
     * <pre>
     *  $user = \Shmock\Shmock::create($this, 'User', function ($user) {
     *    $user->supervisor()->return_shmock('Supervisor', function ($supervisor) {
     *      $supervisor->send_angry_email("I need you to work this weekend");
     *    });
     *  });
     * </pre>
     * @param  string       $class          the name of the class to mock.
     * @param  callable     $shmock_closure a closure that will act as the class's build phase.
     * @return \Shmock\Spec
     */
    public function return_shmock($class, $shmockClosure)
    {
        // use PHPUnit instances for now...
        $phpunitInstance = new PHPUnitMockInstance($this->testCase, $class);
        $shmockClosure($phpunitInstance);

        return $this->return_value($phpunitInstance->replay());
    }

    /**
    * @internal invoked at the end of the build() phase
    * @param mixed $mock
    * @param \Shmock\Policy[] $policies
    * @param boolean $static
    * @param string the name of the class being mocked
    * @return void
    */
    public function __shmock_finalize_expectations($mock, array $policies, $static, $class)
    {

    }

    /**
     * @return string the name of the specification
     */
    public function __shmock_name()
    {
        return "Expectations for {$this->methodName}";
    }

    /**
     * @return void
     */
    public function __shmock_verify()
    {
        $this->frequency->verify();
    }

    /**
     * @param mixed|null
     * @param mixed|null
     * @return bool
     */
    private function argumentMatches($expected, $actual)
    {
        if (is_a($expected, '\PHPUnit_Framework_Constraint')) {
            return $expected->evaluate($actual,"", true);
        } else {
            return $expected == $actual;
        }
    }

    /**
     * @param \Shmock\ClassBuilder\Invocation
     * @return mixed|null
     */
    public function doInvocation(Invocation $invocation)
    {
        $this->frequency->addCall();

        $args = $invocation->getArguments();

        $i = 0;
        foreach ($this->arguments as $expected) {
            $argi = null;
            if ($i < count($args)) {
                $argi = $args[$i];
            }
            if (!$this->argumentMatches($expected, $argi)) {
                $expectedStr = print_r($expected, true);
                $actualStr = print_r($argi, true);
                $extra = "";
                if (strlen($expectedStr) > 100) {
                     $differ = new \SebastianBergmann\Diff\Differ();
                     $extra = "Diff: \n" . $differ->diff($expectedStr, $actualStr);
                }
                throw new \PHPUnit_Framework_AssertionFailedError(sprintf("Unexpected argument#%s %s (%s) to method '%s', was expecting %s (%s). %s", $i, $actualStr, gettype($argi), $this->methodName, $expectedStr, print_r(gettype($expected), true), $extra));
            }

            $i++;
        }

        if ($this->will) {
            return call_user_func($this->will, new InvocationImpl($args));
        }

        if ($this->returnThis) {
            $target = $invocation->getTarget();

            // as implemented, returnThis can only be verified by policies at
            // calltime.
            foreach ($this->policies as $policy) {
                $this->check_method_return_value($this->className, $this->methodName, $target, true);
            }

            return $target;
        }

        return $this->returnValue;
    }
}

class InvocationImpl implements \PHPUnit_Framework_MockObject_Invocation
{
    /**
     * @var array
     */
    public $parameters;

    /**
     * @param array
     */
    public function __construct(array &$parameters)
    {
        $this->parameters = &$parameters;
    }
}
