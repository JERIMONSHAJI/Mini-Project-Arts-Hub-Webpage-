<?php
// Start session
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Include database connection
require 'db_connect.php';

// Get current user ID
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $current_user = $stmt->fetch();
    $current_user_id = $current_user['id'] ?? 0;
} catch (PDOException $e) {
    $error = "Failed to fetch user data: " . $e->getMessage();
}

// Fetch notifications from notifications table
$notifications = [];
try {
    $stmt = $pdo->prepare("
        SELECT n.id, n.type, n.message, n.created_at, n.post_id, u.username
        FROM notifications n
        JOIN users u ON n.actor_id = u.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
    ");
    $stmt->execute([$current_user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Failed to load notifications: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConnectSphere - Notifications</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1, #96c93d);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            color: #fff;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Navigation Bar */
        .navbar {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar .logo {
            font-size: 1.5em;
            font-weight: bold;
        }

        .navbar .nav-links a {
            color: #fff;
            text-decoration: none;
            margin-left: 20px;
            font-size: 0.9em;
            transition: color 0.3s ease;
        }

        .navbar .nav-links a:hover {
            color: #4ecdc4;
        }

        /* Notifications List */
        .notifications {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .notification {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .notification-message {
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .notification-message a {
            color: #4ecdc4;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .notification-message a:hover {
            color: #fff;
            text-decoration: underline;
        }

        .notification-timestamp {
            font-size: 0.8em;
            color: rgba(255, 255, 255, 0.7);
        }

        .error {
            color: #ff4d4d;
            background: rgba(0, 0, 0, 0.3);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9em;
            text-align: center;
        }

        /* Submit Address Button */
        .submit-address-button {
            display: inline-block;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
            color: #fff;
            font-size: 0.9em;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            margin-top: 10px;
        }

        .submit-address-button:hover {
            background: linear-gradient(45deg, #4ecdc4, #ff6b6b);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .submit-address-button:active {
            transform: translateY(0);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        /* Submitted Text */
        .submitted-text {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            font-size: 0.9em;
            margin-top: 10px;
        }

        /* Responsive Design */
        @media (max-width: 600px) {
            .container {
                padding: 10px;
            }

            .navbar {
                flex-direction: column;
                gap: 10px;
            }

            .navbar .nav-links a {
                margin-left: 10px;
            }

            .notification {
                padding: 10px;
            }

            .notification-message {
                font-size: 0.8em;
            }

            .notification-timestamp {
                font-size: 0.7em;
            }

            .submit-address-button {
                font-size: 0.8em;
                padding: 6px 12px;
            }

            .submitted-text {
                font-size: 0.8em;
                padding: 6px 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="navbar">
            <div class="logo">ConnectSphere</div>
            <div class="nav-links">
                <a href="home.php">Home</a>
                <a href="notifications.php">Notifications</a>
                <a href="profile.php">Profile</a>
                <a href="learn.php">Learn</a>
                <a href="index.php">Logout</a>
            </div>
        </div>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="notifications">
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification">
                        <div class="notification-message">
                            <a href="<?php echo ($notification['username'] === $_SESSION['username']) ? 'profile.php' : 'view_profile.php?username=' . urlencode($notification['username']); ?>">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </a>
                        </div>
                        <div class="notification-timestamp"><?php echo htmlspecialchars($notification['created_at']); ?></div>
                        <?php if ($notification['type'] === 'bid_won' && $notification['post_id']): ?>
                            <?php
                            // Check if address is already submitted
                            $stmt = $pdo->prepare("SELECT id FROM bid_addresses WHERE post_id = ? AND user_id = ?");
                            $stmt->execute([$notification['post_id'], $current_user_id]);
                            $address_submitted = $stmt->fetch();
                            ?>
                            <?php if ($address_submitted): ?>
                                <span class="submitted-text">Submitted</span>
                            <?php else: ?>
                                <a href="submit_address.php?post_id=<?php echo htmlspecialchars($notification['post_id']); ?>" class="submit-address-button">Submit Address</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No notifications available.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>