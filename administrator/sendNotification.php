<?php

// Include the FCMNotificationSender class
require_once "../src/config/Dbh.config.php";
require_once "../src/FCMNotificationSender .php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'failed', 'error' => 'Invalid request method']);
    exit();
}

// Define the path to your JSON file containing authentication data
$jsonFilePath = "../src/config/eswap-f5090-4152f379e7ae.json";

//Get email from request header
$email = htmlspecialchars($_POST['email']);

// Define the title and message for the notification
$title = 'This is an example title';
$message = htmlspecialchars($_POST['msg']);

// Define an array of device tokens to send notifications to
$deviceTokens = FCMNotificationSender::getUserTokensFromDb($conn);

if ($deviceTokens === []) {

    http_response_code(404);
    echo json_encode(['status' => 'failed', 'error' => 'Could not send notification!']);
    exit();
}

// Create an instance of the FCMNotificationSender class
$sender = new FCMNotificationSender($jsonFilePath, $deviceTokens, $title, $message);

// Send notifications and get a list of unsent devices (if any)
$unsentDevices = $sender->sendNotifications();
updateSwapRequests($message, $conn);

if (!empty($unsentDevices)) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'failed', 'error' => 'Notifications failed to send to some devices', 'unsent_devices' => $unsentDevices]);

} else {
    http_response_code(200); // OK
    echo json_encode(['status' => 'success', 'message' => 'All notifications sent successfully']);
}

function updateSwapRequests(string $msg, PDO $conn)
{
    try {
        $sql = "INSERT INTO `swap_requests`(`type`,`message`) VALUES (:type,:msg)";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':type', 'd', PDO::PARAM_STR);
        $stmt->bindParam(':msg', $msg, PDO::PARAM_STR);
        return $stmt->execute();

    } catch (\Throwable $e) {
        return false;
    }
}

// $deviceTokens = [
// 'dTd9CeTSQa-oTzU15_pN-L:APA91bGEWPnsLuSh-VRAJU4P32m2dRtJqh0ERAQ6udjW_4h-VW6xSw7RsAERxOuP3TJLHlbc4irKU0LTp3r7TisUqlcUcGdKBcqAetFg2i61wbTQk4rol5fPkUX6VzFPJaYOe7N9ET2C'
// ];
