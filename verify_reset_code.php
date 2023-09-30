<?php

require_once __DIR__ . '/src/config/Dbh.config.php'; // Include the database connection

use Php101\Php101\SecureVault;

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
        'reset_code' => htmlspecialchars(stripslashes(trim($data['code']))),
    ];

    // Check if the email exists in the database and fetch the user's fname and update_info
    $stmt = $conn->prepare("SELECT COUNT(*) as count, first_name, update_info FROM `users` WHERE `email` = ?");
    $stmt->execute([$inputData['email']]);
    $result = $stmt->fetch();

    if ($result['count'] === 0) {
        http_response_code(404); // Not Found
        echo json_encode(['status' => 'failed', 'error' => 'Email not found']);
        exit();
    }

    if (isset($result['update_info'])) {
        // Fetch the existing update_info
        $existingUpdateInfo = json_decode($result['update_info'], true);

        // Find the active token for verification
        $activeTokenIndex = null;
        foreach ($existingUpdateInfo as $index => $token) {
            if ($token['active'] && !$token['verified'] && (int)$token['reset_code'] === (int)$inputData['reset_code']) {
                // Check if the token is not expired
                $expDate = strtotime($token['exp']);
                if (time() > $expDate) {
                    http_response_code(400); // Bad Request
                    echo json_encode(['status' => 'failed', 'error' => 'Token has expired']);
                    exit();
                }
                $token['verified'] = true; // mark the token as verified
                $existingUpdateInfo[$index] = $token; // update the token in the original array
                $activeTokenIndex = $index;
                break;
            }
        }
        
        // Check if an active token for verification was found
        if ($activeTokenIndex === null) {
            http_response_code(400); // Bad Request
            echo json_encode(['status' => 'failed', 'error' => 'Invalid or expired token']);
            exit();
        }
        
        // Encode the updated array back to JSON
        $mergedUpdateInfo = json_encode($existingUpdateInfo);
        
        // Update the update_info column in the database
        $stmt = $conn->prepare("UPDATE `users` SET `update_info` = ? WHERE `email` = ?");
        $stmt->execute([$mergedUpdateInfo, $inputData['email']]);
        
    } else {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'failed', 'error' => 'No tokens found']);
        exit();
    }

    // Send the response
    echo json_encode(['status' => 'success', 'message' => 'Token verified successfully']);

    $conn = null; // Close PDO connection
} catch (\Throwable $e) {
    // new ErrorLogger($e->getMessage(), time(), $conn); // Log the error to DB
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'failed', 'error' => 'An error occurred while verifying the token']);
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

