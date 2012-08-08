<?php
/**
 * @codeCoverageIgnore
 */
class http_request_test
{
	
	public function __construct($http_request)
	{
		$this->_http_request = $http_request;
		$this->_timeout      = $http_request->http_timeout;		
	}
	
	public function perform()
	{
		$get_vars = array();
		
		// Collect data 
		$url_parts = parse_url($this->_http_request->target_url);		

		$port = $url_parts['scheme'] == 'https' ? 443 : (isset($url_parts['port']) && $url_parts['port'] ? (int) $url_parts['port'] : 80);

		$url_parts['path'] = isset($url_parts['path']) ? $url_parts['path'] : '/';

		$uri = $url_parts['path'] . (isset($url_parts['query']) ? '?' . $url_parts['query'] : '');

		$headers = array (
			'command' => "{$this->_http_request->http_request_method} $uri HTTP/1.1",
			'host'    => "Host: {$url_parts['host']}",
			'user-agent' => 'User-Agent: Mollie.nl HTTP client/1.0',
		);

		if (isset($url_parts['query']) && !empty($url_parts['query']))
		{			
			parse_str($url_parts['query'], $get_vars);
		}
		
		if (is_array($this->_http_request->http_parameters))
			$post_data = http_build_query($this->_http_request->http_parameters);
		else
			$post_data = $this->_http_request->http_parameters;

		$request_body = array (
			'uri'       => $uri,
			'headers'   => join("\r\n", $headers) . "\r\n",
			'get_vars'  => $get_vars,
			'post_body' => $post_data
		);
	
		$url = sprintf(
			'%s://%s%s%s', 
			$url_parts['scheme'],
			$url_parts['host'],
			$port != 80 && $port != 443 ? ':' . $port : '',
			$url_parts['path']
		);
		
		$fake_request_data = mollie_fakeweb::get_registered_url($url);
		
// 'Perform' the fake request

		if (isset($fake_request_data['verify_request_func']))
		{			
			mollie_fakeweb::call_verify_func($fake_request_data['verify_request_func'], $request_body);
		}		

		$date = date(DATE_RFC1123);

		$headers_raw = array(
			"HTTP/1.1 {$fake_request_data['response_data']['code']}",
			"Date: $date",
			"Server: Mollie PHPFakeWeb/1.0",
			"Connection: close",
			"Content-Type: text/html"
		);
		
		$headers_raw = array_merge($headers_raw, $fake_request_data['response_data']['headers']);
		$parsed_headers = $this->_http_request->response_headers_as_array($headers_raw);
		
		$fakeweb_result = TRUE;
		if ($fake_request_data['response_data']['code'] < 200 || $fake_request_data['response_data']['code'] > 399)
			$fakeweb_result = FALSE;

		$body = $fake_request_data['response_data']['body'];
		
		if ( $this->_http_request->http_max_response_size > 0 ) 
		{
			$body = substr($body, 0, $this->_http_request->http_max_response_size);
		}
		$result = array (
			'error'          => $fakeweb_result === FALSE,
			'error_message'  => $fakeweb_result ? '' : 'Fakeweb returned false',
			'duration'       => 1.0,
			'response'       => $body,
			'response_code'  => $parsed_headers['code'],
			'request'        => '',
			'headers_raw'    => $headers_raw,
			'headers'        => $parsed_headers,
			'timed_out'      => FALSE
		);
		
		return $result;		
	}
}
