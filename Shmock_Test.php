<?php

require_once 'Shmock.php';

/**
* :D
*/
class Shmock_Test extends PHPUnit_Framework_TestCase
{
	public function test_foo_class_should_be_able_to_statically_mock_weewee_from_within_lala()
	{
		$foo = Shmock::create($this, 'Shmock_Foo', function($foo)
		{
			$foo->shmock_class(function($Shmock_Foo)
			{
				$Shmock_Foo->weewee()->return_value(3);
			});
		});
		$this->assertEquals(3, $foo->lala());
	}

	public function test_foo_class_should_be_able_to_statically_mock_weewee()
	{
		$Shmock_Foo = Shmock::create_class($this, 'Shmock_Foo', function($Shmock_Foo)
		{
			$Shmock_Foo->weewee()->return_value(6);
		});
		$this->assertEquals(6, $Shmock_Foo::weewee());
	}

	public function ignore_test_return_value_map_stubbed_twice_called_once()
	{
		$this->markTestSkipped('Not sure how to find out that it should have thrown an exception');
		$foo = Shmock::create($this, 'Shmock_Foo', function($foo)
		{
			$foo->for_value_map()->return_value_map(array(
				array(1, 2, 3),
				array(2, 2, 3),
			));
		});

		$this->assertEquals(3, $foo->for_value_map(2, 2), 'value map busted');
	}

	public function test_return_value_map_stubbed_and_called_twice()
	{
		$foo = Shmock::create($this, 'Shmock_Foo', function($foo)
		{
			$foo->for_value_map()->return_value_map(array(
				array(1, 2, 3),
				array(2, 2, 3),
			));
		});

		$this->assertEquals(3, $foo->for_value_map(1, 2), 'value map busted');
		$this->assertEquals(3, $foo->for_value_map(2, 2), 'value map busted');
	}
	
	public function test_mocking_no_methods_at_all_should_preserve_original_methods()
	{
		$foo = Shmock::create($this, 'Shmock_Foo', function($foo)
		{
			$foo->disable_original_constructor();
		});
		$this->assertEquals(5, $foo->weewee());
	}
}

class Shmock_Foo
{
	public function lala()
	{
		return static::weewee();
	}

	public function for_value_map($a, $b)
	{
		return $a * $b;
	}

	public static function weewee()
	{
		return 5;
	}
}
