<?php
require_once __DIR__ . '/src/config/Dbh.config.php'; // Include the database connection

use Php101\Php101\SecureVault;

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['status' => 'failed', 'error' => 'Invalid request method']);
        exit();
    }

    // Get the auth token and user token from the request headers.
    $headers = capitalizeFirstLetterKeys(apache_request_headers());
    $authToken = $headers['Authorization'] ?? null;
    $secure = new SecureVault; // Create an instance of a secure vault

    // Check if the JWT is valid
    if (!$secure->validateJWT($authToken, $_SERVER['SERVER_JWT_KEY'])) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'failed', 'error' => 'Invalid service token']);
        exit();
    }

    $userToken = $headers['X-bearer-token'] ?? null; 

    // Decode the user token to obtain the user's ID
    $userId = $secure->decodeJWT($userToken, $_SERVER['SERVER_JWT_KEY']);

    // Check if the user's ID is valid
    if (!$userId) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'failed', 'error' => 'Invalid User token']);
        exit();
    }

    // Read the swapRequest data for the user
    $swapRequestList = readSwapRequests($userId, $conn);

    if (!isset($swapRequestList)) {
        $swapRequestList = []; 
    }

    echo json_encode(['status' => 'success', 'swapRequests' => $swapRequestList]);
 
    $conn = null;

} catch (\Throwable $e) {

    // new ErrorLogger($e->getMessage(), time(), $conn); // Log the error to DB
    http_response_code(500); // Internal Server Error
    echo $e->getMessage();
    echo json_encode(['status' => 'failed', 'error' => 'An error occurred while fetching swap requests']);
    exit();  

}

function capitalizeFirstLetterKeys(array $array){
    $result = array();
    foreach ($array as $key => $value) {
        $result[ucfirst($key)] = $value;
    }
    return $result;
}

function readSwapRequests(string $userId, PDO $conn) {
    
    $sql = "SELECT 
                n.id, 
                sr.swap_id, 
                n.is_read AS 'viewed', 
                u.first_name AS from_user_first_name, 
                u.last_name AS from_user_last_name, 
                u.image AS from_user_image, 
                n.message, 
                n.notification_type AS 'type', 
                sr.status, 
                sr.date_requested
            FROM 
                notifications n
            LEFT JOIN 
                swap_requests sr ON n.id = sr.notification_id
            LEFT JOIN 
                Users u ON n.user_id = u.id
            WHERE u.id = :user_id
            ORDER BY 
                sr.date_requested DESC;";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_STR);
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($results !== false && !empty($results)) {
        foreach ($results as &$result) {
            $timestamp = strtotime($result['date_requested']);
            $result['date_requested'] = timeAgo($timestamp); 
        }
        return $results;
    } else {
        return null; 
    }
}


function timeAgo($timestamp) {
    $currentTime = time();
    $timeDifference = $currentTime - $timestamp;

    if ($timeDifference < 60) {
        return $timeDifference . " seconds ago";
    } elseif ($timeDifference < 3600) {
        $minutes = floor($timeDifference / 60);
        return $minutes . " minutes ago";
    } elseif ($timeDifference < 86400) {
        $hours = floor($timeDifference / 3600);
        return $hours . " hours ago";
    } elseif ($timeDifference < 2592000) {
        $days = floor($timeDifference / 86400);
        return $days . " days ago";
    } elseif ($timeDifference < 31536000) {
        $months = floor($timeDifference / 2592000);
        return $months . " months ago";
    } else {
        $years = floor($timeDifference / 31536000);
        return $years . " years ago";
    }
}

