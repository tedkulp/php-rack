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

class Request implements \ArrayAccess
{
	protected $env = null;

	public function __construct(&$env)
	{
		$this->env = $env;
	}

	public function body()
	{
		return file_get_contents($this->env['rack.input']);
	}

	public function scriptName()
	{
		return $this->env['SCRIPT_NAME'];
	}

	public function pathInfo()
	{
		return $this->env['PATH_INFO'];
	}

	public function requestMethod()
	{
		return $this->env['REQUEST_METHOD'];
	}

	public function queryString()
	{
		return $this->env['QUERY_STRING'];
	}

	public function contentLength()
	{
		return $this->env['CONTENT_LENGTH'];
	}

	public function session()
	{
		return $this->env['rack.session'];
	}

	public function logger()
	{
		return $this->env['rack.logger'];
	}

	public function scheme()
	{
		if ($this->env['HTTPS'] == 'on')
			return 'https';
		else if ($this->env['HTTP_X_FORWARDED_SSL'] == 'on')
			return 'https';
		else if (isset($this->env['HTTP_X_FORWARDED_PROTO']))
		{
			$ary = explode(',', $this->env['HTTP_X_FORWARDED_PROTO']);
			return $ary[0];
		}
		else
			return $this->env['rack.url_scheme'];
	}

	public function isSsl()
	{
		return $this->scheme() == 'https';
	}

	public function hostWithPort()
	{
		if (isset($this->env['HTTP_X_FORWARDED_HOST']))
		{
			return array_pop(preg_split('/,\s?/', $this->env['HTTP_X_FORWARDED_HOST']));
		}
		else
		{
			if (isset($this->env['HTTP_HOST']))
				return $this->env['HTTP_HOST'];
			else if ($this->env['SERVER_NAME'])
				return $this->env['SERVER_NAME'] . ':' . $this->env['SERVER_PORT'];
			else
				return $this->env['SERVER_ADDR'] . ':' . $this->env['SERVER_PORT'];
		}
	}

	public function port()
	{
		if (count(explode(':', $this->hostWithPort())) > 1)
		{
			$ary = explode(':', $this->hostWithPort());
			return $ary[1];
		}
		else if (isset($this->env['HTTP_X_FORWARDED_PORT']))
		{
			return (int)$this->env['HTTP_X_FORWARDED_PORT'];
		}
		else if ($this->isSsl())
		{
			return 443;
		}
		else
		{
			return (int)$this->env['SERVER_PORT'];
		}
	}

	public function host()
	{
		return preg_replace('/:\d\z/', '', $this->hostWithPort());
	}

	public function isDelete()
	{
		return $this->requestMethod() == 'DELETE';
	}

	public function isGet()
	{
		return $this->requestMethod() == 'GET';
	}

	public function isHead()
	{
		return $this->requestMethod() == 'HEAD';
	}

	public function isOptions()
	{
		return $this->requestMethod() == 'OPTIONS';
	}

	public function isPost()
	{
		return $this->requestMethod() == 'POST';
	}

	public function isPut()
	{
		return $this->requestMethod() == 'PUT';
	}

	public function isTrace()
	{
		return $this->request_method() == 'TRACE';
	}

	public function referer()
	{
		return $this->env['HTTP_REFERER'];
	}

	public function referrer()
	{
		return $this->referer();
	}

	public function userAgent()
	{
		return $this->env['HTTP_USER_AGENT'];
	}

	public function isXhr()
	{
		return isset($this->env['HTTP_X_REQUESTED_WITH']) && $this->env['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
	}

	public function baseUrl()
	{
		$url = $this->scheme() . '://';
		$url .= $this->host();

		if (($this->scheme() == 'https' && $this->port() != 443) || ($this->scheme() == 'http' && $this->port != 80))
		{
			$url .= ':' . $this->port();
		}

		return $url;
	}

	public function get()
	{
		if ($this->env['rack.request.query_string'] !== $this->queryString())
		{
			$this->env["rack.request.query_string"] = $this->queryString();
			$this->env["rack.request.query_hash"] = $_GET;
		}

		return $this->env["rack.request.query_hash"];
	}

	public function post()
	{
		if (!$this->env["rack.input"])
			return array();
		else if ($this->env["rack.request.form_input"] == $this->env["rack.input"])
			return $this->env["rack.request.form_hash"];
		else
		{
			$this->env["rack.request.form_input"] = $this->env["rack.input"];
			$form_vars = stream_get_contents($this->env["rack.input"]);
			$this->env["rack.request.form_vars"] = $form_vars;
			$this->env["rack.request.form_hash"] = $_POST;
			return $this->env["rack.request.form_hash"];
		}
	}

	public function params()
	{
		return array_merge($this->get(), $this->post());
	}

	public function url()
	{
		return $this->baseUrl() . $this->fullpath();
	}

	public function path()
	{
		return $this->scriptName() . $this->pathInfo();
	}

	public function fullpath()
	{
		return $this->queryString() ? $this->path() : $this->path() . '?' . $this->queryString();
	}

	public function acceptEncoding()
	{
		//TODO: Fix me
	}

	public function ip()
	{
		if (isset($this->env['HTTP_X_FORWARDED_FOR']))
		{
			//TODO: Fix me -- need examples
			return $this->env['REMOTE_ADDR'];
		}
		else
		{
			return $this->env['REMOTE_ADDR'];
		}
	}
	
	/**
	 * Used for the ArrayAccessor implementation.
	 *
	 * @param string The key to set with the given value
	 * @param mixed The value to set for the given key
	 * @return void
	 */
	function offsetSet($key, $value)
	{
		$params = $this->params();
		if (isset($params[$key]))
			$params[$key] = $value;
	}

	/**
	 * Used for the ArrayAccessor implementation.
	 *
	 * @param string The key to look up
	 * @return mixed The value of the $obj['field']
	 */
	function offsetGet($key)
	{
		$params = $this->params();
		if (isset($params[$key]))
			return $params[$key];
	}

	/**
	 * Used for the ArrayAccessor implementation.
	 *
	 * @param string The key to unset
	 * @return bool Whether or not it does exist
	 */
	function offsetUnset($key)
	{
		$params = $this->params();
		if (isset($params[$key]))
			unset($params[$key]);
	}

	/**
	 * Used for the ArrayAccessor implementation.
	 *
	 * @param string The key to lookup to see if it exists
	 * @return bool Whether or not it does exist
	 **/
	function offsetExists($offset)
	{
		$params = $this->params();
		return isset($params[$offset]);
	}
	
}
