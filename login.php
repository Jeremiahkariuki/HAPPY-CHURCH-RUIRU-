<?php
declare(strict_types=1);

session_start();

/* Load database connection */
$db_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . "db.php";
require_once $db_path;

/* Confirm PDO exists */
if ($pdo === null) {
    $db_error = true;
} else {
    $db_error = false;
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, "UTF-8");
}

/* Ensure admin account exists */
function ensure_admin(PDO $pdo): void {

    $pdo->query("SELECT 1 FROM users LIMIT 1");

    $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(username)=LOWER(?) LIMIT 1");
    $stmt->execute(["admin"]);
    $id = (int)($stmt->fetchColumn() ?: 0);

    $hash = password_hash("123", PASSWORD_DEFAULT);

    if ($id === 0) {
        $ins = $pdo->prepare(
            "INSERT INTO users (username,password_hash,role) VALUES (?,?, 'admin')"
        );
        $ins->execute(["admin",$hash]);
    }
}

$error = "";
$username_value = "";

try {
    if (!$db_error) {
        ensure_admin($pdo);
    }
} catch (Throwable $e) {
    $db_error = true;
}

if ($db_error) {
    $error = "Database error. Ensure the users table exists.";
}

/* Login process */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if ($db_error) {
        $error = "Database connection failed. Please ensure the database is set up.";
    } else {

    $username_value = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $pdo->prepare(
        "SELECT id,username,role,password_hash 
         FROM users 
         WHERE LOWER(username)=LOWER(?) 
         LIMIT 1"
    );

    $stmt->execute([$username_value]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password,$user["password_hash"])) {

        session_regenerate_id(true);

        $_SESSION["user"] = [
            "id" => $user["id"],
            "username" => $user["username"],
            "role" => $user["role"]
        ];

        header("Location: dashboard.php");
        exit;
    }

    $error = "Invalid username or password.";
    }
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login • Church Events System</title>
<link rel="stylesheet" href="style.css">
</head>

<body>

<div style="max-width:980px;margin:0 auto;padding:28px;">

<div class="card" style="max-width:560px;margin:10vh auto;background:linear-gradient(135deg, rgba(124,92,255,.16), rgba(46,233,166,.09));">

<div style="display:flex;gap:14px;align-items:center;">
<div class="brand-mark">✝</div>

<div>
<div style="font-weight:950;font-size:1.35rem;">Church Events System</div>
<div class="small">Secure Admin Login</div>
</div>
</div>

<?php if ($error): ?>
<div class="flash error" style="margin-top:14px;">
<?= e($error) ?>
</div>
<?php endif; ?>

<form method="post" style="margin-top:16px;display:grid;gap:12px;">

<div>
<label class="small">Username</label>
<input class="input" name="username" placeholder="Enter username" required value="<?= e($username_value) ?>">
</div>

<div>
<label class="small">Password</label>
<input class="input" type="password" name="password" placeholder="Enter password" required>
</div>

<button class="btn" type="submit">Login</button>

</form>

<div class="small" style="margin-top:14px;">
“Let all things be done decently and in order.” — <b>1 Corinthians 14:40</b>
</div>

</div>
</div>

</body>
</html>