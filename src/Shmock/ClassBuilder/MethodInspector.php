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
        try {
            $this->reflMethod = new \ReflectionMethod($this->className, $this->methodName);
        } catch (\ReflectionException $e) {
            $this->reflMethod = null;
        }
    }

    /**
     * @return string[] the string representations of function's arguments
     */
    public function signatureArgs()
    {
        if ($this->reflMethod == null) {
            return [""];
        }
        $parameters = $this->reflMethod->getParameters();
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
            if ($parameter->isPassedByReference()) {
                $arg[] = "&";
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
