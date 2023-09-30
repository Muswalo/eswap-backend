<?php

require_once __DIR__ . '/src/config/Dbh.config.php'; // Include the database connection

use Php101\Php101\SecureVault;

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'failed', 'error' => 'Invalid request method']);
    exit();
}

// Get the auth token from the request header.
$headers = capitalizeFirstLetterKeys(apache_request_headers());
$authToken = $headers['Authorization'] ?? null;
$secure = new SecureVault; // Create an instance of a secure vault

// Check if the JWT is valid
if (!$secure->validateJWT($authToken, $_SERVER['SERVER_JWT_KEY'])) {
    http_response_code(403); // Forbiden
    echo json_encode(['status' => 'failed', 'error' => 'Invalid service token']);
    exit();
}

// Get the Bearer token containing the user's ID
$bearerToken = $headers['X-bearer-token'] ?? null;

// Decode the Bearer token to obtain the user's ID
$userId = $secure->decodeJWT($bearerToken, $_SERVER['SERVER_JWT_KEY']);

// Check if the user's ID is valid
if (!$userId) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'failed', 'error' => 'Invalid Bearer token']);
    exit();
}

// Grab the data from the php input stream.
$requestData = file_get_contents('php://input');

// Convert the JSON data into an associative array
$data = json_decode($requestData, true);

$message = $data['message'];

if (addMessage($message, $userId, $conn)) {
    echo json_encode(['status'=>'success', 'message'=>'sms added']);

} else{
    http_response_code(400);
    echo json_encode(['status'=>'failed', 'message'=>'sms could not be added']);
}

$conn = null;

function capitalizeFirstLetterKeys(array $array)
{
    $result = array();
    foreach ($array as $key => $value) {
        $result[ucfirst($key)] = $value;
    }
    return $result;
}


function addMessage($sms, $userId, $conn) {
    try {
        // Prepare the SQL statement
        $sql = "UPDATE users SET confirmation_sms = :sms WHERE id = :userId";

        // Prepare the query
        $query = $conn->prepare($sql);

        // Bind the parameters
        $query->bindParam(":sms", $sms, PDO::PARAM_STR);
        $query->bindParam(":userId", $userId, PDO::PARAM_INT);

        // Execute the query
        if ($query->execute()) {
            return true; // Message added successfully
        } else {
            return false; // Failed to add message
        }
    } catch (PDOException $e) {
        // Handle any exceptions here
        return false;
    }
}