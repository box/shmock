<?php

namespace Shmock\Constraints;

class AtLeastOnce implements Frequency
{
    /**
     * @var bool
     */
    private $called = false;

    /**
     * @var string
     */
    private $methodName;

    /**
     * @param string $methodName
     */
    public function __construct($methodName)
    {
        $this->methodName = $methodName;
    }

    /**
     * @return void
     */
    public function addCall()
    {
        $this->called = true;
    }

    /**
     * @return void
     */
    public function verify()
    {
        if (!$this->called) {
            throw new \PHPUnit_Framework_AssertionFailedError(sprintf("Expected %s to be called at least once", $this->methodName));
        }
    }

}
