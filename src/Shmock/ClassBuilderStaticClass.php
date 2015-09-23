<?php

namespace Shmock;
use \Shmock\ClassBuilder\ClassBuilder;
use \Shmock\ClassBuilder\MethodInspector;
use \Shmock\ClassBuilder\Invocation;

use \Shmock\Constraints\Ordering;
use \Shmock\Constraints\Unordered;
use \Shmock\Constraints\MethodNameOrdering;

class ClassBuilderStaticClass implements Instance
{
    /**
     * @var string the name of the class being mocked
     */
    protected $className;

    /**
     * @var string[]
     */
    protected $expectedStaticMethodCalls = [];

    /**
     * @var \PHPUnit_Framework_TestCase
     */
    protected $testCase;

    /**
     * @var Ordering
     */
    protected $ordering = null;

    /**
     * @param \PHPUnit_Framework_TestCase $testCase
     * @param string                      $className
     */
    public function __construct($testCase, $className)
    {
        $this->testCase = $testCase;
        $this->className = $className;
    }

    /**
     * @return void
     */
    public function verify()
    {
        if ($this->ordering !== null) {
            $this->ordering->verify();
        }
    }

    /**
     * @return \Shmock\Instance
     */
    public function disable_original_constructor()
    {
        // no-op
        return $this;
    }

    /**
     * @param *mixed|null
     * @return \Shmock\Instance
     */
    public function set_constructor_arguments()
    {
        // no-op
        return $this;
    }

    /**
     * @param bool|void whether to stub static methods
     * @return \Shmock\Instance
     */
    public function dont_preserve_original_methods($stubStaticMethods = true)
    {
        $reflectionClass = new \ReflectionClass($this->className);
        $methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $stubMethod = $stubStaticMethods ? $method->isStatic() : !$method->isStatic();
            if (!$method->isFinal() && $stubMethod) {
                $this->__call($method->getName(), [])->any()->return_null();
            }
        }
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
     * @return \Shmock\Instance
     */
    public function order_matters()
    {
        if ($this->ordering === null) {
            $this->ordering = new MethodNameOrdering();
        } else if ($this->ordering instanceof Unordered) {
            $this->ordering = $this->ordering->convertToMethodNameOrdering();
        } else {
            throw new \InvalidArgumentException("You cannot set the ordering constraint more than once. (It is implicitly set to 'unordered' after the first method is specified)");
        }
        return $this;
    }

    /**
     * Disables order checking. Note that order is already disabled by default, so this does not need
     * to be invoked unless order_matters was previously invoked
     * @see \Shmock\Instance::order_matters() See order_matters() to trigger order enforcement
     * @return \Shmock\Instance
     */
    public function order_doesnt_matter()
    {
        if ($this->ordering !== null && !($this->ordering instanceof Unordered)) {
            throw new \InvalidArgumentException("You cannot set the ordering constraint more than once! The ordering constraint is implicity set to 'unordered' after the first method is specified.");
        }
        return $this;
    }

    /**
     * Helper method to properly initialize a class builder with everything
     * ready for $builder->create() to be invoked. Has no side effects
     *
     * @return \Shmock\ClassBuilder\ClassBuilder
     */
    protected function initializeClassBuilder()
    {
        // build the mock class
        $builder = new ClassBuilder();
        if (class_exists($this->className)) {
            $builder->setExtends($this->className);
        } elseif (interface_exists($this->className)) {
            $builder->addInterface($this->className);
        } else {
            throw new \InvalidArgumentException("Class or interface " . $this->className . " does not exist");
        }

        // every mocked method goes through this invocation, which delegates
        // the retrieval of the correct Spec to this Instance's Ordering constraint.
        $resolveCall = function (Invocation $inv) {
            $spec = $this->ordering->nextSpec($inv->getMethodName());
            return $spec->doInvocation($inv);
        };

        return $this->addMethodsToBuilder($builder, $resolveCall);
    }

    /**
     * @return mixed The mock object, now in its replay phase.
     */
    public function replay()
    {
        return $this->initializeClassBuilder()->create();
    }

    /**
     * Helper function to add all called methods to the class builder
     *
     * @param  \Shmock\ClassBuilder\ClassBuilder $builder
     * @param  callable                          $resolveCall
     * @return \Shmock\ClassBuilder\ClassBuilder
     */
    protected function addMethodsToBuilder(ClassBuilder $builder, callable $resolveCall)
    {
        foreach (array_unique($this->expectedStaticMethodCalls) as $methodCall) {
            $inspector = new MethodInspector($this->className, $methodCall);
            $builder->addStaticMethod($methodCall, $resolveCall, $inspector->signatureArgs());
        }
        return $builder;
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
     * <li> The method is static. (or non-static if using a StaticClass ) </li>
     * </ol>
     *
     * Additionally, any expectations set by Shmock policies may trigger an exception when replay() is invoked.
     * @param  string              $methodName the method on the target class
     * @param  array               $with   the arguments to the mocked method
     * @return \Shmock\Spec a spec that can add additional constraints to the invocation.
     * @see \Shmock\Spec See \Shmock\Spec for additional constraints that can be placed on an invocation
     */
    public function __call($methodName, $with)
    {
        if ($this->ordering === null) {
            $this->ordering = new Unordered();
        }
        $spec = $this->initSpec($methodName, $with);
        $this->ordering->addSpec($methodName, $spec);
        $this->recordMethodInvocation($methodName);
        return $spec;
    }

    /**
     * Housekeeping function to record a method invocation
     *
     * @param string $methodName
     * @return void
     */
    protected function recordMethodInvocation($methodName)
    {
        $this->expectedStaticMethodCalls[] = $methodName;
    }

    /**
     * Build a spec object given the method and args
     * @param  string $methodName
     * @param  array  $with
     * @return Spec
     */
    protected function initSpec($methodName, array $with)
    {
        return new StaticSpec($this->testCase, $this->className, $methodName, $with, Shmock::$policies);
    }

    /**
     * Mocks the object's underlying static methods.
     * @param callable
     * @return void
     */
    public function shmock_class($closure)
    {
        throw new \BadMethodCallException("you are already mocking the class");
    }
}
