<?php

namespace Shmock\ClassBuilder;

/**
 * Simple Decorator that takes a closure
 */
class CallableDecorator implements Decorator
{
    /**
     * @var callable
     */
    private $fn;

    /**
     * @param callable $fn
     */
    public function __construct(callable $fn)
    {
        $this->fn = $fn;
    }

    /**
     * @param  JoinPoint  $joinPoint
     * @return mixed|null
     */
    public function decorate(JoinPoint $joinPoint)
    {
        return call_user_func($this->fn, $joinPoint);
    }
}
