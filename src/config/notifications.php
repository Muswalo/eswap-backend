<?php


function getOATHToken() {
    $client = new Google_Client();
    try {
        $client->setAuthConfig("src/config/eswap-f5090-4152f379e7ae.json");
        $client->addScope(Google_Service_FirebaseCloudMessaging::CLOUD_PLATFORM);
        $savedTokenJson = readSavedToken();
        if ($savedTokenJson) {
            $client->setAccessToken($savedTokenJson);
            $accessToken = $savedTokenJson;
            if ($client->isAccessTokenExpired()) {
                $accessToken = generateToken($client);
                $client->setAccessToken($accessToken);
            }
        } else {
            $accessToken = generateToken($client);
            $client->setAccessToken($accessToken);
        }
        $oauthToken = $accessToken["access_token"];
        return $oauthToken;
    } catch (Google_Exception $e) {}
    return false;
}

function readSavedToken() {
    $tk = @file_get_contents('token.cache');
    if ($tk) return json_decode($tk, true);
    else return false;
}

function writeToken($tk) {
    file_put_contents("token.cache", $tk);
}

function generateToken($client) {
    $client->fetchAccessTokenWithAssertion();
    $accessToken = $client->getAccessToken();
    $tokenJson = json_encode($accessToken);
    writeToken($tokenJson);
    return $accessToken;
}



function sendFCMNotification($serverKey, $deviceTokens, $title, $message) {
    
    // Get the last sent index from the session
    $lastSentIndex = isset($_SESSION['last_sent_index']) ? $_SESSION['last_sent_index'] : 0;

    // Initialize an array to keep track of devices for which the notification wasn't sent
    $unsentDevices = [];

    try {
        for ($i = $lastSentIndex; $i < count($deviceTokens); $i++) {
            $deviceToken = $deviceTokens[$i];
            $notification = [
                'title' => $title,
                'body' => $message,
            ];

            $messageData = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => $notification,
                ],
            ];

            $headers = [
                'Authorization: Bearer ' . $serverKey,
                'Content-Type: application/json',
            ];

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/v1/projects/eswap-f5090/messages:send');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messageData));

            $result = curl_exec($ch);

            curl_close($ch);

            // Check if the request was successful
            if ($result !== false) {
                // Uncomment the line below if you want to see a success message for each device
                // echo "Notification sent successfully to device: $deviceToken\n";
            } else {
                // Add the device token to the list of unsent devices
                $unsentDevices[] = $deviceToken;
            }

            // Update the last sent index in the session
            $_SESSION['last_sent_index'] = $i;
        }
    } catch (Exception $e) {
        echo "An error occurred: " . $e->getMessage() . "\n";
    }

    // Return the list of unsent devices
    return $unsentDevices;
}

// // Your Firebase Cloud Messaging Server API key
// $serverKey = getOATHToken();

// // Array of device tokens
// $deviceTokens = [
//     // Add your device tokens here
// ];

// // Notification title and message
// $title = 'Great work emmauel';
// $message = 'This is a test message by emmanuel muswalo from http server';

// // Call the function to send notifications and get the list of unsent devices
// $unsentDevices = sendFCMNotification($serverKey, $deviceTokens, $title, $message);

// // Output the list of unsent devices
// if (!empty($unsentDevices)) {
//     echo "Notifications failed to send to the following devices:\n";
//     foreach ($unsentDevices as $device) {
//         echo "$device\n";
//     }
// }

