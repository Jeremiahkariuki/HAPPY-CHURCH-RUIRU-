<?php
declare(strict_types=1);
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/helpers.php";

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
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, status) VALUES (?, ?, ?, ?, 'Pending')");
                    $stmt->execute([$username, $email, $hash, $role]);
                } catch (PDOException $e) {
                    // If error is about missing status column, try to add it
                    if (strpos($e->getMessage(), "Unknown column 'status'") !== false) {
                        $pdo->exec("ALTER TABLE users ADD COLUMN status varchar(20) NOT NULL DEFAULT 'Pending' AFTER role");
                        // Try insert again
                        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, status) VALUES (?, ?, ?, ?, 'Pending')");
                        $stmt->execute([$username, $email, $hash, $role]);
                    } else {
                        throw $e;
                    }
                }
                $newId = (int)$pdo->lastInsertId();
                $otp = (string)rand(100000, 999999);
                $pdo->prepare("UPDATE users SET otp_code = ? WHERE id = ?")->execute([$otp, $newId]);

                $success = "Account created! A welcome email with your OTP has been sent. Please <a href='verify_otp.php?id=$newId' style='color:var(--brand2); font-weight:900;'>click here to verify your account now</a>.";
                
                // Welcome Notification via Brevo
                $subj = "Welcome to HAPPY CHURCH RUIRU • Verify Your Account";
                $verifyLink = "https://" . ($_SERVER['HTTP_HOST'] ?? 'happy-church-ruiru-tsln.onrender.com') . "/verify_otp.php?id=$newId";
                $msg  = "Dear <strong>$username</strong>,<br><br>" .
                        "Welcome to our church family! We are thrilled to have you join us online.<br><br>" .
                        "<strong>Your Verification OTP:</strong> <span style='font-size:1.5rem; color:#7c5cff; font-weight:950;'>$otp</span><br><br>" .
                        "Please use the code above to verify your account here:<br>" .
                        "<a href='$verifyLink' style='background:#7c5cff; color:#fff; padding:10px 20px; text-decoration:none; border-radius:8px; display:inline-block; margin:10px 0;'>Verify My Account Now</a><br><br>" .
                        "Once verified, your account will be automatically approved for login.<br><br>" .
                        "God bless you!";
                
                send_church_email($email, $subj, $msg);
                }
            }
        } catch (PDOException $e) {
            // Check specifically for duplicate entry (which happens instantly if a user double-taps on mobile)
            if ($e->getCode() == 23000) {
                if (stripos($e->getMessage(), 'email') !== false) {
                    $error = "This email address is already registered.";
                } elseif (stripos($e->getMessage(), 'username') !== false) {
                    $error = "This username is already taken.";
                } else {
                    $error = "An account with these details already exists.";
                }
            } else {
                $error = "Database error: " . $e->getMessage();
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
<title>Register • Church Events System</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div style="max-width:980px;margin:0 auto;padding:28px;">
<div class="card" style="max-width:560px;margin:5vh auto;background:linear-gradient(135deg, rgba(124,92,255,.16), rgba(46,233,166,.09));">
<div style="display:flex;gap:14px;align-items:center;">
<div class="brand-mark">✝</div>
<div>
<div style="font-weight:950;font-size:1.35rem;">Church Events System</div>
<div class="small">Create New Account</div>
</div>
</div>

<?php if ($error): ?>
<div class="flash error" style="margin-top:14px;"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="flash success" style="margin-top:14px;"><?= e($success) ?></div>
<div style="margin-top:14px;"><a href="login.php" class="btn">Go to Login</a></div>
<?php else: ?>

<form method="post" style="margin-top:16px;display:grid;gap:12px;" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerText = 'Starting...';">
<div>
<label class="small">Username</label>
<input class="input" name="username" placeholder="Choose a username" required value="<?= e($username ?? "") ?>" autocorrect="off" autocapitalize="none">
</div>
<div>
<label class="small">Email Address (Gmail preferred)</label>
<input class="input" type="email" name="email" placeholder="email@gmail.com" required value="<?= e($email ?? "") ?>" autocorrect="off" autocapitalize="none">
</div>
<div>
<label class="small">Desired Role</label>
<select class="input" name="role" style="background:#0f1a2e; color:white;">
<option value="Member">Member</option>
<option value="Receptionist">Receptionist</option>
<option value="Volunteer">Volunteer</option>
</select>
</div>
<div>
<label class="small">Password</label>
<input class="input" type="password" name="password" placeholder="Min 6 characters" required>
</div>
<div>
<label class="small">Confirm Password</label>
<input class="input" type="password" name="confirm_password" placeholder="Repeat password" required>
</div>
<button class="btn" type="submit">Create Account</button>
</form>

<div class="small" style="margin-top:14px;">
Already have an account? <a href="login.php" style="color:var(--brand);font-weight:800;text-decoration:none;">Login here</a>
</div>
<?php endif; ?>

</div>
</div>
</body>
</html>
