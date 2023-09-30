<?php

require_once __DIR__.'/src/config/Dbh.config.php'; // this also connects config.php which has other configurations

use Php101\Php101\SecureVault;
use Php101\Php101\validate;
use Php101\Php101\Passwordhandler;
use Php101\Php101\ErrorLogger;
use Php101\Php101\UserInterface;

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'failed', 'error' => 'Invalid request method']);
        exit();
    }
    
    
    // Get the auth token from the requets header.
    $headers = capitalizeFirstLetterKeys(apache_request_headers());
    $token = $headers['Authorization'] ?? null;
    
    
    $secure = new SecureVault; // create an instance of a secure vault
    
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
        echo json_encode(array('status' => 'failed','error' => 'Failed to read request data'));
        exit();
    }
    
    // Convert the JSON data into an associative array
    $data = json_decode($Data, true);
    
    
    // Check if JSON decoding was successful
    if ($data === null) {
        http_response_code(400); // Bad Request
        echo json_encode(array('status' => 'failed', 'error' => 'Failed to read request data'));
        exit();
    }

    // validity check ###
    $inputData = [
        'name' => htmlspecialchars(stripslashes(trim($data['user_name']))),
        'email' => htmlspecialchars(stripslashes(trim($data['email']))),
        'phone' => htmlspecialchars(stripcslashes(trim($data['phone']))),
        'fname' => htmlspecialchars(stripslashes(trim($data['fname']))),
        'lname' => htmlspecialchars(stripslashes(trim($data['lname']))),
        'password'=> $data['password'],
        'role' => 'user'
    ];
    
    
    $validity = validate($inputData); // check validity

    if ($validity !== true) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'failed', 'errors' => $validity]);
        exit();
    }

    $passWord = new Passwordhandler($inputData['password']);

    $password = $passWord->hash_password(); // generate hashed password

    $userId = UserInterface::generateUUID(); //generate the user id
    
    $userId = UserInterface::createUser($conn,$userId, $inputData['name'], $inputData['email'], $inputData['phone'], $password, $inputData['fname'], $inputData['lname'], $inputData['role']);

    if ($userId === 'err1') {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'failed', 'error' => 'Username already taken']);
        exit();
    } elseif ($userId === 'err2') {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'failed', 'error' => 'Something went wrong']);
        exit();
    }

    // Generate a login JWT 
    $loginJWT = $secure->generateLoginJWT($userId, $_SERVER['SERVER_JWT_KEY']);

    // Return the login JWT in the response
    echo json_encode(['status' => 'success', 'message'=>'user created succesfully','JWT' => $loginJWT]);

    $conn = null; // close PDO connection

} catch (\Throwable $e) {

    // new ErrorLogger($e->getMessage(), time(), $conn); //log the error to DB
    http_response_code(500); //internal server error
    echo json_encode(array('status'=>'failed','error' => 'An error occurred while signing up.'));
    exit();

}


function validate($data) {

    $errArr = []; //this will hold errors
    
    $rules = [
        'name' => ['required','Alnum','min:3','max:50'],
        'email' => ['required', 'email'],
        'phone' => ['required','Alnum','min:9','max:12'],
        'fname' => ['required', 'alpha', 'min:3', 'max:50'],
        'lname' => ['required', 'alpha', 'min:3', 'max:50'],
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

    // password validation
    $passWord = new Passwordhandler($data['password']);
    $strenth = $passWord->validateStrength();
    $passwordErrLen = count($strenth);
    
    if ($passwordErrLen > 0) {

        for ($i=0; $i < $passwordErrLen; $i++) {
            array_push($errArr,$strenth[$i]);
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
