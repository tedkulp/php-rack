<?php
/*

   PHP Rack v0.1.0

   Copyright (c) 2010 Jim Myhrberg.

   Permission is hereby granted, free of charge, to any person obtaining
   a copy of this software and associated documentation files (the
   'Software'), to deal in the Software without restriction, including
   without limitation the rights to use, copy, modify, merge, publish,
   distribute, sublicense, and/or sell copies of the Software, and to
   permit persons to whom the Software is furnished to do so, subject to
   the following conditions:

   The above copyright notice and this permission notice shall be
   included in all copies or substantial portions of the Software.

   THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND,
   EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
   MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
   IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
   CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
   TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
   SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/

namespace rack;

if (!defined('DS'))
	define('DS', DIRECTORY_SEPARATOR);
if (!defined('RACK_LIB_ROOT'))
	define('RACK_LIB_ROOT', dirname(__FILE__));
if (!defined('RACK_ROOT'))
	define('RACK_ROOT', dirname(dirname(__FILE__)));

include_once(RACK_LIB_ROOT . DS . 'rack' . DS . 'Session.php');
include_once(RACK_LIB_ROOT . DS . 'rack' . DS . 'Request.php');
include_once(RACK_LIB_ROOT . DS . 'rack' . DS . 'Response.php');

class Rack
{
	public static
		$middleware = array(),
		$env = array();
	
	private static
		$constructed = false,
		$ob_started = false;

	protected static $_statuses = array(
		100 => 'Continue',
		101 => 'Switching Protocols',
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		307 => 'Temporary Redirect',
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Time-out',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Large',
		415 => 'Unsupported Media Type',
		416 => 'Requested range not satisfiable',
		417 => 'Expectation Failed',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Time-out'
	);
	
	public static function init($middleware = array())
	{
		// easy initialization
		if (!empty($middleware) && is_array($middleware))
		{
			$ware = array();
			foreach ($middleware as $key => $value)
				$ware[$value] = true;
			self::$middleware = array_merge(self::$middleware, $ware);
		}
		
		// don't output anything before Rack has output it's headers
		ob_start();
		self::$ob_started = true;
	}

	public static function clear()
	{
		if (!self::$constructed)
		{
			self::$middleware = array();
			return true;
		}
		return false;
	}
	
	public static function add($name, $file = null, $object = null)
	{
		if (!self::$ob_started)
			self::init();

		if (!self::$constructed)
		{
			if ($object == null)
				$object = true;

			self::$middleware[$name] = $object;
			self::requireFile($file);
			return true;
		}
		return false;
	}
	
	public static function insertBefore($target, $name, $file = null)
	{
		if (!self::$constructed)
		{
			if (array_key_exists($target, self::$middleware))
			{
				$keys = array_keys(self::$middleware);
				$length = count($keys);
				$middleware = array();
				for ($i=0; $i < $length; $i++)
				{
					if ($keys[$i] == $target)
					{
						$middleware[$name] = true;
					}
					$middleware[$keys[$i]] =& self::$middleware[$keys[$i]];
				}
				self::$middleware = $middleware;
				self::requireFile($file);
				return true;
			}
		}
		return false;
	}
	
	public static function insertAfter($target, $name, $file = null)
	{
		if (!self::$constructed)
		{
			if (array_key_exists($target, self::$middleware))
			{
				$keys = array_keys(self::$middleware);
				$length = count($keys);
				$middleware = array();
				for ($i=0; $i < $length; $i++)
				{
					$middleware[$keys[$i]] =& self::$middleware[$keys[$i]];
					if ($keys[$i] == $target)
					{
						$middleware[$name] = true;
					}
				}
				self::$middleware = $middleware;
				self::requireFile($file);
				return false;
			}
		}
		return false;
	}
	
	public static function replace($target, $name, $file = null)
	{
		if (!self::$constructed)
		{
			if (array_key_exists($target, self::$middleware))
			{
				$keys = array_keys(self::$middleware);
				$length = count($keys);
				$middleware = array();
				for ($i=0; $i < $length; $i++)
				{
					if ($keys[$i] == $target)
					{
						$middleware[$name] = true;
					}
					else
					{
						$middleware[$keys[$i]] =& self::$middleware[$keys[$i]];
					}
				}
				self::$middleware = $middleware;
				self::requireFile($file);
				return false;
			}
		}
		return false;
	}
	
	public static function notFound()
	{
		return array(404, array("Content-Type" => "text/html"), array("Not Found"));
	}
	
	public static function run(array $server_vars = array(), $send_output = true)
	{
		// build ENV (allow passing for testing)
		self::$env = array_merge($_SERVER, $server_vars);
		if (strstr(self::$env['REQUEST_URI'], '?'))
		{
			self::$env["PATH_INFO"] = substr(self::$env['REQUEST_URI'], 0, strpos(self::$env['REQUEST_URI'], '?'));
		}
		else
		{	
			self::$env["PATH_INFO"] = self::$env['REQUEST_URI'];
		}
		self::$env['PATH_INFO'] = rtrim(self::$env['PATH_INFO'], '/');

		self::$env['rack.version'] = array(1,1);
		self::$env['rack.url_scheme'] = 'http';
		if ((isset(self::$env['HTTPS']) && strtolower(self::$env['HTTPS']) == 'on') ||
			(isset(self::$env['SERVER_PORT']) && self::$env['SERVER_PORT'] == 443))
			self::$env['rack.url_scheme'] = 'https';

		self::$env['rack.multithread'] = false;
		self::$env['rack.multiprocess'] = false;
		self::$env['rack.run_once'] = false;
		
		self::$env['rack.session'] = new \Rack\Session();
		self::$env['rack.input'] = fopen('php://input', 'r');
		self::$env['rack.errors'] = fopen('php://stderr', 'w');

		foreach(array_keys($_SERVER) as $key)
		{
			unset($_SERVER[$key]);
		}

		// construct middlewares
		self::$constructed = true;
		$middleware = array_reverse(self::$middleware);
		$previous = null;
		foreach($middleware as $key => $value)
		{
			if (is_bool($value) && $value == true)
				self::$middleware[$key] = new $key($previous);
			$previous =& self::$middleware[$key];
		}
		
		// call the middleware stack
		reset(self::$middleware);
		$first = current(array_keys(self::$middleware));
		list($status, $headers, $body) = self::$middleware[$first]->call(self::$env);

		@fclose($env['rack.input']);
		@fclose($env['rack.errors']);

		if (!isset($headers['X-Powered-By']))
			$headers['X-Powered-By'] = "Rack ".implode('.',self::$env['rack.version']);

		if (!isset($headers['Status']))
			$headers['Status'] = $status . ' ' . self::$_statuses[(int)$status];	

		// send headers

		// Make sure this one is first
		$server_prot_string = strtoupper(self::$env["rack.url_scheme"])."/1.1 " . $status . ' ' . self::$_statuses[(int)$status];
		$headers = array($server_prot_string => '') + $headers;

		foreach ($headers as $key => $value)
		{
			self::sendHeader($key, $value, $send_output);
		}
		
		// output any buffered content from middlewares
		$buffer = ob_get_contents();
		ob_end_clean();
		self::$ob_started = false;

		if (!empty($buffer) && $send_output)
		{
			echo $buffer;
		}
		
		// output body
		if ($send_output)
		{
			if (is_array($body))
			{
				echo implode("", $body);
			}
			else
			{
				echo $body;
			}
		}

		self::$constructed = false;

		//Mainly for testing purposes
		return array($status, $headers, $body);
	}

	private static function sendHeader($key, $value = '', $send_output = true)
	{
		$to_send = $key;
		if ($value != '')
			$to_send .= ': ' . $value;

		//If headers have already been sent, just ignore them
		if ($send_output && !headers_sent())
			header($to_send);
	}
	
	private static function requireFile($file = null)
	{
		if ($file != null && is_file($file))
		{
			require_once($file);
		}
	}

	public static function httpStatusCodes()
	{
		return self::$_statuses;
	}
}
