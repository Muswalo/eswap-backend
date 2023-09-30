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

    // Check if the JWT is valid
    if (!$secure->validateJWT($authToken, $_SERVER['SERVER_JWT_KEY'])) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'failed', 'error' => 'Invalid service token']);
        exit();
    }

    // Fetch unique categories
    $categories = getUniqueCategoriesFromSwapsTable($conn);

    $arr = [];
    foreach ($categories as $category) {
        array_push($arr, ['id' => $category, 'name' => $category]);
    }
    array_unshift($arr, ['id'=>'all', 'name'=>'All']);
    // Close the database connection
    $conn = null;

    echo json_encode($arr);

} catch (\Throwable $e) {
    // new ErrorLogger($e->getMessage(), time(), $conn); // Log the error to DB
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

// Function to get unique categories from the swaps table
function getUniqueCategoriesFromSwapsTable($conn)
{
    $query = "SELECT category, COUNT(*) AS repetition_count
    FROM swaps
    GROUP BY category
    ORDER BY repetition_count DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return $categories;
}
