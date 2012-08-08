<?php
/**
 * @covers Mollie_iDEAL_Payment
 */
class idealClassTest extends Mollie_Fakeweb_TestCase
{
	protected static $banks_xml = <<< EOX
<?xml version="1.0" ?>
<response>
	<bank>
		<bank_id>1234</bank_id>
		<bank_name>Test bank 1</bank_name>
	</bank>
	<bank>
		<bank_id>0678</bank_id>
		<bank_name>Test bank 2</bank_name>
	</bank>
	<message>This is the current list of banks and their ID's that currently support iDEAL-payments</message>
</response>
EOX;

	protected static $check_payment_xml = <<< EOX
<?xml version="1.0"?>
<response>
	<order>
		<transaction_id>1234567890</transaction_id>
		<amount>1000</amount>
		<currency>EUR</currency>
		<payed>true</payed>
		<message>This iDEAL-order has successfuly been payed for, and this is the first time you check it.</message>
	</order>
</response>
EOX;

	protected static $create_payment_xml = <<< EOX
<?xml version="1.0"?>
<response>
	<order>
		<transaction_id>1234567890</transaction_id>
		<amount>1000</amount>
		<currency>EUR</currency>
		<URL>http://bankurl.com/?transaction_id=1234567890</URL>
		<message>Your iDEAL-payment has succesfuly been setup. Your customer should visit the given URL to make the payment</message>
	</order>
</response>
EOX;

	public function testBankListActionReturnsArrayOfBanks()
	{
		$expectedBanks = array (
			'1234' => 'Test bank 1',
			'0678' => 'Test bank 2'
		);
	
		mollie_fakeweb::register_uri(
			'POST',
			'https://secure.mollie.nl/xml/ideal/',
			array ( 'code' => 200, 'body' => self::$banks_xml)
		);
		
		$iDEAL = new Test_iDEAL_Payment(1001);
		$banks = $iDEAL->getBanks();
		
		$this->assertEquals($banks, $expectedBanks);
	}

	public function testBankListRespectsTestMode ()
	{
		$iDEAL = new Test_iDEAL_Payment(1001);
		$iDEAL->setTestmode(TRUE);

		$verify_func = <<< EOF
			parse_str(\$request_data['post_body'], \$post_vars);
			\$self->assertArrayHasKey("testmode", \$post_vars);
			\$self->assertEquals("true", \$post_vars["testmode"]);
EOF;

		$this->_registerFakeWebUrlCallback(
			create_function('$request_data,$self', $verify_func ),
			'POST',
			'https://secure.mollie.nl/xml/ideal/',
			array (
				'code' => 200,
				'body' => self::$banks_xml
			)
		);

		$this->assertInternalType("array", $banks = $iDEAL->getBanks());
	}

	public function testCreatePaymentActionRequiresParameters()
	{
		$output = '';
		mollie_fakeweb::register_uri(
			'POST',
			'https://secure.mollie.nl/xml/ideal/',
			array ( 'code' => 200, 'body' => $output)
		);

		$iDEAL = new Test_iDEAL_Payment(1001);
		
		$parameters = array (
			'bank_id' => '0031',
			'amount' => '1000',
			'description' => 'Description', 
			'return_url' => 'http://customer.local/return.php', 
			'report_url' => 'http://customer.local/report.php'
		);
				
		foreach (array('bank_id','amount','description','return_url','report_url') as $parameter)
		{
			$testParameters = $parameters;
			$testParameters[$parameter] = NULL;
			
			$result = call_user_func_array(array($iDEAL, 'createPayment'), $testParameters);
			
			$this->assertFalse($result);
			$this->assertNotEmpty($iDEAL->getErrorMessage());
		}
	}

	public function testCheckPaymentsRespectsTestMode ()
	{
		$iDEAL = new Test_iDEAL_Payment(1001);
		$iDEAL->setTestmode(TRUE);

		$verify_func = <<< EOF
			parse_str(\$request_data['post_body'], \$post_vars);
			\$self->assertArrayHasKey("testmode", \$post_vars);
			\$self->assertEquals("true", \$post_vars["testmode"]);
EOF;

		$this->_registerFakeWebUrlCallback(
			create_function('$request_data,$self', $verify_func ),
			'POST',
			'https://secure.mollie.nl/xml/ideal/',
			array (
				'code' => 200,
				'body' => self::$check_payment_xml
			)
		);

		$iDEAL->checkPayment("09f911029d74e35bd84156c5635688c0");
	}

	public function testCreatePaymentCanSendProfileKey()
	{
		$verify_func = <<< EOF
			parse_str(\$request_data['post_body'], \$post_vars);

			\$expected_data = array( 
				'a'           => 'fetch',
				'partnerid'   => 1001,
				'bank_id'     => '0031',
				'amount'      => 1000,
				'reporturl'   => 'http://customer.local/report.php',
				'description' => 'Description',
				'returnurl'   => 'http://customer.local/return.php',
				'profile_key' => '12341234'
			);
		
			\$self->assertTrue(count(array_diff(\$expected_data, \$post_vars)) == 0, "MISMATCH DATA:\n" . var_export(array_diff(\$expected_data, \$post_vars), TRUE));
EOF;

				$this->_registerFakeWebUrlCallback(
					create_function('$request_data,$self', $verify_func ),
					'POST',
					'https://secure.mollie.nl/xml/ideal/',
					array (
						'code' => 200,
						'body' => self::$create_payment_xml,
					)
				);

				$iDEAL = new Test_iDEAL_Payment(1001);
				$iDEAL->setProfileKey('12341234');
				
				$result = $iDEAL->createPayment(
					'0031',
					'1000',
					'Description',
					'http://customer.local/return.php',
					'http://customer.local/report.php'
				);
				
				$this->assertTrue($result);
	}

	public function testCreatepaymentRespectsTestMode ()
	{
		$iDEAL = new Test_iDEAL_Payment(1001);
		$iDEAL->setTestmode(TRUE);

		// The bank id confers that we use test mode.
		$verify_func = <<< EOF
			parse_str(\$request_data['post_body'], \$post_vars);
			\$self->assertArrayNotHasKey("testmode", \$post_vars);
EOF;

		$this->_registerFakeWebUrlCallback(
			create_function('$request_data,$self', $verify_func ),
			'POST',
			'https://secure.mollie.nl/xml/ideal/',
			array (
				'code' => 200,
				'body' => self::$create_payment_xml,
			)
		);

		$result = $iDEAL->createPayment(
			'0031',
			'1000',
			'Description',
			'http://customer.local/return.php',
			'http://customer.local/report.php'
		);

		$this->assertTrue($result);
	}

	public function testCreatePaymentActionSetsUpPaymentAtMollie()
	{
$output = <<< EOX
<?xml version="1.0"?>
<response>
	<order>
		<transaction_id>1234567890</transaction_id>
		<amount>1000</amount>
		<currency>EUR</currency>
		<URL>http://bankurl.com/?transaction_id=1234567890</URL>
		<message>Your iDEAL-payment has succesfuly been setup. Your customer should visit the given URL to make the payment</message>
	</order>
</response>
EOX;

$verify_func = <<< EOF
	parse_str(\$request_data['post_body'], \$post_vars);
	
	\$expected_data = array( 
		'a'           => 'fetch',
		'partnerid'   => 1001,
		'bank_id'     => '0031',
		'amount'      => 1000,
		'reporturl'   => 'http://customer.local/report.php',
		'description' => 'Description',
		'returnurl'   => 'http://customer.local/return.php'
	);
	
	\$self->assertEquals(\$post_vars, \$expected_data);
EOF;
		$this->_registerFakeWebUrlCallback(
			create_function('$request_data,$self', $verify_func ),
			'POST',
			'https://secure.mollie.nl/xml/ideal/',
			array (
				'code' => 200,
				'body' => $output
			)
		);
			
		$iDEAL = new Test_iDEAL_Payment(1001);
		$result = $iDEAL->createPayment(
			'0031',
			'1000',
			'Description',
			'http://customer.local/return.php',
			'http://customer.local/report.php'
		);
		
		$this->assertTrue($result);
	}
	
	public function testCreatePaymentActionFailureSetsErrorVariables()
	{
$output = <<< EOX
<?xml version="1.0" ?>
<response>
	<item type="error">
		<errorcode>-3</errorcode>
		<message>The Report URL you have specified has an issue</message>
	</item>
</response>
EOX;

		$this->_registerFakeWebUrlCallback(
			NULL,
			'POST',
			'https://secure.mollie.nl/xml/ideal/',
			array (
				'code' => 200,
				'body' => $output
			)
		);
			
		$iDEAL = new Test_iDEAL_Payment(1001);
		$result = $iDEAL->createPayment(
			'0031',
			'1000',
			'Description',
			'http://customer.local/return.php',
			'http://customer.local/report.php'
		);
		
		$this->assertFalse($result);		
		$this->assertEquals($iDEAL->getErrorMessage(), 'The Report URL you have specified has an issue');
		$this->assertEquals($iDEAL->getErrorCode(), '-3');
		
	}
	
	public function testCheckPaymentActionChecksPaymentStatusAtMollie()
	{
$verify_func = <<< EOF
	parse_str(\$request_data['post_body'], \$post_vars);

	\$expected_data = array( 
		'a'           => 'check',
		'partnerid'   => 1001,
		'transaction_id' => '1234567890'
	);

	\$self->assertEquals(\$post_vars, \$expected_data);
EOF;

		$this->_registerFakeWebUrlCallback(
			create_function('$request_data,$self', $verify_func ),
			'POST',
			'https://secure.mollie.nl/xml/ideal/',
			array (
				'code' => 200,
				'body' => self::$check_payment_xml
			)
		);
			
		$iDEAL = new Test_iDEAL_Payment(1001);
		$result = $iDEAL->checkPayment('1234567890');
		
		$this->assertTrue($result);
	}
	
	public function testCheckPaymentActionChecksTransactionId()
	{
		$iDEAL = new Test_iDEAL_Payment(1001);
		$result = $iDEAL->checkPayment(NULL);

		$this->assertFalse($result);
		$this->assertEquals("Er is een onjuist transactie ID opgegeven", $iDEAL->getErrorMessage());
	}

	public function testAPIErrorDetectedCorrectly ()
	{
		$iDEAL = new Test_iDEAL_Payment(1001);

		$xml = new SimpleXMLElement("<?xml version=\"1.0\" ?>
		<response>
			<item type=\"error\">
				<errorcode>42</errorcode>
				<message>The flux capacitator is over capacity</message>
			</item>
		</response>");

		$this->assertTrue($iDEAL->_XMLisError($xml));
	}

	public function testNormalXmlIsNotAnError()
	{
		$iDEAL = new Test_iDEAL_Payment(1001);

		$xml = new SimpleXMLElement(self::$banks_xml);

		$this->assertFalse($iDEAL->_XMLisError($xml));
	}

	public function testBankErrorDetectedCorrectly()
	{
		$iDEAL = new Test_iDEAL_Payment(1001);

		$xml = new SimpleXMLElement("<?xml version=\"1.0\" ?>
		<response>
			<order>
				<transaction_id></transaction_id>
				<amount></amount>
				<currency></currency>
				<URL>https://www.mollie.nl/files/idealbankfailure.html</URL>
				<error>true</error>
				<message>Your iDEAL-payment has not been setup because of a temporary technical error at the bank</message>
			</order>
		</response>");

		$this->assertTrue($iDEAL->_XMLisError($xml));
	}

	public function testInvalidXmlDetected ()
	{
		$iDEAL = new Test_iDEAL_Payment(1001);
		$this->assertFalse($iDEAL->_XMLtoObject("invalid xml"));
		$this->assertEquals(-2, $iDEAL->getErrorCode());
		$this->assertEquals("Kon XML resultaat niet verwerken", $iDEAL->getErrorMessage());
	}
}