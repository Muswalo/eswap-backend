<?php
session_start();

if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== true) {
    session_destroy();
    header('Location: index.php');
    exit();
}



require_once('../src/config/Dbh.config.php');
require('templates/table.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>E-swap Admin dashboard</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/index.css">
    <script src="https://kit.fontawesome.com/21ede28d79.js" crossorigin="anonymous"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/table.css">
    <script src="js/index.js" defer></script>
    <script src="js/modal.js" defer></script>
    <style>

        .control-panel {
            display: flex;
            justify-content: space-around;
            padding: 20px;
        }

        .control-card {
            background-color: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            padding: 20px;
            width: 300px;
            margin-left: 20px;
        }

        h2 {
            margin-top: 0;
        }

        /* Form styles */
        form {
            display: flex;
            flex-direction: column;
        }

        input[type="text"],
        select,
        textarea {
            margin: 8px 0;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            background-color: #007bff;
            color: #fff;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #0056b3;
        }

        /* Shutdown button style */
        #shutdownServer {
            background-color: #dc3545;
        }

        #shutdownServer:hover {
            background-color: #c82333;
        }

        .modal {
        display: none;
        position: fixed;
        z-index: 1;
        padding-top: 100px;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgb(0, 0, 0);
        background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
        background-color: #fefefe;
        margin: auto;
        padding: 20px;
        border: 1px solid #888;
        width: 80%;
        }

        .close {
        color: #aaaaaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        }

        .close:hover,
        .close:focus {
        color: #000;
        text-decoration: none;
        cursor: pointer;
        }

        /* Responsive design */
        @media screen and (max-width: 768px) {
            .control-panel {
                flex-direction: column;
                align-items: center;
            }

            .control-card {
                margin-bottom: 20px;
            }
        }
    </style>
</head>

<body>
    <nav>
        <div class="side-nav-container">
            <ul class="side-nav-list">
                <li><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></li>
                <li><i class="fas fa-bell"></i> <span>Notifications</span></li>
                <li><i class="fas fa-users"></i> <span>Users</span></li>
                <li><i class="fas fa-shopping-cart"></i> <span>Subscriptions</span></li>
                <li><i class="fas fa-exchange-alt"></i> <span>Swaps</span></li>
                <li><i class="fas fa-cog"></i> <span>Settings</span></li>
                <li><i class="fas fa-user-circle"></i> <span>Profile</span></li>
                <li><i class="fas fa-sign-out-alt"></i> <span>Log out</span></li>
            </ul>

            <div class="user-info">
                <div class="user-info-image">
                    <img src="http://localhost/IMG_20230826_175743.png" alt="">
                </div>

                <span class="user-info-details">
                    <span class="user-info-name det">Evans Mulenga</span>
                    <span class="user-info-status det">Admin</span>
                </span>
            </div>

        </div>

    </nav>

    <main class="page-content">
        <span class="hamberger">
            <i class="fas fa-bars"></i>
        </span>
        <div id="myModal" class="modal">

        <div class="modal-content">
            <span class="close" id="closeBtn">&times;</span>
            <h3>User Information</h3>
            <p>Name: <span id="userName"></span></p>
            <p>Email: <span id="userEmail"></span></p>
            <p>Phone: <span id="userPhone"></span></p>
        </div>

        </div>

        <div class="control-panel">
            <div class="control-card">
                <h2>Add Participant</h2>
                <!-- Form to add a participant -->
                <form id="addParticipantForm">
                    <input type="text" placeholder="Participant First Name" required>
                    <input type="text" placeholder="Participant Last Name" required>
                    <input type="text" placeholder="Participant User Name" required>
                    <input type="text" placeholder="Participant Email" required>
                    <input type="text" placeholder="Participant Phone" required>
                    <input type="text" placeholder="Participant Password" required>

                    <select id="subscriptionType" required>
                        <option value="">subcription</option>
                        <option value="premium">Yes</option>
                        <option value="premium">No</option>

                    </select>
                    <button type="submit">Add Participant</button>
                </form>
            </div>
            <div class="control-card">
                <h2>Send Notification</h2>
                <!-- Form to send notifications -->
                <form id="sendNotificationForm">
                    <input type="text" placeholder="email (Leave empty for all)" name="email">
                    <textarea placeholder="Notification Message" required name="msg"></textarea>
                    <button type="submit" id="sendNot">Send Notification</button>
                    <p id="statusIndicator"></p>
                </form>
            </div>
            <div class="control-card">
                <h2>Server Controls</h2>
                <button id="shutdownServer">Shutdown Server</button>
                <br>
                <p style="color: red;">Please be advised that shutting down the server will prevent it from being restarted remotely. Proceed with caution as this action may cause errors in internal operations. In case of any issues, please contact the super user for assistance. This button should only be used in the event of a DDOS attack.
                </p>
            </div>
        </div>

        <div class="data-cont">
            <?php
            $interanl_data = fetchInternalMetrics($conn);
            $tableHeading = array('ID', 'Metric', 'Value', 'Date');
            $tableData = array(
                array('Total User (inDB)',  $interanl_data['total_user'], date('d/m/Y')),
                array('Paid Users', $interanl_data['total_paid_users'], date('d/m/Y')),
                array('Income',  $interanl_data['income'] , date('d/m/Y')),
                array('Approved Swaps', $interanl_data['approved_swaps'] , date('d/m/Y')),
                array('Rejected Swaps', $interanl_data['rejected_swaps'], date('d/m/Y')),
                array('Total swaps (inDB)', $interanl_data['total_swaps'] , date('d/m/Y')),
            );
            $tableName = 'E-swap internal metrics';

            generateDynamicTable($tableHeading, $tableData, $tableName);

            ?>


            <div class="user-management">
                <h2>User Mangement</h2>
                <div class="search-container">
                    <input type="text" class="search-input" placeholder="Search for a user...">
                    <button class="search-button">Search</button>
                </div>
                <?php
                $user = fetchUserManagementData($conn);
                $tableHeading = array(
                    'ID',
                    'First Name',
                    'Last Name',
                    'Date Joined',
                    'Payment Status',
                    'Swaps Posted',
                    'Last Logged In',
                    'View Profile',
                    'Delete User',

                );

                $tableData = array();

                foreach($user as $row){

                    $status = isset($row['subscription_id']) ? 'paid' : 'unpaid';
                    $tableData[] = [
                        $row['first_name'],
                        $row['last_name'],
                        $row['created_at'],
                        $status,
                        $row['total_swaps'],
                        '2023-09-15',
                        '<button class="red-button" onclick="openModal(\'' . $row['first_name'] . ' ' . $row['last_name'] . '\', \'' . $row['email'] . '\', \'' . $row['phone'] . '\')">Profile</button>',
                        '<button class="red-button" onclick="deleteUser(\'' . $row['user_id'] . '\', \'user\', \'' . $row['first_name'] . ' ' . $row['last_name'] . '\')">Delete</button>'
                    ];
                                                         }

                $tableName = '';

                // Call the function to generate the dynamic table
                generateDynamicTable($tableHeading, $tableData, $tableName);


                ?>
            </div>


            <div class="swpa-management">
                <h2>Swaps management</h2>
                <div class="search-container">
                    <input type="text" class="search-input" placeholder="Search for a swap...">
                    <button class="search-button">Search</button>
                </div>

                <?php
                $swapData = fetchSwapManagementData($conn);
                // Example data for swaps
                $tableHeading = array(
                    'ID',
                    'Name',
                    'Current Location',
                    'Preferred Location',
                    'Ministry',
                    'Date Posted',
                    'Status',
                    'Delete'
                );

                $tableData = array();

                foreach ($swapData as $row) {
                    if ($row['status'] == true) {
                        $status = 'approved';
                    }else{
                        $status = 'pending';
                    }
                    $tableData[] = [$row['first_name'], $row['from_location'], $row['to_location'], $row['category'], $row['date_posted'], $status, '<button class="red-button" onclick="alert(\'Bad operation\')">Delete</button>' ];
                }

                $tableName = '';

                // Call the function to generate the dynamic table
                generateDynamicTable($tableHeading, $tableData, $tableName);

                ?>
            </div>


            <div>
                <h2>Subsription mangement</h2>

                <div class="search-container">
                    <input type="text" class="search-input" placeholder="Search for a subcription...">
                    <button class="search-button">Search</button>
                </div>

                <?php
                // Example data for subscriptions
                $tableHeading = array(
                    'ID',
                    'User Name',
                    'Price (ZMW)',
                    'Duration',
                    'Status',
                    'View',
                    'Delete'
                );

                $tableData = array(
                    array('John Smith', '100', '1 month', 'Active', '<button class="red-button">View</button>', '<button class="red-button">Suspend</button>'),
                    array('Jane Doe', '200', '3 months', 'Active', '<button class="red-button">View</button>', '<button class="red-button">Suspend</button>'),
                    array('Alice Johnson', '150', '2 months', 'Suspended', '<button class="red-button">View</button>', '<button class="red-button">Suspend</button>'),
                    array('Michael Brown', '120', '1 month', 'Active', '<button class="red-button">View</button>', '<button class="red-button">Suspend</button>'),
                    array('Emily Davis', '250', '6 months', 'Active', '<button class="red-button">View</button>', '<button class="red-button">Suspend</button>'),
                    array('David Wilson', '180', '2 months', 'Suspended', '<button class="red-button">View</button>', '<button class="red-button">Suspend</button>'),
                    array('Olivia Lee', '300', '12 months', 'Active', '<button class="red-button">View</button>', '<button class="red-button">Suspend</button>'),
                    array('Liam Johnson', '220', '3 months', 'Suspended', '<button class="red-button">View</button>', '<button class="red-button">Suspend</button>'),
                    array('Sophia Wilson', '150', '1 month', 'Active', '<button class="red-button">View</button>', '<button class="red-button">Suspend</button>'),
                    array('Aiden Smith', '190', '4 months', 'Active', '<button class="red-button">View</button>', '<button class="red-button">Suspend</button>'),
                );

                $tableName = '';

                // Call the function to generate the dynamic table
                generateDynamicTable($tableHeading, $tableData, $tableName);

                ?>
            </div>
        </div>
    </main>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>

</html>

<?php

function fetchUserManagementData($conn)
{
    $SQL = "SELECT
    u.id AS user_id,
    u.first_name,
    u.email,
    u.phone,
    u.last_name,
    u.created_at,
    sub.id AS subscription_id,
    COUNT(sr.id) AS total_swaps
    FROM
        users AS u
    LEFT JOIN
        subscriptions AS sub
    ON
        u.id = sub.user_id
    LEFT JOIN
        swaps AS sr
    ON
        u.id = sr.user_id
    GROUP BY
    u.id, u.first_name, u.last_name, u.created_at, sub.id";

    $stmt = $conn->prepare($SQL);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function fetchSwapManagementData($conn)
{
    $SQL = "SELECT s.id, u.first_name, s.from_location, s.to_location, s.category, s.date_posted, sr.status
    FROM swaps AS s
    INNER JOIN users AS u
    ON s.user_id = u.id
    LEFT JOIN swap_requests AS sr
    ON s.id = sr.swap_id";

    $stmt = $conn->prepare($SQL);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function fetchInternalMetrics($conn) {
    $SQL = "SELECT COUNT(u.id) AS 'total_user', COUNT(sub.id) AS 'total_paid_users', SUM(sub.amount) AS 'income', 
    (SELECT COUNT(id) FROM swaps) AS 'total_swaps',
    (SELECT COUNT(id) FROM swap_requests WHERE swap_requests.status = 'accepted') AS 'approved_swaps',
    (SELECT COUNT(id) FROM swap_requests WHERE swap_requests.status = 'rejected') AS 'rejected_swaps'
    FROM users AS u
    LEFT JOIN subscriptions AS sub
    ON u.id = sub.user_id";

    $stmt = $conn->prepare($SQL);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}