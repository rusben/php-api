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

        // Hay algo detrás de users/
        if ($chunks[3] != "") {
        
        } else {
            
            
            if ($_SERVER["REQUEST_METHOD"] == "GET") {
                // GET users/
                echo ApiController::getLinks(ApiController::JSON);
                die();
            } else if ($_SERVER["REQUEST_METHOD"] == "POST") {
                // POST users/

            }

        }
        break;

    case 'not-found':
    default:
        http_response_code(404);
        echo '<h1>Not found!</h1>';
        die();
}
