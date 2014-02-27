<?php

namespace Shmock\Constraints;

/**
 * Resolves Specs with an implementation-specific ordering
 * resolution. Used internally by implementations of Instance
 * to track which Specs to invoke when.
 *
 * Ordering is not necessarily compatible when combined with frequency
 * constraints set on individual Specs.
 */
interface Ordering
{
    /**
     * Add a new Spec to the end of the ordering chain.
     * @param  string       $methodName
     * @param  \Shmock\Spec $spec
     * @return void
     */
    public function addSpec($methodName, $spec);

    /**
     * Get the next spec from the chain given our current position. It is possible
     * for this method to return the same spec multiple times. If the method requested
     * is not available at this point in the chain, an assertion error will be thrown.
     * @param  string       $methodName
     * @return \Shmock\Spec
     */
    public function nextSpec($methodName);

    /**
     * Reset the ordering back to its initial state.
     * @return void
     */
    public function reset();

    /**
     * Verifies that all the underlying specs have been satisfied
     * @return void
     */
    public function verify();
}
