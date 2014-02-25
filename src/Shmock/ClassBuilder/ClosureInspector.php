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
    public function signatureArgs()
    {
        $reflMethod = new \ReflectionFunction($this->func);
        $parameters = $reflMethod->getParameters();
        $result = [];
        foreach ($parameters as $parameter) {
            $arg = [];
            if ($parameter->isArray()) {
                $arg []= "array";
            } elseif ($parameter->isCallable()) {
                $arg []= "callable";
            } elseif ($parameter->getClass()) {
                $arg []=  "\\". $parameter->getClass()->getName();
            }
            $arg[]= "$" . $parameter->getName();
            if ($parameter->isDefaultValueAvailable()) {
                $arg[] = "= " . var_export($parameter->getDefaultValue(), true);
            }
            $result[] = implode($arg, " ");
        }

        return $result;
    }
}
