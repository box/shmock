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
    /**
     * @param string the class to mock
     * @param callable|null|void The build phase closure
     * @return mixed
     */
    public function shmock($clazz, $closure=null)
    {
        return Shmock::create($this, $clazz, $closure);
    }

    /**
     * @param string the class to create a class mock of
     * @param callable|null|void The build phase closure
     * @return string the name of the mock class
     */
    public function shmock_class($clazz, $closure=null)
    {
        return Shmock::create_class($this, $clazz, $closure);
    }
}
