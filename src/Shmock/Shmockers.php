<?php

/**
 * @package \Shmock
 */
namespace Shmock;

/**
* Include this in instances of PHPUnit_Framework_TestCase to make Shmock easily accessible
*/
trait Shmockers
{
    public function shmock($clazz, $closure=null)
    {
        return Shmock::create($this, $clazz, $closure);
    }

    public function shmock_class($clazz, $closure=null)
    {
        return Shmock::create_class($this, $clazz, $closure);
    }
}
