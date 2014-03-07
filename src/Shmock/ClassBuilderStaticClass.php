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
    private $className;

    /**
     * @var string[]
     */
    private $expectedMethodCalls = [];

    /**
     * @var \PHPUnit_Framework_TestCase
     */
    private $testCase;

    /**
     * @var Ordering
     */
    private $ordering = null;

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
     * @return void
     */
    public function disable_original_constructor()
    {
        // no-op
    }

    /**
     * @param *mixed|null
     * @return void
     */
    public function set_constructor_arguments()
    {

    }

    /**
     * @return void
     */
    public function dont_preserve_original_methods()
    {
        $reflectionClass = new \ReflectionClass($this->className);
        $methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            if (!$method->isFinal() && $method->isStatic()) {
                $this->__call($method->getName(), [])->any()->return_null();
            }
        }
    }

    /**
     * @return void
     */
    public function order_matters()
    {
        if ($this->ordering !== null) {
            throw new \InvalidArgumentException("You cannot set the ordering constraint more than once. (It is implicitly set to 'unordered' after the first method is specified)");
        }
        $this->ordering = new MethodNameOrdering();
    }

    /**
     * @return void
     */
    public function order_doesnt_matter()
    {
        // no-op
    }

    /**
     * @return mixed The mock object, now in its replay phase.
     */
    public function replay()
    {
        // build the mock class
        $builder = new ClassBuilder();
        if (class_exists($this->className)) {
            $builder->setExtends($this->className);
        } elseif (interface_exists($this->className)) {
            $builder->addImplements($this->className);
        } else {
            throw new \InvalidArgumentException("Class or interface " . $this->className . " does not exist");
        }

        // every mocked method goes through this invocation, which delegates
        // the retrieval of the correct Spec to this Instance's Ordering constraint.
        $resolveCall = function (Invocation $inv) {
            $spec = $this->ordering->nextSpec($inv->getMethodName());

            return $spec->doInvocation($inv);
        };

        foreach (array_unique($this->expectedMethodCalls) as $methodCall) {
            $this->addMethodToBuilder($builder, $methodCall, $resolveCall);
        }

        return $builder->create();
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
        $builder->addStaticMethod($methodCall, $resolveCall, $inspector->signatureArgs());
    }

    /**
     * @param  string $methodName
     * @param  array  $arguments
     * @return Spec   a new spec that will be used when finalizing
     * the expectations of this mock.
     */
    public function __call($methodName, $with)
    {
        if ($this->ordering === null) {
            $this->ordering = new Unordered();
        }
        $spec = $this->initSpec($methodName, $with);
        $this->ordering->addSpec($methodName, $spec);
        $this->expectedMethodCalls[] = $methodName;

        return $spec;
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
