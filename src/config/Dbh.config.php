<?php
require_once __DIR__ . '/config.php';
use Dotenv\Dotenv;
use Php101\Php101\DBInterface;
use Php101\Php101\ErrorLogger;

try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    $servername = $_ENV['DB_HOST'];
    $username = $_ENV['DB_USER'];
    $password = $_ENV['DB_PASS'];
    $database = $_ENV['DB_NAME'];

    // Create a new DBInterface instance and establish a connection
    $dbInterface = new DBInterface($servername, $username, $password, $database);
    $conn = $dbInterface->conn();

} catch (\Exception $e) {

    // new ErrorLogger($e->getMessage(), time(), $conn);
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(array('status'=>'failed','error' => 'An error occurred.'));
    exit();
    
}
