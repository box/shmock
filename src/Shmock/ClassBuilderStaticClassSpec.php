<?php
namespace Shmock;

use \Shmock\ClassBuilder\ClassBuilder;
use \Shmock\ClassBuilder\JoinPoint;
use \Shmock\ClassBuilder\MethodInspector;

use \Shmock\Constraints\CountOfTimes;
use \Shmock\Constraints\AnyTimes;
use \Shmock\Constraints\AtLeastOnce;

class ClassBuilderStaticClassSpec implements Spec
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
     * @param \PHPUnit_Framework_TestCase $testCase
     * @param string                      $className
     * @param string                      $methodName
     * @param array
     */
    public function __construct($testCase, $className, $methodName, $arguments)
    {
        $this->testCase = $testCase;
        $this->className = $className;
        $this->methodName = $methodName;
        $this->arguments = $arguments;
        $this->frequency = new CountOfTimes(1, $this->methodName);
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

        $this->will = function () use ($mapping) {
            $invocation = func_get_args()[0];
            $args = $invocation->parameters;
            foreach ($mapping as $map) {
                list($possibleArgs, $possibleRet) = $map;
                if ($possibleArgs == $args) {
                    return $possibleRet;
                }
            }
            throw new \PHPUnit_Framework_AssertionFailedError(sprintf("Did not expect to be called with args %s", print_r($possibleArgs, true)));
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
        $this->returnValue = true;

        return $this;
    }

    /**
     * Specifies that the method will return false.
     * This is a shorthand for <code>return_value(false)</code>
     * @return \Shmock\Spec
     */
    public function return_false()
    {
        $this->returnValue = false;

        return $this;
    }

    /**
    * Specifies that the method will return null.
    * This is a shorthand for <code>return_value(null)</code>
    * @return \Shmock\Spec
    */
    public function return_null()
    {
        $this->returnValue = null;

        return $this;
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

        $this->will(function () use ($e) {
            throw $e ?: new Exception();
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
    public function return_shmock($class, $shmock_closure)
    {
        // use PHPUnit instances for now...
        $phpunitInstance = new PHPUnitMockInstance($this->testCase, $class);
        $shmock_closure($phpunitInstance);
        $this->returnValue = $phpunitInstance->replay();

        return $this;
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
     * @param  \Shmock\ClassBuilder\ClassBuilder $builder
     * @return void
     */
    public function __shmock_configureClassBuilder(ClassBuilder $builder)
    {
        if ($this->returnThis) {
            $builder->addDecorator(function (JoinPoint $point) {
                $ret = $point->execute();
                if ($point->methodName() === $this->methodName) {
                    $ret = $point->target();
                }

                return $ret;
            });
        }
        $inspector = new MethodInspector($this->className, $this->methodName);
        $builder->addStaticMethod($this->methodName, function () {
            $this->frequency->addCall();

            $args = func_get_args();

            if (count($args) != count($this->arguments) && count($this->arguments) !== 0) {
                throw new \PHPUnit_Framework_AssertionFailedError(sprintf("Expected %s arguments to %s, got %s", count($this->arguments), $this->methodName, count($args)));
            }

            $i = 0;
            foreach ($this->arguments as $argument) {
                if (is_a($argument, '\PHPUnit_Framework_Constraint')) {
                    if (!$argument->evaluate($args[$i],"", true)) {
                        throw new \PHPUnit_Framework_AssertionFailedError(sprintf("Unexpected argument#%s %s to method %s", $i, print_r($args[$i], true), $this->methodName));
                    }
                } else {
                    if ($argument !== $args[$i]) {
                        throw new \PHPUnit_Framework_AssertionFailedError(sprintf("Unexpected argument#%s %s (%s) to method %s", $i, print_r($args[$i], true), gettype($args[$i]), $this->methodName));
                    }
                }
                $i++;
            }

            if ($this->will) {
                return call_user_func($this->will, new InvocationImpl($args));
            }

            return $this->returnValue;
        }, $inspector->signatureArgs());
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
    public function __construct($parameters)
    {
        $this->parameters = $parameters;
    }
}
