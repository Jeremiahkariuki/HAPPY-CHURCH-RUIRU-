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
<title>Verify OTP • Church Events System</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div style="max-width:980px;margin:0 auto;padding:28px;">
<div class="card" style="max-width:480px;margin:10vh auto;background:linear-gradient(135deg, rgba(124,92,255,.16), rgba(46,233,166,.09)); text-align:center;">
<div class="brand-mark" style="margin:0 auto 14px;">🔐</div>
<div style="font-weight:950;font-size:1.5rem;">Verification Required</div>
<div class="small" style="margin-top:4px;">Enter the 6-digit code sent to your email.</div>

<?php if ($error): ?>
<div class="flash error" style="margin-top:14px;"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="flash success" style="margin-top:14px;"><?= e($success) ?></div>
<div style="margin-top:14px;"><a href="login.php" class="btn">Proceed to Login</a></div>
<?php else: ?>

<form method="post" style="margin-top:20px;display:grid;gap:16px;">
<input type="hidden" name="id" value="<?= $user_id ?>">
<div>
<input class="input" name="otp" type="text" maxlength="6" placeholder="000000" required 
       style="font-size:2rem; text-align:center; letter-spacing:10px; font-weight:950; border-color:var(--brand2); border-radius:12px; height:80px;">
</div>
<button class="btn" type="submit" style="height:54px; font-size:1rem; font-weight:900;">Verify & Approve Account</button>
</form>

<div class="small" style="margin-top:14px;">
Didn't receive the email? Check your Spam folder.
</div>
<?php endif; ?>

</div>
</div>
</body>
</html>
