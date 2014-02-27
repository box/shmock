<?php

namespace Shmock\Constraints;

class MethodNameOrdering implements Ordering
{
    /**
     * @var mixed[][]
     */
    private $chain = [];

    /**
     * @var int
     */
    private $pointer = -1;

    /**
     * Add a new Spec to the end of the ordering chain.
     * @param  string       $methodName
     * @param  \Shmock\Spec $spec
     * @return void
     */
    public function addSpec($methodName, $spec)
    {
        $this->chain[] = [$methodName, $spec];
    }

    /**
     * This naive implementation of ordering will ignore
     * frequency requirements.
     * @param  string       $methodName
     * @return \Shmock\Spec
     */
    public function nextSpec($methodName)
    {
        if ($this->pointer >= count($this->chain)) {
            throw new \PHPUnit_Framework_AssertionFailedError("There are no more method calls expected given assigned ordering");
        }
        if ($this->pointer + 1 < count($this->chain)) {
            $next = $this->chain[$this->pointer + 1];
            if ($next[0] === $methodName) {
                // if the next method has the same name as the passed
                // $methodName, then advance the pointer and return the
                // given spec.
                $this->pointer++;

                return $next[1];
            }
        }

        if ($this->pointer === -1) {
            throw new \PHPUnit_Framework_AssertionFailedError(sprintf("Unexpected method invocation %s at call index %s, haven't seen any method calls yet", $methodName, $this->pointer, true));
        }
        $current = $this->chain[$this->pointer];
        if ($current[0] !== $methodName) {
            // if the current spec does not match the method name,
            // we do not have any other possible specs to return
            // so we throw an assertion failure with a print of every
            // method we've run so far:
            $methodsSoFar = [];
            for ($i = 0; $i <= $this->pointer; $i++) {
                $methodsSoFar[] = $this->chain[$i][0];
            }

            throw new \PHPUnit_Framework_AssertionFailedError(sprintf("Unexpected method invocation %s at call index %s, seen method calls so far: %s", $methodName, $this->pointer, print_r(implode($methodsSoFar, "\n"), true)));
        }

        return $current[1];
    }

    /**
     * Reset the ordering back to its initial state.
     * @return void
     */
    public function reset()
    {
        $this->pointer = 0;
    }

    /**
     * @return void
     */
    public function verify()
    {
        foreach ($this->chain as $specWithName) {
            $specWithName[1]->__shmock_verify();
        }
    }

}
