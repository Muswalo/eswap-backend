<?php
session_start();

require_once('../src/config/Dbh.config.php');

// Check the request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Sorry, you can`t access this resource like this.');
}

// Check if the user is logged in
if (!loggedIn()) {
    header('Location: login.php');
    die();
}

// Parse JSON data from the request body
$data = json_decode(file_get_contents('php://input'), true);

if ($data['type'] == 'user') {
    if (deleteUser($data['id'], $conn)) {
        echo json_encode(['status' => true]);
    } else {
        echo json_encode(['status' => false]);
    }
} elseif ($data['type'] == 'swap') {
    if (deleteSwap($data['id'], $conn)) {
        echo json_encode(['status' => true]);
    } else {
        echo json_encode(['status' => false]);
    }
}

function deleteUser($userId, $conn) {
    try {
        // Prepare the SQL statement
        $sql = "DELETE FROM users WHERE id = :userId";

        // Prepare the query
        $query = $conn->prepare($sql);

        // Bind the parameter
        $query->bindParam(":userId", $userId, PDO::PARAM_INT);

        // Execute the query
        if ($query->execute()) {
            return true; // User deleted successfully
        } else {
            return false; // Deletion failed
        }
    } catch (PDOException $e) {
        // Handle any exceptions here (log or provide feedback)
        echo $e;
        return false;
    }
}

function deleteSwap($swapId, $conn) {
    try {
        // Prepare the SQL statement
        $sql = "DELETE FROM swaps WHERE id = :swapId";

        // Prepare the query
        $query = $conn->prepare($sql);

        // Bind the parameter
        $query->bindParam(":swapId", $swapId, PDO::PARAM_INT);

        // Execute the query
        if ($query->execute()) {
            return true; // Swap deleted successfully
        } else {
            return false; // Deletion failed
        }
    } catch (PDOException $e) {
        // Handle any exceptions here (log or provide feedback)
        return false;
    }
}

function loggedIn() {
    if (session_status() === PHP_SESSION_ACTIVE && $_SESSION['loggedin']) {
        return true;
    }

    return false;
}
