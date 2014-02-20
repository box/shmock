<?php

namespace Shmock;

/**
 * @package Shmock
 * A Spec defines the expectations of a single method invocation on a mock object. A mock object
 * is composed of many specs.
 */
interface Spec
{
    /**
     * Specify that the method will be invoked $times times.
     * <pre>
     *  // expect notify will be called 5 times
     *  $shmock->notify()->times(5);
     * </pre>
     *
     * @param  int          $times the number of times to expect the given call
     * @return \Shmock\Spec
     * @see \Shmock\Spec::at_least_once() See at_least_once()
     */
    public function times($times);

    /**
     * Specify that the method will be invoked once.
     *
     * This is a shorthand for <code>times(1)</code>
     * @return \Shmock\Spec
     * @see \Shmock\Spec::times() See times()
     */
    public function once();

    /**
     * Specify that the method will be invoked twice.
     *
     * This is a shorthand for <code>times(2)</code>
     * @return \Shmock\Spec
     * @see \Shmock\Spec::times() See times()
     */
    public function twice();

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
    public function any();

    /**
     * Specifies that this method is never to be invoked.
     *
     * This is an alias for <code>times(0)</code>
     * @return \Shmock\Spec
     * @see \Shmock\Spec::times() See times()
     */
    public function never();

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
    public function at_least_once();

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
    public function will($will_closure);

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
    public function return_value_map($map_of_args_to_values);

    /**
     * Specifies that the method will return true.
     * This is a shorthand for <code>return_value(true)</code>
     * @return \Shmock\Spec
     */
    public function return_true();

    /**
     * Specifies that the method will return false.
     * This is a shorthand for <code>return_value(false)</code>
     * @return \Shmock\Spec
     */
    public function return_false();

    /**
    * Specifies that the method will return null.
    * This is a shorthand for <code>return_value(null)</code>
    * @return \Shmock\Spec
    */
    public function return_null();

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
    public function return_value($value);

    /**
     * Specifies that the method will return the invocation target. This is
     * useful for mocking other objects that have fluent interfaces.
     * <pre>
     *  $latte->add_foam()->return_this();
     *  $latte->caffeine_free()->return_this();
     * </pre>
     * @return \Shmock\Spec
     */
    public function return_this();

    /**
     * Throws an exception on invocation.
     * @param \Exception|void $e the exception to throw. If not specified, Shmock will provide an instance of
     * the base \Exception.
     * @return \Shmock\Spec
     */
    public function throw_exception($e=null);

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
    public function return_consecutively($array_of_values, $keep_returning_last_value=false);

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
    public function return_shmock($class, $shmock_closure);

    /**
    * @internal invoked at the end of the build() phase
    * @param mixed $mock
    * @param \Shmock\Policy[] $policies
    * @param boolean $static
    * @param string the name of the class being mocked
    * @return void
    */
    public function finalize_expectations($mock, array $policies, $static, $class);

}
