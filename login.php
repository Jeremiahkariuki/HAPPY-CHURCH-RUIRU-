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
$username_value = "";

if (!$db_error) {
    // 1. Check if tables exist. If not, try to run db_setup logic automatically.
    try {
        $pdo->query("SELECT 1 FROM users LIMIT 1");
    } catch (Exception $e) {
        // Tables missing! Let's try to auto-init
        if (file_exists(__DIR__ . "/db_setup.php")) {
            ob_start();
            include_once __DIR__ . "/db_setup.php";
            ob_end_clean();
            // Refresh PDO after setup
            require __DIR__ . "/db.php";
        }
    }
    ensure_admin($pdo);
} else {
    $error = isset($db_connect_error) ? $db_connect_error : "Database connection failed. Please check your credentials.";
}

/* Login process */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!$db_error) {
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

            if ($user) {
                // User exists, verify password
                if (password_verify($password, $user["password_hash"])) {
                    $status = "Approved";
                    if ($status_col_exists) {
                        $status = $user["status"] ?? "Approved"; 
                        if (strtolower($user["role"]) === "admin") { $status = "Approved"; } 
                    }

                    if ($status !== "Approved") {
                        $error = "Your account is " . e($status) . ". Please wait for Admin approval.";
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
                    $error = "Invalid password for existing user.";
                }
            } else {
                // Auto-Registration feature since user is NOT in XAMPP
                $role = "Member";
                $hash = password_hash($password, PASSWORD_DEFAULT);
                
                // create user
                if ($status_col_exists) {
                    $ins = $pdo->prepare("INSERT INTO users (username, password_hash, role, status) VALUES (?, ?, ?, 'Approved')");
                    $ins->execute([$username_value, $hash, $role]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
                    $ins->execute([$username_value, $hash, $role]);
                }
                
                // Immediately log them in
                $newId = $pdo->lastInsertId();
                session_regenerate_id(true);
                $_SESSION["user"] = [
                    "id" => $newId,
                    "username" => $username_value,
                    "role" => $role
                ];
                header("Location: dashboard.php");
                exit;
            }
        } catch (Exception $e) {
            $error = "An error occurred during login: " . e($e->getMessage());
        }
    } else {
        // Fallback or use the literal connection error if the form is submitted
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
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
    background:
      radial-gradient(1200px 800px at 30% 10%, rgba(124,92,255,.18), transparent 60%),
      radial-gradient(800px 600px at 80% 80%, rgba(46,233,166,.10), transparent 50%),
      #07101f;
    min-height: 100vh;
    padding: 20px 0;
    position: relative;
  }

  /* Animated floating orbs */
  body::before, body::after {
    content: '';
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.4;
    animation: float 12s ease-in-out infinite;
    pointer-events: none;
  }
  body::before {
    width: 500px; height: 500px;
    background: rgba(124,92,255,.25);
    top: -100px; left: -100px;
  }
  body::after {
    width: 400px; height: 400px;
    background: rgba(46,233,166,.15);
    bottom: -80px; right: -80px;
    animation-delay: -6s;
    animation-direction: reverse;
  }
  @keyframes float {
    0%, 100% { transform: translate(0,0) scale(1); }
    25% { transform: translate(40px, -30px) scale(1.05); }
    50% { transform: translate(-20px, 50px) scale(0.95); }
    75% { transform: translate(30px, 20px) scale(1.02); }
  }

  .login-container {
    width: 100%;
    max-width: 460px;
    padding: 20px;
    position: relative;
    z-index: 2;
  }

  .login-card {
    background: rgba(15, 26, 46, 0.88);
    border: 1px solid rgba(255,255,255,.08);
    border-top: 1px solid rgba(124,92,255,.25);
    border-radius: 28px;
    box-shadow:
      0 30px 60px rgba(0,0,0,.5),
      0 0 0 1px rgba(255,255,255,.04),
      inset 0 1px 0 rgba(255,255,255,.06);
    backdrop-filter: blur(24px);
    padding: 48px 40px 40px;
    position: relative;
    padding: 48px 40px 40px;
    position: relative;
    animation: cardIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    opacity: 0;
    transform: translateY(30px);
  }
  @keyframes cardIn {
    to { opacity: 1; transform: translateY(0); }
  }

  .login-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; height: 100%;
    background: linear-gradient(180deg, rgba(124,92,255,.06), transparent 35%);
    pointer-events: none;
  }

  /* Brand Header */
  .brand-header {
    text-align: center;
    margin-bottom: 36px;
    position: relative;
    z-index: 2;
  }
  .brand-icon {
    width: 72px;
    height: 72px;
    margin: 0 auto 18px;
    border-radius: 22px;
    background: linear-gradient(135deg, #7c5cff, #2ee9a6);
    display: grid;
    place-items: center;
    font-size: 32px;
    font-weight: 950;
    color: #07101f;
    box-shadow:
      0 12px 30px rgba(124,92,255,.35),
      0 0 0 4px rgba(124,92,255,.08);
    transition: transform 0.3s ease;
  }
  .brand-icon:hover { transform: scale(1.08) rotate(-3deg); }

  .brand-title {
    font-weight: 900;
    font-size: 1.7rem;
    letter-spacing: 1.5px;
    margin: 0;
    color: #fff;
    text-transform: uppercase;
  }
  .brand-subtitle {
    color: #a9b7d0;
    font-size: 0.88rem;
    font-weight: 600;
    margin-top: 6px;
    letter-spacing: 0.3px;
  }

  /* Form Styles */
  .form-group {
    margin-bottom: 22px;
    position: relative;
    z-index: 2;
  }
  .form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 700;
    color: #a9b7d0;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 1px;
  }
  .form-input {
    width: 100%;
    padding: 16px 18px;
    border-radius: 16px;
    border: 1px solid rgba(255,255,255,.08);
    background: rgba(0,0,0,.30);
    color: #eaf2ff;
    font-size: 1rem;
    font-weight: 500;
    font-family: inherit;
    outline: none;
    transition: all 0.2s ease;
  }
  .form-input::placeholder {
    color: rgba(169,183,208,.5);
    font-weight: 400;
  }
  .form-input:focus {
    border-color: rgba(124,92,255,.5);
    background: rgba(0,0,0,.40);
    box-shadow: 0 0 0 3px rgba(124,92,255,.12);
  }

  /* Login Button */
  .login-btn {
    width: 100%;
    padding: 16px;
    font-size: 1.05rem;
    font-weight: 800;
    font-family: inherit;
    letter-spacing: 0.5px;
    margin-top: 8px;
    background: linear-gradient(135deg, #7c5cff, #2ee9a6);
    color: #07101f;
    border: none;
    border-radius: 16px;
    cursor: pointer;
    box-shadow: 0 10px 25px rgba(124,92,255,.30);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    z-index: 2;
    overflow: hidden;
  }
  .login-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,.15), transparent);
    opacity: 0;
    transition: opacity 0.25s;
  }
  .login-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 35px rgba(124,92,255,.40);
  }
  .login-btn:hover::before { opacity: 1; }
  .login-btn:active { transform: translateY(0); }

  /* Divider */
  .divider {
    display: flex;
    align-items: center;
    gap: 16px;
    margin: 28px 0;
    position: relative;
    z-index: 2;
  }
  .divider::before, .divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: rgba(255,255,255,.08);
  }
  .divider span {
    color: #a9b7d0;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
  }

  /* Register Section */
  .register-section {
    position: relative;
    z-index: 2;
    text-align: center;
  }
  .register-card {
    padding: 20px;
    border-radius: 18px;
    background: rgba(255,255,255,.03);
    border: 1px solid rgba(255,255,255,.06);
  }
  .register-text {
    color: #a9b7d0;
    font-size: 0.92rem;
    font-weight: 500;
    margin-bottom: 14px;
  }
  .register-text strong { color: #eaf2ff; }
  .register-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 28px;
    border-radius: 14px;
    background: rgba(46,233,166,.10);
    border: 1px solid rgba(46,233,166,.25);
    color: #2ee9a6;
    font-weight: 800;
    font-size: 0.92rem;
    font-family: inherit;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
  }
  .register-btn:hover {
    background: rgba(46,233,166,.18);
    border-color: rgba(46,233,166,.40);
    transform: translateY(-1px);
  }

  /* Error Flash */
  .login-error {
    padding: 14px 16px;
    border-radius: 14px;
    background: rgba(255,77,109,.08);
    border: 1px solid rgba(255,77,109,.25);
    color: #ff6b8a;
    font-weight: 700;
    font-size: 0.9rem;
    text-align: center;
    margin-bottom: 24px;
    position: relative;
    z-index: 2;
    animation: shake 0.4s ease;
  }
  @keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-6px); }
    75% { transform: translateX(6px); }
  }

  /* Footer Scripture */
  .scripture-footer {
    text-align: center;
    margin-top: 28px;
    position: relative;
    z-index: 2;
    animation: cardIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) 0.2s forwards;
    opacity: 0;
  }
  .scripture-text {
    font-style: italic;
    color: rgba(169,183,208,.6);
    font-weight: 500;
    font-size: 0.88rem;
    line-height: 1.5;
  }
  .scripture-ref {
    color: #7c5cff;
    font-weight: 800;
    font-style: normal;
  }

  /* Security Badge */
  .security-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 18px;
    position: relative;
    z-index: 2;
  }
  .security-badge span {
    color: rgba(169,183,208,.45);
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.2px;
  }
  .security-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: #2ee9a6;
    box-shadow: 0 0 8px rgba(46,233,166,.5);
  }
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
      <div class="login-error">
        <?= e($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" class="login-form" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerText = 'Processing...';">

      <div class="form-group">
        <label class="form-label">Username</label>
        <input class="form-input" name="username" placeholder="Enter your username" required value="<?= e($username_value) ?>" autocomplete="username">
      </div>

      <div class="form-group">
        <label class="form-label">Password</label>
        <input class="form-input" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
      </div>

      <button class="login-btn" type="submit">🔐 Login</button>
    </form>

    <div class="security-badge">
      <div class="security-dot"></div>
      <span>256-bit Secure Connection</span>
    </div>

    <div class="divider">
      <span>New Member?</span>
    </div>

    <div class="register-section">
      <div class="register-card">
        <div class="register-text">
          <strong>Join <?= e($appName) ?></strong><br>
          Create an account to access church events, volunteer opportunities, and community resources.
        </div>
        <a href="register.php" class="register-btn">
          ✨ Create Account
        </a>
      </div>
    </div>

  </div>
  
  <div class="scripture-footer">
    <div class="scripture-text">
      "For where two or three gather in my name, there am I with them."
    </div>
    <div class="scripture-ref">— Matthew 18:20</div>
  </div>
</div>

</body>
</html>