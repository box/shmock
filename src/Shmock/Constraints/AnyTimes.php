<?php

namespace Shmock\Constraints;

class AnyTimes implements Frequency
{
    /**
     * @return void
     */
    public function addCall()
    {
        // no op
    }

    /**
     * @return void
     */
    public function verify()
    {
        // no op
    }
}
