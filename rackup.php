<?php

use \rack\Rack;

define("ROOT", dirname(__FILE__));

require(ROOT."/lib/Rack.php");

// add some middlewares
Rack::add("Format", ROOT."/app/format.php");
Rack::add("App", ROOT."/app/app.php");

// insert the Api middleware before App
Rack::insert_before("App", "Api", ROOT."/app/api.php");

Rack::run();
