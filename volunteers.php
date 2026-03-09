<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_login();

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/csrf.php";
require_once __DIR__ . "/helpers.php";

$action = $_GET["action"] ?? "";
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

/* -----------------------
   CREATE / UPDATE
------------------------ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  csrf_verify();

  $mode = $_POST["mode"] ?? "create";
  $full_name = trim((string)($_POST["full_name"] ?? ""));
  $phone = trim((string)($_POST["phone"] ?? ""));
  $email = trim((string)($_POST["email"] ?? ""));
  $ministry = trim((string)($_POST["ministry"] ?? ""));
  $availability = (string)($_POST["availability"] ?? "Both");
  $notes = trim((string)($_POST["notes"] ?? ""));

  if ($full_name === "" || $ministry === "") {
    flash_set("Please fill Full Name and Ministry.", "error");
    redirect("volunteers.php");
  }

  if ($mode === "update") {
    $vid = (int)($_POST["id"] ?? 0);
    $stmt = $pdo->prepare("UPDATE volunteers SET full_name=?, phone=?, email=?, ministry=?, availability=?, notes=? WHERE id=?");
    $stmt->execute([$full_name, $phone ?: null, $email ?: null, $ministry, $availability, $notes, $vid]);
    flash_set("Volunteer updated.");
  } else {
    $stmt = $pdo->prepare("INSERT INTO volunteers (full_name, phone, email, ministry, availability, notes) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$full_name, $phone ?: null, $email ?: null, $ministry, $availability, $notes]);
    flash_set("Volunteer added.");
  }

  redirect("volunteers.php");
}

/* -----------------------
   DELETE
------------------------ */
if ($action === "delete" && $id > 0) {
  $pdo->prepare("DELETE FROM volunteers WHERE id=?")->execute([$id]);
  flash_set("Volunteer deleted.");
  redirect("volunteers.php");
}

/* -----------------------
   EDIT LOAD
------------------------ */
$edit = null;
if ($action === "edit" && $id > 0) {
  $stmt = $pdo->prepare("SELECT * FROM volunteers WHERE id=?");
  $stmt->execute([$id]);
  $edit = $stmt->fetch();
}

/* -----------------------
   FILTERS + PAGINATION
------------------------ */
$q = trim((string)($_GET["q"] ?? ""));
$ministryF = trim((string)($_GET["ministry"] ?? ""));
$availF = trim((string)($_GET["availability"] ?? ""));

$page = max(1, (int)($_GET["page"] ?? 1));
$perPage = 8;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($q !== "") {
  $where[] = "(full_name LIKE ? OR phone LIKE ? OR email LIKE ?)";
  $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($ministryF !== "") {
  $where[] = "ministry LIKE ?";
  $params[] = "%$ministryF%";
}
if ($availF !== "") {
  $where[] = "availability = ?";
  $params[] = $availF;
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// total count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM volunteers $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// list rows
$stmt = $pdo->prepare("SELECT * FROM volunteers $whereSql ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* -----------------------
   UI
------------------------ */
require_once __DIR__ . "/header.php";
?>

<div style="margin-bottom: 20px;">
  <a class="btn btn-ghost" href="dashboard.php">← Back to Dashboard</a>
</div>

<div class="grid">
  <!-- Top: Form (Full Width) -->
  <div class="col-12">
    <div class="card">
      <div style="font-weight:950; font-size:1.4rem;">
        <?= $edit ? "Edit Volunteer" : "Add Volunteer" ?>
      </div>
      <div class="small">Manage serving teams with order and clarity.</div>

      <form method="post" style="margin-top:20px; display:grid; gap:20px;">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="mode" value="<?= $edit ? "update" : "create" ?>">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit["id"] ?>"><?php endif; ?>

        <div class="grid">
          <div class="col-6">
            <label class="small">Full Name</label>
            <input class="input" name="full_name" required value="<?= e($edit["full_name"] ?? "") ?>" placeholder="e.g. John Mwangi">
          </div>
          <div class="col-6">
            <label class="small">Ministry / Department</label>
            <input class="input" name="ministry" required value="<?= e($edit["ministry"] ?? "") ?>" placeholder="Choir, Media, Ushering...">
          </div>

          <div class="col-4">
            <label class="small">Phone</label>
            <input class="input" name="phone" value="<?= e($edit["phone"] ?? "") ?>" placeholder="e.g. 0712...">
          </div>
          <div class="col-4">
            <label class="small">Email</label>
            <input class="input" type="email" name="email" value="<?= e($edit["email"] ?? "") ?>" placeholder="e.g. name@email.com">
          </div>
          <div class="col-4">
            <label class="small">Availability</label>
            <select class="select" name="availability">
              <?php
                foreach (["Weekdays","Weekends","Both"] as $o) {
                  $sel = ($o === ($edit["availability"] ?? "Both")) ? "selected" : "";
                  echo "<option $sel>".e($o)."</option>";
                }
              ?>
            </select>
          </div>

          <div class="col-12">
            <label class="small">Notes</label>
            <textarea class="textarea" name="notes" placeholder="Extra notes..." style="min-height:46px;"><?= e($edit["notes"] ?? "") ?></textarea>
          </div>
        </div>

        <div style="display:flex; gap:12px;">
          <button class="btn" type="submit" style="min-width:180px; padding:12px;"><?= $edit ? "Save Changes" : "Add Volunteer" ?></button>
          <?php if ($edit): ?>
            <a class="btn btn-ghost" href="volunteers.php">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Bottom: List (Full Width) -->
  <div class="col-12">
    <div class="card">
      <div style="font-weight:950; font-size:1.4rem;">Volunteers List</div>
      <div class="small">Search, filter, and manage church serving teams.</div>

      <div style="margin-top:20px;">
        <form method="get" class="card" style="background:rgba(255,255,255,.03); border-color:rgba(255,255,255,.05); margin-bottom:20px; padding:20px;">
          <div style="display:flex; gap:20px; flex-wrap:wrap; align-items:flex-end;">
            <div style="flex:1; min-width:200px;">
              <label class="small" style="display:block; margin-bottom:8px;">Search</label>
              <input class="input" name="q" value="<?= e($q) ?>" placeholder="Name, phone, email...">
            </div>
            <div style="width:180px;">
              <label class="small" style="display:block; margin-bottom:8px;">Availability</label>
              <select class="select" name="availability">
                <option value="">All Availabilities</option>
                <?php foreach (["Weekdays","Weekends","Both"] as $a): ?>
                  <option value="<?= e($a) ?>" <?= $availF===$a?'selected':'' ?>><?= e($a) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:nowrap;">
              <button class="btn" type="submit" style="padding: 10px 20px;">Apply</button>
              <a class="btn btn-ghost" href="volunteers.php" style="padding: 10px 15px;">Reset</a>
              <a class="btn btn-ghost" href="volunteers_export.php?<?= e(http_build_query($_GET)) ?>" style="padding: 10px 15px;">CSV</a>
              <a class="btn btn-ghost" target="_blank" href="volunteers_report.php?<?= e(http_build_query($_GET)) ?>" style="padding: 10px 15px; white-space:nowrap;">Print List</a>
            </div>
          </div>
        </form>

        <div style="overflow-x:auto;">
          <table class="table">
            <thead>
              <tr>
                <th>Name</th><th>Ministry</th><th>Phone / Email</th><th>Availability</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td style="font-weight:700;"><?= e($r["full_name"]) ?></td>
                  <td><span class="pill" style="font-size:0.75rem; margin:0;"><?= e($r["ministry"]) ?></span></td>
                  <td class="small">
                    <?php if ($r["phone"]): ?>
                      <a href="tel:<?= e($r["phone"]) ?>" style="color:var(--brand2); font-weight:700; display:flex; align-items:center; gap:6px;">
                        📞 <?= e($r["phone"]) ?>
                      </a>
                    <?php else: ?>
                      -
                    <?php endif; ?>
                    <div style="margin-top:4px; opacity:0.8;"><?= e($r["email"] ?: "-") ?></div>
                  </td>
                  <td>
                    <span style="font-weight:800; font-size:0.85rem; color:var(--brand2);">📂 <?= e($r["availability"]) ?></span>
                  </td>
                  <td class="actions">
                    <a class="btn btn-ghost" href="volunteers.php?action=edit&id=<?= (int)$r["id"] ?>">Edit</a>
                    <a class="btn btn-danger" href="volunteers.php?action=delete&id=<?= (int)$r["id"] ?>"
                       onclick="return confirm('Delete this volunteer?');">Delete</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$rows): ?>
                <tr><td colspan="5" style="text-align:center; padding:40px; color:var(--muted);">No volunteers found matching your criteria.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:20px;">
          <div class="small">Page <?= $page ?>/<?= $totalPages ?> (Total: <?= $total ?>)</div>
          <div style="display:flex; gap:10px;">
            <?php
              $base = $_GET;
              if ($page > 1) {
                $base["page"] = $page - 1;
                echo '<a class="btn btn-ghost" href="volunteers.php?' . e(http_build_query($base)) . '">← Prev</a>';
              }
              if ($page < $totalPages) {
                $base["page"] = $page + 1;
                echo '<a class="btn btn-ghost" href="volunteers.php?' . e(http_build_query($base)) . '">Next →</a>';
              }
            ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
