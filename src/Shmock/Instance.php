<?php

namespace Shmock;

/**
 * A mock provider is a strategy for creating, building, and
 * verifying expectations on the mock objects that shmock creates.
 */
interface Instance
{
    /**
     * This will verify that the expectations established via the Specs were met.
     * @return void
     */
    public function verify();

    /**
     * @return void
     */
    public function disable_original_constructor();

    /**
     * @param *mixed|null
     * @return void
     */
    public function set_constructor_arguments();

    /**
     * @return void
     */
    public function dont_preserve_original_methods();

    /**
     * @return void
     */
    public function order_matters();

    /**
     * @return void
     */
    public function order_doesnt_matter();

    /**
     * @return mixed The mock object, now in its replay phase.
     */
    public function replay();

    /**
     * @param  string $methodName
     * @param  array  $arguments
     * @return Spec   a new spec that will be used when finalizing
     * the expectations of this mock.
     */
    public function __call($methodName, $with);

    /**
     * Mocks the object's underlying static methods.
     * @return void
     */
    public function shmock_class($closure);

}
