<?php

namespace Shmock\ClassBuilder;

/**
 * Utility class to grab the type hints off of a class method.
 */
class MethodInspector
{
    /**
     * @var string
     */
    private $className;

    /**
     * @var string
     */
    private $methodName;

    /**
     * @param string the name of the class
     * @param string the name of the method
     */
    public function __construct($className, $methodName)
    {
        $this->className = $className;
        $this->methodName = $methodName;
    }

    /**
     * @return string[] the string representations of the type hints
     */
    public function typeHints()
    {
        $reflMethod = new \ReflectionMethod($this->className, $this->methodName);
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
