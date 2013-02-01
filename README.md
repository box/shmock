# Shmock (SHorthand for MOCKing)

## What is this?

Shmock is a smooth alternative for creating mocks with PHPUnit that uses the mock/replay concept from EasyMock but uses closures to define the scope for mocking.

  ```php
	<?php
	namespace Foo;

	/**
	 * Here's a class we're trying to test yay.
	 */
	class Foo
	{
		private $foo = 0;
		private $incrementing_service = null;

		public function __construct(Incrementing_Service $incrementing_service)
		{
			$this->incrementing_service = $incrementing_service;
		}

		public function next_foo()
		{
			$this->foo = $this->incrementing_service->increment($this->foo);
			return $this->foo;
		}
	}

	/**
	 * Our test case runs the same test case twice - once with the original PHPUnit mocking
	 * syntax and a second time with Shmock syntax.
	 */
	class Foo_Test extends PHPUnit_Framework_TestCase
	{
                use \Shmock\Shmockers; // This enables the use of the Shmock helper methods (replicated below)

                public function test_phpunit_original_mocking_syntax()
		{
			// this is the original PHPUnit mock syntax

			$incrementing_service_mock = $this->getMock('\Foo\Incrementing_Service', array('increment'));
			$incrementing_service_mock->expects($this->once())
				->method('increment')
				->with($this->equalTo(0))
				->will($this->returnValue(1));

			$foo = new Foo($incrementing_service_mock);
			$this->assertEquals(1, $foo->next_foo(0));
		}

		/**
		 * Create a shmock representation for $class_name and configure expected
		 * mock interaction with $conf_closure
		 * @return Shmock A fully configured mock object
		 * @note You do not need this protected method if you use the Shmockers trait, shown above
		 */
		protected function shmock($class_name, $conf_closure)
		{
			return \Shmock\Shmock::create_class($this, $class_name, $conf_closure);
		}

		public function test_shmock_syntax()
		{
			// here's shmock. Neat huh?
			$incrementing_service_mock = $this->shmock('\Foo\Incrementing_Service', function($shmock)
			{
				$shmock->increment(0)->return_value(1);
			});

			$foo = new Foo($incrementing_service_mock);
			$this->assertEquals(1, $foo->next_foo(0));
		}
	}
  ```
## Installation

Shmock can be installed directly from [Packagist](https://packagist.org/packages/box/shmock).

```json
"require": {
    "box/shmock": "1.0.0.x-dev"
}
```
Alternatively you can download Shmock.php and Shmockers.php into your test directory and run

```
  require_once 'Shmock.php';
```
PHPUnit should already be on the load path for this to work.

## 5.3 compatible, 5.4 approved

For users of PHP 5.3, you will need to use the static methods `Shmock::create` and `Shmock::create_class` directly and write your own helper functions. >=5.4 users should use the Shmockers trait for optimal helperness. 

## Type Safety

Shmock is typesafe by default and will attempt to tell you when you're using the wrong mocking approach. Shmock will throw errors in cases where:

* You mock a static method as though it were an instance method or vice versa.
* You mock a private method as though it were protected or public.
* You mock a method that does not exist _and_ there is no __call / __callStatic magic method provided.

These checks can be disabled by calling `$mock_object->disable_strict_method_checking()` inside the shmock closure. We also plan on supporting parameter and return value checking if it complies with yet-to-be-defined PHPDoc conventions.

## Full list of Shmock features:
  ```php
  <?php
  // This code could conceptually be part of a test method from the above Foo_Test class
	$inc_service = $this->shmock('\Foo\Incrementing_Service', function($my_class_shmock) // [1]
	{
		$my_class_shmock->no_args_method(); // [2]
		$my_class_shmock->two_arg_method('param1', 'param2'); // [3]
		$my_class_shmock->method_that_returns_a_number()->return_value(100); // [4]
		$my_class_shmock->method_that_gets_run_twice()->times(2); // [5]
		$my_class_shmock->method_that_gets_run_any_times()->any(); // [6]

		$my_class_shmock->method_puts_it_all_together('with', 'args')->times(2)->return_value(false);

		$my_class_shmock->method_returns_another_mock()->return_shmock('\Another_Namespace\Another_Class', function($another_class) // [7]
		{
			$another_class->order_matters(); // [8]
			$another_class->disable_original_constructor(); // [9a]
			$another_class->set_constructor_arguments(1, 'Foo'); // [9b]

			$another_class->method_dies_horribly()->throw_exception(new InvalidArgumentException()); // [10]

			$another_class->method_gets_stubbed(1,2)->will(function(PHPUnit_Framework_MockObject_Invocation $invocation)
			{
				$a = $invocation->parameters[0];
				$b = $invocation->parameters[1];
				return $a + $b; // [11]
			});
		});

		$my_class_shmock->shmock_class(function($Inc_Service)
		{
			$Inc_Service->my_static_method()->any()->return_value('This was returned inside the mock instance using the static:: prefix'); // [12]
		});

	})

	$another_class = $this->shmock_class('\Another_Namespace\Another_Class', function($Another_Class) // [13]
	{
		$Another_Class->a_static_method()->return_value(1);
	});
  ```

1. Shmock lets you configure a mock object inside a closure. You work with a proxy object that feels like the real thing.
2. Invoking a method sets up the expectation that it will be called once.
3. Invoking a method with arguments causes it to expect those arguments when actually invoked.
4. You can return values from specific invocations. In the example, the value 100 will be returned when you call the method.
5. You can specify an expectation for the number of times a method will be called. By default it's expected once.
6. Or you can specify "0 or more" times with any()
7. You can nest your Shmock invocations, letting you define your mock dependencies elegantly. (If you have a two-way dependency, you can always just `return_value($other_shmock)` and define it somewhere else )
8. On an object-level you can specify "order matters", meaning that the ordering of function invocations should be asserted against as well. Under the hood, this uses PHPUnit's `at(N)` calls automatically
9. You have some options as far as defining constructor arguments. a) You can opt to disable the original constructor. Normally PHPUnit will run the original constructor. b) You can run the original constructor with the given arguments.
10. Instead of returning a value, you can throw an exception when a method gets called.
11. Even more sophisticated, you can execute an arbitrary closure when the function gets called.
12. If you want to mock static functions, you call `shmock_class` which will give you all the same Shmock semantics as instances (where it makes sense). This is particularly useful when you want to partially mock an object, keeping some of the original behavior, but mocking out static / protected methods that may exist that the method you are testing is dependent on.
13. You can also mock a class independently of a mock instance.



