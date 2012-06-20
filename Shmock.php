<?php

// PHP 5.3 or later. 
// The 'Shmockers' helper trait is available in PHP 5.4
// PHPUnit 3.6 or later should be in the path
require_once 'PHPUnit/Autoload.php';

/**
*
* Introducing Shmock - so luscious!
*
* $mock = $this->shmock('Box_File', function($shmock) use ($user)
* {
*	$shmock->set_user($user)->once()->return_value(true);
* });
* 
* $mock_file = $this->shmock('Box_File', function($shmock_file)
* {
*		$shmock_file->user()->return_mock('Box_Account', function($shmock_user)
* 		{
*			$shmock_user->user_id()->return_value(5);
*		});
*  });
* 
*
* Questions: 
* - Hamcrest matchers?
*/
class Shmock
{

	protected $test_case = null;
	protected $specs = array();
	protected $class = null;
	protected $preserve_original_methods = true;
	protected $disable_original_constructor = false;
	protected $strict_method_checking = true;
	
	/* If we want to shmock the static context of a shmock'd object
	* we need to call get_class() on the final mock, so we save
	* any configuration closure until after everything is done. 
	*/
	protected $shmock_class_closure = null;

	protected $order_matters = false;
	protected $call_index = 0;
	
	protected $constructor_arguments = array();
	protected $methods = array();


	public static function create($test_case, $class, $closure)
	{
		$shmock = new Shmock($test_case, $class);
		if ($closure)
		{
			$closure($shmock);
		}
		return $shmock->replay();
	}
	
	public static function create_class($test_case, $class, $closure)
	{
		$shmock_class = new Shmock_Class($test_case, $class);
		if ($closure)
		{
			$closure($shmock_class);
		}
		return $shmock_class->replay();
	}

	protected function __construct($test_case, $class)
	{
		$this->test_case = $test_case;
		$this->class = $class;
	}
	
	/**
	* When strict method checking is enabled, Shmock will verify
	* that a named method, or any __call method, is defined on the
	* target object when setting expectations on that method.
	*
	* Note that having a declared method is not actually required
	* for PHPUnit to successfully mock the target object. It is an
	* additional check made by Shmock to help identify breakages 
	* between classes that use mocks to achieve test isolation.
	*/
	public function disable_strict_method_checking()
	{
		$this->strict_method_checking = false;
	}
	
	public function disable_original_constructor()
	{
		$this->disable_original_constructor = true;
		return $this;
	}
	
	/**
	 * Any arguments passed in here will be included in the 
	 * constructor call for the mocked class.
	 */
	public function set_constructor_arguments()
	{
		$this->constructor_arguments = func_get_args();
	}

	public function dont_preserve_original_methods()
	{
		$this->preserve_original_methods = false;
		return $this;
	}

	public function order_matters()
	{
		$this->order_matters = true;
		return $this;
	}

	public function order_doesnt_matter()
	{
		$this->order_matters = false;
		return $this;
	}

	protected function construct_mock()
	{
		$builder = $this->test_case->getMockBuilder($this->class);
		
		if ($this->disable_original_constructor)
		{
			$builder->disableOriginalConstructor();
		}
		if ($this->preserve_original_methods)
		{
			if (count($this->methods) == 0)
			{
				/*
				* If you pass an empty array of methods to the PHPUnit mock builder,
				* it's effectively like saying don't preserve any methods at all. Instead
				* we tell the builder to mock a single fake method when necessary.
				*/
				$this->methods[] = "__fake_method_for_shmock_to_preserve_methods"; 
			}
			$builder->setMethods(array_unique($this->methods));	
		}
		if ($this->constructor_arguments)
		{
			$builder->setConstructorArgs($this->constructor_arguments);
		}
		$mock = $builder->getMock();
		return $mock;
	}

	public function replay()
	{
		$shmock_instance_class = null;
		if ($this->shmock_class_closure)
		{
			$s = $this->shmock_class_closure;
			$shmock_instance_class = new Shmock_Instance_Class($this->test_case, $this->class);
			$s($shmock_instance_class);
			$this->methods = array_merge($this->methods, $shmock_instance_class->methods);
		}

		$mock = $this->construct_mock();

		if ($shmock_instance_class)
		{
			$shmock_instance_class->set_mock($mock);
			$shmock_instance_class->replay();
		}
		
		foreach ($this->specs as $spec)
		{
			$spec->finalize_expectations($mock);
		}
		
		
		return $mock;
	}

	protected function do_strict_method_test($method)
	{
		$err_msg = "Attempted to expect #$method, which is not defined as a non-static method in the class {$this->class}. If you wish to disable this check, call \$shmock->disable_strict_method_checking()";
		try
		{
			$reflection_method = new ReflectionMethod($this->class, $method);
			$this->test_case->assertTrue(!$reflection_method->isStatic(), $err_msg);
		}
		catch (ReflectionException $e)
		{
			$this->test_case->assertTrue(method_exists($this->class, '__call'), $err_msg);			
		}
	}

	public function shmock_class($closure)
	{
		$this->shmock_class_closure = $closure;
	}
	
	public function __call($method, $with)
	{
		if ($this->strict_method_checking)
		{
			$this->do_strict_method_test($method);
		}
		$this->methods[] = $method;
		$spec = new Shmock_PHPUnit_Spec($this->test_case, $this, $method, $with, $this->order_matters, $this->call_index);
		$this->specs[] = $spec;
		$this->call_index++;
		return $spec;
	}
}


class Shmock_Class extends Shmock
{
	
	protected function do_strict_method_test($method)
	{
		$err_msg = "Attempted to expect #$method, which is not defined statically in the class {$this->class}. You may implement __callStatic or, if you wish to disable this check, call \$shmock->disable_strict_method_checking()";
		try
		{
			$reflection_method = new ReflectionMethod($this->class, $method);
			$this->test_case->assertTrue($reflection_method->isStatic(), $err_msg);
		}
		catch (ReflectionException $e)
		{
			$this->test_case->assertTrue(method_exists($this->class, '__callStatic'), $err_msg);
		}

	}
	
	/**
	* Since you can't use the builder paradigm for mock classes, we have to play dirty here. 
	*/
	public function replay()
	{
		$mock_class = get_class($this->construct_mock());
		
		foreach ($this->specs as $spec)
		{
			$spec->finalize_expectations($mock_class, true);
		}
		
		return $mock_class;
	}
}


/**
* This is a private class, do not use.
*/
class Shmock_Instance_Class extends Shmock_Class
{
	private $mock;

	public function set_mock($mock)
	{
		$this->mock = $mock;
	}
	
	protected function construct_mock()
	{
		return $this->mock;
	}
}


class Shmock_PHPUnit_Spec
{
	
	private $test_case = null;
	private $method = null;
	private $with = null;
	private $times = 1;
	private $will = null;
	private $order_matters = null;
	private $call_index = null;
	
	public function __construct($test, $shmock, $method, $with, $order_matters, $call_index)
	{
		$this->test_case = $test;
		$this->method = $method;
		$this->with = $with;
		$this->order_matters = $order_matters;
		$this->call_index = $call_index;
	}
	
	public function times($times)
	{
		$this->times = $times;
		return $this;
	}

	public function once()
	{
		return $this->times(1);
	}
	
	public function any()
	{
		return $this->times(null);
	}
	
	public function never()
	{
		return $this->times(0);
	}
	
	public function will($will_closure)
	{
		$this->will = $will_closure;
		return $this;
	}
	
	public function return_value($value)
	{
		return $this->will(function() use ($value)
		{
			return $value;
		});
		return $this;
	}

	public function throw_exception($e=null)
	{
		return $this->will(function() use ($e)
		{
			if (!$e)
			{
				$e = new Exception();
			}
			throw $e;
		});
	}
	
	public function return_value_map($map_of_args_to_values)
    {
            $limit = count($map_of_args_to_values);
            $this->test_case->assertGreaterThan(0, $limit, 'Must specify at least one return value');
            $this->times($limit);

            $stub = new PHPUnit_Framework_MockObject_Stub_ReturnValueMap($map_of_args_to_values);
            return $this->will(function($invocation) use ($stub)
            {
                    return $stub->invoke($invocation);
            });
            return $this;
    }
    

	public function return_consecutively($array_of_values, $keep_returning_last_value=false)
	{
		$this->will(function() use ($array_of_values, $keep_returning_last_value)
		{
			static $counter = -1;
			$counter++;
			if ($counter == count($array_of_values))
			{
				if ($keep_returning_last_value)
				{
					return $array_of_values[count($array_of_values)-1];
				}
			}
			else
			{
				return $array_of_values[$counter];
			}
		});
		if (!$keep_returning_last_value)
		{
			$this->times(count($array_of_values));
		}
		return $this;
	}

	public function return_shmock($class, $shmock_closure=null)
	{
		$test_case = $this->test_case;
		if ($shmock_closure)
		{
			return $this->will(function() use ($class, $shmock_closure, $test_case)
			{
				return Shmock::create($test_case, $class, $shmock_closure);
			});
		}
		else
		{
			return $this;
		}
	}

	public function finalize_expectations($mock, $static=false)
	{	
		$test_case = $this->test_case;

		if ($this->times === null)
		{
			if ($static)
			{
				$builder = $mock::staticExpects($test_case->any());
			}
			else
			{
				$builder = $mock->expects($test_case->any());
			}
		}
		else if ($this->order_matters)
		{
			if ($static)
			{
				$builder = $mock::staticExpects($test_case->at($this->call_index));
			}
			else
			{
				$builder = $mock->expects($test_case->at($this->call_index));
			}
		}
		else
		{
			if ($static)
			{				
				$builder = $mock::staticExpects($test_case->exactly($this->times));
			}
			else
			{
				$builder = $mock->expects($test_case->exactly($this->times));
			}
		}
		
		$builder->method($this->method);
		
		if ($this->with)
		{	
			$function = new ReflectionMethod(get_class($builder),'with');
			$function->invokeargs($builder, $this->with);
		}
		
		if ($this->will)
		{
			$builder->will(new Shmock_Closure_Invoker($this->will));
		}
		
	}
}

class Shmock_Closure_Invoker implements PHPUnit_Framework_MockObject_Stub
{
	private $closure = null;
	
	public function __construct($closure)
	{
		$this->closure = $closure;
	}
	public function invoke(PHPUnit_Framework_MockObject_Invocation $invocation)
	{
		$fn = $this->closure;
		return $fn($invocation);
	}
	
	public function toString()
	{
		return "Closure invoker";
	}
}

if (phpversion() >= 5.4)
{
	require_once 'Shmockers.php';
}
