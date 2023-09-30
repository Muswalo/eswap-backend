<?php
require_once __DIR__ . '/src/config/Dbh.config.php'; // Include the database connection
require_once "./src/FCMNotificationSender .php";

$jsonFilePath = "./src/config/eswap-f5090-4152f379e7ae.json";


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
    $userIdA0 = $secure->decodeJWT($userToken, $_SERVER['SERVER_JWT_KEY']);

    // Check if the user's ID is valid
    if (!$userIdA0) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'failed', 'error' => 'Invalid User token']);
        exit();
    }

    // Grab the swap ID from the PHP input stream.
    $requestData = json_decode(file_get_contents('php://input'), true);
    $swapId = $requestData['swapId'] ?? null;

    // Check if swap ID is provided
    if (!$swapId) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'failed', 'error' => 'Swap ID is required']);
        exit();
    }

    //Obtain the user id from the swapper id. this user ID represents the person to whom the current user is sending a swap resquest to. A1
    $userIdA1 = getUserIdFromSwapId($swapId, $conn);

    if (!$userIdA1) {
        http_response_code(404); // user not found
        echo json_encode(['status' => 'failed', 'error' => 'user with the swap id not found']);
        exit();
    }



    if (updateSwapRequests($userIdA1, $swapId, $userIdA0 ,$conn)) {

        echo json_encode(['status' => 'success', 'message' => 'Swap request sent successfully']);

        $deviceTokens = getDeviceTokensByUserId($userIdA1, $conn);
        print_r($deviceTokens);
        if ($deviceTokens !== []) {
            // Create an instance of the FCMNotificationSender class
            $sender = new FCMNotificationSender($jsonFilePath, $deviceTokens, 'Swap Request', 'Someone wants to swap with you 😊');

            // Send notifications and get a list of unsent devices (if any)
            $sender->sendNotifications();
        }

    } else {

        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'failed', 'error' => 'Failed to update swap requests']);
        exit();
    }
    
    $conn = null;

} catch (\Throwable $e) {

    // new ErrorLogger($e->getMessage(), time(), $conn); // Log the error to DB
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'failed', 'error' => 'An error occurred while sending the swap request']);
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

function getUserIdFromSwapId(string $swapId, PDO $conn){
    $sql = "SELECT user_id
    FROM swaps
    WHERE id = :swapId";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':swapId', $swapId, PDO::PARAM_STR);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result !== false && isset($result['user_id'])) {
        return $result['user_id'];
    } else {
        return null; 
    }
}

function getNotification(string $notification_type, string $userId, string $title, string $message, PDO $conn) {
    try {
        // Insert a new notification record
        $sql = "INSERT INTO `notifications`(`user_id`, `title`, `message`,`notification_type`) VALUES (:userId, :title, :message, :notification_type)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_STR);
        $stmt->bindParam(':title', $title, PDO::PARAM_STR);
        $stmt->bindParam(':message', $message, PDO::PARAM_STR);
        $stmt->bindParam(':notification_type', $notification_type, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            // If the insert was successful, return the ID of the new notification
            return $conn->lastInsertId();
        } else {
            return false;
        }
    } catch (\Throwable $e) {
        echo $e;
        return false;
    }
}


function updateSwapRequests(string $receiverId, string $swapId, string $senderId, PDO $conn) {
    try {
        // Insert a new swap request record
        $sql = "INSERT INTO `swap_requests`(`sender_id`,`recipient_id`,`swap_id`,`notification_id`) VALUES (:sender,:receiver,:swapId,:notification_id)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':sender', $senderId, PDO::PARAM_STR);
        $stmt->bindParam(':receiver', $receiverId, PDO::PARAM_STR);
        $stmt->bindParam(':swapId', $swapId, PDO::PARAM_STR);

        // Create a notification with title "Swap Request" and message "Swap Request From"
        $title = "Swap Request";
        $message = "Swap Request From";
        $notificationId = getNotification('sr',$receiverId, $title, $message, $conn);

        if ($notificationId !== false) {
            // Bind the notification ID to the swap request
            $stmt->bindParam(':notification_id', $notificationId, PDO::PARAM_INT);
            return $stmt->execute();
        } else {
            return false;
        }
    } catch (\Throwable $e) {
        echo $e;
        return false;
    }
}


function getDeviceTokensByUserId(string $userId, PDO $conn) {

    try {
        // Prepare an SQL statement to fetch device tokens for the user
        $sql = "SELECT `device_token` FROM `device_tokens` WHERE `user_id` = :userId";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_STR);
        $stmt->execute();

        // Fetch the results as an indexed array of device tokens
        $deviceTokens = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return $deviceTokens;
    } catch (\Throwable $e) {
        return [];
    }

}



