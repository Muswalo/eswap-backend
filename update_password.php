<?php
require_once __DIR__ . '/src/config/Dbh.config.php'; // Include the database connection

use Php101\Php101\SecureVault;
use Php101\Php101\Passwordhandler;

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'failed', 'error' => 'Invalid request method']);
        exit();
    }

    // Get the auth token from the request header.
    $headers = capitalizeFirstLetterKeys(apache_request_headers());
    $token = $headers['Authorization'] ?? null;

    $secure = new SecureVault; // Create an instance of a secure vault

    // Check if the JWT is valid
    if (!$secure->validateJWT($token, $_SERVER['SERVER_JWT_KEY'])) {
        http_response_code(401); // Unauthorized
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
        'new_password' => htmlspecialchars(stripslashes(trim($data['new_password'])))
    ];

    // Check if the email and new password are provided
    if (empty($inputData['email']) || empty($inputData['new_password'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'failed', 'error' => 'Email and new password are required']);
        exit();
    }

    // Check if the email exists in the database and fetch the user's record
    $stmt = $conn->prepare("SELECT COUNT(*) as count, first_name, update_info FROM `users` WHERE `email` = ?");
    $stmt->execute([$inputData['email']]);
    $result = $stmt->fetch();

    if ($result['count'] === 0) {
        http_response_code(404); // Not Found
        echo json_encode(['status' => 'failed', 'error' => 'Email not found']);
        exit();
    }
    // Check if the update_info exists and contains a verified token
    if (isset($result['update_info'])) {
        $existingUpdateInfo = json_decode($result['update_info'], true);

        // Find the active token that is verified
        $activeToken = null;

        foreach ($existingUpdateInfo as $token) {

            if ($token['active'] && $token['verified']) {
                $activeToken = $token;
                break;
            }
        }

        if (!$activeToken) {
            http_response_code(400); // Bad Request
            echo json_encode(['status' => 'failed', 'error' => 'No verified token found']);
            exit();
        }

        // Check the strength of the new password
        $passwordHandler = new Passwordhandler($inputData['new_password']);
        $strength = $passwordHandler->validateStrength();
        $passwordErrLen = count($strength);

        if ($passwordErrLen > 0) {
            http_response_code(400); // Bad Request
            echo json_encode(['status' => 'failed', 'error' => $strength]);
            exit();
        }

        // Hash the new password
        $hashedPassword = $passwordHandler->hash_password();

        // Update the user's password in the database
        $stmt = $conn->prepare("UPDATE `users` SET `password` = ? WHERE `email` = ?");
        $stmt->execute([$hashedPassword, $inputData['email']]);

        // Set the active state of the token to false
        print_r($existingUpdateInfo);
        foreach ($existingUpdateInfo as &$token) {
            if ($token['active']) {
                $token['active'] = false;
            }
        }
        print_r($existingUpdateInfo);

        // Add an update object to the `updates` column
        $updateObject = [
            'type' => 'password update',
            'date' => date('Y-m-d H:i:s'),
            'description' => 'Password updated',
            'token' => $activeToken // Add the token information to the update object
        ];

        // Add the update object to the existing updates (if updates exist) or create a new array with the update object
        $updates = isset($result['updates']) ? json_decode($result['updates'], true) : [];
        $updates[] = $updateObject;

        // Encode the updates array back to JSON
        $updatedUpdates = json_encode($updates);

        // Update the updates column in the database
        $stmt = $conn->prepare("UPDATE `users` SET `update_info` = ?, `updates` = ? WHERE `email` = ?");
        $stmt->execute([json_encode($existingUpdateInfo), $updatedUpdates, $inputData['email']]);

        // Send the response
        echo json_encode(['status' => 'success', 'message' => 'Password updated successfully']);
    } else {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'failed', 'error' => 'No tokens found']);
        exit();
    }
    $conn = null; // Close PDO connection
} catch (\Throwable $e) {
    // new ErrorLogger($e->getMessage(), time(), $conn); // Log the error to DB
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'failed', 'error' => 'An error occurred while updating the password']);
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
