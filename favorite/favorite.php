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
		$requestContent = file_get_contents("php://input");
		$requestObject = json_decode($requestContent);

		if(empty($requestObject->favoriteProfileId) ===true) {
			throw (new \InvalidArgumentException("No Profile Linked to the Favorite.", 405));
		}
		if(empty($requestObject->favoriteCharacterId) === true) {
			throw (new \InvalidArgumentException("No Character Linked to the Favorite", 405));
		}
		if(empty($requestObject->favoriteDate) === true) {
			$requestObject->favoriteDate = date("Y-m-d H:i:s");
		}

		if($method === "POST") {
			//enforce that the end user has an XRSF Token
			verifyXsrf();
			//enforce the end user has a JWT token
			validateJwtHeader();
			//enforce the user is signed in
			if(empty($_SESSION["profile"]) === true) {
				throw(new \InvalidArgumentException("You Must Be Logged in to Favorite Characters", 403));
			}
			validateJwtHeader();
			$favorite = new Favorite($_SESSION["profile"]->getProfileId(), $requestObject->favoriteCharacterId);
			$favorite->insert($pdo);
			$reply->message = "Favorited Character Successful.";

		} else if($method === "PUT") {
			//enforce the end user has an XRSF Token
			verifyXsrf();
			//enforce the end user has a JWT token
			validateJwtHeader();
			//grab the favorite by its composite key
			$favorite = Favorite::getFavoriteByFavoriteProfileIdAndFavoriteCharacterId($pdo, $requestObject->favoriteProfileId, $requestObject->favoriteCharacterid);
			if($favorite === null) {
				throw (new RuntimeException("Favorite Does Not Exist"));
			}
			//enforce the user is signed in and only trying to edit their own favorites, either add or delete
			if(empty($_SESSION["profile"]) === true || $_SESSION["profile"]->getProfileId()->toString() !== $favorite->getFavoriteProfileId()->toString()) {
				throw(new \InvalidArgumentException("You Are Not Allowed To Delete This Character", 403));
			}

			//perform the actual removal of the Favorite
			$favorite->delete($pdo);
			//update the message
			$reply->message = "Favorite Successfully Removed.";
		}
	} else {
		throw new \InvalidArgumentException("Invalid HTTP Request", 400);
	}
	//catch any exceptions that are thrown and update the reply status and message accordingly
} catch(\Exception | \TypeError $exception) {
	$reply->status = $exception->getCode();
	$reply->message = $exception->getMessage();
}

header("Content-Type: application/jason");
if($reply->data === null) {
	unset($reply->data);
}

//encode and return reply to front end user
echo json_encode($reply);