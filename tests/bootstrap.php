<?php
require_once "class.http_request_test.php";
require_once "class.mollie_fakeweb.php";
require_once "class.mollie_fakeweb_testcase.php";
require_once dirname(dirname(__FILE__)) . "/Mollie/iDEAL/Payment.php";

/**
 * @codeCoverageIgnore
 */
class Test_iDEAL_Payment extends Mollie_iDEAL_Payment
{
	/**
	 * Create a custom version of _sendRequest so that we can test this with Mollie Fakeweb
	 *
	 * @param string $path
	 * @param string $data
	 *
	 * @return bool|string
	 */
	protected function _sendRequest ($path, $data)
	{
		$port = 80;

		$post_len = strlen($data);

		$post_config = array (
			'method'  => 'POST',
			'content' => $data,
			'header'  => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: $post_len\r\n"
		);

		$context = stream_context_create(array('http' => $post_config));

		$url = $this->api_host . ($port != 80 ? ":$port" : "") . $path;

		$fp = fopen($url, 'r', NULL, $context);

		if (!$fp)
			return FALSE;

		$data = '';
		while (!feof($fp))
		{
			$data .= fread($fp, 2048);
		}
		fclose($fp);

		return $data;
	}

	/**
	 * Make public so we can test it.
	 *
	 * @param SimpleXMLElement $xml
	 * @return bool
	 */
	public function _XMLisError(SimpleXMLElement $xml)
	{
		return parent::_XMLisError($xml);
	}

	/**
	 * Make public so we can test it.
	 *
	 * @param $xml
	 * @return bool|object
	 */
	public function _XMLtoObject($xml)
	{
		return parent::_XMLtoObject($xml);
	}
}