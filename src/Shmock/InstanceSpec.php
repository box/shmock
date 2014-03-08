<?php

namespace Shmock;

// TODO: make BaseSpec + instance/static specializations
class InstanceSpec extends StaticSpec implements Spec
{
    /**
     * @return void
     */
    protected function doStrictMethodCheck()
    {
        $errMsg = "#{$this->methodName} is a static method on the class {$this->className}, but you expected it to be an instance method.";
        try {
            $reflectionMethod = new \ReflectionMethod($this->className, $this->methodName);
            $this->testCase->assertFalse($reflectionMethod->isStatic(), $errMsg);
            $this->testCase->assertFalse($reflectionMethod->isPrivate(), "#{$this->methodName} is a private method on {$this->className}, but you cannot mock a private method.");
        } catch (\ReflectionException $e) {
            $this->testCase->assertTrue(method_exists($this->className, '__call'), "The method #{$this->methodName} does not exist on the class {$this->className}");
        }
    }

    /**
     * @return bool
     */
    protected function isStatic()
    {
        return false;
    }
}