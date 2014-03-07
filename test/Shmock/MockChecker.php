<?php

namespace Shmock;

trait MockChecker
{
    protected $staticClass = null;

    /**
     * plucked from https://github.com/sebastianbergmann/phpunit-mock-objects/blob/master/tests/MockObjectTest.php
     */
    protected function resetMockObjects()
    {
        $refl = new \ReflectionObject($this);
        $refl = $refl->getParentClass();
        $prop = $refl->getProperty('mockObjects');
        $prop->setAccessible(true);
        $prop->setValue($this, array());
    }

    protected function assertFailsMockExpectations(callable $fn, $message)
    {
        $threw = true;
        try {
            $fn();
            $threw = false;
        } catch (\PHPUnit_Framework_AssertionFailedError $e) {

        }
        $this->assertTrue($threw, "Expected callable to throw phpunit failure: $message");

        $this->resetMockObjects();
        $this->staticClass = null;

    }

    protected function assertMockObjectsShouldFail($message)
    {
        $threw = true;
        try {
            $this->verifyMockObjects();
            $this->staticClass->verify();
            $threw = false;
        } catch ( \PHPUnit_Framework_AssertionFailedError $e) {

        }
        if (!$threw) {
            $this->fail("Expected mock objects to fail in PHPUnit: $message");
        }
        $this->resetMockObjects();
    }
}
