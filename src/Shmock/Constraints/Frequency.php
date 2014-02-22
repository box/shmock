<?php

namespace Shmock\Constraints;

/**
 * @package Shmock
 * This is a generic interface to enforce a number of invocations
 */
interface Frequency
{
    /**
     * Indicates that an invocation of the method has been hit. During
     * this phase, it is possible for addCall to terminate in an error
     * @throws \PHPUnit_Framework_AssertionFailedError
     * @return void
     */
    public function addCall();

    /**
     * Checks whether the final state of frequencies was correct
     * @throws \PHPUnit_Framework_AssertionFailedError
     * @return void
     */
    public function verify();
}
