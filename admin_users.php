<?php
declare(strict_types=1);
require_once __DIR__ . "/auth.php";
require_login();

// Only Admins can access this page
if (($_SESSION["user"]["role"] ?? "") !== "admin") {
    header("Location: dashboard.php");
    exit;
}

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/helpers.php";

$message = "";
$type = "success";

// Handle Actions
if (isset($_GET["action"]) && isset($_GET["id"])) {
    $id = (int)$_GET["id"];
    $action = $_GET["action"];
    
    if ($action === "approve") {
        $stmt = $pdo->prepare("UPDATE users SET status = 'Approved' WHERE id = ?");
        $stmt->execute([$id]);
        $message = "User Approved Successfully!";
    } elseif ($action === "reject") {
        $stmt = $pdo->prepare("UPDATE users SET status = 'Rejected' WHERE id = ?");
        $stmt->execute([$id]);
        $message = "User Rejected.";
        $type = "error";
    } elseif ($action === "delete") {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$id]);
        $message = "User Deleted.";
        $type = "error";
    }
}

$users_list = $pdo->query("SELECT id, username, role, status, created_at FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . "/header.php";
?>

<div class="hero">
  <h1 class="heroTitle">User Management</h1>
  <div class="heroSub">Approve or Reject new account requests</div>
</div>

<?php if ($message): ?>
    <div class="flash <?= $type ?>"><?= e($message) ?></div>
<?php endif; ?>

<div class="card" style="margin-top:20px;">
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse: collapse;">
            <thead>
                <tr style="text-align:left; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <th style="padding:12px;">Username</th>
                    <th style="padding:12px;">Role</th>
                    <th style="padding:12px;">Status</th>
                    <th style="padding:12px;">Requested At</th>
                    <th style="padding:12px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users_list)): ?>
                    <tr><td colspan="5" style="padding:20px; text-align:center; color:var(--muted);">No users found.</td></tr>
                <?php endif; ?>
                <?php foreach ($users_list as $u): ?>
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <td style="padding:12px; font-weight:800;"><?= e($u["username"]) ?></td>
                        <td style="padding:12px;"><span class="tag"><?= e($u["role"]) ?></span></td>
                        <td style="padding:12px;">
                            <?php 
                                $status_class = "pending";
                                if ($u["status"] === "Approved") $status_class = "success";
                                if ($u["status"] === "Rejected") $status_class = "error";
                            ?>
                            <span class="tag <?= $status_class ?>" style="opacity:0.9;"><?= e($u["status"]) ?></span>
                        </td>
                        <td style="padding:12px; font-size:0.85rem; color:var(--muted);"><?= e($u["created_at"]) ?></td>
                        <td style="padding:12px; display:flex; gap:8px;">
                            <?php if ($u["status"] === "Pending"): ?>
                                <a href="admin_users.php?action=approve&id=<?= $u["id"] ?>" class="btn" style="padding:4px 10px; font-size:0.75rem;">Approve</a>
                                <a href="admin_users.php?action=reject&id=<?= $u["id"] ?>" class="btn btn-ghost" style="padding:4px 10px; font-size:0.75rem; color:#ff4d6d;">Reject</a>
                            <?php else: ?>
                                <a href="admin_users.php?action=delete&id=<?= $u["id"] ?>" class="btn btn-ghost" style="padding:4px 10px; font-size:0.75rem; color:#ff4d6d;" onclick="return confirm('Relly delete this user?')">Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
