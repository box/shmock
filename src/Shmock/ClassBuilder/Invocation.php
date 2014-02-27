<?php

namespace Shmock\ClassBuilder;

/**
 * Passed to installed methods.
 */
class Invocation
{
    /**
     * @var string|object
     */
    private $target = null;

    /**
     * @var array
     */
    private $arguments = null;

    /**
     * @var string
     */
    private $methodName;

    /**
     * @param string|object
     * @param string
     * @param array
     */
    public function __construct($target, $methodName, $arguments)
    {
        $this->target = $target;
        $this->methodName = $methodName;
        $this->arguments = $arguments;
    }

    /**
     * @return string|object
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @return string
     */
    public function getMethodName()
    {
        return $this->methodName;
    }
}
