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
$post = null;
$user = null;
$post_type = isset($_GET['type']) ? trim($_GET['type']) : '';
$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($post_type) || !in_array($post_type, ['art', 'video']) || $post_id <= 0) {
    $error = "Invalid post type or ID.";
} else {
    try {
        if ($post_type === 'art') {
            // Fetch art post
            $stmt = $pdo->prepare("
                SELECT p.id, p.image, p.description, p.status, p.price, p.min_trade_value, p.timestamp, p.user_id,
                       u.username, u.profile_photo
                FROM posts p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE p.id = ?
            ");
            $stmt->execute([$post_id]);
            $post = $stmt->fetch();
            if (!$post) {
                $error = "Art post not found.";
            }
        } else {
            // Fetch video post
            $stmt = $pdo->prepare("
                SELECT v.id, v.video, v.title, v.description, v.timestamp, v.user_id,
                       u.username, u.profile_photo
                FROM videos v
                LEFT JOIN users u ON v.user_id = u.id
                WHERE v.id = ?
            ");
            $stmt->execute([$post_id]);
            $post = $stmt->fetch();
            if (!$post) {
                $error = "Video post not found.";
            }
        }
        if ($post) {
            $user = ['username' => $post['username'], 'profile_photo' => $post['profile_photo']];
        }
    } catch (PDOException $e) {
        $error = "Failed to fetch post: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConnectSphere - View Post</title>
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

        .post-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .post-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
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
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        .post-image-container {
            margin-bottom: 15px;
        }

        .post-image {
            max-width: 100%;
            border-radius: 8px;
            display: block;
        }

        .post-video {
            max-width: 100%;
            border-radius: 8px;
        }

        .post-title {
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .post-caption {
            font-size: 1em;
            margin-bottom: 10px;
            color: rgba(255, 255, 255, 0.9);
        }

        .post-status {
            font-size: 0.9em;
            margin-bottom: 10px;
            color: rgba(255, 255, 255, 0.8);
        }

        .post-timestamp {
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

        @media (max-width: 600px) {
            .container {
                padding: 10px;
            }

            .post-container {
                padding: 15px;
            }

            .navbar {
                flex-direction: column;
                gap: 10px;
            }

            .navbar .nav-links a {
                margin-left: 10px;
            }

            .post-header img {
                width: 30px;
                height: 30px;
                margin-right: 8px;
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
                <a href="profile.php">Profile</a>
                <a href="learn.php">Learn</a>
                <a href="index.php">Logout</a>
            </div>
        </div>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($post && $user): ?>
            <div class="post-container">
                <div class="post-header">
                    <a href="view_profile.php?username=<?php echo urlencode($user['username']); ?>">
                        <img src="<?php echo htmlspecialchars($user['profile_photo'] ?? 'https://via.placeholder.com/40'); ?>" alt="User">
                    </a>
                    <a href="view_profile.php?username=<?php echo urlencode($user['username']); ?>">
                        <span><?php echo htmlspecialchars($user['username']); ?></span>
                    </a>
                </div>
                <?php if ($post_type === 'art'): ?>
                    <div class="post-image-container">
                        <img src="<?php echo htmlspecialchars($post['image']); ?>" alt="Art Post" class="post-image">
                    </div>
                    <?php if ($post['description']): ?>
                        <div class="post-caption"><?php echo htmlspecialchars($post['description']); ?></div>
                    <?php endif; ?>
                    <?php if ($post['status'] !== 'share'): ?>
                        <div class="post-status">
                            <?php
                            if ($post['status'] == 'sell') {
                                echo 'For Sale: $' . number_format($post['price'], 2);
                            } elseif ($post['status'] == 'trade') {
                                echo 'For Trade: Minimum Value $' . number_format($post['min_trade_value'], 2);
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="post-image-container">
                        <video controls class="post-video">
                            <source src="<?php echo htmlspecialchars($post['video']); ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                    <div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div>
                    <?php if ($post['description']): ?>
                        <div class="post-caption"><?php echo htmlspecialchars($post['description']); ?></div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="post-timestamp"><?php echo htmlspecialchars($post['timestamp']); ?></div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>