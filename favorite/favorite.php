<?php

require_once dirname(__DIR__, 3) . "/vendor/autoload.php";
require_once dirname(__DIR__, 3) . "/Classes/autoload.php";
require_once("/etc/apache2/capstone-mysql/Secrets.php");
require_once dirname(__DIR__, 3) . "/lib/xsrf.php";
require_once dirname(__DIR__, 3) . "/lib/jwt.php";
require_once dirname(__DIR__, 3) . "/lib/uuid.php";

use SuperSmashLore\SuperSmashLore\Profile;
/**
 * API for the favorite class
 */

//verify the session, start if not already active
if(session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

//prepare and empty reply
$reply = new stdClass();
$reply->status = 200;
$reply->data = null;

try {
	$secrets = new \Secrets("/etc/apache2/capstone-mysql/smash.ini");
	$pdo = $secrets->getPdoObject();

	//determine which HTTP method was used
	$method = $_SERVER["HTTP_X_HTTP_METHOD"] ?? $_SERVER["REQUEST_METHOD"];

	//sanitize the searching param
	$favoriteProfileId = $id = filter_input(INPUT_GET, "favoriteProfileId", FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
	$favoriteCharacterId = $id = filter_input(INPUT_GET, "favoriteCharacterId", FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

	if($method === "GET") {
		//set xsrf cookie
		setXsrfCookie();

		//gets a specific favorite associated based on the composite key
		if ($favoriteProfileId !== null && $favoriteCharacterId !== null) {
			$favorite = Favorite::getFavoriteByFavoriteProfileIdAndFavoriteCharacterId ($pdo, $favoriteProfileId, $favoriteCharacterId);

			if($favorite !== null) {
				$reply->data = $favorite;
			}
			//if none of the search parameters are met, throw and exception
		} else if(empty($favoriteProfileId) === false) {
			$reply->data = Favorite::getFavoriteByFavoriteProfileId($pdo, $favoriteProfileId)->toArray();
			//get all the favorites associated with the characterId
		} else if(empty($favoriteCharacterId) == false) {
			$reply->data = Favorite::getFavoriteByFavoriteChaaracterId($pdo, $favoriteCharacterId)->toArray();
		} else {
			throw new InvalidArgumentException("Incorrect Search Parameters", 404);
		}
	} else if($method === "POST" || $method === "PUT") {
		//decode the response from the front end
	}
}