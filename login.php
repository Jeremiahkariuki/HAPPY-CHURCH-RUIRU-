<?php
declare(strict_types=1);

session_start();

// Force browser not to cache this page so the user sees the newest UI immediately
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/* Load database connection */
$db_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . "db.php";
require_once $db_path;

/* Confirm PDO exists */
$db_error = ($pdo === null);

/* Load church name from config */
$_cfg = require __DIR__ . "/config.php";
$appName = $_cfg["app"]["name"] ?? "HAPPY CHURCH RUIRU";
unset($_cfg);

if (!function_exists('e')) {
    function e(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, "UTF-8");
    }
}

/* Ensure admin account exists */
function ensure_admin(PDO $pdo): void {
    try {
        $pdo->query("SELECT 1 FROM users LIMIT 1");
        $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(username)=LOWER(?) LIMIT 1");
        $stmt->execute(["admin"]);
        $id = (int)($stmt->fetchColumn() ?: 0);

        if ($id === 0) {
            $hash = password_hash("123", PASSWORD_DEFAULT);
            $ins = $pdo->prepare("INSERT INTO users (username,password_hash,role,status) VALUES (?,?, 'admin', 'Approved')");
            $ins->execute(["admin",$hash]);
        }
    } catch (Throwable $e) {
        // Silently continue if tables don't exist yet
    }
}

$error = "";
$success = "";

if (!$db_error) {
    // 1. Check if tables exist. If not, try to run db_setup logic automatically.
    try {
        $pdo->query("SELECT 1 FROM users LIMIT 1");
    } catch (Exception $e) {
        if (file_exists(__DIR__ . "/db_setup.php")) {
            ob_start();
            include_once __DIR__ . "/db_setup.php";
            ob_end_clean();
            require __DIR__ . "/db.php";
        }
    }
    ensure_admin($pdo);
} else {
    $error = isset($db_connect_error) ? $db_connect_error : "Database connection failed. Please check your credentials.";
}

/* Login/Auto-Registration process */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!$db_error) {
        $action = $_POST["action"] ?? "";
        $target_role = $_POST["target_role"] ?? "";
        
        $username_value = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";

        try {
            // Check if status column exists
            $status_col_exists = false;
            try {
                $pdo->query("SELECT status FROM users LIMIT 1");
                $status_col_exists = true;
            } catch (Exception $e) {}

            $query = $status_col_exists ? "SELECT id,username,role,password_hash,status FROM users WHERE LOWER(username)=LOWER(?) LIMIT 1" : "SELECT id,username,role,password_hash FROM users WHERE LOWER(username)=LOWER(?) LIMIT 1";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([$username_value]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($target_role === "admin") {
                // ADMIN LOGIN LOGIC
                if ($user && password_verify($password, $user["password_hash"])) {
                    if (strtolower($user["role"]) !== "admin") {
                        $error = "Access Denied: You are not an Administrator. Please use the Member Section below.";
                    } else {
                        // Admin is always approved
                        session_regenerate_id(true);
                        $_SESSION["user"] = [
                            "id" => $user["id"],
                            "username" => $user["username"],
                            "role" => $user["role"]
                        ];
                        header("Location: dashboard.php");
                        exit;
                    }
                } else {
                    $error = "Admin account not found or invalid password.";
                }
            } 
            elseif ($target_role === "member") {
                // MEMBER HYBRID LOGIC (Login or Auto-Register with Approval)
                if ($user) {
                    // User already exists, handle login
                    if (password_verify($password, $user["password_hash"])) {
                        $status = "Approved";
                        if ($status_col_exists) {
                            $status = $user["status"] ?? "Approved"; 
                            if (strtolower($user["role"]) === "admin") { $status = "Approved"; } 
                        }

                        if ($status !== "Approved") {
                            $error = "Your account is currently " . e($status) . ". Please wait for Admin approval to access the dashboard.";
                        } else {
                            session_regenerate_id(true);
                            $_SESSION["user"] = [
                                "id" => $user["id"],
                                "username" => $user["username"],
                                "role" => $user["role"]
                            ];
                            header("Location: dashboard.php");
                            exit;
                        }
                    } else {
                        $error = "Invalid password for this member account.";
                    }
                } else {
                    // User does NOT exist in XAMPP -> Auto-create as 'Pending'
                    if (strlen($username_value) < 3 || strlen($password) < 3) {
                        $error = "Username and password must be at least 3 characters to register.";
                    } else {
                        $role = "Member";
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        
                        if ($status_col_exists) {
                            $ins = $pdo->prepare("INSERT INTO users (username, password_hash, role, status) VALUES (?, ?, ?, 'Pending')");
                            $ins->execute([$username_value, $hash, $role]);
                        } else {
                            // Failsafe if status column doesn't exist
                            $ins = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
                            $ins->execute([$username_value, $hash, $role]);
                            // Force adding status column
                            $pdo->exec("ALTER TABLE users ADD COLUMN status varchar(20) NOT NULL DEFAULT 'Pending' AFTER role");
                            $pdo->prepare("UPDATE users SET status='Pending' WHERE username=?")->execute([$username_value]);
                        }
                        
                        $success = "Member account successfully created! Your status is Pending. Please wait for an Admin to approve your account before you can log in.";
                    }
                }
            }
        } catch (Exception $e) {
            $error = "An error occurred: " . e($e->getMessage());
        }
    } else {
        $error = isset($db_connect_error) ? $db_connect_error : "Login unavailable: Database is not connected.";
    }
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login • <?= e($appName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    display: flex; align-items: center; justify-content: center; min-height: 100vh;
    font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
    background: radial-gradient(1200px 800px at 30% 10%, rgba(124,92,255,.18), transparent 60%),
                radial-gradient(800px 600px at 80% 80%, rgba(46,233,166,.10), transparent 50%),
                #07101f;
    padding: 20px 0; position: relative;
  }
  body::before, body::after {
    content: ''; position: fixed; border-radius: 50%; filter: blur(80px); opacity: 0.4;
    animation: float 12s ease-in-out infinite; pointer-events: none;
  }
  body::before { width: 500px; height: 500px; background: rgba(124,92,255,.25); top: -100px; left: -100px; }
  body::after { width: 400px; height: 400px; background: rgba(46,233,166,.15); bottom: -80px; right: -80px; animation-delay: -6s; animation-direction: reverse; }
  @keyframes float { 0%, 100% { transform: translate(0,0) scale(1); } 50% { transform: translate(-20px, 40px) scale(1.05); } }

  .login-container { width: 100%; max-width: 480px; padding: 0 20px; z-index: 10; position: relative; }
  .login-card {
    background: rgba(15, 26, 46, 0.85); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,.05);
    border-radius: 24px; padding: 40px 32px; box-shadow: 0 20px 60px rgba(0,0,0,.4); width: 100%;
  }
  .brand-header { text-align: center; margin-bottom: 30px; }
  .brand-icon {
    width: 64px; height: 64px; border-radius: 20px; background: linear-gradient(135deg, #7c5cff, #2ee9a6);
    display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;
    font-size: 32px; font-weight: 800; color: #07101f; box-shadow: 0 10px 25px rgba(124,92,255,.4);
  }
  .brand-title { color: #ffffff; font-size: 1.6rem; font-weight: 900; letter-spacing: 0.5px; margin-bottom: 4px; }
  .brand-subtitle { color: rgba(169,183,208,.6); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }

  .alert { padding: 14px 16px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; margin-bottom: 24px; text-align: center; }
  .alert-error { background: rgba(255,107,138,.1); border: 1px solid rgba(255,107,138,.2); color: #ff6b8a; }
  .alert-success { background: rgba(46,233,166,.1); border: 1px solid rgba(46,233,166,.2); color: #2ee9a6; }

  /* Section Styling */
  .auth-section { border: 1px solid rgba(255,255,255,.06); border-radius: 16px; padding: 24px; margin-bottom: 24px; background: rgba(0,0,0,.2); }
  .admin-section { border-top: 3px solid #7c5cff; }
  .member-section { border-top: 3px solid #2ee9a6; }
  .section-title { font-size: 1.1rem; color: #fff; font-weight: 800; margin-bottom: 16px; text-align: center; text-transform: uppercase; letter-spacing: 1px; }

  .form-group { margin-bottom: 16px; }
  .form-label { display: block; font-size: 0.75rem; font-weight: 700; color: rgba(169,183,208,.8); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px; }
  .form-input {
    width: 100%; background: rgba(7, 16, 31, .6); border: 1px solid rgba(255,255,255,.08);
    border-radius: 12px; padding: 14px 16px; color: #ffffff; font-size: 1rem; outline: none; transition: all 0.3s;
  }
  .form-input:focus { border-color: rgba(124,92,255,.5); box-shadow: 0 0 0 4px rgba(124,92,255,.1); }

  .submit-btn {
    width: 100%; border: none; border-radius: 12px; padding: 16px; font-size: 1rem; font-weight: 800;
    color: #ffffff; cursor: pointer; transition: all 0.3s ease; display: inline-block; text-align: center; text-decoration: none;
  }
  .admin-btn { background: linear-gradient(135deg, #7c5cff, #6445e8); box-shadow: 0 8px 20px rgba(124,92,255,.3); }
  .admin-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 25px rgba(124,92,255,.5); }
  
  .member-btn { background: linear-gradient(135deg, #2ee9a6, #24bd86); color:#07101f; box-shadow: 0 8px 20px rgba(46,233,166,.3); }
  .member-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 25px rgba(46,233,166,.5); color:#000; }

  .security-badge { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 18px; position: relative; z-index: 2; }
  .security-badge span { color: rgba(169,183,208,.45); font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; }
  .security-dot { width: 6px; height: 6px; border-radius: 50%; background: #2ee9a6; box-shadow: 0 0 8px rgba(46,233,166,.5); }
</style>
</head>
<body>

<div class="login-container">
  <div class="login-card">
    
    <div class="brand-header">
      <div class="brand-icon">✝</div>
      <h1 class="brand-title"><?= e($appName) ?></h1>
      <div class="brand-subtitle">Church Management System</div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <!-- TOP SECTION: ADMIN LOGIN -->
    <div class="auth-section admin-section">
      <div class="section-title">Admin Account</div>
      <form method="post" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerText = 'Authenticating...';">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="target_role" value="admin">
        <div class="form-group">
          <label class="form-label">Admin Username</label>
          <input class="form-input" name="username" placeholder="Enter 'admin'" required autocomplete="off">
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input class="form-input" type="password" name="password" placeholder="Enter password (e.g. 123)" required autocomplete="off">
        </div>
        <button class="submit-btn admin-btn" type="submit">Log in to Admin</button>
      </form>
    </div>

    <!-- BOTTOM SECTION: MEMBER AUTO-CREATE / LOGIN -->
    <div class="auth-section member-section">
      <div class="section-title" style="color:#2ee9a6;">Member Account</div>
      <p style="color:rgba(169,183,208,.8); font-size:0.8rem; text-align:center; margin-bottom:16px;">
        Login here or create a new profile instantly.
      </p>
      <form method="post" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerText = 'Processing...';">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="target_role" value="member">
        <div class="form-group">
          <label class="form-label">Member Username</label>
          <input class="form-input" name="username" placeholder="Choose a username" required autocomplete="off">
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input class="form-input" type="password" name="password" placeholder="Set your password" required autocomplete="off">
        </div>
        <button class="submit-btn member-btn" type="submit">Login / Create as New Member</button>
      </form>
    </div>

    <div class="security-badge">
      <div class="security-dot"></div>
      <span>256-bit Secure Connection</span>
    </div>

  </div>
</div>

</body>
</html>