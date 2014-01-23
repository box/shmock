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
     * is an of \Shmock\Shmock_Instance. This instance will allow you to mock any
     * instance method on the class MyCalculator, so it might allow <code>add</code> or <code>subtract</code>,
     * but not <code>openFileStream()</code> or <code>sbutract</code>. The result of the method
     * is an instance of \Shmock\PHPUnitSpec, which contains many of the familiar
     * expectation-setting methods for mock frameworks.
     *
     * You may easily design your own build / replay lifecycle to meet your needs by
     * using the Shmock_Instance and Shmock_Class classes directly.
     *
     * <pre>
     * $shmock = new \Shmock\Shmock_Instance($this, 'MyCalculator');
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
     * @see \Shmock\Shmock_Instance \Shmock\Shmock_Instance
     * @see \Shmock\Shmock_Class \Shmock\Shmock_Class
     * @see \Shmock\PHPUnitSpec See \Shmock\PHPUnitSpec to get a sense of what methods are available for setting expectations.
     * @see \Shmock\Shmockers See the Shmockers trait for a shorthand helper to use in test cases.
     */
    public static function create(\PHPUnit_Framework_TestCase $test_case, $class, callable $closure)
    {
        $shmock = new Shmock_Instance($test_case, $class);
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
        $shmock_class = new Shmock_Class($test_case, $class);
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
    public function clear_policies()
    {
        self::$policies = [];
    }
}

/**
* Shmock_Instance can be used to build mock instances of a class.
*
* Shmock_Instance is the receiver for all method invocations during the build phase.
*/
class Shmock_Instance
{

    /**
    * @var \PHPUnit_Framework_TestCase
    * @internal
    */
    protected $test_case = null;

    /**
     * @internal
     */
    protected $specs = [];

    /**
     * @internal The class being mocked
     */
    protected $class = null;

    /**
     * Whether or not to preserve the original
     * methods on the mock object or stub them out.
     * Default is to preserve original methods.
     * @var bool
     * @see \Shmock\Shmock_Intstance::dont_preserve_original_methods() Call dont_preserve_original_methods() to disable the preservation behavior
     */
    protected $preserve_original_methods = true;

    /**
     * Whether or not to disable the constructor belonging to the class
     * being mocked. By default Shmock will create a mock object that
     * invokes the constructor.
     * @var bool
     * @see \Shmock\Shmock_Instance::disable_original_constructor() Call disable_original_constructor() to prevent invocation of the original constructor
     * @see \Shmock\Shmock_Instance::set_constructor_arguments() Call set_constructor_arguments() to call the original constructor with specific args.
     */
    protected $disable_original_constructor = false;

    /**
    * @var callable
    * @internal
    * If we want to shmock the static context of a shmock'd object
    * we need to call get_class() on the final mock, so we save
    * any configuration closure until after everything is done.
    */
    protected $shmock_class_closure = null;

    /**
     * @var bool
     * Indicates whether the order of invocations should be tracked.
     * @see \Shmock\Shmock_Instance::order_matters() Call order_matters() to trigger order enforcement
     */
    protected $order_matters = false;

    /**
     * @internal
     * When invoking the mock builder, will use this value in the at() method
     */
    protected $call_index = 0;

    /**
     * Arguments that will be passed to the original constructor.
     * @var array
     * @see \Shmock\Shmock_Instance::set_constructor_arguments() Call set_constructor_arguments() to call the original constructor with specific args.
     * @see \Shmock\Shmock_Instance::disable_original_constructor() Call disable_original_constructor() to disable the original constructor
     */
    protected $constructor_arguments = [];

    /**
     * @internal
     * The methods being mocked
     */
    protected $methods = [];

    /**
     * The Shmock_Instance is active during the build phase of a mock of an instance. This object acts
     * as a receiver for methods you wish to mock by implementing __call.
     * @param PHPUnit_Framework_TestCase $test_case
     * @param string                     $class     the class being mocked
     */
    public function __construct($test_case, $class)
    {
        $this->test_case = $test_case;
        $this->class = $class;
    }

    /**
     * Prevent the original constructor from being called when
     * the replay phase begins. This can be important if the
     * constructor of the class being mocked takes complex arguments or
     * performs work that cannot be intercepted.
     * @return \Shmock\Shmock_Instance
     * @see \Shmock\Shmock_Instance::set_constructor_arguments()
     */
    public function disable_original_constructor()
    {
        $this->disable_original_constructor = true;

        return $this;
    }

    /**
     * Any arguments passed in here will be included in the
     * constructor call for the mocked class.
     * @param *mixed|null Arguments to the target constructor
     * @return void
     * @see \Shmock\Shmock_Instance::disable_original_constructor()
     */
    public function set_constructor_arguments()
    {
        $this->constructor_arguments = func_get_args();
    }

    /**
     * When this is called, Shmock will disable any of the original implementations
     * of methods on the mocked class. This can be useful when no expectations are set
     * on a particular method but the original implementation cannot be called in
     * testing.
     * @return \Shmock\Shmock_Instance
     */
    public function dont_preserve_original_methods()
    {
        $this->preserve_original_methods = false;

        return $this;
    }

    /**
     * When this is called, Shmock will begin keeping track of the order of calls made on this
     * mock. This is implemented by using the PHPUnit at() feature and keeping an internal
     * counter to track order.
     *
     * <pre>
     *  $shmock->order_matters();
     *  $shmock->notify('first notification');
     *  $shmock->notify('second notification');
     * </pre>
     * In this example, the string "first notification" is expected to be sent to notify first during replay. If
     * any other string, including "second notification" is received, it will fail the expectation.
     *
     * Shmock does not expose the at() feature directly.
     * @return \Shmock\Shmock_Instance
     */
    public function order_matters()
    {
        $this->order_matters = true;

        return $this;
    }

    /**
     * Disables order checking. Note that order is already disabled by default, so this does not need
     * to be invoked unless order_matters was previously invoked
     * @see \Shmock\Shmock_Instance::order_matters() See order_matters() to trigger order enforcement
     * @return \Shmock\Shmock_Instance
     */
    public function order_doesnt_matter()
    {
        $this->order_matters = false;

        return $this;
    }

    /**
     * @internal
     * Finalizes mock creation
     * @return mixed the mock object
     */
    protected function construct_mock()
    {
        $builder = $this->test_case->getMockBuilder($this->class);

        if ($this->disable_original_constructor) {
            $builder->disableOriginalConstructor();
        }
        if ($this->preserve_original_methods) {
            if (count($this->methods) == 0) {
                /*
                 * If you pass an empty array of methods to the PHPUnit mock builder,
                 * it's effectively like saying don't preserve any methods at all. Instead
                 * we tell the builder to mock a single fake method when necessary.
                 */
                $this->methods[] = "__fake_method_for_shmock_to_preserve_methods";
            }
            $builder->setMethods(array_unique($this->methods));
        }
        if ($this->constructor_arguments) {
            $builder->setConstructorArgs($this->constructor_arguments);
        }
        $mock = $builder->getMock();

        return $mock;
    }

    /**
     * When this is invoked, Shmock concludes this instance's build phase, runs
     * any policies that may have been registered, and creates a mock object in the
     * replay phase.
     * @return mixed an instance of a subclass of the mocked class.
     */
    public function replay()
    {
        $shmock_instance_class = null;
        if ($this->shmock_class_closure) {
            /** @var callable $s */
            $s = $this->shmock_class_closure;
            $shmock_instance_class = new Shmock_Instance_Class($this->test_case, $this->class);
            $s($shmock_instance_class);
            $this->methods = array_merge($this->methods, $shmock_instance_class->methods);
        }

        $mock = $this->construct_mock();

        if ($shmock_instance_class) {
            $shmock_instance_class->set_mock($mock);
            $shmock_instance_class->replay();
        }

        foreach ($this->specs as $spec) {
            $spec->finalize_expectations($mock, Shmock::$policies, false, $this->class);
        }

        return $mock;
    }

    /**
     * @internal
     * @param string $method
     * @param array  $with
     */
    protected function do_strict_method_test($method, $with)
    {
        if (!class_exists($this->class) && !interface_exists($this->class)) {
            $this->test_case->fail("Class {$this->class} not found.");
        }

        $err_msg = "#$method is a static method on the class {$this->class}, but you expected it to be an instance method.";

        try {
            $reflection_method = new \ReflectionMethod($this->class, $method);
            $this->test_case->assertFalse($reflection_method->isStatic(), $err_msg);
            $this->test_case->assertFalse($reflection_method->isPrivate(), "#$method is a private method on {$this->class}, but you cannot mock a private method.");
        } catch (\ReflectionException $e) {
            $this->test_case->assertTrue(method_exists($this->class, '__call'), "The method $method does not exist on the class {$this->class}.");
        }
    }

    /**
     * When mocking an object instance, it may be desirable to mock static methods as well. Because
     * Shmock has strict rules that mock instances may only mock instance methods, to mock a static method
     * requires dropping into the mock class context.
     *
     * This is made simple by the shmock_class() method on Shmock_Instance.
     *
     * <pre>
     *   // User is an ActiveRecord-style class with finders and data members
     *   // (this is just an example, this is probably not a good way to organize this code)
     *   class User {
     *      private $id;
     *      private $userName;
     *
     *      public function updateUserName($userName) {
     *         /// persist to db
     *         $this->userName = $userName;
     *         $handle = static::dbHandle();
     *         $handle->update(['userName' => $this->userName]);
     *         $this->fireEvent('/users/username');
     *      }
     *
     *      public function fireEvent($eventType) {
     *         Notifications::fire($this->id, $eventType);
     *      }
     *
     *      public static function dbHandle() {
     *        return new DbHandle('schema.users');
     *      }
     *   }
     *
     *   // In a test we want to ensure that save() will fire notifications
     *   // and correctly persist to the database.
     *   $mock = $this->shmock('User', function ($user) {
     *
     *      // ensure that the user will fire the event
     *      $user->fireEvent('/users/username')->once();
     *
     *      // use shmock_class to mock the static method dbHandle()
     *      $user->shmock_class(function ($user_class) {
     *         $user_class->dbHandle()->return_value(new FakeDBHandle());
     *      });
     *   });
     * </pre>
     * @param callable $closure
     */
    public function shmock_class($closure)
    {
        $this->shmock_class_closure = $closure;
    }

    /**
     * Shmock intercepts all non-shmock methods here.
     *
     * Shmock will fail the test if any of the following are true:
     *
     * <ol>
     * <li> The class being mocked doesn't exist. </li>
     * <li> The method being mocked doesn't exist AND there is no __call handler on the class. </li>
     * <li> The method is private. </li>
     * <li> The method is static. (or non-static if using a Shmock_Class ) </li>
     * </ol>
     *
     * Additionally, any expectations set by Shmock policies may trigger an exception when replay() is invoked.
     * @param  string              $method the method on the target class
     * @param  array               $with   the arguments to the mocked method
     * @return \Shmock\PHPUnitSpec a spec that can add additional constraints to the invocation.
     * @see \Shmock\PHPUnitSpec See \Shmock\PHPUnitSpec for additional constraints that can be placed on an invocation
     */
    public function __call($method, $with)
    {
        $this->do_strict_method_test($method, $with);
        $this->methods[] = $method;
        $spec = new PHPUnitSpec($this->test_case, $this, $method, $with, $this->order_matters, $this->call_index);
        $this->specs[] = $spec;
        $this->call_index++;

        return $spec;
    }
}

/**
 * Shmock_Class is the static equivalent of Shmock_Instance. Shmock_Class instances can be used
 * to build a mocked class.
 *
 * Usage of this class is identical to Shmock_Instance, except that methods
 * that are mocked on a Shmock_Class must be static.
 */
class Shmock_Class extends Shmock_Instance
{

    /**
     * @internal
     */
    protected function do_strict_method_test($method, $with)
    {
        $err_msg = "#$method is an instance method on the class {$this->class}, but you expected it to be static.";
        try {
            $reflection_method = new \ReflectionMethod($this->class, $method);
            $this->test_case->assertTrue($reflection_method->isStatic(), $err_msg);
            $this->test_case->assertFalse($reflection_method->isPrivate(), "#$method is a private method on {$this->class}, but you cannot mock a private method.");
        } catch (\ReflectionException $e) {
            $this->test_case->assertTrue(method_exists($this->class, '__callStatic'), "The method #$method does not exist on the class {$this->class}");
        }

    }

    /**
    * @internal
    * Since you can't use the builder paradigm for mock classes, we have to play dirty here.
    */
    public function replay()
    {
        $mock_class = get_class($this->construct_mock());

        foreach ($this->specs as $spec) {
            $spec->finalize_expectations($mock_class, Shmock::$policies, true, $this->class);
        }

        return $mock_class;
    }
}

/**
* This class is only used when in the context of mocked instance and the shmock_class function is used.
* @internal
*/
class Shmock_Instance_Class extends Shmock_Class
{
    /**
     * @internal
     */
    private $mock;

    /**
     * @internal
     */
    public function set_mock($mock)
    {
        $this->mock = $mock;
    }

    /**
     * @internal
     */
    protected function construct_mock()
    {
        return $this->mock;
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
     */
    public function __construct($closure)
    {
        $this->closure = $closure;
    }

    /**
     * @internal
     */
    public function invoke(\PHPUnit_Framework_MockObject_Invocation $invocation)
    {
        $fn = $this->closure;

        return $fn($invocation);
    }

    /**
     * @internal
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
