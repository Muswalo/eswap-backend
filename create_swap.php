<?php
require_once __DIR__ . '/src/config/Dbh.config.php'; // Include the database connection

use Php101\Php101\SecureVault;
use Php101\Php101\validate;

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
    $authToken = $headers['Authorization'] ?? null;

    $secure = new SecureVault; // Create an instance of a secure vault

    // Check if the JWT is valid
    if (!$secure->validateJWT($authToken, $_SERVER['SERVER_JWT_KEY'])) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'failed', 'error' => 'Invalid service token']);
        exit();
    } 
    // Get the Bearer token containing the user's ID
    $bearerToken = $headers['X-bearer-token'] ?? null;

    // Decode the Bearer token to obtain the user's ID
    $userId = $secure->decodeJWT($bearerToken, $_SERVER['SERVER_JWT_KEY']);

    // Check if the user's ID is valid
    if (!$userId) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'failed', 'error' => 'Invalid Bearer token']);
        exit();
    }

    $inputData = [
        'id' => 'ESWAP_SWAP_SWAP'.hash('md5', random_bytes(32)),
        'from_location' => htmlspecialchars(stripslashes(trim($_POST['from_location']))),
        'to_location' => htmlspecialchars(stripslashes(trim($_POST['to_location']))),
        'category' => htmlspecialchars(stripslashes(trim($_POST['category']))),
        'current_department' => htmlspecialchars(stripslashes(trim($_POST['current_department']))),
        'preferred_department' => htmlspecialchars(stripslashes(trim($_POST['preferred_department']))),
        'current_job_title' => htmlspecialchars(stripslashes(trim($_POST['current_job_title']))),
        'preferred_job_title' => htmlspecialchars(stripslashes(trim($_POST['preferred_job_title']))),
        'years_of_service' => intval($_POST['years_of_service']),
        'experience_or_skills' => htmlspecialchars(stripslashes(trim($_POST['experience_or_skills']))),
        'reason_for_swap' => htmlspecialchars(stripslashes(trim($_POST['reason_for_swap']))),
        'additional_notes' => htmlspecialchars(stripslashes(trim($_POST['additional_notes']))),
        'salary_scale' => htmlspecialchars(stripslashes(trim($_POST['salary_scale']))),
        'imageNames' => array()
    ];

    if (isset($_FILES['file_upload'])) {
            // Loop through each uploaded file
    foreach ($_FILES['file_upload']['tmp_name'] as $index => $tmpName) {
        // Create an array to simulate the structure of a single uploaded file
        $uploadedSingleFile = array(
            'name' => $_FILES['file_upload']['name'][$index],
            'type' => $_FILES['file_upload']['type'][$index],
            'tmp_name' => $tmpName,
            'error' => $_FILES['file_upload']['error'][$index],
            'size' => $_FILES['file_upload']['size'][$index]
        );

        // Call the uploadImage function for each image
        $uploadedImageResult = uploadImage($uploadedSingleFile, './'); 

        // Check if the upload was successful
        if (is_array($uploadedImageResult) && isset($uploadedImageResult['file_name'])) {
            // Collect the uploaded image name
            $inputData['imageNames'][] = $uploadedImageResult['file_name'];
        } else {
        }
    }

    }

    // validate the given data with rules
    $validity = validate($inputData);

    if ($validity !== true) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'failed', 'errors' => $validity]);
        exit();
    }

    if (!createSwapEntry($conn, $userId, $inputData)) {
        throw new Exception("failed to create swap");
    }

    // close DB connection
    $conn = null;

    echo json_encode(['status' => 'success', 'message' => 'Swap entry created successfully']);

} catch (\Throwable $e) {
    // new ErrorLogger($e->getMessage(), time(), $conn); //log the error to DB
    http_response_code(500); //internal server error
    echo json_encode(array('status'=>'failed','error' => 'An error occurred while creating swap.'));
    echo $e->getMessage();
    exit();

}

// Function to create a new swap entry in the swaps table
function createSwapEntry($conn, $userId, $data)
{
    // Check if the user has an active subscription
    $hasActiveSubscription = checkUserSubscription($conn, $userId);

    if (!$hasActiveSubscription) {
        http_response_code(402); // payment required
        echo json_encode(['status' => 'failed', 'error' => 'You need an active subscription to create a swap']);
        exit();
    }
    $img = json_encode($data['imageNames']);
    // Proceed with creating the swap entry
    $query = "INSERT INTO swaps (id, user_id, from_location, to_location, category, current_department, preferred_department, current_job_title, preferred_job_title, years_of_service, salary_scale, experience_or_skills, reason_for_swap, additional_notes, date_posted, `image`)
    VALUES (:id, :user_id, :from_location, :to_location, :category, :current_department, :preferred_department, :current_job_title, :preferred_job_title, :years_of_service,:salary_scale, :experience_or_skills, :reason_for_swap, :additional_notes, NOW(), :images)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $data['id'], PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_STR);
    $stmt->bindParam(':from_location', $data['from_location'], PDO::PARAM_STR);
    $stmt->bindParam(':to_location', $data['to_location'], PDO::PARAM_STR);
    $stmt->bindParam(':category', $data['category'], PDO::PARAM_STR);
    $stmt->bindParam(':current_department', $data['current_department'], PDO::PARAM_STR);
    $stmt->bindParam(':preferred_department', $data['preferred_department'], PDO::PARAM_STR);
    $stmt->bindParam(':current_job_title', $data['current_job_title'], PDO::PARAM_STR);
    $stmt->bindParam(':preferred_job_title', $data['preferred_job_title'], PDO::PARAM_STR);
    $stmt->bindParam(':years_of_service', $data['years_of_service'], PDO::PARAM_INT);
    $stmt->bindParam('salary_scale', $data['salary_scale'], PDO::PARAM_STR);
    $stmt->bindParam(':experience_or_skills', $data['experience_or_skills'], PDO::PARAM_STR);
    $stmt->bindParam(':reason_for_swap', $data['reason_for_swap'], PDO::PARAM_STR);
    $stmt->bindParam(':additional_notes', $data['additional_notes'], PDO::PARAM_STR);
    $stmt->bindParam(':images', $img, PDO::PARAM_STR);

    return $stmt->execute();

}



// Function to check if the user has an active subscription
function checkUserSubscription($conn, $userId)
{
    $query = "SELECT id FROM subscriptions WHERE user_id = :user_id AND status = 'active'";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->rowCount() > 0; // Return true if there's an active subscription, false otherwise
}



function validate($data) {

    $errArr = []; //this will hold errors
    
    $rules = [
        'from_location' => ['required', 'min:0', 'max:300'],
        'to_location' => ['required', 'min:0', 'max:100'],
        'category' => ['required', 'max:50'],
        'current_department' => ['max:300'],
        'preferred_department' => ['max:300'],
        'current_job_title' => ['max:300'],
        'preferred_job_title' => ['max:300'],
        'years_of_service' => ['min:1', 'max:300'],
        'experience_or_skills' => ['max:255'],
        'reason_for_swap' => ['max:3000'],
        'additional_notes' => ['max:3000'],
    ];
    
    
    $validator = new validate($data, $rules);
    $validator->validate(); // call validate to validate the values

    // Check for validation errors
    if ($validator->hasErrors()) {

        $errors = $validator->getErrors(); // get validation errors

        foreach ($errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                array_push($errArr,$error);
            }
        }
    }

    if (count($errArr) > 0) {
        return $errArr;
    }else{
        return true;
    }

}


function capitalizeFirstLetterKeys(array $array) {
    $result = array();
    foreach ($array as $key => $value) {
        $result[ucfirst($key)] = $value;
    }
    return $result;
}

function uploadImage($uploadedFile, $destinationPath) {
    // Validate MIME type
    $allowedMimeTypes = array("image/jpeg", "image/png", "image/gif");
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($fileInfo, $uploadedFile['tmp_name']);
    finfo_close($fileInfo);

    if (!in_array($mime, $allowedMimeTypes)) {
        return "Invalid MIME type.";
    }

    // File extension validation
    $allowedExtensions = array("jpg", "jpeg", "png", "gif");
    $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));

    if (!in_array($fileExtension, $allowedExtensions)) {
        return "Invalid file extension.";
    }

    // Recreate image using GD
    if ($mime === "image/jpeg") {
        $image = imagecreatefromjpeg($uploadedFile['tmp_name']);
    } elseif ($mime === "image/png") {
        $image = imagecreatefrompng($uploadedFile['tmp_name']);
    } elseif ($mime === "image/gif") {
        $image = imagecreatefromgif($uploadedFile['tmp_name']);
    } else {
        return "Unsupported image format.";
    }

    $file_size = $uploadedFile['size'];
    $max_size = 20 * 1024 * 1024; // 5 MB
    
    if ($file_size > $max_size) {
        return "file to large";
    }

    // Generate unique filename
    $uniqueFileName = uniqid('ESWAP_IMG_') . "_" . time() . "." . $fileExtension;

    // Save to destination
    $destinationFile = $destinationPath . 'storage/' . $uniqueFileName;
    $saveResult = imagejpeg($image, $destinationFile, 10); // Save as JPEG with 10% quality

    if (!$saveResult) {
        return "Failed to save image.";
    }

    // Close image resource
    imagedestroy($image);

    // Return image details
    return array(
        "file_name" => $uniqueFileName,
        "file_path" => $destinationFile
    );
}


function findMatch($conn, $toLocation) {

    try {
        // Query to find existing entries with the same "to_location" and swap_status not equal to 1
        $query = "SELECT * FROM swaps WHERE to_location = :toLocation AND swap_status != 1";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':toLocation', $toLocation, PDO::PARAM_STR);
        $stmt->execute();

        // Fetch all matching entries as an associative array
        $matchingEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $matchingEntries;
    } catch (\Throwable $e) {
        // Handle any exceptions or errors here
        return [];
    }

}
