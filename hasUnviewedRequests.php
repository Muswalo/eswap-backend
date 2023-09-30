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

    // Get the user token from the request headers.
    $headers = capitalizeFirstLetterKeys(apache_request_headers());
    $userToken = $headers['X-bearer-token'] ?? null;

    // Create an instance of the SecureVault
    $secure = new SecureVault;

    // Decode the user token to obtain the user's ID
    $userId = $secure->decodeJWT($userToken, $_SERVER['SERVER_JWT_KEY']);

    if (!$userId) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'failed', 'error' => 'Invalid User token in checking']);
        exit();
    }

    // Check if there are unviewed swap requests for the user
    $unviewedRequests = checkUnviewedSwapRequests($userId, $conn);

    if ($unviewedRequests) {
        echo json_encode(['status' => 'success', 'hasUnviewedRequests' => true]);
    } else {
        echo json_encode(['status' => 'success', 'hasUnviewedRequests' => false]);
    }

} catch (\Throwable $e) {
    http_response_code(500); // Internal Server Error
    echo $e;
    echo json_encode(['status' => 'failed', 'error' => 'An error occurred while checking unviewed swap requests']);
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

function checkUnviewedSwapRequests(string $userId, PDO $conn)
{
    $sql = "SELECT COUNT(*) AS unviewed_count
            FROM swap_requests
            WHERE recipient_id = :userId
            AND viewed = 0";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_STR);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result !== false && isset($result['unviewed_count']) && $result['unviewed_count'] > 0) {
        return true;
    } else {
        return false;
    }
}
