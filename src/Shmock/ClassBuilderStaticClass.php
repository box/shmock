<?php

namespace Shmock;
use \Shmock\ClassBuilder\ClassBuilder;

class ClassBuilderStaticClass implements Instance
{
    /**
     * @var string the name of the class being mocked
     */
    private $className;

    /**
     * @var ClassBuilderStaticClassSpec[]
     */
    private $expectations = [];

    /**
     * @var \PHPUnit_Framework_TestCase
     */
    private $testCase;

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
        foreach ($this->expectations as $expectation) {
            $expectation->__shmock_verify();
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
        throw new \BadMethodCallException("ordering is not supported yet");
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

        foreach ($this->expectations as $expectation) {
            $expectation->__shmock_configureClassBuilder($builder);
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
        $spec = new ClassBuilderStaticClassSpec($this->testCase, $this->className, $methodName, $with);
        $this->expectations[$methodName] = $spec;

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
