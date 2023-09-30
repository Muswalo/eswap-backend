<?php
require_once __DIR__ . '/src/config/Dbh.config.php'; // Include the database connection

use Php101\Php101\SecureVault;
use Php101\Php101\MessageInterface;

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

    // Generate a random reset code
    $resetCode = generateResetCode();

    $exp = $expirationDate = date('Y-m-d H:i:s', strtotime('+1 hour'));
    // Prepare the new update_value
    $updateValue = [
        'reset_code' => $resetCode,
        'reset_date' => date('Y-m-d H:i:s'),
        'exp' => $exp,
        'active' => true,
        'verified' => false
    ];


    if (isset($result['update_info'])) {
        // Fetch the existing update_info
        $existingUpdateInfo = json_decode($result['update_info'], true);
    
        // Set all existing tokens as inactive (active: false)
        foreach ($existingUpdateInfo as &$existingToken) {
            $existingToken['active'] = false;
        }
    
        // Add the new entry with a unique key and set it as active
        $existingUpdateInfo[] = $updateValue;
    
        // Encode the updated array back to JSON
        $mergedUpdateInfo = json_encode($existingUpdateInfo);
    } else {
        // If the update_info key doesn't exist, create an array with the new entry
        $mergedUpdateInfo = json_encode([$updateValue]);
    }

    // Update the update_info column in the database
    $stmt = $conn->prepare("UPDATE `users` SET `update_info` = ? WHERE `email` = ?");
    $stmt->execute([$mergedUpdateInfo, $inputData['email']]);


    // Prepare the email content
    $content = [
        'sender_email' => 'emuswalo7@gmail.com',
        'sender_name' => 'Emmanuel Muswalo',
        'recipient_name' => $result['first_name'],
        'subject' => 'Password Reset Code',
        'msg' => generateResetEmailContent($result['first_name'], $resetCode, $exp),
        'ishtml' => true,
        'altmsg' => '',
        'attachment' => false,
    ];

    // Send the reset code email using MessageInterface class
    $message = new MessageInterface('mail', $inputData['email'], $content);

    if ($message->createMsg()) {
        echo json_encode(['status' => 'success', 'message' => 'Reset code sent successfully']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'failed', 'error' => 'Failed to send reset code']);
    }

    $conn = null; // Close PDO connection

} catch (\Throwable $e) {
    // new ErrorLogger($e->getMessage(), time(), $conn); // Log the error to DB
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'failed', 'error' => 'An error occurred while sending the reset code']);
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

function generateResetCode()
{
    return mt_rand(100000, 999999);
}


function generateResetEmailContent($recipientName, $resetCode, $exp)
{
    // Email content with HTML and CSS styling
    $emailContent = '<html>
                        <head>
                            <style>
                                body {
                                    font-family: Arial, sans-serif;
                                    color: #333;
                                }
                                .container {
                                    background-color: #f2f2f2;
                                    padding: 20px;
                                    border-radius: 5px;
                                }
                                .title {
                                    font-size: 24px;
                                    font-weight: bold;
                                    margin-bottom: 10px;
                                }
                                .code {
                                    font-size: 32px;
                                    font-weight: bold;
                                    color: #007bff;
                                    margin-bottom: 20px;
                                }
                                .expire {
                                    font-size: 14px;
                                    color: #888;
                                }
                                .disclaimer {
                                    font-size: 12px;
                                    color: #888;
                                    margin-top: 30px;
                                }
                            </style>
                        </head>
                        <body>
                            <div class="container">
                                <div class="title">Dear ' . $recipientName . ',</div>
                                <div class="code">Your password reset code is: ' . $resetCode . '</div>
                                <div class="expire">This code will expire on ' . $exp . '</div>
                                <div class="disclaimer">Please do not share this code with anyone. If you did not request a password reset, please ignore this email.</div>
                            </div>
                        </body>
                    </html>';

    return $emailContent;
}
