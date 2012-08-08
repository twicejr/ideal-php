<?php
/**
 * Class boven op PHPUnit_Framework_TestCase die enige utility functies biedt 
 * bij het gebruik van Mollie Fakeweb met PHPUnit 
 *
 * @author Mathieu Kooiman <mathieu@mollie.nl>
 * @copyright Copyright (C) 2009, Mollie B.V.
 * @codeCoverageIgnore
 */
class Mollie_Fakeweb_TestCase extends PHPUnit_Framework_TestCase
{
	public function setUp()
	{
		mollie_fakeweb::enable();
		mollie_fakeweb::$allow_net_connect = FALSE;
	}
	
	public function tearDown()
	{
		mollie_fakeweb::disable();
	}
	
	protected function _registerFakeWebUrlCallback($function, $method = 'GET', $url = 'http://localhost/~mollie/test.php', $result_array = NULL)
	{
		if (is_null($result_array) || !is_array($result_array))
			$result_array = array ('code' => 200, 'body' => 'Test output');
			
		$callable_data = NULL;

		if ($function instanceof Closure) {
			$callable_data = $function;
		}
		else if (is_string($function)) {
			$callable_data = array($function, array('self' => $this));
		}
		else if (is_null($function)) {
			$callable_data = null;
		}
		else {
			throw new Exception('Unknown callback type');
		}
		mollie_fakeweb::register_uri(
			$method,
			$url,
			$result_array,
			$callable_data
		);
	}	
}