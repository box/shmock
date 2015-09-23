<?php
/**
 * @package \Shmock provides a stricter, more fluid interface on top of vanilla PHPUnit mockery. In
 * addition to a fluent builder syntax, it will do stricter inspection of mock objects to
 * ensure that they meet important criteria. Out of the box, Shmock will enforce that static
 * methods cannot be mocked non-statically, that private methods cannot be mocked at all,
 * and that the class or interface being mocked must exist. You can even program custom
 * checks that will apply to every mock object you create using Shmock Policies.
 */
namespace Shmock;

/**
 * The Shmock\Shmock class is the entry point to the fluent Shmock interface. You may use this class
 * directly to create mocks. Alternatively, use the Shmockers trait to include shorthand versions
 * of create() and create_class() in your test cases.
 */
class Shmock
{
    /**
     * @var \Shmock\Policy[] Do not modify this directly, use {add_policy()} and {clear_policies()}
     */
    public static $policies = [];

    /**
     * @var \Shmock\Instance This is the set of all outstanding mock objects that have not been verified.
     * Call `Shmock::verify()` to assert that all expectations for mock objects have been met.
     * This function will not exist in version 3.x of Shmock
     */
    private static $outstanding_shmocks = [];

    /**
     * Create an instance of a mock object. Shmock uses a build / replay model for building mock objects.
     * The third argument to the create method is a callable that acts as the mock's build phase. The resulting
     * object from the create method is the object in the replay phase.
     *
     * Sample usage:
     * <pre>
     * // build a mock of MyCalculator, expecting a call to add
     * // with arguments [1,2] and return the value 3, exactly once.
     *
     * $mock = \Shmock\Shmock::create($this, 'MyCalculator', function ($calc) {
     *   $calc->add(1,2)->return_value(3);
     * });
     * </pre>
     *
     * In the example above, the invocation target of the method <code>add(1,2)</code>
     * is an of \Shmock\Instance. This instance will allow you to mock any
     * instance method on the class MyCalculator, so it might allow <code>add</code> or <code>subtract</code>,
     * but not <code>openFileStream()</code> or <code>sbutract</code>. The result of the method
     * is an instance of \Shmock\Spec, which contains many of the familiar
     * expectation-setting methods for mock frameworks.
     *
     * You may easily design your own build / replay lifecycle to meet your needs by
     * using the Instance and StaticClass classes directly.
     *
     * <pre>
     * $shmock = new \Shmock\Instance($this, 'MyCalculator');
     * $shmock->add(1,2)->return_value(3);
     * $mock = $shmock->replay();
     * </pre>
     *
     * @param  \PHPUnit_Framework_TestCase $test_case
     * @param  string                      $class     the class being mocked
     * @param  callable                    $closure   the build phase of the mock
     * @return mixed                       An instance of a subclass of $class. PHPUnit mocks require that all mocks
     * be subclasses of the target class in order to replace target methods. For this reason, mocking
     * will fail if the class is final.
     * @see \Shmock\Instance \Shmock\Instance
     * @see \Shmock\Class \Shmock\StaticClass
     * @see \Shmock\Spec See \Shmock\Spec to get a sense of what methods are available for setting expectations.
     * @see \Shmock\Shmockers See the Shmockers trait for a shorthand helper to use in test cases.
     */
    public static function create(\PHPUnit_Framework_TestCase $test_case, $class, callable $closure)
    {
        $shmock = new ClassBuilderInstanceClass($test_case, $class);
        self::$outstanding_shmocks[] = $shmock;
        if ($closure) {
            $closure($shmock);
        }

        return $shmock->replay();
    }

    /**
     * Create a mock class. Mock classes go through the build / replay lifecycle like mock instances do.
     * @param  \PHPUnit_Framework_TestCase $test_case
     * @param  string                      $class     the class to be mocked
     * @param  callable                    $closure   the closure to apply to the class mock in its build phase.
     * @return string                      a subclass of $class that has mock expectations set on it.
     * @see \Shmock\Shmock::create()
     */
    public static function create_class($test_case, $class, $closure)
    {
        $shmock_class = new ClassBuilderStaticClass($test_case, $class);
        self::$outstanding_shmocks[] = $shmock_class;
        if ($closure) {
            $closure($shmock_class);
        }

        return $shmock_class->replay();
    }

    /**
     * Add a policy to Shmock that ensures qualities about mock objects as they are created. Policies
     * allow you to highly customize the behavior of Shmock.
     * @param  \Shmock\Policy $policy
     * @return void
     * @see \Shmock\Policy See \Shmock\Policy for documentation on how to create custom policies.
     */
    public static function add_policy(Policy $policy)
    {
        self::$policies[] = $policy;
    }

    /**
     * Clears any set policies.
     * @return void
     * @see \Shmock\Policy See \Shmock\Policy for documentation on how to create custom policies.
     */
    public static function clear_policies()
    {
        self::$policies = [];
    }

    /**
     * This will verify that all mocks so far have been satisfied. It will clear
     * the set of outstanding mocks, regardless if any have failed.
     * @return void
     */
    public static function verify()
    {
        $mocks = self::$outstanding_shmocks;
        self::$outstanding_shmocks = [];
        foreach ($mocks as $mock) {
            $mock->verify();
        }
    }

}

/**
 * This is used to support the will() response to mocking a method.
 * @internal
 */
class Shmock_Closure_Invoker implements \PHPUnit_Framework_MockObject_Stub
{
    /** @var callable */
    private $closure = null;

    /**
     * @internal
     * @param callable
     */
    public function __construct($closure)
    {
        $this->closure = $closure;
    }

    /**
     * @internal
     * @param  \PHPUnit_Framework_MockObject_Invocation $invocation
     * @return mixed|null                               the result of the invocation
     */
    public function invoke(\PHPUnit_Framework_MockObject_Invocation $invocation)
    {
        $fn = $this->closure;

        return $fn($invocation);
    }

    /**
     * @internal
     * @return string
     */
    public function toString()
    {
        return "Closure invoker";
    }
}

/**
 * It is recommended for Shmock policy implementors to use the \Shmock\Shmock_Exception type
 * to signal policy infringements.
 * @see \Shmock\Policy Documentation on policies
 */
class Shmock_Exception extends \Exception {}
