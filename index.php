<?php
// Start session
session_start();

// Include database connection
require 'db_connect.php';

// Initialize error message
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Basic validation
    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Query the database for the user
        try {
            $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['username'] = $user['username'];
                header("Location: home.php");
                exit();
            } else {
                $error = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            $error = "Login failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artistic Login Page</title>
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

        .login-container {
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

        .login-container::before {
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

        .login-container h2 {
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

        .register-link {
            margin-top: 20px;
            position: relative;
            z-index: 1;
        }

        .register-link a {
            color: #fff;
            text-decoration: none;
            font-size: 0.9em;
            transition: color 0.3s ease;
        }

        .register-link a:hover {
            color: #4ecdc4;
            text-decoration: underline;
        }

        @media (max-width: 500px) {
            .login-container {
                padding: 20px;
                margin: 10px;
            }

            .login-container h2 {
                font-size: 1.8em;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Unlock Your Journey</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter your username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit">Embark</button>
        </form>
        <div class="register-link">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        </div>
    </div>
</body>
</html>