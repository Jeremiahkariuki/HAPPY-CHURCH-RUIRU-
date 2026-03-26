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

$users_list = $pdo->query("SELECT id, username, email, role, status, created_at FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . "/header.php";
?>

<div style="margin-bottom: 20px;">
  <a class="btn btn-ghost" href="dashboard.php">← Back to Dashboard</a>
</div>

<div class="grid">
  <div class="col-12">
    <div class="card" style="background: linear-gradient(135deg, rgba(124,92,255,.12), rgba(46,233,166,.06));">
      <div style="font-weight:950; font-size:1.6rem;">User Management</div>
      <div class="small">Review, Approve, or Reject new membership and staff account requests.</div>
    </div>
  </div>

  <?php if ($message): ?>
      <div class="col-12"><div class="flash <?= $type ?>"><?= e($message) ?></div></div>
  <?php endif; ?>

  <div class="col-12">
    <div class="card">
      <div style="overflow-x:auto;">
        <table class="table">
          <thead>
            <tr>
              <th>User Details</th>
              <th>Role</th>
              <th>Status</th>
              <th class="hide-mobile">Requested</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users_list)): ?>
              <tr><td colspan="5" style="text-align:center; padding:60px; color:var(--muted);">No users currently in the system.</td></tr>
            <?php endif; ?>
            <?php foreach ($users_list as $u): ?>
              <tr>
                <td>
                  <div style="font-weight:850; font-size:1.05rem;"><?= e($u["username"]) ?></div>
                  <div class="small" style="opacity:0.7;"><?= e($u["email"] ?: "No email provided") ?></div>
                </td>
                <td><span class="pill" style="font-size:0.75rem; margin:0;"><?= e($u["role"]) ?></span></td>
                <td>
                  <?php 
                    $color = ["Pending"=>"var(--brand)", "Approved"=>"var(--brand2)", "Rejected"=>"var(--danger)"][$u["status"]] ?? "var(--text)";
                  ?>
                  <span style="color:<?= $color ?>; font-weight:850; font-size:0.85rem;">● <?= e($u["status"]) ?></span>
                </td>
                <td class="hide-mobile small" style="opacity:0.7;">
                  <?= e(format_date($u["created_at"])) ?>
                </td>
                <td class="actions">
                  <?php if ($u["status"] === "Pending"): ?>
                    <a href="admin_users.php?action=approve&id=<?= $u["id"] ?>" class="btn" style="padding:6px 14px; font-size:0.8rem;">Approve</a>
                    <a href="admin_users.php?action=reject&id=<?= $u["id"] ?>" class="btn btn-ghost" style="padding:6px 14px; font-size:0.8rem; color:var(--danger);" onclick="return confirm('Reject this user?')">Reject</a>
                  <?php elseif ($u["role"] !== "admin"): ?>
                    <a href="admin_users.php?action=delete&id=<?= $u["id"] ?>" class="btn btn-ghost" style="padding:6px 14px; font-size:0.8rem; opacity:0.6;" onclick="return confirm('Permanently delete this user?')">Delete</a>
                  <?php else: ?>
                    <span class="small" style="opacity:0.5;">System Admin</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
