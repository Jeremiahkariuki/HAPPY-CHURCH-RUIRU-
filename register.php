<?php
declare(strict_types=1);
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/helpers.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim((string)($_POST["username"] ?? ""));
    $password = (string)($_POST["password"] ?? "");
    $confirm  = (string)($_POST["confirm_password"] ?? "");
    $role     = trim((string)($_POST["role"] ?? "Member"));

    if ($username === "" || $password === "") {
        $error = "Please fill all fields.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = "Username already taken.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, status) VALUES (?, ?, ?, 'Pending')");
                    $stmt->execute([$username, $hash, $role]);
                } catch (PDOException $e) {
                    // If error is about missing status column, try to add it
                    if (strpos($e->getMessage(), "Unknown column 'status'") !== false) {
                        $pdo->exec("ALTER TABLE users ADD COLUMN status varchar(20) NOT NULL DEFAULT 'Pending' AFTER role");
                        // Try insert again
                        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, status) VALUES (?, ?, ?, 'Pending')");
                        $stmt->execute([$username, $hash, $role]);
                    } else {
                        throw $e;
                    }
                }
                $success = "Account created! Please wait for Admin approval before logging in.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
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

<form method="post" style="margin-top:16px;display:grid;gap:12px;">
<div>
<label class="small">Username</label>
<input class="input" name="username" placeholder="Choose a username" required value="<?= e($username ?? "") ?>">
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
