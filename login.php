<?php

require_once __DIR__ . '/src/config/Dbh.config.php'; // this also connects config.php which has other configurations

use Php101\Php101\SecureVault;
use Php101\Php101\Passwordhandler;
use Php101\Php101\ErrorLogger;

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');


try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(418); // I'm a teapot
        echo json_encode(['status' => 'failed', 'error' => 'Invalid request method']);
        exit();
    }

    // Get the auth token from the requets header.
    $headers = capitalizeFirstLetterKeys(apache_request_headers());
    $token = $headers['Authorization'] ?? null;


    $secure = new SecureVault; // create an instance of a secure vault

    // Check if the JWT is valid
    if (!$secure->validateJWT($token, $_SERVER['SERVER_JWT_KEY'])) {
        http_response_code(405);
        echo json_encode(['status' => 'failed', 'error' => 'Invalid token']);
        exit();
    }

    // Grab the data from the php input stream.
    $Data = file_get_contents('php://input');

    // Check if the data was successfully read
    if ($Data === false) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'failed', 'error' => 'Failed to read request data']);
        exit();
    }

    // Convert the JSON data into an associative array
    $data = json_decode($Data, true);

    // Check if JSON decoding was successful
    if ($data === null) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'failed', 'error' => 'Failed to read request data']);
        exit();
    }

    $inputData = [
        'email' => htmlspecialchars(stripslashes(trim($data['email']))),
        'password' => $data['password']
    ];

    $stmt = $conn->prepare("SELECT users.id,users.password FROM `users` WHERE `email` = ?");
    $stmt->execute([$inputData['email']]);
    $result = $stmt->fetchAll();
    $count = count($result);

    if ($count !== 1) {
        http_response_code(401); // Unauthorized  
        echo json_encode(['status' => 'failed', 'error' => 'Invalid email or password']);
        exit();
    }

    $passWord = new Passwordhandler($inputData['password']);

    // Check if the provided password matches the hashed password stored in the database
    if (!$passWord::validate_password($result[0]['password'], $inputData['password'])) {
        http_response_code(401); // Unauthorized  
        echo json_encode(['status' => 'failed', 'error' => 'Invalid email or password']);
        exit();
    }

    // User login successful
    $userId = $result[0]['id'];

    // Generate a login JWT 
    $secure = new SecureVault;
    $loginJWT = $secure->generateLoginJWT($userId, $_SERVER['SERVER_JWT_KEY'], 604800);

    // Return the login JWT in the response
    echo json_encode(['status' => 'success', 'message' => 'Login successful', 'JWT' => $loginJWT]);

    $conn = null; // close PDO connection


} catch (\Throwable $e) {
    // new ErrorLogger($e->getMessage(), time(), $conn); //log the error to DB
    http_response_code(500); //internal server error
    echo json_encode(['status' => 'failed', 'error' => "An error occurred while logging in.{$e->getMessage()}"]);
    exit();
}

function capitalizeFirstLetterKeys(array $array)
{
    $result = array();
    foreach ($array as $key => $value) {
        $result[ucfirst($key)] = $value;
    }
    return $result;
}
