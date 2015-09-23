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
     * An internal mapping only used for converting this ordering
     * to a MethodNameOrdering object if needed.
     *
     * @var array
     */
    private $orderedMap = [];

    /**
     * @param  string       $methodName
     * @param  \Shmock\Spec $spec
     * @return void
     */
    public function addSpec($methodName, $spec)
    {
        $this->map[$methodName] = $spec;
        $this->orderedMap[] = [$methodName, $spec];
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

    /**
     * @return MethodNameOrdering
     */
    public function convertToMethodNameOrdering()
    {
        $methodOrdering = new MethodNameOrdering();
        for ($i = 0; $i < count($this->orderedMap); $i++) {
            $methodOrdering->addSpec($this->orderedMap[$i][0], $this->orderedMap[$i][1]);
        }
        return $methodOrdering;
    }
}
