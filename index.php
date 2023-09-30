<!-- <!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>SSE Example</title>
</head>
<body>

  <script>
    const authToken = "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpZCI6ImVzd2FwXzJmYWFlNjI4YzhiYTViNWEwMzBkN2EwNWYxNmUxMjhlXzE2OTExMzQ4NTc5MjEzIiwiZXhwIjoxNjk0MzM3NzgwfQ.8HXhTuWEa4umnrmhIfpXC_EwmotOr2IYHGmRuqjggWg"; // Replace with your actual auth token

    // Create the EventSource with custom headers
    const eventSource = new EventSource('sse-test.php', {
      headers: {
        'X-bearer-token': authToken, // Include the bearer token here
      }
    });

    eventSource.onmessage = (event) => {
      // Handle incoming SSE messages
      const rawTextData = event.data;

      // Log the raw text data to the console
      console.log('Received raw SSE data:', rawTextData);

    };

    eventSource.onerror = (error) => {
      // Log the error message to the console
    //   console.error('SSE error message:', error.message);

    };
  </script>
</body>
</html> -->


<?php

// // Include the FCMNotificationSender class
require_once "./src/FCMNotificationSender .php";
require_once "./src/config/Dbh.config.php";


// // Define the path to your JSON file containing authentication data
$jsonFilePath = "src/config/eswap-f5090-4152f379e7ae.json";
$deviceTokens = FCMNotificationSender::getUserTokensFromDb($conn);
print_r($deviceTokens);
// // Define an array of device tokens to send notifications to
// $deviceTokens = [
// // 'dTd9CeTSQa-oTzU15_pN-L:APA91bGEWPnsLuSh-VRAJU4P32m2dRtJqh0ERAQ6udjW_4h-VW6xSw7RsAERxOuP3TJLHlbc4irKU0LTp3r7TisUqlcUcGdKBcqAetFg2i61wbTQk4rol5fPkUX6VzFPJaYOe7N9ET2C'
//   'dTd9CeTSQa-oTzU15_pN-L:APA91bGEWPnsLuSh-VRAJU4P32m2dRtJqh0ERAQ6udjW_4h-VW6xSw7RsAERxOuP3TJLHlbc4irKU0LTp3r7TisUqlcUcGdKBcqAetFg2i61wbTQk4rol5fPkUX6VzFPJaYOe7N9ET2C'
// ];
// $deviceTokens = $token;

// // Define the title and message for the notification
 $title = 'Great work emmauel';
 $message = 'This is a test message by emmanuel muswalo from http server';

// // Create an instance of the FCMNotificationSender class
$sender = new FCMNotificationSender($jsonFilePath, $deviceTokens, $title, $message);

// // Send notifications and get a list of unsent devices (if any)
 $unsentDevices = $sender->sendNotifications();

if (!empty($unsentDevices)) {
    echo "Notifications failed to send to the following devices:\n";
    foreach ($unsentDevices as $device) {
        echo "$device\n";
    }
  } else {
       echo "All notifications sent successfully!\n";
     }

?>


<!-- <!DOCTYPE html>
<html>
<head>
  <title></title>
</head>
<body>
  <script>
    // The URL you want to fetch
    const url = 'http://eswap-app.rf.gd/';

    // Use fetch() to get data from the URL
    fetch(url)
      .then(response => {
        // Check if the request was successful
        if(response.ok) {
          return response.text();
        } else {
          throw new Error('Network response was not ok');
        }
      })
      .then(data => {
        // Here's your data
        console.log(data);
      })
      .catch(error => {
        // If there's an error, log it
        console.error('There has been a problem with your fetch operation:', error);
      });
  </script>
</body>
</html> -->
