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
    $authToken = $headers['Authorization'] ?? null;

    $secure = new SecureVault; // Create an instance of a secure vault

    // Check if the service token is valid
    if (!$secure->validateJWT($authToken, $_SERVER['SERVER_JWT_KEY'])) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'failed', 'error' => 'Invalid service token']);
        exit();
    }

    // Get the user token from the request header
    $userToken = $headers['X-bearer-token'] ?? null;

    // Decode the user token to obtain the user's ID
    $userId = $secure->decodeJWT($userToken, $_SERVER['SERVER_JWT_KEY']);

    // Check if the user's ID is valid
    if (!$userId) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'failed', 'error' => 'Invalid User token']);
        exit();
    }

    // Get the swapId from the request body
    $requestData = file_get_contents('php://input');
    $data = json_decode($requestData, true);
    $swapId = $data['swapId'] ?? null;

    if (!$swapId) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'failed', 'error' => 'Missing swapId in request body']);
        exit();
    }

    // Check if the user has an active subscription
    $hasActiveSubscription = checkUserSubscription($conn, $userId);

    if (!$hasActiveSubscription) {
        http_response_code(402); // Payment Required
        echo json_encode(['status' => 'failed', 'error' => 'User has no active subscription']);
        exit();
    }

    // Fetch swapper data based on swapId
    $swapperData = getSwapperData($conn, $swapId);

    if (!$swapperData) {
        http_response_code(404); // resource not found
        echo json_encode(['status' => 'failed', 'error' => 'Swapp not found']);
        exit();
    }

    // Close the database connection
    $conn = null;
    
    echo json_encode(['status' => 'success', 'data' => $swapperData]);

} catch (\Throwable $th) {
    // new ErrorLogger($e->getMessage(), time(), $conn); // Log the error to DB
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'failed', 'error' => 'An error occurred while fetching data']);
    exit();
}

// Function to check if the user has an active subscription
function checkUserSubscription($conn, $userId)
{
    $query = "SELECT id FROM subscriptions WHERE user_id = :user_id AND status = 'active'";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->rowCount() > 0; // Return true if there's an active subscription, false otherwise
}

// Function to fetch swapper data based on swapId
function getSwapperData($conn, $swapId)
{
    $query = "SELECT s.*, u.first_name, u.last_name, u.email, u.phone, u.image AS profile_photo
    FROM swaps AS s
    INNER JOIN users AS u ON s.user_id = u.id
    WHERE s.id = :swap_id
    ";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':swap_id', $swapId, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function capitalizeFirstLetterKeys(array $array)
{
    $result = array();
    foreach ($array as $key => $value) {
        $result[ucfirst($key)] = $value;
    }
    return $result;
}