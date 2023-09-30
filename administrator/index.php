<?php

// // Function to generate HMAC for a given data payload
// function generateHmac(array $data, $serverKey)
// {
//     return hash_hmac('sha256', json_encode($data), $serverKey);
// }

// // Function to verify HMAC for a given URL
// function verifyHmac($url, $serverKey)
// {
//     // Parse the URL to extract the HMAC and data
//     $urlParts = parse_url($url);
//     parse_str($urlParts['query'], $query);

//     $providedHmac = $query['hmac'];
//     $data = json_decode(base64_decode($query['data']), true);

//     // Calculate the HMAC based on the provided data and the server key
//     $calculatedHmac = generateHmac($data, $serverKey);

//     // Compare the provided HMAC with the calculated HMAC
//     if ($providedHmac === $calculatedHmac) {
//         // Check if the link has expired (e.g., within a certain time limit)
//         $timestamp = $data['timestamp'];
//         $expirationTime = $timestamp + 3600; // Link expires in 1 hour (adjust as needed)
//         if (time() <= $expirationTime) {
//             // The HMAC is valid, and the link is not expired
//             return true;
//         }
//     }

//     // The HMAC is not valid or the link has expired
//     return false;
// }
?>











<?php

// require_once "../src/config/Dbh.config.php";
// use Php101\Php101\MessageInterface;

// session_start();

// $serverKey = $_SERVER['SERVER_JWT_KEY']; // Replace with your actual server's secret key
// $yourEmail = "emuswalo7@gmail.com"; // Replace with your email

// function generateHmac(array $data, string $serverKey): string {
//     return hash_hmac('sha256', json_encode($data), $serverKey);
// }

// function verifyHmac(string $data, string $hmac, string $serverKey): bool {
//     return hash_equals(generateHmac(json_decode($data, true), $serverKey), $hmac);
// }

// // Check if a session is already started
// if (isset($_SESSION['loggedIn']) && $_SESSION['loggedIn'] === true) {
//     // Session is started, redirect to the home page
//     header("Location: home.php");
//     exit();
// }

// // Check if the data attribute is present in the URL
// if (isset($_GET['data']) && isset($_GET['hmac'])) {
//     // Verify the HMAC for the secure link
// 	$verified = verifyHmac(base64_decode($_GET['data']), $_GET['hmac'], $serverKey);

//     if ($verified) {

// 		if (session_status() !== PHP_SESSION_ACTIVE) {
// 		    session_start();
// 		    $_SESSION['loggedIn'] = true;
// 		}else{
// 		    $_SESSION['loggedIn'] = true;
// 		}


//         // Redirect to the home page
//         header("Location: home.php");
//         exit();
//     }
// }

// // Session is not started, send a secure link to your email
// $data = [
//     'timestamp' => time(),
// ];

// $hmac = generateHmac($data, $serverKey);

// $encodedData = base64_encode(json_encode($data));
// $secureLink = "http://localhost/administrator/?data={$encodedData}&hmac={$hmac}";

// // Send the secure link to your email
//     $content = [
//         'sender_email' => 'emuswalo7@gmail.com',
//         'sender_name' => 'Emmanuel Muswalo',
//         'recipient_name' => 'Emmanuel Muswalo',
//         'subject' => 'Password Reset Code',
//         'msg' => $secureLink,
//         'ishtml' => false,
//         'altmsg' => '',
//         'attachment' => false,
//     ];

//     // Send the reset code email using MessageInterface class
//     $message = new MessageInterface('mail', 'emuswalo7@gmail.com', $content);
//     $message->createMsg();
// // Display a message to the user
// $res = "Email sent with a secure link.";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Link</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        h1 {
            font-weight: bold;
            color: royalblue;
            text-align: center;
        }
    </style>
</head>
<body>
	<h1 style="text-decoration: underline;">Make sure you open the link on the same browser and same device!</h1>
    <h1><?php echo $res; ?></h1>
</body>
</html>

