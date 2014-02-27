<?php

namespace Shmock\ClassBuilder;

/**
 * @package ClassBuilder
 * @internal
 * This class provides a concrete implementation of JoinPoint. Its primary
 * feature is that it wraps an array of `Decorator` instances. On invoking
 * `execute`, it will grab the next decorator, increment its internal counter
 * and invoke `decorator->decorate($this)`. It will do this until no remaining
 * `Decorator` instances exist in the array, at which point it will invoke the
 * underlying method. The counter is decrement after each execution, allowing
 * for multiple invocations.
 */
class DecoratorJoinPoint implements JoinPoint
{
    /**
     * @internal
     * @var Decorator[]
     */
    private $decorators = [];

    /**
     * @internal
     * @var int
     */
    private $index = 0;

    /**
     * @internal
     * @var string|object
     */
    private $target;

    /**
     * @internal
     * @var string
     */
    private $methodName;

    /**
     * @internal
     * @var callable
     */
    private $actualCallable;

    /**
     * @internal
     * @var array
     */
    private $arguments;

    /**
     * @return mixed|null
     */
    public function execute()
    {
        if ($this->index >= count($this->decorators)) {
            $invocation = new Invocation($this->target, $this->methodName, $this->arguments);

            return call_user_func($this->actualCallable, $invocation);
        } else {
            $nextDecorator = $this->decorators[$this->index];
            $this->index++;
            $ret = $nextDecorator->decorate($this);
            $this->index--;

            return $ret;
        }
    }

    /**
     * @param string|object $target The method receiver, which may be a class or
     * instance.
     * @param string        $methodName the method to invoke
     * @param callable|void $callable   specifically for the given
     * target and methodName. This is useful when you wish a Decorator
     * to decorate according to the target/method but need another layer
     * of indirection (via a proxy potentially). If not specified, it
     * will be composed from [$target, $callable]
     */
    public function __construct($target, $methodName, $callable=null)
    {
        $this->target = $target;
        $this->methodName = $methodName;
        $this->actualCallable = $callable ?: [$target, $methodName];

        if (!method_exists($target, $methodName)) {
            throw new \InvalidArgumentException("$methodName is not a method on the given target");
        }
    }

    /**
     * @param  array $newArguments the new arguments to pass
     * @return void
     */
    public function setArguments(array $newArgs)
    {
        $this->arguments = $newArgs;
    }

    /**
     * @param Decorator[] Decorators that will wrap this execution
     * @return void
     */
    public function setDecorators(array $decorators)
    {
        $this->decorators = $decorators;
    }

    /**
     * @return string|object
     */
    public function target()
    {
        return $this->target;
    }

    /**
     * @return string
     */
    public function methodName()
    {
        return $this->methodName;
    }

    /**
     * @return array
     */
    public function arguments()
    {
        return $this->arguments;
    }
}
