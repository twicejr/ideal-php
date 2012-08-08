<?php
/**
 * Mollie FakeWeb 
 *
 * Een door Ruby's FakeWeb geinspireerd setje classes welke het mogelijk maakt om 
 * alle HTTP requests die via PHP streams worden gemaakt af te vangen en te forceren
 * bepaalde gegevens terug te geven en te verifieren of het gemaakte request bepaalde details bevat.
 *
 * Voorbeeld gebruik:
 * 
 * mollie_fakeweb::register_uri(
 *	'GET', 
 *	'http://127.0.0.1/~mathieuk/t.php', 
 *	array('code' => 200, 'body' => 'You got stubbed'),
 *	function ($request_data) { 
 *		if (!isset($request_data['post_vars']['q'])) {			
 *			throw new Exception("Greatest failure ever: geen q post var!");
 *		}
 *	} 
 * );
 *
 * @author Mathieu Kooiman <mathieu@mollie.nl>
 * @codeCoverageIgnore
 */
class mollie_fakeweb
{
	public static $allow_net_connect = true;
	public static $show_debug_messages = false;
	
	private static $_uri_registry = array();
	private static $_initialized = FALSE;
	
	private $_impl = NULL;
	
	public $context = array();

	private static $stream_wrappers_enabled = false;

	public static function enable()
	{
		self::reset();
		
		stream_wrapper_unregister('http');
		stream_wrapper_unregister('https');
		stream_wrapper_register('http', 'mollie_fakeweb', STREAM_IS_URL);
		stream_wrapper_register('https', 'mollie_fakeweb', STREAM_IS_URL);

		stream_context_set_default(array('http' => array('method' => 'GET')));
		stream_context_set_default(array('https' => array('method' => 'GET')));

		self::$stream_wrappers_enabled = true;

		self::$_initialized = TRUE;
	}
	
	public static function disable()
	{
		self::reset();

		if (self::$stream_wrappers_enabled)
		{
			stream_wrapper_restore('http');
			stream_wrapper_restore('https');
			self::$stream_wrappers_enabled = false;
		}
	}
	
	public static function reset()
	{
		self::$_uri_registry = array();		
	}
	
	public static function register_uri($method, $url, $return_data = array(), $verify_request_func = NULL)
	{	
		if (!self::$_initialized)
			self::enable();
				
		$response_data = array_merge(
			array (
				'code'    => 200,
				'headers' => array(),
				'body'    => 'You got stubbed!' 
			),
			$return_data
		);
		
		self::$_uri_registry[$url] = array (
			'method'               => $method, 
			'response_data'        => $response_data,
			'verify_request_func'  => $verify_request_func
		);
		
		if (isset($url_parts['query']) && !empty($url_parts['query']))
		{			
			parse_str($url_parts['query'], $get_vars);
		}
	}
	
	public static function get_registered_url($url)
	{
		if (!isset(self::$_uri_registry[$url]))
			throw new mollie_fakeweb_exception("Cannot fake connection for $url: unknown url!");

		return self::$_uri_registry[$url];		
	}
	
	public static function call_verify_func($func, $data)
	{
		if (is_callable($func))
		{
			return $func($data);
		}
		else if (is_array($func))
		{
			$function_name = $func[0];
			$extra_arguments = $func[1];
			
			array_unshift($extra_arguments, $data); // request_data, [...] 

			return call_user_func_array($function_name, $extra_arguments);
		}
		
		throw new Exception( var_export($func) . ' is not a callable verify_func');			
	}
	
	/**
	 * Stream proxy methods 
	 */
	public function stream_open($path, $mode, $options, &$opened_path)
	{
		$url_parts = parse_url($path);
		$url_parts['path'] = isset($url_parts['path']) ? $url_parts['path'] : '/';
		
		$url = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'];

		if (!isset(self::$_uri_registry[$url]))
		{
			if (!self::$allow_net_connect)
				throw new mollie_fakeweb_exception("Real HTTP connections not allowed: $path");

			$this->_impl = new mollie_fakeweb_real_connection($this->context);
		} else
			$this->_impl = new mollie_fakeweb_stubbed_connection($path, self::$_uri_registry[$url], $this->context);
					
		return $this->_impl->stream_open($path, $mode, $options, $opened_path);		
	}
	
	public function stream_eof()
	{
		return $this->_impl->stream_eof();
	}
	
	public function stream_read($count)
	{
		return $this->_impl->stream_read($count);
	}
	
	public function stream_cast($cast_as)
	{
		return STDIN;
	}
}

/**
 * Base class voor connecties. Hiervan worden de stubbed_connection en de real_connection
 * classes vanaf gebaseerd
 *
 * @codeCoverageIgnore
 */
abstract class mollie_fakeweb_connection
{
	public function construct_request_body($path, $context)
	{
		$post_vars = array();
		$post_body = '';
		$get_vars = array();
		
		$url_parts = parse_url($path);		

		if (!is_resource($context))
			$stream_options = stream_context_get_default($url_parts['scheme']);
		else 
			$stream_options = stream_context_get_options($context);

		$stream_options = $stream_options['http'];

		$url_parts['path'] = isset($url_parts['path']) ? $url_parts['path'] : '/';

		$uri = $url_parts['path'] . (isset($url_parts['query']) ? '?' . $url_parts['query'] : '');

		$headers = array (
			'command' => "{$stream_options['method']} $uri HTTP/1.1",
			'host'    => "Host: {$url_parts['host']}"
		);
		
		if (isset($stream_options['header']))
		{
			$stream_options['header'] = trim($stream_options['header']);
			
			foreach(explode("\r\n", $stream_options['header']) as $header)
			{
				list($header_name, $header_value) = explode(':', $header);
				$headers[mb_strtolower($header_name)] = $header;
			}
		}
					
		if (isset($stream_options['content'])) {
			$post_body = $stream_options['content'];
		}
		
		if (isset($url_parts['query']) && !empty($url_parts['query']))
		{			
			parse_str($url_parts['query'], $get_vars);
		}
		
		return array (
			'uri' => $uri,
			'headers'   => join("\r\n", $headers) . "\r\n",
			'get_vars'  => $get_vars,
			'post_body' => $post_body			
		);
	}
	
}

/**
 * Retourneert voorgedefinieerde data in plaats van een daadwerkelijk HTTP
 * request uit te voeren.
 *
 * @codeCoverageIgnore
 */
class mollie_fakeweb_stubbed_connection extends mollie_fakeweb_connection
{
	private $_response_data = array();
	private $_position = 0;
	
	public function __construct($path, $request_data, $context)
	{		
		$this->_position = 0;
		$this->_response_data = $request_data['response_data'];	
		$this->_request_data  = $request_data;
		$this->_context = $context;
		
	}
	
	public function stream_open($path, $mode, $options, &$opened_path)
	{	
		$request_body = $this->construct_request_body($path, $this->_context);

		if (mollie_fakeweb::$show_debug_messages)
			echo "[FAKEWEB] Faking access to URL $path\n";
		
		if (isset($this->_request_data['verify_request_func']))
		{			
			mollie_fakeweb::call_verify_func($this->_request_data['verify_request_func'], $request_body);
		}
				
		$date = date(DATE_RFC1123);
		
		$GLOBALS['http_response_header']= array(
			"HTTP/1.1 {$this->_response_data['code']}",
			"Date: $date",
			"Server: Mollie PHPFakeWeb/1.0",
			"Connection: close",
			"Content-Type: text/html"
		);
		
		$GLOBALS['http_response_header'] = array_merge($GLOBALS['http_response_header'], $this->_request_data['response_data']['headers']);
		
		if ($this->_response_data['code'] < 200 || $this->_response_data['code'] > 299)
			return false;
							
		return true;
	}

	public function stream_eof() 
	{
		return $this->_position >= strlen($this->_response_data['body']);		
	}
	
	public function stream_read($count)
	{
		$result = substr($this->_response_data['body'], $this->_position, $count);
		$this->_position += $count;
		
		return $result;
	}	
	

	
}

/**
 * Voert een HTTP request alsnog uit (maar zónder de HTTP stream).
 *
 * @codeCoverageIgnore
 */
class mollie_fakeweb_real_connection extends mollie_fakeweb_connection
{	
	private $_result_data = '';
	private $_position = 0;
	private $_redirect_count = 0;
	
	private $_max_redirects = -1;
	
	public function __construct($context)
	{
		$this->context = $context;	
	}
	
	public function stream_open($path, $mode, $options, &$opened_path)
	{
		if (mollie_fakeweb::$show_debug_messages)
			echo "[FAKEWEB] Actually accessing URL $path\n";
			
		$this->_result_data = '';
		$this->_position = 0;
		
		$this->_result_data = $this->_perform_http_request($path, $this->context);

		if ($this->_result_data === false)
			return false;

		$this->_headers = explode("\r\n", $this->_result_data['headers_raw']);
		
		if ($this->_result_data['headers']['code'] < 200 || $this->_result_data['headers']['code'] > 399)
			return false;	
				
		return true;
	}
	
	public function stream_eof() 
	{
		return $this->_position >= strlen($this->_result_data['body']);		
	}

	public function stream_read($count)
	{
		$result = substr($this->_result_data['body'], $this->_position, $count);
		$this->_position += $count;

		return $result;
	}	
	
	private function _perform_http_request($path, $context)
	{	
		$request_body = $this->construct_request_body($path, $context);

		$url_parts = parse_url($path);
		 
		$port = isset($url_parts['port']) && $url_parts['port'] ? $url_parts['port'] : 80;
		
		if ($port == 443)
			$url_parts['host'] = 'ssl://' . $url_parts['host'];
		
		$fp = @fsockopen($url_parts['host'], $port);	
		
		if (!$fp)
			return false;	

		fwrite($fp, $request_body['headers']);

		if (isset($request_body['post_body']) && !empty($request_body['post_body']))
		{
			fwrite($fp, "\r\n");
			fwrite($fp, $request_body['post_body']);
		}
		
		fwrite($fp, "\r\n\r\n");
			
		$result = '';
		while (!feof($fp)) { 
			$result .= fread($fp, 2048);
		} 
		
		fclose($fp);

		list($headers, $content) = explode("\r\n\r\n", $result, 2);
		
		$result_data = array (
			'headers' => $this->_parse_http_headers($headers),
			'headers_raw' => $headers,
			'body' => $content
		);

		$GLOBALS['http_response_header'] = explode("\r\n", $headers);
		
		if ($result_data['headers']['code'] == 301 || $result_data['headers']['code'] == 302)
		{
			if ($this->_max_redirects != -1 && $this->_redirect_count++ >= $this->_max_redirects) {
				trigger_error("Too many redirects for $path", E_USER_WARNING);
				return false;
			}
		
			$result_data = $this->_perform_http_request($result_data['headers']['location'], $context);

			 if ($this->_result_data === false)
				return false;
		}
			
		return $result_data;
	}
	
	private function _parse_http_headers($headers)
	{
		$header_lines = explode("\r\n",$headers);		
		$status_line = array_shift($header_lines);
		
		$result = array();

		if (preg_match('~^HTTP/1.[01] ([0-9]{3})~', $status_line, $matches))
		{
			$result['code'] = $matches[1];
		} else { 
			$result['code'] = 'unknown';	
		}
		
		foreach ($header_lines as $line) {
			list($header, $header_content) = explode(': ', $line);
			$result[strtolower($header)] = trim($header_content);
		}
		
		return $result;				
	}
}
/**
 * @codeCoverageIgnore
 */
class mollie_fakeweb_exception extends exception {}
