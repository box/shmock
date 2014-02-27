<?php

namespace Shmock\Constraints;

class MethodNameOrdering implements Ordering
{
    /**
     * Add a new Spec to the end of the ordering chain.
     * @param  string       $methodName
     * @param  \Shmock\Spec $spec
     * @return void
     */
    public function addSpec($methodName, $spec)
    {
        throw new \BadMethodCallException();
    }

    /**
     * Get the next spec from the chain given our current position. It is possible
     * for this method to return the same spec multiple times. If the method requested
     * is not available at this point in the chain, an assertion error will be thrown.
     * @param  string       $methodName
     * @return \Shmock\Spec
     */
    public function nextSpec($methodName)
    {
        throw new \BadMethodCallException();
    }

    /**
     * Reset the ordering back to its initial state.
     * @return void
     */
    public function reset()
    {

    }

    /**
     * @return void
     */
    public function verify()
    {

    }

}
