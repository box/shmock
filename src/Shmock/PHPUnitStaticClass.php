<?php

namespace Shmock;

/**
 * StaticClass is the static equivalent of Instance. StaticClass instances can be used
 * to build a mocked class.
 *
 * Usage of this class is identical to Instance, except that methods
 * that are mocked on a StaticClass must be static.
 */
class PHPUnitStaticClass extends PHPUnitMockInstance
{

    /**
     * @internal
     * @param string $method
     * @param array the arguments
     * @return void
     */
    protected function do_strict_method_test($method, $with)
    {
        $err_msg = "#$method is an instance method on the class {$this->class}, but you expected it to be static.";
        try {
            $reflection_method = new \ReflectionMethod($this->class, $method);
            $this->test_case->assertTrue($reflection_method->isStatic(), $err_msg);
            $this->test_case->assertFalse($reflection_method->isPrivate(), "#$method is a private method on {$this->class}, but you cannot mock a private method.");
        } catch (\ReflectionException $e) {
            $this->test_case->assertTrue(method_exists($this->class, '__callStatic'), "The method #$method does not exist on the class {$this->class}");
        }

    }

    /**
    * @internal
    * Since you can't use the builder paradigm for mock classes, we have to play dirty here.
    * @return string the name of the mock class
    */
    public function replay()
    {
        $mock_class = get_class($this->construct_mock());

        foreach ($this->specs as $spec) {
            $spec->__shmock_finalize_expectations($mock_class, Shmock::$policies, true, $this->class);
        }

        return $mock_class;
    }
}
