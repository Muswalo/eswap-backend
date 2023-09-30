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

    // Create an instance of the SecureVault
    $secure = new SecureVault;

    $serviceToken = $secure->generateLoginJWT(hash('sha256', random_bytes(32)), $_SERVER['SERVER_JWT_KEY'],7776000);

    echo json_encode(['status' => 'success', 'serviceToken' => $serviceToken]);
} catch (\Throwable $th) {
    http_response_code(500); // Internal Server Error
    echo $th->getMessage();
    echo json_encode(['status' => 'failed', 'error' => 'incase you concerned i also dont know what happend. you might aswell just live thank you!']);
    exit();
}