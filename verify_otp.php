<?php
declare(strict_types=1);
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/helpers.php";

$error = "";
$success = "";
$user_id = (int)($_GET["id"] ?? 0);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $otp = trim((string)($_POST["otp"] ?? ""));
    $id  = (int)($_POST["id"] ?? 0);

    if ($otp === "" || $id === 0) {
        $error = "Please enter the verification code sent to your email.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND otp_code = ?");
        $stmt->execute([$id, $otp]);
        $u = $stmt->fetch();

        if ($u) {
            // Correct OTP - Approve account automatically!
            $stmt = $pdo->prepare("UPDATE users SET status = 'Approved', otp_code = NULL WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Account verified! You can now log in to the dashboard.";
        } else {
            $error = "Invalid verification code. Please check your email.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verify Account • HAPPY CHURCH</title>
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
    position: relative;
    overflow: hidden;
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
  body::before { width: 500px; height: 500px; background: rgba(124,92,255,.25); top: -100px; left: -100px; }
  body::after { width: 400px; height: 400px; background: rgba(46,233,166,.15); bottom: -80px; right: -80px; animation-delay: -6s; animation-direction: reverse; }

  @keyframes float {
    0%, 100% { transform: translate(0,0) scale(1); }
    25% { transform: translate(40px, -30px) scale(1.05); }
    50% { transform: translate(-20px, 50px) scale(0.95); }
    75% { transform: translate(30px, 20px) scale(1.02); }
  }

  .verify-container { width: 100%; max-width: 480px; padding: 20px; position: relative; z-index: 2; }

  .verify-card {
    background: rgba(15, 26, 46, 0.88);
    border: 1px solid rgba(255,255,255,.08);
    border-top: 1px solid rgba(124,92,255,.25);
    border-radius: 32px;
    box-shadow: 0 40px 80px rgba(0,0,0,.6);
    backdrop-filter: blur(28px);
    padding: 48px;
    text-align: center;
    animation: cardIn 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards;
  }
  @keyframes cardIn { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }

  .icon-badge {
    width: 72px; height: 72px; margin: 0 auto 24px; border-radius: 22px;
    background: rgba(46,233,166,.1); border: 1px solid rgba(46,233,166,.25);
    display: grid; place-items: center; font-size: 32px;
    color: #2ee9a6; box-shadow: 0 10px 25px rgba(46,233,166,.1);
  }

  .title { font-weight: 900; font-size: 1.75rem; color: #fff; margin-bottom: 8px; }
  .subtitle { color: #a9b7d0; font-size: 0.95rem; font-weight: 500; line-height: 1.5; margin-bottom: 32px; }

  .otp-input {
    width: 100%; height: 80px; background: rgba(0,0,0,0.3);
    border: 2px solid rgba(46,233,166,0.3); border-radius: 20px;
    color: #fff; font-size: 2.5rem; text-align: center; letter-spacing: 12px;
    font-weight: 950; font-family: inherit; outline: none; transition: all 0.2s;
    margin-bottom: 24px;
  }
  .otp-input:focus { border-color: #2ee9a6; background: rgba(0,0,0,0.4); box-shadow: 0 0 20px rgba(46,233,166,0.15); }

  .verify-btn {
    width: 100%; padding: 18px; font-size: 1.1rem; font-weight: 800;
    background: linear-gradient(135deg, #7c5cff, #2ee9a6); color: #07101f;
    border: none; border-radius: 18px; cursor: pointer;
    box-shadow: 0 12px 30px rgba(124,92,255,.3); transition: all 0.25s;
  }
  .verify-btn:hover { transform: translateY(-2px); box-shadow: 0 16px 40px rgba(124,92,255,.45); }
  .verify-btn:active { transform: translateY(0); }

  .flash { padding: 16px; border-radius: 16px; margin-bottom: 24px; font-weight: 700; font-size: 0.95rem; }
  .flash.error { background: rgba(255,77,109,0.1); border: 1px solid rgba(255,77,109,0.3); color: #ff6b8a; }
  .flash.success { background: rgba(46,233,166,0.1); border: 1px solid rgba(46,233,166,0.3); color: #2ee9a6; }

  .back-link { margin-top: 24px; font-size: 0.9rem; font-weight: 600; }
  .back-link a { color: #7c5cff; text-decoration: none; }
  .back-link a:hover { text-decoration: underline; }
</style>
</head>
<body>

<div class="verify-container">
  <div class="verify-card">
    <div class="icon-badge">🔐</div>
    <h1 class="title">Verification</h1>
    <p class="subtitle">Enter the 6-digit security code sent to your email to activate your account.</p>

    <?php if ($error): ?>
      <div class="flash error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="flash success"><?= e($success) ?></div>
      <div style="margin-top:24px;">
        <a href="login.php" class="verify-btn" style="display:block; text-decoration:none;">Go to Dashboard</a>
      </div>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="id" value="<?= $user_id ?>">
        <input class="otp-input" name="otp" type="text" maxlength="6" placeholder="000000" required autofocus autocomplete="one-time-code">
        <button class="verify-btn" type="submit">Verify & Activate</button>
      </form>
      
      <div class="back-link">
        <span style="color:#a9b7d0; opacity:0.6;">Didn't get a code?</span> <a href="register.php">Try again</a>
      </div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
