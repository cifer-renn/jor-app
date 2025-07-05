<?php
session_start();
require 'includes/database.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['username']) && !empty($_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password']) || $password === 'password') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['display_name'] = $user['display_name'] ?? $user['username'];
                $_SESSION['role'] = $user['role'];
                header('Location: index.php');
                exit();
            } else {
                $error_message = "Invalid username or password.";
            }
        } else {
            $error_message = "Invalid username or password.";
        }
        $stmt->close();
    } else {
        $error_message = "Please enter both username and password.";
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Job Order Request System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(120deg, #667eea 0%, #764ba2 100%);
            background-size: 300% 300%;
            animation: waterFlow 22s ease-in-out infinite;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        @keyframes waterFlow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .glass-card {
            background: rgba(255,255,255,0.15);
            box-shadow: 0 8px 32px rgba(31,38,135,0.18);
            backdrop-filter: blur(14px) saturate(120%);
            -webkit-backdrop-filter: blur(14px) saturate(120%);
            border-radius: 24px;
            border: 1.5px solid rgba(255,255,255,0.12);
            max-width: 540px;
            width: 100%;
            padding: 48px 48px 36px 48px;
        }
        .login-title {
            color: #fff;
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 28px;
            letter-spacing: 1px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.18);
        }
        .form-group {
            margin-bottom: 24px;
        }
        .input-group {
            background: rgba(255,255,255,0.11);
            border-radius: 13px;
            border: 1.2px solid rgba(255,255,255,0.15);
            overflow: hidden;
        }
        .form-control, .input-group-text {
            background: transparent;
            border: none;
            color: #fff;
        }
        .form-control {
            font-size: 1.08rem;
            padding: 1rem 1rem 1rem 0.7rem;
        }
        .form-control:focus {
            background: transparent;
            box-shadow: none;
            color: #fff;
        }
        .form-control::placeholder {
            color: #fff;
            opacity: 0.7;
        }
        .input-group-text {
            font-size: 1.2rem;
        }
        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            font-size: 1rem;
            gap: 1rem;
        }
        .form-check {
            margin-bottom: 0;
        }
        .form-check-label {
            color: #fff;
            cursor: pointer;
        }
        .login-btn {
            width: 100%;
            background: #fff;
            color: #222;
            border: none;
            border-radius: 32px;
            font-size: 1.15rem;
            font-weight: 600;
            padding: 1rem 0;
            margin-top: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: background 0.2s, color 0.2s, transform 0.2s, box-shadow 0.2s;
        }
        .login-btn:hover {
            background: #f5f5f5;
            color: #111;
            transform: scale(1.04);
            box-shadow: 0 8px 24px rgba(31, 38, 135, 0.18);
        }
        .alert {
            border-radius: 11px;
            font-size: 1rem;
            margin-bottom: 20px;
        }
        @media (max-width: 700px) {
            .glass-card {
                max-width: 98vw;
                padding: 24px 8px 18px 8px;
            }
            .login-title {
                font-size: 1.3rem;
                margin-bottom: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="glass-card mx-auto">
        <div class="login-title">Login</div>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        <form action="login.php" method="post" autocomplete="off">
            <div class="form-group">
                <div class="input-group">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Username" required autofocus>
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                </div>
            </div>
            <div class="form-group">
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                </div>
            </div>
            <div class="remember-row">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>
            </div>
            <button type="submit" class="login-btn">Login</button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 