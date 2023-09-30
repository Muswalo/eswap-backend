<?php

require_once "config/config.php";

class FCMNotificationSender {
    private $client;
    private $deviceTokens;
    private $title;
    private $message;

    public function __construct($jsonFilePath, $deviceTokens, $title, $message) {
        $this->client = new \Google_Client();
        $this->client->setAuthConfig($jsonFilePath);
        $this->client->addScope(\Google_Service_FirebaseCloudMessaging::CLOUD_PLATFORM);
        $this->deviceTokens = $deviceTokens;
        $this->title = $title;
        $this->message = $message;
    }

    private function getOATHToken() {
        try {
            if ($this->client->isAccessTokenExpired()) {
                $accessToken = $this->generateToken($this->client);
                $this->client->setAccessToken($accessToken);
            }

            $oauthToken = $this->client->getAccessToken()["access_token"];
            return $oauthToken;
        } catch (Google_Exception $e) {}
        return false;
    }

    private function readSavedToken() {
        $tk = @file_get_contents('token.cache');
        if ($tk) return json_decode($tk, true);
        else return false;
    }

    private function writeToken($tk) {
        file_put_contents("token.cache", $tk);
    }

    private function generateToken($client) {
        $client->fetchAccessTokenWithAssertion();
        $accessToken = $client->getAccessToken();
        $tokenJson = json_encode($accessToken);
        $this->writeToken($tokenJson);
        return $accessToken;
    }

    public function sendNotifications() {
        // Check if a session is already started
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Get the last sent index from the session
        $lastSentIndex = isset($_SESSION['last_sent_index']) ? $_SESSION['last_sent_index'] : 0;

        // Initialize an array to keep track of devices for which the notification wasn't sent
        $unsentDevices = [];

        try {

            for ($i = $lastSentIndex; $i < count($this->deviceTokens); $i++) {

                $deviceToken = $this->deviceTokens[$i];

                $notification = [
                    'title' => $this->title,
                    'body' => $this->message,
                ];

                $messageData = [
                    'message' => [
                        'token' => $deviceToken,
                        'notification' => $notification,
                    ],
                ];

                $headers = [
                    'Authorization: Bearer ' . $this->getOATHToken(),
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
                if ($result == false) {
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


    public static function getUserTokensFromDb(PDO $conn, string $email = ''){

        if (!empty($email)) {
            // If an email is provided, try to fetch the device token for the user with that email
            $SQL = "SELECT device_token FROM device_tokens
                    WHERE user_id = (SELECT id FROM users WHERE email = :email)";
            
            $stmt = $conn->prepare($SQL);
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_NUM);

        } else {
            // If no email is provided, fetch all device tokens
            $SQL = "SELECT device_token FROM device_tokens";
            
            $stmt = $conn->prepare($SQL);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_NUM);
        }

        // Extract the tokens from the result
        $tokens = [];
        foreach ($result as $row) {
            $tokens[] = $row[0];
        }

        return $tokens;
    }


}
?>
