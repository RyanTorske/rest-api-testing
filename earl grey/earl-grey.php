<?php
//no clue what this does
require_once dirname(__DIR__, 3) . "/lib/xsrf.php";
require_once dirname(__DIR__, 3) . "/Classes/autoload.php";

$reply = new stdClass();
$reply->status = 200;
$reply->data = null;

use SuperSmashLore\SuperSmashLore\Profile;

