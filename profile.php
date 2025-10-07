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
$username = $_SESSION['username'];
$error = '';
$success = '';

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT id, username, bio, profile_photo FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user) {
        $error = "User not found.";
        $user = ['id' => 0, 'username' => $username, 'bio' => '', 'profile_photo' => ''];
    }
    $user_id = $user['id'];
    $bio = $user['bio'] ?? '';
    $profile_photo = $user['profile_photo'] ?? '';
} catch (PDOException $e) {
    $error = "Failed to fetch user data: " . $e->getMessage();
}

// Fetch user's posts (include sold status and art type)
try {
    $stmt = $pdo->prepare("
        SELECT id, image, description, status, price, min_trade_value, timestamp, sold, art_type
        FROM posts
        WHERE user_id = ?
        ORDER BY timestamp DESC
    ");
    $stmt->execute([$user_id]);
    $art_posts = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Failed to fetch posts: " . $e->getMessage();
}

// Fetch user's videos (include art_type)
try {
    $stmt = $pdo->prepare("
        SELECT id, video, title, description, art_type, timestamp
        FROM videos
        WHERE user_id = ?
        ORDER BY timestamp DESC
    ");
    $stmt->execute([$user_id]);
    $user_videos = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Failed to fetch videos: " . $e->getMessage();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle profile update
    if (isset($_POST['update_profile'])) {
        $new_username = trim($_POST['username']);
        $new_bio = trim($_POST['bio']);
        if (empty($new_username)) {
            $error = "Username cannot be empty.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$new_username, $user_id]);
            if ($stmt->fetch()) {
                $error = "Username is already taken.";
            } elseif (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['profile_photo']['tmp_name'];
                $file_name = $_FILES['profile_photo']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($file_ext, $allowed_ext)) {
                    $error = "Only JPG, JPEG, PNG, and GIF files are allowed for profile photo.";
                } elseif ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) {
                    $error = "Profile photo size must be less than 5MB.";
                } else {
                    $upload_dir = 'Uploads/';
                    $photo_name = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
                    $photo_path = $upload_dir . $photo_name;
                    if (move_uploaded_file($file_tmp, $photo_path)) {
                        try {
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, bio = ?, profile_photo = ? WHERE id = ?");
                            $stmt->execute([$new_username, $new_bio, $photo_path, $user_id]);
                            $_SESSION['username'] = $new_username;
                            $username = $new_username;
                            $bio = $new_bio;
                            $profile_photo = $photo_path;
                            $success = "Profile updated successfully!";
                        } catch (PDOException $e) {
                            $error = "Failed to update profile: " . $e->getMessage();
                        }
                    } else {
                        $error = "Failed to upload profile photo.";
                    }
                }
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, bio = ? WHERE id = ?");
                    $stmt->execute([$new_username, $new_bio, $user_id]);
                    $_SESSION['username'] = $new_username;
                    $username = $new_username;
                    $bio = $new_bio;
                    $success = "Profile updated successfully!";
                } catch (PDOException $e) {
                    $error = "Failed to update profile: " . $e->getMessage();
                }
            }
        }
    }

    // Handle art post upload
    if (isset($_POST['upload_art']) && isset($_FILES['art_image']) && $_FILES['art_image']['error'] == UPLOAD_ERR_OK) {
        $art_description = trim($_POST['art_description'] ?? '');
        $art_status = $_POST['art_status'] ?? 'share';
        $art_type = $_POST['art_type'] ?? '';
        $art_price = ($art_status == 'sell') ? floatval($_POST['art_price'] ?? 0) : 0;
        $art_min_trade_value = ($art_status == 'trade') ? floatval($_POST['art_min_trade_value'] ?? 0) : 0;
        $file_tmp = $_FILES['art_image']['tmp_name'];
        $file_name = $_FILES['art_image']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_ext, $allowed_ext)) {
            $error = "Only JPG, JPEG, PNG, and GIF files are allowed for art posts.";
        } elseif ($_FILES['art_image']['size'] > 5 * 1024 * 1024) {
            $error = "Art image size must be less than 5MB.";
        } elseif ($art_status == 'sell' && $art_price <= 0) {
            $error = "Please enter a valid price for selling.";
        } elseif ($art_status == 'trade' && $art_min_trade_value <= 0) {
            $error = "Please enter a valid minimum trade value.";
        } elseif (empty($art_type)) {
            $error = "Please select an art type.";
        } else {
            $upload_dir = 'Uploads/';
            $image_name = 'art_' . $user_id . '_' . time() . '.' . $file_ext;
            $image_path = $upload_dir . $image_name;
            if (move_uploaded_file($file_tmp, $image_path)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO posts (user_id, image, description, status, price, min_trade_value, art_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $image_path, $art_description, $art_status, $art_price, $art_min_trade_value, $art_type]);
                    $success = "Art post uploaded successfully!";
                    $stmt = $pdo->prepare("SELECT id, image, description, status, price, min_trade_value, timestamp, sold, art_type FROM posts WHERE user_id = ? ORDER BY timestamp DESC");
                    $stmt->execute([$user_id]);
                    $art_posts = $stmt->fetchAll();
                } catch (PDOException $e) {
                    $error = "Failed to save post: " . $e->getMessage();
                }
            } else {
                $error = "Failed to upload art image.";
            }
        }
    }

    // Handle art post edit
    if (isset($_POST['edit_art']) && isset($_POST['post_id']) && is_numeric($_POST['post_id'])) {
        $post_id = intval($_POST['post_id']);
        $art_description = trim($_POST['art_description'] ?? '');
        $art_status = $_POST['art_status'] ?? 'share';
        $art_type = $_POST['art_type'] ?? '';
        $art_price = ($art_status == 'sell') ? floatval($_POST['art_price'] ?? 0) : 0;
        $art_min_trade_value = ($art_status == 'trade') ? floatval($_POST['art_min_trade_value'] ?? 0) : 0;
        $stmt = $pdo->prepare("SELECT image, status, price, sold FROM posts WHERE id = ? AND user_id = ?");
        $stmt->execute([$post_id, $user_id]);
        $post = $stmt->fetch();
        if (!$post) {
            $error = "Post not found or you don't have permission to edit it.";
        } else {
            if ($post['sold']) {
                $art_status = $post['status'];
                $art_price = $post['price'];
                $art_min_trade_value = 0;
            }
            $art_image = $post['image'];
            if ($art_status == 'sell' && $art_price <= 0 && !$post['sold']) {
                $error = "Please enter a valid price for selling.";
            } elseif ($art_status == 'trade' && $art_min_trade_value <= 0 && !$post['sold']) {
                $error = "Please enter a valid minimum trade value.";
            } elseif (empty($art_type)) {
                $error = "Please select an art type.";
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE posts SET image = ?, description = ?, status = ?, price = ?, min_trade_value = ?, art_type = ? WHERE id = ? AND user_id = ?");
                    $stmt->execute([$art_image, $art_description, $art_status, $art_price, $art_min_trade_value, $art_type, $post_id, $user_id]);
                    $success = "Art post updated successfully!";
                    $stmt = $pdo->prepare("SELECT id, image, description, status, price, min_trade_value, timestamp, sold, art_type FROM posts WHERE user_id = ? ORDER BY timestamp DESC");
                    $stmt->execute([$user_id]);
                    $art_posts = $stmt->fetchAll();
                } catch (PDOException $e) {
                    $error = "Failed to update post: " . $e->getMessage();
                }
            }
        }
    }

    // Handle close bidding action
    if (isset($_POST['close_bidding']) && isset($_POST['post_id']) && is_numeric($_POST['post_id'])) {
        $post_id = intval($_POST['post_id']);
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Verify post exists and is a trade post
            $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ? AND user_id = ? AND status = 'trade' AND sold = 0");
            $stmt->execute([$post_id, $user_id]);
            $post = $stmt->fetch();
            if (!$post) {
                $pdo->rollBack();
                $error = "Failed to close bidding. Post not found, not a trade post, or already sold.";
            } else {
                // Find the last bidder
                $stmt = $pdo->prepare("
                    SELECT b.user_id, u.username
                    FROM bids b
                    JOIN users u ON b.user_id = u.id
                    WHERE b.post_id = ?
                    ORDER BY b.timestamp DESC
                    LIMIT 1
                ");
                $stmt->execute([$post_id]);
                $last_bidder = $stmt->fetch();

                // Update post to mark as sold
                $stmt = $pdo->prepare("UPDATE posts SET sold = 1 WHERE id = ?");
                $stmt->execute([$post_id]);

                // Create notification for the last bidder if found
                if ($last_bidder) {
                    $message = "Congratulations! You won the bid for a post.";
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, type, post_id, actor_id, message)
                        VALUES (?, 'bid_won', ?, ?, ?)
                    ");
                    $stmt->execute([$last_bidder['user_id'], $post_id, $user_id, $message]);
                }

                // Refresh posts
                $stmt = $pdo->prepare("
                    SELECT id, image, description, status, price, min_trade_value, timestamp, sold, art_type
                    FROM posts
                    WHERE user_id = ?
                    ORDER BY timestamp DESC
                ");
                $stmt->execute([$user_id]);
                $art_posts = $stmt->fetchAll();

                $pdo->commit();
                $success = "Bidding closed successfully! Post marked as sold.";
                if (!$last_bidder) {
                    $success .= " No bidders found.";
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Failed to close bidding: " . $e->getMessage();
        }
    }

    // Handle art post deletion
    if (isset($_POST['delete_art']) && isset($_POST['post_id']) && is_numeric($_POST['post_id'])) {
        $post_id = intval($_POST['post_id']);
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Delete associated bid_addresses
            $stmt = $pdo->prepare("DELETE FROM bid_addresses WHERE post_id = ?");
            $stmt->execute([$post_id]);

            // Delete associated notifications
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE post_id = ?");
            $stmt->execute([$post_id]);

            // Delete associated likes
            $stmt = $pdo->prepare("DELETE FROM likes WHERE post_id = ?");
            $stmt->execute([$post_id]);

            // Delete associated bids
            $stmt = $pdo->prepare("DELETE FROM bids WHERE post_id = ?");
            $stmt->execute([$post_id]);

            // Delete the post
            $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?");
            $stmt->execute([$post_id, $user_id]);

            if ($stmt->rowCount() > 0) {
                $success = "Art post deleted successfully!";
                // Refresh posts
                $stmt = $pdo->prepare("SELECT id, image, description, status, price, min_trade_value, timestamp, sold, art_type FROM posts WHERE user_id = ? ORDER BY timestamp DESC");
                $stmt->execute([$user_id]);
                $art_posts = $stmt->fetchAll();
                $pdo->commit();
            } else {
                $pdo->rollBack();
                $error = "Post not found or you don't have permission to delete it.";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Failed to delete post: " . $e->getMessage();
        }
    }

    // Handle video upload
    if (isset($_POST['upload_video']) && isset($_FILES['video_file']) && $_FILES['video_file']['error'] == UPLOAD_ERR_OK) {
        $video_title = trim($_POST['video_title'] ?? '');
        $video_description = trim($_POST['video_description'] ?? '');
        $art_type = $_POST['art_type'] ?? '';
        $file_tmp = $_FILES['video_file']['tmp_name'];
        $file_name = $_FILES['video_file']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['mp4'];
        if (!in_array($file_ext, $allowed_ext)) {
            $error = "Only MP4 files are allowed for videos.";
        } elseif ($_FILES['video_file']['size'] > 100 * 1024 * 1024) {
            $error = "Video size must be less than 10MB.";
        } elseif (empty($video_title)) {
            $error = "Video title is required.";
        } elseif (empty($art_type)) {
            $error = "Please select an art type.";
        } else {
            $upload_dir = 'Uploads/';
            $video_name = 'video_' . $user_id . '_' . time() . '.' . $file_ext;
            $video_path = $upload_dir . $video_name;
            if (move_uploaded_file($file_tmp, $video_path)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO videos (user_id, video, title, description, art_type) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $video_path, $video_title, $video_description, $art_type]);
                    $success = "Video uploaded successfully!";
                    $stmt = $pdo->prepare("SELECT id, video, title, description, art_type, timestamp FROM videos WHERE user_id = ? ORDER BY timestamp DESC");
                    $stmt->execute([$user_id]);
                    $user_videos = $stmt->fetchAll();
                } catch (PDOException $e) {
                    $error = "Failed to save video: " . $e->getMessage();
                    @unlink($video_path); // Delete file if DB insert fails
                }
            } else {
                $error = "Failed to upload video.";
            }
        }
    }

    // Handle video edit
    if (isset($_POST['edit_video']) && isset($_POST['video_id']) && is_numeric($_POST['video_id'])) {
        $video_id = intval($_POST['video_id']);
        $video_title = trim($_POST['video_title'] ?? '');
        $video_description = trim($_POST['video_description'] ?? '');
        $art_type = $_POST['art_type'] ?? '';
        $stmt = $pdo->prepare("SELECT video, art_type FROM videos WHERE id = ? AND user_id = ?");
        $stmt->execute([$video_id, $user_id]);
        $video = $stmt->fetch();
        if (!$video) {
            $error = "Video not found or you don't have permission to edit it.";
        } else {
            $video_file = $video['video'];
            if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] == UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['video_file']['tmp_name'];
                $file_name = $_FILES['video_file']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_ext = ['mp4'];
                if (!in_array($file_ext, $allowed_ext)) {
                    $error = "Only MP4 files are allowed for videos.";
                } elseif ($_FILES['video_file']['size'] > 10 * 1024 * 1024) {
                    $error = "Video size must be less than 10MB.";
                } else {
                    $upload_dir = 'Uploads/';
                    $video_name = 'video_' . $user_id . '_' . time() . '.' . $file_ext;
                    $video_file = $upload_dir . $video_name;
                    if (!move_uploaded_file($file_tmp, $video_file)) {
                        $error = "Failed to upload new video.";
                    }
                }
            }
            if (!$error) {
                if (empty($video_title)) {
                    $error = "Video title is required.";
                } elseif (empty($art_type)) {
                    $error = "Please select an art type.";
                } else {
                    try {
                        $stmt = $pdo->prepare("UPDATE videos SET video = ?, title = ?, description = ?, art_type = ? WHERE id = ? AND user_id = ?");
                        $stmt->execute([$video_file, $video_title, $video_description, $art_type, $video_id, $user_id]);
                        $success = "Video updated successfully!";
                        $stmt = $pdo->prepare("SELECT id, video, title, description, art_type, timestamp FROM videos WHERE user_id = ? ORDER BY timestamp DESC");
                        $stmt->execute([$user_id]);
                        $user_videos = $stmt->fetchAll();
                    } catch (PDOException $e) {
                        $error = "Failed to update video: " . $e->getMessage();
                    }
                }
            }
        }
    }

    // Handle video deletion
    if (isset($_POST['delete_video']) && isset($_POST['video_id']) && is_numeric($_POST['video_id'])) {
        $video_id = intval($_POST['video_id']);
        try {
            $stmt = $pdo->prepare("DELETE FROM videos WHERE id = ? AND user_id = ?");
            $stmt->execute([$video_id, $user_id]);
            if ($stmt->rowCount() > 0) {
                $success = "Video deleted successfully!";
                $stmt = $pdo->prepare("SELECT id, video, title, description, art_type, timestamp FROM videos WHERE user_id = ? ORDER BY timestamp DESC");
                $stmt->execute([$user_id]);
                $user_videos = $stmt->fetchAll();
            } else {
                $error = "Video not found or you don't have permission to delete it.";
            }
        } catch (PDOException $e) {
            $error = "Failed to delete video: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConnectSphere - Profile</title>
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

        .profile-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 1, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-container h2 {
            font-size: 2em;
            margin-bottom: 20px;
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }

        .profile-photo-container {
            display: inline-block;
            margin-bottom: 20px;
        }

        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            font-size: 0.9em;
            margin-bottom: 8px;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
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

        .form-group input[type="file"] {
            padding: 10px;
            background: none;
            box-shadow: none;
        }

        .form-group select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background: rgba(255, 255, 255, 0.2) url('data:image/svg+xml;utf8,<svg fill="white" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>') no-repeat right 10px center;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.02);
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder,
        .form-group select::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .form-group input[readonly] {
            background: rgba(255, 255, 255, 0.1);
            cursor: not-allowed;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .radio-group label {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .radio-group input[disabled] {
            cursor: not-allowed;
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

        .edit-button {
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

        .edit-button:hover {
            background: linear-gradient(45deg, #4ecdc4, #ff6b6b);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .edit-button:active {
            transform: translateY(0);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .delete-button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(45deg, #ff4d4d, #b22222);
            color: #fff;
            font-size: 1.1em;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            margin-top: 10px;
        }

        .delete-button:hover {
            background: linear-gradient(45deg, #b22222, #ff4d4d);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .delete-button:active {
            transform: translateY(0);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .close-bidding-button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(45deg, #ffa500, #ff4500);
            color: #fff;
            font-size: 1.1em;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            margin-top: 10px;
        }

        .close-bidding-button:hover {
            background: linear-gradient(45deg, #ff4500, #ffa500);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .close-bidding-button:active {
            transform: translateY(0);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
        }

        .close-button {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            color: #fff;
            font-size: 1em;
            cursor: pointer;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            box-sizing: border-box;
            transition: background 0.3s ease;
        }

        .close-button:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .alert-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .alert-modal-content {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
            position: relative;
        }

        .alert-success {
            color: #4ecdc4;
            font-size: 1em;
            margin-bottom: 15px;
        }

        .alert-error {
            color: #ff4d4d;
            font-size: 1em;
            margin-bottom: 15px;
        }

        .alert-close-button {
            padding: 10px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
            color: #fff;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            width: 100%;
        }

        .alert-close-button:hover {
            background: linear-gradient(45deg, #4ecdc4, #ff6b6b);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .alert-close-button:active {
            transform: translateY(0);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .art-upload-container,
        .video-upload-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 30px;
        }

        .art-posts-container,
        .video-posts-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .art-post,
        .video-post {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .art-post img,
        .video-post video {
            max-width: 100%;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .art-post p,
        .video-post p {
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .art-post .timestamp,
        .video-post .timestamp {
            font-size: 0.8em;
            color: rgba(255, 255, 255, 0.7);
        }

        @media (max-width: 600px) {
            .container {
                padding: 10px;
            }

            .profile-container,
            .art-upload-container,
            .video-upload-container,
            .modal-content,
            .alert-modal-content {
                padding: 20px;
            }

            .navbar {
                flex-direction: column;
                gap: 10px;
            }

            .navbar .nav-links a {
                margin-left: 10px;
            }

            .art-posts-container,
            .video-posts-container {
                grid-template-columns: 1fr;
            }

            .radio-group {
                flex-direction: column;
                gap: 10px;
            }

            .form-group select {
                background: rgba(255, 255, 255, 0.2) url('data:image/svg+xml;utf8,<svg fill="white" height="20" viewBox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>') no-repeat right 8px center;
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
        <div class="profile-container">
            <h2>Manage Your Profile</h2>
            <?php if ($profile_photo): ?>
                <div class="profile-photo-container">
                    <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="Profile Photo" class="profile-photo">
                </div>
            <?php else: ?>
                <div class="profile-photo-container">
                    <img src="https://via.placeholder.com/150" alt="Profile Photo" class="profile-photo">
                </div>
            <?php endif; ?>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="update_profile" value="1">
                <div class="form-group">
                    <label for="profile_photo">Profile Photo</label>
                    <input type="file" id="profile_photo" name="profile_photo" accept="image/*">
                </div>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                </div>
                <div class="form-group">
                    <label for="bio">Bio</label>
                    <textarea id="bio" name="bio" placeholder="Tell us about yourself"><?php echo htmlspecialchars($bio); ?></textarea>
                </div>
                <button type="submit">Save Profile Changes</button>
            </form>
        </div>
        <div class="art-upload-container">
            <h2>Share Your Art</h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="upload_art" value="1">
                <div class="form-group">
                    <label for="art_image">Art Image</label>
                    <input type="file" id="art_image" name="art_image" accept="image/*" required>
                </div>
                <div class="form-group">
                    <label for="art_type">Art Type</label>
                    <select id="art_type" name="art_type" required>
                        <option value="" disabled selected>Select Art Type</option>
                        <option value="Paintings">Paintings</option>
                        <option value="Drawings">Drawings</option>
                        <option value="Prints & Reproductions">Prints & Reproductions</option>
                        <option value="Sculpture & 3D Art">Sculpture & 3D Art</option>
                        <option value="Photography">Photography</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="art_description">Description (Optional)</label>
                    <textarea id="art_description" name="art_description" placeholder="Describe your artwork"></textarea>
                </div>
                <div class="form-group" id="art-status-group" style="display: none;">
                    <label>Art Status</label>
                    <div class="radio-group">
                        <label><input type="radio" name="art_status" value="sell"> Sell</label>
                        <label><input type="radio" name="art_status" value="trade"> Trade</label>
                        <label><input type="radio" name="art_status" value="share" checked> Share</label>
                    </div>
                </div>
                <div class="form-group" id="sell-field" style="display: none;">
                    <label for="art_price">Price ($)</label>
                    <input type="number" id="art_price" name="art_price" step="0.01" min="0" placeholder="Enter price">
                </div>
                <div class="form-group" id="trade-field" style="display: none;">
                    <label for="art_min_trade_value">Minimum Trade Value ($)</label>
                    <input type="number" id="art_min_trade_value" name="art_min_trade_value" step="0.01" min="0" placeholder="Enter minimum trade value">
                </div>
                <button type="submit">Upload Art</button>
            </form>
        </div>
        <div class="video-upload-container">
            <h2>Share Your Video</h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="upload_video" value="1">
                <div class="form-group">
                    <label for="video_file">Video File (MP4)</label>
                    <input type="file" id="video_file" name="video_file" accept="video/mp4" required>
                </div>
                <div class="form-group">
                    <label for="video_art_type">Art Type</label>
                    <select id="video_art_type" name="art_type" required>
                        <option value="" disabled selected>Select Art Type</option>
                        <option value="Paintings">Paintings</option>
                        <option value="Drawings">Drawings</option>
                        <option value="Prints & Reproductions">Prints & Reproductions</option>
                        <option value="Sculpture & 3D Art">Sculpture & 3D Art</option>
                        <option value="Photography">Photography</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="video_title">Video Title</label>
                    <input type="text" id="video_title" name="video_title" placeholder="Enter video title" required>
                </div>
                <div class="form-group">
                    <label for="video_description">Description (Optional)</label>
                    <textarea id="video_description" name="video_description" placeholder="Describe your video"></textarea>
                </div>
                <button type="submit">Upload Video</button>
            </form>
        </div>
        <div class="art-posts-container">
            <h2>Your Art Posts</h2>
            <?php if (!empty($art_posts)): ?>
                <?php foreach ($art_posts as $post): ?>
                    <div class="art-post">
                        <img src="<?php echo htmlspecialchars($post['image']); ?>" alt="Art Post">
                        <p><strong><?php echo htmlspecialchars($username); ?></strong></p>
                        <?php if ($post['art_type']): ?>
                            <p>Type: <?php echo htmlspecialchars($post['art_type']); ?></p>
                        <?php endif; ?>
                        <?php if ($post['description']): ?>
                            <p><?php echo htmlspecialchars($post['description']); ?></p>
                        <?php endif; ?>
                        <?php if ($post['status'] == 'sell'): ?>
                            <p><?php echo $post['sold'] ? 'Sold' : 'For Sale'; ?>: $<?php echo number_format($post['price'], 2); ?></p>
                        <?php elseif ($post['status'] == 'trade'): ?>
                            <p><?php echo $post['sold'] ? 'Sold' : 'For Trade'; ?>: Minimum Value $<?php echo number_format($post['min_trade_value'], 2); ?></p>
                        <?php else: ?>
                            <p>Shared</p>
                        <?php endif; ?>
                        <p class="timestamp"><?php echo htmlspecialchars($post['timestamp']); ?></p>
                        <?php if (!$post['sold']): ?>
                            <button class="edit-button" onclick="openEditModal(<?php echo $post['id']; ?>, '<?php echo htmlspecialchars(addslashes($post['description'])); ?>', '<?php echo $post['status']; ?>', <?php echo $post['price']; ?>, <?php echo $post['min_trade_value']; ?>, '<?php echo htmlspecialchars(addslashes($post['art_type'])); ?>', <?php echo $post['sold'] ? 'true' : 'false'; ?>)">Edit</button>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                            <button type="submit" name="delete_art" value="1" class="delete-button">Delete</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No art posts yet.</p>
            <?php endif; ?>
        </div>
        <div class="video-posts-container">
            <h2>Your Videos</h2>
            <?php if (!empty($user_videos)): ?>
                <?php foreach ($user_videos as $video): ?>
                    <div class="video-post">
                        <video controls>
                            <source src="<?php echo htmlspecialchars($video['video']); ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                        <p><strong><?php echo htmlspecialchars($video['title']); ?></strong></p>
                        <?php if ($video['art_type']): ?>
                            <p>Type: <?php echo htmlspecialchars($video['art_type']); ?></p>
                        <?php endif; ?>
                        <?php if ($video['description']): ?>
                            <p><?php echo htmlspecialchars($video['description']); ?></p>
                        <?php endif; ?>
                        <p class="timestamp"><?php echo htmlspecialchars($video['timestamp']); ?></p>
                        <button class="edit-button" onclick="openVideoEditModal(<?php echo $video['id']; ?>, '<?php echo htmlspecialchars(addslashes($video['title'])); ?>', '<?php echo htmlspecialchars(addslashes($video['description'])); ?>', '<?php echo htmlspecialchars(addslashes($video['art_type'])); ?>')">Edit</button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No videos yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal" id="edit-art-modal">
        <div class="modal-content">
            <button class="close-button" onclick="closeEditModal()">×</button>
            <h2>Edit Art Post</h2>
            <form method="POST" action="">
                <input type="hidden" name="post_id" id="edit-post-id">
                <input type="hidden" name="sold" id="edit-sold" value="0">
                <div class="form-group">
                    <label for="edit_art_type">Art Type</label>
                    <select id="edit_art_type" name="art_type" required>
                        <option value="" disabled>Select Art Type</option>
                        <option value="Paintings">Paintings</option>
                        <option value="Drawings">Drawings</option>
                        <option value="Prints & Reproductions">Prints & Reproductions</option>
                        <option value="Sculpture & 3D Art">Sculpture & 3D Art</option>
                        <option value="Photography">Photography</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_art_description">Description (Optional)</label>
                    <textarea id="edit_art_description" name="art_description" placeholder="Describe your artwork"></textarea>
                </div>
                <div class="form-group" id="edit-status-group">
                    <label>Art Status</label>
                    <div class="radio-group">
                        <label><input type="radio" name="art_status" value="sell" id="edit_sell"> Sell</label>
                        <label><input type="radio" name="art_status" value="trade" id="edit_trade"> Trade</label>
                        <label><input type="radio" name="art_status" value="share" id="edit_share"> Share</label>
                    </div>
                </div>
                <div class="form-group" id="edit-sell-field" style="display: none;">
                    <label for="edit_art_price">Price ($)</label>
                    <input type="number" id="edit_art_price" name="art_price" step="0.01" min="0" placeholder="Enter price">
                </div>
                <div class="form-group" id="edit-trade-field" style="display: none;">
                    <label for="edit_art_min_trade_value">Minimum Trade Value ($)</label>
                    <input type="number" id="edit_art_min_trade_value" name="art_min_trade_value" step="0.01" min="0" placeholder="Enter minimum trade value">
                </div>
                <button type="submit" name="edit_art" value="1">Save Changes</button>
                <button type="submit" name="close_bidding" value="1" class="close-bidding-button" id="close-bidding-button" style="display: none;">Close Bidding</button>
            </form>
        </div>
    </div>

    <div class="modal" id="edit-video-modal">
        <div class="modal-content">
            <button class="close-button" onclick="closeVideoEditModal()">×</button>
            <h2>Edit Video</h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="video_id" id="edit-video-id">
                <div class="form-group">
                    <label for="edit_video_file">Video File (Optional, MP4)</label>
                    <input type="file" id="edit_video_file" name="video_file" accept="video/mp4">
                </div>
                <div class="form-group">
                    <label for="edit_video_art_type">Art Type</label>
                    <select id="edit_video_art_type" name="art_type" required>
                        <option value="" disabled>Select Art Type</option>
                        <option value="Paintings">Paintings</option>
                        <option value="Drawings">Drawings</option>
                        <option value="Prints & Reproductions">Prints & Reproductions</option>
                        <option value="Sculpture & 3D Art">Sculpture & 3D Art</option>
                        <option value="Photography">Photography</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_video_title">Video Title</label>
                    <input type="text" id="edit_video_title" name="video_title" placeholder="Enter video title" required>
                </div>
                <div class="form-group">
                    <label for="edit_video_description">Description (Optional)</label>
                    <textarea id="edit_video_description" name="video_description" placeholder="Describe your video"></textarea>
                </div>
                <button type="submit" name="edit_video" value="1">Save Changes</button>
                <button type="submit" name="delete_video" value="1" class="delete-button">Delete Video</button>
            </form>
        </div>
    </div>

    <div class="alert-modal" id="alert-modal">
        <div class="alert-modal-content">
            <?php if ($success): ?>
                <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php elseif ($error): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <button class="alert-close-button" onclick="closeAlertModal()">Close</button>
        </div>
    </div>

    <script>
        const artImageInput = document.getElementById('art_image');
        const artStatusGroup = document.getElementById('art-status-group');
        const sellField = document.getElementById('sell-field');
        const tradeField = document.getElementById('trade-field');
        const radioButtons = document.querySelectorAll('input[name="art_status"]');

        artImageInput.addEventListener('change', () => {
            artStatusGroup.style.display = artImageInput.files.length > 0 ? 'block' : 'none';
            const selectedStatus = document.querySelector('input[name="art_status"]:checked').value;
            sellField.style.display = selectedStatus === 'sell' ? 'block' : 'none';
            tradeField.style.display = selectedStatus === 'trade' ? 'block' : 'none';
        });

        radioButtons.forEach(radio => {
            radio.addEventListener('change', () => {
                sellField.style.display = radio.value === 'sell' ? 'block' : 'none';
                tradeField.style.display = radio.value === 'trade' ? 'block' : 'none';
            });
        });

        function openEditModal(postId, description, status, price, minTradeValue, artType, isSold) {
            const modal = document.getElementById('edit-art-modal');
            const closeBiddingButton = document.getElementById('close-bidding-button');
            document.getElementById('edit-post-id').value = postId;
            document.getElementById('edit_art_description').value = description;
            document.getElementById('edit_' + status).checked = true;
            document.getElementById('edit_art_price').value = price > 0 ? price : '';
            document.getElementById('edit_art_min_trade_value').value = minTradeValue > 0 ? minTradeValue : '';
            document.getElementById('edit_art_type').value = artType;
            document.getElementById('edit-sell-field').style.display = status === 'sell' ? 'block' : 'none';
            document.getElementById('edit-trade-field').style.display = status === 'trade' ? 'block' : 'none';
            document.getElementById('edit-sold').value = isSold ? '1' : '0';

            // Show "Close Bidding" button only for trade posts that are not sold
            closeBiddingButton.style.display = (status === 'trade' && !isSold) ? 'block' : 'none';

            if (isSold) {
                document.getElementById('edit_sell').disabled = true;
                document.getElementById('edit_trade').disabled = true;
                document.getElementById('edit_share').disabled = true;
                document.getElementById('edit_art_price').readOnly = true;
                document.getElementById('edit_art_min_trade_value').readOnly = true;
                document.getElementById('edit_art_type').disabled = true;
                document.getElementById('edit-status-group').style.opacity = '0.5';
                document.getElementById('edit-sell-field').style.opacity = '0.5';
                document.getElementById('edit-trade-field').style.opacity = '0.5';
                document.getElementById('edit_art_type').style.opacity = '0.5';
            } else {
                document.getElementById('edit_sell').disabled = false;
                document.getElementById('edit_trade').disabled = false;
                document.getElementById('edit_share').disabled = false;
                document.getElementById('edit_art_price').readOnly = false;
                document.getElementById('edit_art_min_trade_value').readOnly = false;
                document.getElementById('edit_art_type').disabled = false;
                document.getElementById('edit-status-group').style.opacity = '1';
                document.getElementById('edit-sell-field').style.opacity = '1';
                document.getElementById('edit-trade-field').style.opacity = '1';
                document.getElementById('edit_art_type').style.opacity = '1';
            }

            modal.style.display = 'flex';

            const editRadioButtons = document.querySelectorAll('#edit-art-modal input[name="art_status"]');
            editRadioButtons.forEach(radio => {
                radio.addEventListener('change', () => {
                    if (!isSold) {
                        document.getElementById('edit-sell-field').style.display = radio.value === 'sell' ? 'block' : 'none';
                        document.getElementById('edit-trade-field').style.display = radio.value === 'trade' ? 'block' : 'none';
                        closeBiddingButton.style.display = (radio.value === 'trade' && !isSold) ? 'block' : 'none';
                    }
                });
            });
        }

        function openVideoEditModal(videoId, title, description, artType) {
            const modal = document.getElementById('edit-video-modal');
            document.getElementById('edit-video-id').value = videoId;
            document.getElementById('edit_video_title').value = title;
            document.getElementById('edit_video_description').value = description;
            document.getElementById('edit_video_art_type').value = artType;
            modal.style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('edit-art-modal').style.display = 'none';
        }

        function closeVideoEditModal() {
            document.getElementById('edit-video-modal').style.display = 'none';
        }

        function closeAlertModal() {
            document.getElementById('alert-modal').style.display = 'none';
        }

        window.addEventListener('load', () => {
            <?php if ($success || $error): ?>
                document.getElementById('alert-modal').style.display = 'flex';
            <?php endif; ?>
        });

        window.addEventListener('click', (event) => {
            if (event.target === document.getElementById('edit-art-modal')) {
                closeEditModal();
            }
            if (event.target === document.getElementById('edit-video-modal')) {
                closeVideoEditModal();
            }
            if (event.target === document.getElementById('alert-modal')) {
                closeAlertModal();
            }
        });
    </script>
</body>
</html>