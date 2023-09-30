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

    // Get the auth token from the request header
    $headers = capitalizeFirstLetterKeys(apache_request_headers());
    $authToken = $headers['Authorization'] ?? null;

    $secure = new SecureVault; // Create an instance of a secure vault

    // Check if the JWT is valid
    if (!$secure->validateJWT($authToken, $_SERVER['SERVER_JWT_KEY'])) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'failed', 'error' => 'Invalid service token']);
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

    $type = $data['type'];

    if ($type === 'from') {
        $locations = getDistinctLocationsFromSwapsTable($conn, 'from_location');
    } elseif ($type === 'to') {
        $locations = getDistinctLocationsFromSwapsTable($conn, 'to_location');
    } else {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'failed', 'error' => 'Invalid type value']);
        exit();
    }

    // Close the database connection
    $conn = null;

    $arr = [];

    for ($i=0; $i < count($locations); $i++) {
        $arr[] = ['id'=>$i, 'name'=> $locations[$i]];
    }

    echo json_encode(['status' => 'success', 'locations' => $arr]);


} catch (\Throwable $e) {
}

// Function to get distinct locations from the swaps table
function getDistinctLocationsFromSwapsTable($conn, $locationType)
{
    $query = "SELECT DISTINCT {$locationType} FROM swaps";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return $locations;
}

// Function to capitalize the first letter of each key in an array
function capitalizeFirstLetterKeys(array $array)
{
    $result = array();
    foreach ($array as $key => $value) {
        $result[ucfirst($key)] = $value;
    }
    return $result;
}
