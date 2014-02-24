<?php

namespace Shmock\Constraints;

/**
 * @package Shmock
 * Simplest implementation of frequency constraints. Asserts that a method
 * is called exactly $count times.
 */
class CountOfTimes implements Frequency
{
    private $expectedCount = 0;
    private $actualCount = 0;
    private $methodName;

    /**
     * @param uint   $count
     * @param string $methodName
     */
    public function __construct($count, $methodName)
    {
        $this->expectedCount = $count;
        $this->methodName = $methodName;
    }

    /**
     * @return void
     */
    public function addCall()
    {
        $this->actualCount++;
        if ($this->actualCount > $this->expectedCount) {
            throw new \PHPUnit_Framework_AssertionFailedError(sprintf("Didn't expect %s to be called more than %s times", $this->methodName, $this->expectedCount));
        }
    }

    /**
     * @return void
     */
    public function verify()
    {
        if ($this->actualCount != $this->expectedCount) {
            throw new \PHPUnit_Framework_AssertionFailedError(sprintf("Expected %s to be called exactly %s times, called %s times", $this->methodName, $this->expectedCount, $this->actualCount));
        }
    }
}
