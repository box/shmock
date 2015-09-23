<?php

namespace Shmock;
use \Shmock\ClassBuilder\ClassBuilder;
use \Shmock\ClassBuilder\MethodInspector;
use \Shmock\InstanceSpec;
use \Shmock\ClassBuilder\Invocation;

use \Shmock\Constraints\Ordering;
use \Shmock\Constraints\Unordered;
use \Shmock\Constraints\MethodNameOrdering;

/**
* @package Shmock
* Instance can be used to build mock instances of a class.
*
* Instance is the receiver for all method invocations during the build phase.
*/
class ClassBuilderInstanceClass extends ClassBuilderStaticClass
{
    /**
     * Whether or not to disable the constructor belonging to the class
     * being mocked. By default Shmock will create a mock object that
     * invokes the constructor.
     * @var bool
     * @see \Shmock\Instance::disable_original_constructor() Call disable_original_constructor() to prevent invocation of the original constructor
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
     * Arguments that will be passed to the original constructor.
     * @var array
     * @see \Shmock\Instance::set_constructor_arguments() Call set_constructor_arguments() to call the original constructor with specific args.
     * @see \Shmock\Instance::disable_original_constructor() Call disable_original_constructor() to disable the original constructor
     */
    protected $constructor_arguments = [];

    /**
     * The Instance is active during the build phase of a mock of an instance. This object acts
     * as a receiver for methods you wish to mock by implementing __call.
     * @param \PHPUnit_Framework_TestCase $testCase
     * @param string                      $class     the class being mocked
     */
    public function __construct($testCase, $class)
    {
        parent::__construct($testCase, $class);
    }

    /**
     * Prevent the original constructor from being called when
     * the replay phase begins. This can be important if the
     * constructor of the class being mocked takes complex arguments or
     * performs work that cannot be intercepted.
     * @return \Shmock\Instance
     * @see \Shmock\Instance::set_constructor_arguments()
     */
    public function disable_original_constructor()
    {
        if (!empty($this->constructor_arguments)) {
            throw new \BadMethodCallException("You cannot disable the constructor after you have set constructor arguments!");
        }
        $this->disable_original_constructor = true;
        return $this;
    }

    /**
     * Any arguments passed in here will be included in the
     * constructor call for the mocked class.
     * @param *mixed|null Arguments to the target constructor
     * @return \Shmock\Instance
     */
    public function set_constructor_arguments()
    {
        if ($this->disable_original_constructor) {
            throw new \BadMethodCallException("You cannot set constructor arguments after you have disabled the constructor!");
        }
        $this->constructor_arguments = func_get_args();
        return $this;
    }

    /**
     * When this is called, Shmock will disable any of the original implementations
     * of methods on the mocked class. This can be useful when no expectations are set
     * on a particular method but the original implementation cannot be called in
     * testing.
     * @return \Shmock\Instance
     */
    public function dont_preserve_original_methods()
    {
        return parent::dont_preserve_original_methods(false);
    }

    /**
     * Helper method to properly initialize a class builder with everything
     * ready for $builder->create() to be invoked. Has no side effects
     *
     * @return \Shmock\ClassBuilder\ClassBuilder
     */
    protected function initializeClassBuilder()
    {
        $builder = parent::initializeClassBuilder();
        if ($this->disable_original_constructor) {
            $builder->disableConstructor();
        }
        return $builder;
    }

    /**
     * When this is invoked, Shmock concludes this instance's build phase, runs
     * any policies that may have been registered, and creates a mock object in the
     * replay phase.
     * @return mixed an instance of a subclass of the mocked class.
     */
    public function replay()
    {
        $mockClassName = parent::replay();

        $mockClassReflector = new \ReflectionClass($mockClassName);
        return $mockClassReflector->newInstanceArgs($this->constructor_arguments);
    }

    /**
     * @param  \Shmock\ClassBuilder\ClassBuilder $builder
     * @param  string                            $methodCall
     * @param  callable                          $resolveCall
     * @return void
     */
    protected function addMethodToBuilder(ClassBuilder $builder, $methodCall, callable $resolveCall)
    {
        $inspector = new MethodInspector($this->className, $methodCall);
        $builder->addMethod($methodCall, $resolveCall, $inspector->signatureArgs());
    }

    /**
     * When mocking an object instance, it may be desirable to mock static methods as well. Because
     * Shmock has strict rules that mock instances may only mock instance methods, to mock a static method
     * requires dropping into the mock class context.
     *
     * This is made simple by the shmock_class() method on Instance.
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
     * @param  callable $closure
     * @return void
     */
    public function shmock_class($closure)
    {
        $this->shmock_class_closure = $closure;
    }

    /**
     * Build a spec object given the method and args
     * @param  string $methodName
     * @param  array  $with
     * @return Spec
     */
    protected function initSpec($methodName, array $with)
    {
        return new InstanceSpec($this->testCase, $this->className, $methodName, $with, Shmock::$policies);
    }
}
