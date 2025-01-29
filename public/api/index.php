<?php

require_once "../../vendor/autoload.php";

$request = $_SERVER['REQUEST_URI'];

$chunks = explode("/", $request);

// print_r("<pre>");
// print_r($chunks);
// print_r("</pre>");

switch ($chunks[2]) {
    case '':
    case '/':
        
        // header('HTTP/1.0 401 Unauthorized');
        http_response_code(401);
        echo '<h1>Unauthorized</h1>';
        die();
        break;

    case 'users':

        // There is anything after api/users/??
        if ($chunks[3] != "") {

            $userId = $chunks[3];

            if ($_SERVER["REQUEST_METHOD"] == "GET") {
                // GET users/{id}
                echo ApiController::getUser($userId, ApiController::JSON);
                die();
            } elseif ($_SERVER["REQUEST_METHOD"] == "PUT") {
                // PUT users/{id}
                echo ApiController::updateUser($userId, ApiController::JSON);
                die();
            } elseif ($_SERVER["REQUEST_METHOD"] == "DELETE") {
                // DELETE users/{id}
                echo ApiController::deleteUser($userId);
                die();
            }

        } else {
            
            if ($_SERVER["REQUEST_METHOD"] == "GET") {
                // GET users/
                echo ApiController::getUsers(ApiController::JSON);
                die();
            } else if ($_SERVER["REQUEST_METHOD"] == "POST") {
                // POST users/
                echo ApiController::addUser(ApiController::JSON);
                die();
            } 

        }
        break;

    case 'not-found':
    default:
        http_response_code(404);
        echo '<h1>Not found!</h1>';
        die();
}
