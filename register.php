<?php
declare(strict_types=1);
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/helpers.php";

// Load church name from config (single source of truth)
$_cfg = require __DIR__ . "/config.php";
$appName = $_cfg["app"]["name"] ?? "LOVE CHURCH";
unset($_cfg);

$error = "";
if (!isset($pdo) || $pdo === null) {
    $error = isset($db_connect_error) ? $db_connect_error : "Database connection unavailable. Please ensure MySQL is running.";
}
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim((string)($_POST["username"] ?? ""));
    $email    = trim((string)($_POST["email"] ?? ""));
    $password = (string)($_POST["password"] ?? "");
    $confirm  = (string)($_POST["confirm_password"] ?? "");
    $role     = trim((string)($_POST["role"] ?? "Member"));

    if ($username === "" || $email === "" || $password === "") {
        $error = "Please fill all fields.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif (!$pdo) {
        $error = "Database connection unavailable. Please start MySQL in XAMPP.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = "Username already taken.";
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = "This email address is already registered.";
                } else {
                    // Self-healing: Ensure email column exists
                try {
                    $pdo->query("SELECT email FROM users LIMIT 1");
                } catch (Exception $e) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN email varchar(100) DEFAULT NULL AFTER username");
                    $pdo->exec("ALTER TABLE users ADD UNIQUE (email)");
                }

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $status = ($role === 'admin' || $role === 'Admin') ? 'Approved' : 'Pending';
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $hash, $role, $status]);
                } catch (PDOException $e) {
                    // If error is about missing status column, try to add it
                    if (strpos($e->getMessage(), "Unknown column 'status'") !== false) {
                        $pdo->exec("ALTER TABLE users ADD COLUMN status varchar(20) NOT NULL DEFAULT 'Pending' AFTER role");
                        // Try insert again
                        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$username, $email, $hash, $role, $status]);
                    } else {
                        throw $e;
                    }
                }
                $newId = (int)$pdo->lastInsertId();
                $otp = (string)rand(100000, 999999);
                try {
                    $pdo->prepare("UPDATE users SET otp_code = ? WHERE id = ?")->execute([$otp, $newId]);
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), "Unknown column 'otp_code'") !== false) {
                        $pdo->exec("ALTER TABLE users ADD COLUMN otp_code varchar(10) DEFAULT NULL AFTER status");
                        $pdo->prepare("UPDATE users SET otp_code = ? WHERE id = ?")->execute([$otp, $newId]);
                    } else {
                        throw $e;
                    }
                }

                $success = "Account created! A welcome email with your OTP has been sent. Please <a href='verify_otp.php?id=$newId' style='color:var(--brand2); font-weight:900;'>click here to verify your account now</a>.";
                
                // Welcome Notification via Brevo
                $subj = "Welcome to " . $appName . " • Verify Your Account";
                $verifyLink = "https://" . ($_SERVER['HTTP_HOST'] ?? 'happy-church-ruiru-tsln.onrender.com') . "/verify_otp.php?id=$newId";
                $msg  = "Dear <strong>$username</strong>,<br><br>" .
                        "Welcome to our church family! We are thrilled to have you join us online.<br><br>" .
                        "<strong>Your Verification OTP:</strong> <span style='font-size:1.5rem; color:#7c5cff; font-weight:950;'>$otp</span><br><br>" .
                        "Please use the code above to verify your account here:<br>" .
                        "<a href='$verifyLink' style='background:#7c5cff; color:#fff; padding:10px 20px; text-decoration:none; border-radius:8px; display:inline-block; margin:10px 0;'>Verify My Account Now</a><br><br>" .
                        "Once verified, your account will be automatically approved for login.<br><br>" .
                        "God bless you!";
                
                $ok = send_church_email($email, $subj, $msg);
                if (!$ok) {
                    $success = "Account created! **SYSTEM NOTE:** We couldn't send the email yet, so please USE THIS CODE TO VERIFY: <strong style='font-size:1.4rem; color:var(--brand2);'>$otp</strong>. <a href='verify_otp.php?id=$newId' style='color:#fff; text-decoration:underline;'>Click here to verify now.</a>";
                }
                }
            }
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            // Check specifically for duplicate entry
            if (strpos($msg, '23000') !== false || strpos($msg, '1062') !== false) {
                if (stripos($msg, 'email') !== false) {
                    $error = "This email address is already registered.";
                } elseif (stripos($msg, 'username') !== false) {
                    $error = "This username is already taken.";
                } else {
                    $error = "An account with these details already exists.";
                }
            } else {
                $error = "Database error: " . $msg;
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register • Happy Church Ruiru</title>
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
    padding: 40px 0;
    position: relative;
    overflow-x: hidden;
  }

  body::before, body::after {
    content: '';
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.4;
    animation: float 12s ease-in-out infinite;
    pointer-events: none;
    z-index: 0;
  }
  body::before {
    width: 600px; height: 600px;
    background: rgba(124,92,255,.25);
    top: -150px; left: -150px;
  }
  body::after {
    width: 500px; height: 500px;
    background: rgba(46,233,166,.15);
    bottom: -100px; right: -100px;
    animation-delay: -6s;
    animation-direction: reverse;
  }
  @keyframes float {
    0%, 100% { transform: translate(0,0) scale(1); }
    25% { transform: translate(40px, -30px) scale(1.05); }
    50% { transform: translate(-20px, 50px) scale(0.95); }
    75% { transform: translate(30px, 20px) scale(1.02); }
  }

  .register-container {
    width: 100%;
    max-width: 520px;
    padding: 20px;
    position: relative;
    z-index: 2;
  }

  .register-card {
    background: rgba(15, 26, 46, 0.88);
    border: 1px solid rgba(255,255,255,.08);
    border-top: 1px solid rgba(124,92,255,.25);
    border-radius: 32px;
    box-shadow: 0 40px 80px rgba(0,0,0,.6);
    backdrop-filter: blur(28px);
    padding: 48px;
    position: relative;
    animation: cardIn 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards;
  }
  @keyframes cardIn {
    from { opacity: 0; transform: translateY(40px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .brand-header {
    text-align: center;
    margin-bottom: 32px;
  }
  .brand-mark {
    width: 64px; height: 64px;
    margin: 0 auto 16px;
    border-radius: 20px;
    background: linear-gradient(135deg, #7c5cff, #2ee9a6);
    display: grid; place-items: center;
    font-size: 28px; font-weight: 950; color: #07101f;
    box-shadow: 0 12px 30px rgba(124,92,255,.35);
  }
  .brand-title {
    font-weight: 900; font-size: 1.5rem; letter-spacing: 1px; color: #fff; text-transform: uppercase;
  }
  .brand-subtitle { color: #a9b7d0; font-size: 0.85rem; font-weight: 600; margin-top: 4px; }

  .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 24px; }
  .form-group { margin-bottom: 20px; }
  .full-width { grid-column: span 2; }
  
  .form-label {
    display: block; margin-bottom: 8px; font-weight: 800;
    color: #a9b7d0; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 1px;
  }
  .form-input {
    width: 100%; padding: 14px 16px; border-radius: 14px;
    border: 1px solid rgba(255,255,255,.08); background: rgba(0,0,0,.35);
    color: #eaf2ff; font-size: 0.95rem; font-weight: 500; font-family: inherit;
    outline: none; transition: all 0.2s;
  }
  .form-input:focus { border-color: #7c5cff; background: rgba(0,0,0,.45); box-shadow: 0 0 0 3px rgba(124,92,255,.15); }

  .register-btn {
    width: 100%; padding: 16px; font-size: 1rem; font-weight: 800;
    background: linear-gradient(135deg, #7c5cff, #2ee9a6); color: #07101f;
    border: none; border-radius: 16px; cursor: pointer;
    box-shadow: 0 10px 25px rgba(124,92,255,.3); transition: all 0.25s;
    margin-top: 10px;
  }
  .register-btn:hover { transform: translateY(-2px); box-shadow: 0 14px 35px rgba(124,92,255,.45); }
  .register-btn:disabled { opacity: 0.6; cursor: not-allowed; }

  .flash {
    padding: 14px 18px; border-radius: 14px; margin-bottom: 24px;
    font-weight: 700; font-size: 0.9rem; border: 1px solid transparent;
  }
  .flash.error { background: rgba(255,77,109,.08); border-color: rgba(255,77,109,.25); color: #ff6b8a; }
  .flash.success { background: rgba(46,233,166,.08); border-color: rgba(46,233,166,.25); color: #2ee9a6; line-height: 1.6; }

  .footer-link {
    text-align: center; margin-top: 24px; color: #a9b7d0; font-size: 0.9rem;
  }
  .footer-link a { color: #7c5cff; font-weight: 800; text-decoration: none; }
  .footer-link a:hover { text-decoration: underline; }

  @media (max-width: 480px) {
    .form-grid { grid-template-columns: 1fr; }
    .full-width { grid-column: span 1; }
    .register-card { padding: 32px 24px; }
  }
</style>
</head>
<body>

<div class="register-container">
  <div class="register-card">
    <div class="brand-header">
      <div class="brand-mark">✝</div>
      <h1 class="brand-title">Create Account</h1>
      <p class="brand-subtitle">Join <?= e($appName) ?> Community</p>
    </div>

    <?php if ($error): ?>
      <div class="flash error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="flash success">
        <?= $success ?>
      </div>
      <div style="text-align:center; margin-top:20px;">
        <a href="login.php" class="register-btn" style="display:inline-block; text-decoration:none; text-align:center;">Return to Login</a>
      </div>
    <?php else: ?>
      <form method="post" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerText = 'Creating Account...';">
        <div class="form-grid">
          <div class="form-group full-width">
            <label class="form-label">Full Username</label>
            <input class="form-input" name="username" placeholder="Choose a username" required value="<?= e($username ?? "") ?>" autocomplete="username">
          </div>
          
          <div class="form-group full-width">
            <label class="form-label">Email Address</label>
            <input class="form-input" type="email" name="email" placeholder="yourname@gmail.com" required value="<?= e($email ?? "") ?>" autocomplete="email">
          </div>

          <div class="form-group full-width">
            <label class="form-label">Role</label>
            <select class="form-input" name="role" style="appearance: none; background-image: url('data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'24\' height=\'24\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23a9b7d0\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Cpolyline points=\'6 9 12 15 18 9\'%3E%3C/polyline%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 12px center; background-size: 16px;">
              <option value="Member">Member</option>
              <option value="Volunteer">Volunteer</option>
              <option value="Receptionist">Receptionist</option>
              <option value="Admin">Admin</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Password</label>
            <input class="form-input" type="password" name="password" placeholder="Min 6 chars" required autocomplete="new-password">
          </div>

          <div class="form-group">
            <label class="form-label">Confirm</label>
            <input class="form-input" type="password" name="confirm_password" placeholder="Repeat it" required autocomplete="new-password">
          </div>
        </div>

        <button class="register-btn" type="submit">✨ Create My Account</button>
      </form>

      <div class="footer-link">
        Already have an account? <a href="login.php">Login here</a>
      </div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
