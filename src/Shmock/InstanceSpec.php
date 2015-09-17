<?php
namespace Shmock;

class InstanceSpec extends StaticSpec
{
    /**
     * @return bool
     */
    protected function isStatic()
    {
        return false;
    }
}
