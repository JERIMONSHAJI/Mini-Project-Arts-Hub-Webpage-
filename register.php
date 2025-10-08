<?php
// Include database connection
require 'db_connect.php';

// Initialize error message
$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Basic validation
    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($username) > 50) {
        $error = "Username must be 50 characters or less.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Username is already taken.";
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user into the database
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $stmt->execute([$username, $hashed_password]);
                $success = "Registration successful! You can now <a href='index.php'>login</a>.";
            } catch (PDOException $e) {
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artistic Registration Page</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1, #96c93d);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .register-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .register-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.2) 10%, transparent 10.01%);
            background-size: 20px 20px;
            opacity: 0.3;
            transform: rotate(45deg);
        }

        .register-container h2 {
            color: #fff;
            font-size: 2.2em;
            margin-bottom: 20px;
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .form-group label {
            display: block;
            text-align: left;
            color: #fff;
            font-size: 0.9em;
            margin-bottom: 8px;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
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

        .error {
            color: #ff4d4d;
            background: rgba(0, 0, 0, 0.3);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9em;
            position: relative;
            z-index: 1;
        }

        .success {
            color: #4ecdc4;
            background: rgba(0, 0, 0, 0.3);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9em;
            position: relative;
            z-index: 1;
        }

        .success a {
            color: #fff;
            text-decoration: underline;
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
            position: relative;
            z-index: 1;
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

        .login-link {
            margin-top: 20px;
            position: relative;
            z-index: 1;
        }

        .login-link a {
            color: #fff;
            text-decoration: none;
            font-size: 0.9em;
            transition: color 0.3s ease;
        }

        .login-link a:hover {
            color: #4ecdc4;
            text-decoration: underline;
        }

        @media (max-width: 500px) {
            .register-container {
                padding: 20px;
                margin: 10px;
            }

            .register-container h2 {
                font-size: 1.8em;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Begin Your Adventure</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Choose a username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Create a password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
            </div>
            <button type="submit">Register</button>
        </form>
        <div class="login-link">
            <p>Already have an account? <a href="index.php">Login here</a></p>
        </div>
    </div>
</body>
</html>