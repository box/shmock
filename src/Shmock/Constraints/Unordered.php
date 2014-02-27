<?php

namespace Shmock\Constraints;

class Unordered implements Ordering
{
    /**
     * A naive mapping of methodName to specs
     * @var array
     */
    private $map = [];

    /**
     * @param  string       $methodName
     * @param  \Shmock\Spec $spec
     * @return void
     */
    public function addSpec($methodName, $spec)
    {
        $this->map[$methodName] = $spec;
    }

    /**
     * @param string
     * @return \Shmock\Spec
     */
    public function nextSpec($methodName)
    {
        if (!array_key_exists($methodName, $this->map)) {
            // this assertion should never be reached if the class builder is properly
            // configured.
            throw new \LogicException("Did not expect invocation of $methodName");
        }

        return $this->map[$methodName];
    }

    /**
     * @return void
     */
    public function reset()
    {
        // no-op
    }

    /**
     * @return void
     */
    public function verify()
    {
        foreach ($this->map as $name => $expectation) {
            $expectation->__shmock_verify();
        }
    }
}
