<?php

namespace Shmock;

use \Shmock\ClassBuilder\ClassBuilder;
use \Shmock\ClassBuilder\MethodInspector;

class ClassBuilderInstanceClass extends ClassBuilderStaticClass implements Instance
{
    /**
     * @var bool
     */
    protected $inShmockClass = false;

    /**
     * @var array
     */
    protected $constructorArgs = [];

    /**
     * @var bool
     */
    protected $disableOriginalConstructor = false;

    /**
     * @param string
     * @param array $with
     * @return Spec
     */
    public function initSpec($method, array $with)
    {
        if ($this->inShmockClass) {
            return parent::initSpec($method, $with);
        }
        return new InstanceSpec($this->testCase, $this->className, $method, $with, Shmock::$policies);
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
        if ($this->inShmockClass) {
            parent::addMethodToBuilder($builder, $methodCall, $resolveCall);
        } else {
            $builder->addMethod($methodCall, $resolveCall, $inspector->signatureArgs());
        }
    }

    /**
     * @param callable $fn
     * @return void
     */
    public function shmock_class(callable $fn)
    {
        $this->inShmockClass = true;
        $fn($this);
        $this->inShmockClass = false;
    }

    /**
     * @param *mixed|null
     * @return void
     */
    public function set_constructor_arguments()
    {
        $this->constructorArgs = func_get_args();
    }

    /**
     * @return void
     */
    public function disable_original_constructor()
    {
       $this->disableOriginalConstructor = true;
    }

    /**
     * @return object
     */
    public function replay()
    {
        $builtClass = parent::replay();
        $reflectionClass = new \ReflectionClass($builtClass);
        if ($this->disableOriginalConstructor) {
            return $reflectionClass->newInstanceWithoutConstructor();
        }

        return $reflectionClass->newInstanceArgs($this->constructorArgs);
    }
}