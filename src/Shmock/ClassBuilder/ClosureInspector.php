<?php

namespace Shmock\ClassBuilder;

/**
 * Utility class to grab the type hints off of a closure
 */
class ClosureInspector
{
    private $func;

    /**
     * @param \Closure a closure to inspect
     */
    public function __construct(\Closure $func)
    {
        $this->func = $func;
    }

    /**
     * @return string[] the string representations of the type hints
     */
    public function typeHints()
    {
        $reflMethod = new \ReflectionFunction($this->func);
        $parameters = $reflMethod->getParameters();
        $result = [];
        foreach ($parameters as $parameter) {
            if ($parameter->isArray()) {
                $result[] = "array";
            } elseif ($parameter->isCallable()) {
                $result[] = "callable";
            } elseif ($parameter->getClass()) {
                $result[] = $parameter->getClass()->getName();
            } else {
                $result[] = "";
            }
        }

        return $result;
    }
}
