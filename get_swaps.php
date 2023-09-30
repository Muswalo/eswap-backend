<?php
require_once __DIR__ . '/src/config/Dbh.config.php'; // Include the database connection

use Php101\Php101\SecureVault;

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // I'm a teapot
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

    // Check if the data was successfully read
    if ($requestData === false) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'failed', 'error' => 'Failed to read request data']);
        exit();
    }

    // Convert the JSON data into an associative array
    $data = json_decode($requestData, true);

    // Check if JSON decoding was successful
    if ($data === null) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'failed', 'error' => 'Failed to read request data']);
        exit();
    }
    // Extract offset and limit values from the request data
    $offset = (int)($data['offset'] ?? 0);
    $limit = (int)($data['limit'] ?? 10);
    $from = $data['fromLocation'];
    $to = $data['toLocation'];
    $category = ($data['category'] === 'all') ? '' : $data['category'];
        
    // Check if offset and limit are non-negative integers
    if ($offset < 0 || $limit < 0) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'failed', 'error' => 'Invalid offset or limit']);
        exit();
    }

    // Read the data from the database
    switch (true) {
        case !empty($from) && !empty($to) && !empty($category): 
            $result = fetchByFromToAndCategory($conn, $from, $to, $category, $offset, $limit);
            break;
        case !empty($from) && !empty($category):
            $result = fetchByFromAndCategory($conn, $from, $category, $offset, $limit);
            break;
        case !empty($to) && !empty($category):
            $result = fetchByToAndCategory($conn, $to, $category, $offset, $limit);
            break;
        case !empty($from):
            $result = fetchByFromOnly($conn, $from, $offset, $limit);
            break;
        case !empty($to):
            $result = fetchByToOnly($conn, $to, $offset, $limit);
            break;
        case !empty($category):
            $result = fetchByCategoryOnly($conn, $category, $offset, $limit);
            break;
        default:
            $result = fetchByNoCriteria($conn, $offset, $limit);
            break;
    }

 
    // Check if the user has an active subscription
    $activeSubscription = false;
    
    // Query the subscriptions table
    $stmt = $conn->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date > CURRENT_TIMESTAMP LIMIT 1");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->execute();
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($subscription) {
        // User has an active subscription
        $activeSubscription = true;
    }

    // Iterate through the results and merge the data from swaps and users table
    $mergedData = [];
    foreach ($result as $row) {
        // Extract relevant fields from the result
        $firstName = $row['first_name'];
        $lastName = $row['last_name'];
        $category = $row['category'];
        $fromLocation = $row['from_location'];
        $toLocation = $row['to_location'];
        $swapId = $row['swap_id'];
        $imageUrl = ($activeSubscription) ? $row['pp'] : 'placehoder.jpeg';

        // Replace user name and last name with ***** if subscription is inactive
        if (!$activeSubscription) {
            $firstName = '..........';
            $lastName = '.........';
        }

        // Add the merged data to the result array
        $mergedData[] = [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'category' => $category,
            'fromLocation' => $fromLocation,
            'toLocation' => $toLocation,
            'swapId' => $swapId,
            'imageUrl' => $imageUrl,
        ];
    }

    // Send the response
    echo json_encode(['status' => 'success', 'data' => $mergedData]);

    $conn = null; // Close PDO connection
} catch (\Throwable $th) {
    // new ErrorLogger($e->getMessage(), time(), $conn); // Log the error to DB
    echo $th;
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'failed', 'error' => 'An error occurred while fetching data']);
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


function fetchByFromAndCategory(object $conn, string $fromLocation, string $category, int $offset, int $limit) {

    $sql = "SELECT DISTINCT s.id AS swap_id,  u.image AS pp , u.first_name, u.last_name, s.current_job_title AS category, s.from_location, s.to_location, s.date_posted, s.swap_status
            FROM swaps AS s
            INNER JOIN users AS u ON s.user_id = u.id
            WHERE s.from_location = :fromLocation AND s.category = :category AND swap_status = 0
            ORDER BY s.date_posted DESC
            LIMIT :offset, :limit";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':fromLocation', $fromLocation, PDO::PARAM_STR);
    $stmt->bindValue(':category', $category, PDO::PARAM_STR);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function fetchByToAndCategory(object $conn, string $toLocation, string $category, int $offset, int $limit) {
    $sql = "SELECT DISTINCT s.id AS swap_id,  u.image AS pp , u.first_name, u.last_name, s.current_job_title AS category, s.from_location, s.to_location, s.date_posted, s.swap_status
            FROM swaps AS s
            INNER JOIN users AS u ON s.user_id = u.id
            WHERE s.to_location = :toLocation AND s.category = :category AND swap_status = 0
            ORDER BY s.date_posted DESC
            LIMIT :offset, :limit";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':toLocation', $toLocation, PDO::PARAM_STR);
    $stmt->bindValue(':category', $category, PDO::PARAM_STR);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);

}

function fetchByFromToAndCategory(object $conn, string $fromLocation, string $toLocation, string $category, int $offset, int $limit) {
    $sql = "SELECT DISTINCT s.id AS swap_id, u.image AS pp , u.first_name, u.last_name,  s.current_job_title AS category, s.from_location, s.to_location, s.date_posted, s.swap_status
            FROM swaps AS s
            INNER JOIN users AS u ON s.user_id = u.id
            WHERE s.from_location = :fromLocation AND s.to_location = :toLocation AND s.category = :category AND swap_status = 0
            ORDER BY s.date_posted DESC
            LIMIT :offset, :limit";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':fromLocation', $fromLocation, PDO::PARAM_STR);
    $stmt->bindValue(':toLocation', $toLocation, PDO::PARAM_STR);
    $stmt->bindValue(':category', $category, PDO::PARAM_STR);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);

}

function fetchByFromOnly(object $conn, string $fromLocation, int $offset, int $limit) {
    $sql = "SELECT DISTINCT s.id AS swap_id, u.image AS pp  , u.first_name, u.last_name, s.current_job_title AS category, s.from_location, s.to_location, s.date_posted, s.swap_status
            FROM swaps AS s
            INNER JOIN users AS u ON s.user_id = u.id
            WHERE s.from_location = :fromLocation AND swap_status = 0
            ORDER BY s.date_posted DESC
            LIMIT :offset, :limit";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':fromLocation', $fromLocation, PDO::PARAM_STR);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchByToOnly(object $conn, string $toLocation, int $offset, int $limit) {
    $sql = "SELECT DISTINCT s.id AS swap_id,  u.image AS pp , u.first_name, u.last_name,  s.current_job_title AS category, s.from_location, s.to_location, s.date_posted, s.swap_status
            FROM swaps AS s
            INNER JOIN users AS u ON s.user_id = u.id
            WHERE s.to_location = :toLocation AND swap_status = 0
            ORDER BY s.date_posted DESC
            LIMIT :offset, :limit";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':toLocation', $toLocation, PDO::PARAM_STR);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
}

function fetchByCategoryOnly(object $conn, string $category, int $offset, int $limit) {
    $sql = "SELECT DISTINCT s.id AS swap_id,  u.image AS pp , u.first_name, u.last_name, s.current_job_title AS category, s.from_location, s.to_location, s.date_posted, s.swap_status
            FROM swaps AS s
            INNER JOIN users AS u ON s.user_id = u.id
            WHERE s.category = :category AND swap_status = 0
            ORDER BY s.date_posted DESC
            LIMIT :offset, :limit";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':category', $category, PDO::PARAM_STR);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    
}

function fetchByNoCriteria(object $conn, int $offset, int $limit) {
    $sql = "SELECT DISTINCT s.id AS swap_id, u.image AS pp , u.first_name, u.last_name, s.current_job_title AS category, s.from_location, s.to_location, s.date_posted, s.swap_status
            FROM swaps AS s
            INNER JOIN users AS u ON s.user_id = u.id
            WHERE swap_status = 0
            ORDER BY s.date_posted DESC
            LIMIT :offset, :limit";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
