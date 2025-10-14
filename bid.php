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

// Initialize variables
$error = '';
$success = '';
$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;

// Get current user ID
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $current_user = $stmt->fetch();
    $current_user_id = $current_user['id'] ?? 0;
} catch (PDOException $e) {
    $error = "Failed to fetch user data: " . $e->getMessage();
}

// Fetch post details
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.image, p.description, p.min_trade_value, p.timestamp, p.sold,
               u.username, u.profile_photo
        FROM posts p
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.id = ? AND p.status = 'trade'
    ");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();
    if (!$post) {
        $error = "Post not found or not available for bidding.";
    }
} catch (PDOException $e) {
    $error = "Failed to fetch post: " . $e->getMessage();
}

// Fetch the highest bid for this post
$highest_bid = 0;
if (!$error) {
    try {
        $stmt = $pdo->prepare("SELECT MAX(bid_amount) as highest_bid FROM bids WHERE post_id = ?");
        $stmt->execute([$post_id]);
        $result = $stmt->fetch();
        $highest_bid = $result['highest_bid'] ?? 0;
    } catch (PDOException $e) {
        $error = "Failed to fetch highest bid: " . $e->getMessage();
    }
}

// Handle bid submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_bid']) && !$error) {
    $bid_amount = floatval($_POST['bid_amount'] ?? 0);
    
    // Validate bid
    if ($post['sold']) {
        $error = "This post is already sold.";
    } elseif ($bid_amount < $post['min_trade_value']) {
        $error = "Bid amount must be at least $" . number_format($post['min_trade_value'], 2) . ".";
    } elseif ($bid_amount <= $highest_bid) {
        $error = "Bid amount must be higher than the current highest bid of $" . number_format($highest_bid, 2) . ".";
    } elseif ($post['username'] === $_SESSION['username']) {
        $error = "You cannot bid on your own post.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Insert bid
            $stmt = $pdo->prepare("INSERT INTO bids (post_id, user_id, bid_amount) VALUES (?, ?, ?)");
            $stmt->execute([$post_id, $current_user_id, $bid_amount]);

            // Insert notification for post owner
            $message = $_SESSION['username'] . " placed a bid of $" . number_format($bid_amount, 2) . " on your post: " . ($post['description'] ?: 'Post ID ' . $post['id']);
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, post_id, actor_id, message)
                SELECT p.user_id, 'bid', p.id, ?, ?
                FROM posts p WHERE p.id = ?
            ");
            $stmt->execute([$current_user_id, $message, $post_id]);

            $pdo->commit();
            $success = "Bid placed successfully!";
            header("Location: home.php");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Failed to place bid: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConnectSphere - Place Bid</title>
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

        .bid-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .post-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .post-header a {
            text-decoration: none;
            color: inherit;
        }

        .post-header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        .post-header span {
            font-weight: bold;
            font-size: 1em;
            color: #fff;
        }

        .post-image {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .post-details p {
            font-size: 0.9em;
            margin-bottom: 10px;
        }

        .post-details .description {
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 0.9em;
            margin-bottom: 8px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            font-size: 1em;
            transition: all 0.3s ease;
            box-shadow: inset 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .form-group input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.02);
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }

        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
            color: #fff;
            font-size: 1.1em;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        button:hover {
            background: linear-gradient(45deg, #4ecdc4, #ff6b6b);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        button:active {
            transform: translateY(0);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
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

        .success {
            color: #4ecdc4;
            background: rgba(0, 0, 0, 0.3);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9em;
            text-align: center;
        }

        @media (max-width: 600px) {
            .container {
                padding: 10px;
            }

            .bid-container {
                padding: 20px;
            }

            .post-header img {
                width: 30px;
                height: 30px;
            }

            .post-header span {
                font-size: 0.9em;
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
        <div class="bid-container">
            <h2>Place Your Bid</h2>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php elseif ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($post && !$error): ?>
                <div class="post-header">
                    <a href="<?php echo ($post['username'] === $_SESSION['username']) ? 'profile.php' : 'view_profile.php?username=' . urlencode($post['username']); ?>">
                        <img src="<?php echo htmlspecialchars($post['profile_photo'] ?? 'https://via.placeholder.com/40'); ?>" alt="User">
                    </a>
                    <a href="<?php echo ($post['username'] === $_SESSION['username']) ? 'profile.php' : 'view_profile.php?username=' . urlencode($post['username']); ?>">
                        <span><?php echo htmlspecialchars($post['username']); ?></span>
                    </a>
                </div>
                <img src="<?php echo htmlspecialchars($post['image']); ?>" alt="Art Post" class="post-image">
                <div class="post-details">
                    <?php if ($post['description']): ?>
                        <p class="description"><?php echo htmlspecialchars($post['description']); ?></p>
                    <?php endif; ?>
                    <p><strong>Minimum Trade Value:</strong> $<?php echo number_format($post['min_trade_value'], 2); ?></p>
                    <p><strong>Current Highest Bid:</strong> $<?php echo number_format($highest_bid, 2); ?></p>
                    <p><strong>Posted on:</strong> <?php echo htmlspecialchars($post['timestamp']); ?></p>
                    <?php if ($post['sold']): ?>
                        <p><strong>Status:</strong> Sold</p>
                    <?php endif; ?>
                </div>
                <?php if (!$post['sold'] && $post['username'] !== $_SESSION['username']): ?>
                    <form method="POST" action="">
                        <input type="hidden" name="submit_bid" value="1">
                        <div class="form-group">
                            <label for="bid_amount">Your Bid ($)</label>
                            <input type="number" id="bid_amount" name="bid_amount" step="0.01" min="<?php echo max($post['min_trade_value'], $highest_bid + 0.01); ?>" placeholder="Enter your bid amount" required>
                        </div>
                        <button type="submit">Submit Bid</button>
                    </form>
                <?php else: ?>
                    <p>You cannot bid on this post.</p>
                <?php endif; ?>
            <?php else: ?>
                <p>Unable to display post details.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>