<?php

namespace Shmock\ClassBuilder;

use \StringTemplate\SprintfEngine;

/**
 * @package ClassBuilder
 * Provides an abstraction for building classes
 */
class ClassBuilder
{
    /**
     * @internal
     * @var string
     */
    private $className;

    /**
     * @internal
     * @var string
     */
    private $extends;

    /**
     * @internal
     * @var Method[]
     */
    private $methods = [];

    /**
    * @internal
    * @var string[]
    */
    private $interfaces = [];

    /**
     * @internal
     * @var
     */

    /**
     * @internal
     * @return string
     */
    private function randStr()
    {
        return substr(md5(microtime()),rand(0,26),12);
    }

    /**
     * @return string the name of the class that is being created
     */
    public function create()
    {
        while (!$this->className || class_exists($this->className)) {
            $this->className = "ClassBuilder" . $this->randStr();
        }

        $classTemplate = <<<EOF
class <className> <extends> <implements>
{
    /* trait inclusions */
    <uses>

    /* method implementations */
    private static \$__implementations__ = [];

    private static \$__decorators__ = [];

    /**
     * @param string
     * @param callable
     * @return void
     */
    public static function __add_implementation__(\$name, \$fn)
    {
        self::\$__implementations__[\$name] = \$fn;
    }

    /**
     * @param \Shmock\ClassBuilder\Decorator \$decorator
     * @return void
     */
    public static function __add_decorator__(\Shmock\ClassBuilder\Decorator \$decorator)
    {
        self::\$__decorators__[] = \$decorator;
    }

    <methods>
}
EOF;
        $engine = new SprintfEngine("<", ">");

        $functions = array_map(function ($method) {
            return $method->render();
        }, $this->methods);

        $classDef = $engine->render($classTemplate, [
            "className" => $this->className,
            "uses" => "",
            "implements" => $this->interfaceStr(),
            "methods" => implode($functions, "\n"),
            "extends" => $this->extends,
        ]);

        eval($classDef);

        foreach ($this->methods as $method) {
            $method->addToBuiltClass($this->className);
        }

        return $this->className;

    }

    /**
     * @internal
     * @return string
     */
    private function interfaceStr()
    {
        if (count($this->interfaces) == 0) {
            return "";
        }

        return " implements " . implode($this->interfaces, ",");
    }

    /**
     * @param string the method name
     * @param callable the implementation of the method
     * @param string[] the type hints for each argument. Use empty string for no typehint
     * @param string|void the access type, defaults to public
     * @return void
     */
    public function addMethod($methodName, $fn, $typeList, $accessLevel = "public")
    {
        $this->methods[] = new Method($accessLevel, $methodName, $typeList, $fn);
    }

    /**
     * @param string The name of the class to be created.
     * @return void
     * @throws InvalidArgumentException if the class has already been used by another class, interface or trait
     */
    public function setName($className)
    {
        if (class_exists($className) || trait_exists($className) || interface_exists($className)) {
            throw new InvalidArgumentException("The name $className has already been taken by something else");
        }
        $this->className = $className;
    }

    /**
     * @param  string                   $className T
     * @return void
     * @throws InvalidArgumentException if the class to extend does not exist
     */
    public function setExtends($className)
    {
        if (!class_exists($className)) {
            throw new \InvalidArgumentException("$className is not a valid class and cannot be extended");
        }
        $this->extends = " extends $className ";
    }

    /**
     * @param  string $interfaceName
     * @return void
     */
    public function addInterface($interfaceName)
    {
        $this->interfaces[] = $interfaceName;
    }
}

/**
 * @package ClassBuilder
 * Defines a method that can be built
 */
class Method
{
    private $accessLevel;
    private $methodName;
    private $argList;
    private $callable;
    private $isStatic = false;

    /**
     * @param string   $accessLevel must be public, protected or private
     * @param string   $methodName  must be [a-zA-Z\_][a-zA-Z\_\d]* and unique to the class
     * @param string[] $argList     describes the expected types defined on the method signature
     * @param callable $fn          the implementation of the method
     */
    public function __construct($accessLevel, $methodName, $typeList, $callable)
    {
        if (!in_array($accessLevel, ["private", "protected", "public"])) {
            throw new InvalidArgumentException("$accessLevel is not a valid level");
        }
        $this->accessLevel = $accessLevel;
        $this->methodName = $methodName;
        $this->typeList = array_map(function ($v) {
            static $i = 0;

            return "$v \$arg" . $i++;
        }, $typeList);
        $this->callable = $callable;
    }

    /**
     * @return string the rendered method. Once eval'ing this method, you must
     * call Method->addToBuiltClass($clazz) on the eval'd class to register the
     * implementation.
     */
    public function render()
    {
        $functionTemplate = <<<EOF
        /**
        * @param *mixed|null
        * @return mixed|null
        */
    <accessLevel> <static> function <methodName>(<typeList>)
    {
        \$fn = self::\$__implementations__["<methodName>"];

        \$joinPoint = new \Shmock\ClassBuilder\DecoratorJoinPoint(\$this,"<methodName>",\$fn);
        \$joinPoint->setArguments(func_get_args());
        \$joinPoint->setDecorators(self::\$__decorators__);

        return \$joinPoint->execute();
    }

EOF;
        $engine = new SprintfEngine("<", ">");

        return $engine->render($functionTemplate, [
            "accessLevel" => $this->accessLevel,
            "methodName" => $this->methodName,
            "typeList" => implode($this->typeList, ","),
            "static" => $this->isStatic ? "static" : "",
        ]);
    }

    /**
     * Register the underlying behavior of this function on the target class.
     * @param string the class
     * @return void
     */
    public function addToBuiltClass($class)
    {
        $class::__add_implementation__($this->methodName, $this->callable);
    }

    /**
     * @param bool
     * @return void
     */
    public function setStatic($isStatic)
    {
        $this->isStatic = $isStatic;
    }
}
