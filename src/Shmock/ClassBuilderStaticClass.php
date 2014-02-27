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
        $this->ordering->verify();
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
        $builder = new ClassBuilder();
        if (class_exists($this->className)) {
            $builder->setExtends($this->className);
        } elseif (interface_exists($this->className)) {
            $builder->addImplements($this->className);
        }

        $resolveCall = function (Invocation $inv) {
            $spec = $this->ordering->nextSpec($inv->getMethodName());

            return $spec->doInvocation($inv);
        };

        foreach (array_unique($this->expectedMethodCalls) as $methodCall) {
            $inspector = new MethodInspector($this->className, $methodCall);
            $builder->addStaticMethod($methodCall, $resolveCall, $inspector->signatureArgs());
        }

        return $builder->create();
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
        $spec = new ClassBuilderStaticClassSpec($this->testCase, $this->className, $methodName, $with);
        $this->ordering->addSpec($methodName, $spec);
        $this->expectedMethodCalls[] = $methodName;

        return $spec;
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
