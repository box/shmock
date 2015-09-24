<?php

namespace Shmock;

class Policy_Test extends \PHPUnit_Framework_TestCase
{
    use Shmockers;

    protected function setUp()
    {
        Shmock::add_policy(new Even_Number_Policy());
    }

    public function tearDown()
    {
        Shmock::clear_policies();
        Shmock::verify();
    }

    /**
     * @expectedException \Shmock\Shmock_Exception
     */
    public function test_policies_intercept_mock_arguments()
    {
        // Even_Calculator does not except anything but
        // integers, which our Even_Number_Policy catches.
        // We expect that a \Shmock\Shmock_Exception will be thrown
        // as a result of using a non-integer here

        $this->shmock('Shmock\Even_Calculator', function ($calculator) {
            $calculator->raise_to_even(4.4)->return_value(6);
        });

        $this->fail("should not reach here");
    }

    public function test_policies_allow_valid_parameters()
    {
        $calculator = $this->shmock('Shmock\Even_Calculator', function ($calculator) {
            $calculator->raise_to_even(5)->return_value(6);
        });

        $this->assertEquals(6, $calculator->raise_to_even(5));
    }

    /**
     * @expectedException \Shmock\Shmock_Exception
     */
    public function test_policy_prevents_odd_return_values()
    {
        $calculator = $this->shmock('Shmock\Even_Calculator', function ($calculator) {
            $calculator->raise_to_even(5)->any()->return_value(7);
        });

        $this->fail("should not reach here");
    }

    /**
     * @expectedException \Shmock\Shmock_Exception
     */
    public function test_policy_prevents_unexpected_throws()
    {
        $calculator = $this->shmock('Shmock\Even_Calculator', function ($calculator) {
            $calculator->raise_to_even(5)->any()->throw_exception(new \Exception());
        });

        $this->fail("should not reach here");
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function test_policy_allows_valid_throws()
    {
        $calculator = $this->shmock('Shmock\Even_Calculator', function ($calculator) {
            $calculator->raise_to_even(5)->throw_exception(new \InvalidArgumentException());
        });

        $calculator->raise_to_even(5);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function test_arg_policy_throws_when_no_args_passed()
    {
        $this->shmock('Shmock\Even_Calculator', function ($calculator) {
            $calculator->raise_to_even();
        });
    }

    public function test_arg_policy_does_not_throw_when_no_args_passed_and_escape_flag_set()
    {
        Shmock::$check_args_for_policy_on_instance_method_when_no_args_passed = false;
        $calc = $this->shmock('Shmock\Even_Calculator', function ($calculator) {
            $calculator->raise_to_even()->return_value(6);
        });
        Shmock::$check_args_for_policy_on_instance_method_when_no_args_passed = true;
        $this->assertEquals($calc->raise_to_even(13), 6);
    }
}

class Even_Calculator
{
    /**
     * Should always return an even number
     */
    public function raise_to_even($value)
    {
        if (!is_integer($value)) {
            throw new \InvalidArgumentException("$value");
        }
        if ($value % 2 != 0) {
            return $value + 1;
        }

        return $value;
    }
}

/**
 * When mocking the Even_Calculator, we want the mock objects to be subject to the
 * same contracts that would be enforced at runtime.
 */
class Even_Number_Policy extends Policy
{
    public function check_method_parameters($class, $method, $parameters, $static)
    {
        if (count($parameters) == 0) {
            throw new \InvalidArgumentException("No args passed!");
        }

        if (!is_integer($parameters[0])) {
            throw new Shmock_Exception();
        }
    }

    public function check_method_return_value($class, $method, $return_value, $static)
    {
        if ($return_value % 2 === 1) {
            throw new Shmock_Exception();
        }
    }

    public function check_method_throws($class, $method, $exception, $static)
    {
        if (!($exception instanceof \InvalidArgumentException)) {
            throw new Shmock_Exception();
        }
    }
}
