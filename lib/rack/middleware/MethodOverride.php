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

namespace rack\middleware;

/**
 * Class that allows additional RESTful actions to work via POST
 *
 * (c)2011 Fredi Machado
 * https://github.com/fredi/Phoenix/blob/master/lib/Phoenix/middleware/MethodOverride.php
 **/
class MethodOverride
{
	private $http_methods = array("GET", "HEAD", "PUT", "POST", "DELETE", "OPTIONS");

	function __construct(&$app)
	{
		$this->app =& $app;
	}
	
	function call(&$env)
	{
		if ($env['REQUEST_METHOD'] == "POST")
		{
			$req = new \rack\Request($env);
			$method = (isset($req['_method']) ? $req['_method'] : (isset($env['HTTP_X_HTTP_METHOD_OVERRIDE']) ? $env['HTTP_X_HTTP_METHOD_OVERRIDE'] : 'POST'));
			$method = strtoupper($method);
			if (in_array($method, $this->http_methods))
			{
				$env["rack.methodoverride.original_method"] = $env["REQUEST_METHOD"];
				$env["REQUEST_METHOD"] = $method;
			}
		}

		return $this->app->call($env);
	}
}
