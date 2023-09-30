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


    // Grab the data from the php input stream.
    $Data = json_decode(file_get_contents('php://input'), true);

    $notificationId = $Data['notId'];

    $operation = $Data['operation'];

    $params = $Data['params'];


    switch ($operation) {
        case 'view':
            handleNotificationView($notificationId, $userId, $conn);
            break;
        case 'approval':
            handleSwapApproval($notificationId, $userId, $conn);
            break;
        case 'DisApproval':
            handleSwapDisApproval($notificationId, $userId, $conn);
            break;
        case 'fetch':
            fetchSwapDataDeviceAndUser($userId, $params, $conn);
            break;
        case 'remove':
            handleNotificationRemoved($notificationId, $userId, $conn);
            break;
        default:
            http_response_code(400); // Bad Request
            echo json_encode(['status' => 'failed', 'error' => 'Invalid operation']);
            exit();
    }

} catch (\Throwable $th) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'failed', 'error' => 'An error occurred']);
    exit();
}

function capitalizeFirstLetterKeys(array $array){
    $result = array();
    foreach ($array as $key => $value) {
        $result[ucfirst($key)] = $value;
    }
    return $result;
}

// call when the notification is clicked and we have been redirected to the view section where the user can approve or disApprove a request

function fetchSwapDataDeviceAndUser(string $userId, array $params, PDO $conn) {
    try {
        // Fetch user details
        $userDetails = getUserDetails($userId, $conn);

        if ($userDetails === false) {
            // Handle the case where the user is not found
            http_response_code(404); // Not Found
            echo json_encode(['status' => 'failed', 'error' => 'User not found']);
            return;
        }

        // Fetch the device token from the params array
        $deviceToken = $params['device_token'] ?? null;

        if (!$deviceToken) {
            // Handle the case where device_token is missing
            http_response_code(400); // Bad Request
            echo json_encode(['status' => 'failed', 'error' => 'Device token is missing']);
            return;
        }

        // Check and reassign the device token
        $reassigned = reassignDeviceToken($conn, $deviceToken, $userId);

        if (!$reassigned) {
            // Handle any errors or log them as needed
        }

        // Send the response as JSON
        echo json_encode([
            'status' => 'success',
            'user_details' => $userDetails,
            'isSubscribed' => checkUserSubscription($conn, $userId),
            'message' => 'Data retrieved successfully'
        ]);
    } catch (\Throwable $e) {
        echo $e;
        // Handle any exceptions or errors here
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'failed', 'error' => 'An error occurred']);
    }
}



function handleNotificationView(string $notificationId, string $userId, PDO $conn) {
    try {
        // Update swap requests table and set viewed to true
        $sql = "UPDATE notifications
                SET is_read = 1
                WHERE id = :notificationId  AND user_id = :userId";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':notificationId', $notificationId, PDO::PARAM_INT);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_STR);
        
        if ($stmt->execute()) {

            echo json_encode([
                'status' => 'success', 
                'message' => 'Notification viewed successfully', 
                'data' => getSenderInfoByNotificationId1($notificationId, $conn)]
            );

        } else {
            http_response_code(500); // Internal Server Error
            echo json_encode(['status' => 'failed', 'error' => 'Failed to update notification']);
        }
    } catch (\Throwable $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'failed', 'error' => 'An error occurred while updating notification']);
    }
}



function handleSwapApproval(string $notificationId, string $userId, PDO $conn) {
    try {
        // Start a transaction
        $conn->beginTransaction();

        // Fetch the swap_id from the swap_requests table
        $fetchSwapIdSql = "SELECT swap_id FROM swap_requests WHERE notification_id = :notificationId AND recipient_id = :userId";
        $stmtFetchSwapId = $conn->prepare($fetchSwapIdSql);
        $stmtFetchSwapId->bindParam(':notificationId', $notificationId, PDO::PARAM_INT);
        $stmtFetchSwapId->bindParam(':userId', $userId, PDO::PARAM_STR);
        $stmtFetchSwapId->execute();

        $result = $stmtFetchSwapId->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $swapId = $result['swap_id'];

            // Update swap requests table and set status to 'accepted'
            $updateSwapRequestsSql = "UPDATE swap_requests SET status = 'accepted' WHERE notification_id = :notificationId AND recipient_id = :userId";
            $stmtUpdateSwapRequests = $conn->prepare($updateSwapRequestsSql);
            $stmtUpdateSwapRequests->bindParam(':notificationId', $notificationId, PDO::PARAM_INT);
            $stmtUpdateSwapRequests->bindParam(':userId', $userId, PDO::PARAM_STR);

            // Update swap table and set swap_status to 1
            $updateSwapTableSql = "UPDATE swaps SET swap_status = 1 WHERE id = :swapId";
            $stmtUpdateSwapTable = $conn->prepare($updateSwapTableSql);
            $stmtUpdateSwapTable->bindParam(':swapId', $swapId, PDO::PARAM_INT);

            if ($stmtUpdateSwapRequests->execute() && $stmtUpdateSwapTable->execute()) {
                // Commit the transaction
                $conn->commit();
                
                // Call the approvalHelper function to send a notification to the sender
                $senderId = getSenderIdByNotificationId($notificationId, $conn);

                if ($senderId) {
                    approvalHelper($senderId, $swapId, $conn);
                }

                echo json_encode(['status' => 'success', 'message' => 'Swap request approved successfully']);
            } else {
                $conn->rollBack(); // Roll back the transaction in case of an error
                http_response_code(500); // Internal Server Error
                echo json_encode(['status' => 'failed', 'error' => 'Failed to approve swap request']);
            }
        } else {
            // The notification_id does not correspond to a valid swap request
            $conn->rollBack(); // Roll back the transaction
            http_response_code(400); // Bad Request
            echo json_encode(['status' => 'failed', 'error' => 'Invalid notification ID']);
        }
    } catch (\Throwable $e) {
        $conn->rollBack(); // Roll back the transaction in case of an error
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'failed', 'error' => 'An error occurred while approving swap request']);
    }
}

// Function to get the sender's ID based on the notification ID
function getSenderIdByNotificationId(string $notificationId, PDO $conn) {

    $sql = "SELECT sender_id FROM swap_requests WHERE notification_id = :notificationId";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':notificationId', $notificationId, PDO::PARAM_INT);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && isset($result['sender_id'])) {
        return $result['sender_id'];
    } else {
        return null;
    }
}



function handleSwapDisApproval(string $notificationId, string $userId, PDO $conn) {
    try {
        // Update swap requests table and set status to 'disapproved'
        $sql = "UPDATE swap_requests
                SET status = 'rejected'
                WHERE id = :notificationId AND recipient_id = :userId";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':notificationId', $notificationId, PDO::PARAM_INT);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Swap request disapproved successfully']);
        } else {
            http_response_code(500); // Internal Server Error
            echo json_encode(['status' => 'failed', 'error' => 'Failed to disapprove swap request']);
        }
    } catch (\Throwable $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'failed', 'error' => 'An error occurred while disapproving swap request']);
    }
}


function handleNotificationRemoved(string $notificationId, string $userId, PDO $conn) {
    try {
        $conn->beginTransaction(); // Start a transaction

        // Step 1: Delete the row from swap_requests table
        $sql1 = "DELETE FROM swap_requests
                 WHERE id = :notificationId AND recipient_id = :userId";

        $stmt1 = $conn->prepare($sql1);
        $stmt1->bindParam(':notificationId', $notificationId, PDO::PARAM_INT);
        $stmt1->bindParam(':userId', $userId, PDO::PARAM_STR);
        
        // Step 2: Delete corresponding records from notifications table
        $sql2 = "DELETE FROM notifications
                 WHERE id = :notificationId";

        $stmt2 = $conn->prepare($sql2);
        $stmt2->bindParam(':notificationId', $notificationId, PDO::PARAM_INT);

        if ($stmt1->execute() && $stmt2->execute()) {
            $conn->commit(); // Commit the transaction
            echo json_encode(['status' => 'success', 'message' => 'Swap request removed successfully']);
        } else {
            $conn->rollBack(); // Roll back the transaction in case of an error
            http_response_code(500); // Internal Server Error
            echo json_encode(['status' => 'failed', 'error' => 'Failed to remove swap request']);
        }
    } catch (\Throwable $e) {
        $conn->rollBack(); // Roll back the transaction in case of an error
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'failed', 'error' => 'An error occurred while removing swap request']);
    }
}



function getSenderInfoByNotificationId1(string $notificationId, PDO $conn) {
    $sql = "SELECT u.image, u.id, sr.status
            FROM swap_requests sr
            JOIN users u ON sr.sender_id = u.id
            WHERE sr.notification_id = :notificationId";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':notificationId', $notificationId, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result !== false && isset($result['image'])) {
        return $result;
    } else {
        return null;
    }
}


function approvalHelper(string $senderId, string $swapId, PDO $conn) {
    try {
        // Fetch swap information from the swaps table using the swap ID
        $fetchSwapInfoSql = "SELECT s.*, u.first_name, u.last_name, u.phone, u.email
                             FROM swaps s
                             JOIN users u ON s.user_id = u.id
                             WHERE s.id = :swapId";

        $stmtFetchSwapInfo = $conn->prepare($fetchSwapInfoSql);
        $stmtFetchSwapInfo->bindParam(':swapId', $swapId, PDO::PARAM_INT);
        $stmtFetchSwapInfo->execute();

        $swapInfo = $stmtFetchSwapInfo->fetch(PDO::FETCH_ASSOC);

        if ($swapInfo) {
            // Format the swap information as needed (e.g., add line breaks, create a message)
            $formattedMessage = "Swap ID: {$swapInfo['id']}\n";
            $formattedMessage .= "From: {$swapInfo['from_location']}\n";
            $formattedMessage .= "To: {$swapInfo['to_location']}\n";
            $formattedMessage .= "Name: {$swapInfo['first_name']} {$swapInfo['last_name']}\n";
            $formattedMessage .= "Phone: {$swapInfo['phone']}\n";
            $formattedMessage .= "Email: {$swapInfo['email']}\n";

            // Add more details as needed

            // Insert the formatted message into the notifications table
            $insertNotificationSql = "INSERT INTO notifications (user_id, title, message, notification_type, is_read)
                                      VALUES (:senderId, 'Approval', :message, 'nn', 0)";
            $stmtInsertNotification = $conn->prepare($insertNotificationSql);
            $stmtInsertNotification->bindParam(':senderId', $senderId, PDO::PARAM_STR);
            $stmtInsertNotification->bindParam(':message', $formattedMessage, PDO::PARAM_STR);

            if ($stmtInsertNotification->execute()) {
                // Notification successfully inserted
                return true;
            } else {
                // Failed to insert the notification
                return false;
            }
        } else {
            // Swap information not found
            return false;
        }
    } catch (\Throwable $e) {
        // Handle any exceptions or errors here
        return false;
    }
}


function reassignDeviceToken(PDO $conn, string $deviceToken, string $newUserId): bool {

    try {
        // Check if the device token exists
        $checkTokenSql = "SELECT user_id FROM device_tokens WHERE device_token = :deviceToken";
        $stmtCheckToken = $conn->prepare($checkTokenSql);
        $stmtCheckToken->bindParam(':deviceToken', $deviceToken, PDO::PARAM_STR);
        $stmtCheckToken->execute();

        $existingUserId = $stmtCheckToken->fetchColumn();

        if ($existingUserId) {
            if ($existingUserId !== $newUserId) {
                // Update the user ID associated with the device token
                $updateTokenSql = "UPDATE device_tokens SET user_id = :newUserId WHERE device_token = :deviceToken";
                $stmtUpdateToken = $conn->prepare($updateTokenSql);
                $stmtUpdateToken->bindParam(':deviceToken', $deviceToken, PDO::PARAM_STR);
                $stmtUpdateToken->bindParam(':newUserId', $newUserId, PDO::PARAM_STR);

                if ($stmtUpdateToken->execute()) {
                    // Token reassignment successful
                    return true;
                } else {
                    // Failed to update the token
                    return false;
                }
            } else {
                // Token is already associated with the correct user
                return true;
            }
        } else {
            // Token doesn't exist in the database, create a new entry
            $insertTokenSql = "INSERT INTO device_tokens (device_token, user_id) VALUES (:deviceToken, :newUserId)";
            $stmtInsertToken = $conn->prepare($insertTokenSql);
            $stmtInsertToken->bindParam(':deviceToken', $deviceToken, PDO::PARAM_STR);
            $stmtInsertToken->bindParam(':newUserId', $newUserId, PDO::PARAM_STR);

            if ($stmtInsertToken->execute()) {
                // New token entry created
                return true;
            } else {
                // Failed to create the new token entry
                return false;
            }
        }
    } catch (\Throwable $e) {
        // Handle any exceptions or errors here
        return false;
    }
}


function getUserDetails($user_id, $conn) {
    try {
        // Query to retrieve user details by user_id
        $query = "SELECT first_name, last_name, image FROM Users WHERE id = :user_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
        $stmt->execute();

        // Check if a user with the given user_id exists
        if ($stmt->rowCount() > 0) {
            $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            return $userDetails;
        } else {
            return false; // User not found
        }
    } catch (\Throwable $e) {
        // Handle any exceptions or errors here
        return false;
    }
}


function checkUserSubscription(PDO $conn, string $userId) {
    try {
        // Query to check if the user has an active subscription
        $currentDate = date("Y-m-d H:i:s"); // Get the current date and time
        $query = "SELECT * FROM subscriptions WHERE user_id = :userId AND end_date > :currentDate";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_STR);
        $stmt->bindParam(':currentDate', $currentDate, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    } catch (\Throwable $e) {
        // Handle any exceptions or errors here
        return false;
    }
}
