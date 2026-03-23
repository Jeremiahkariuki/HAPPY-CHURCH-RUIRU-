<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_login();

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/csrf.php";
require_once __DIR__ . "/helpers.php";

$action = $_GET["action"] ?? "";
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

$events = $pdo->query("SELECT id, title, event_date FROM events ORDER BY event_date DESC")->fetchAll();

/* -----------------------
   CREATE / UPDATE
------------------------ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  csrf_verify();

  $mode = $_POST["mode"] ?? "create";
  $full_name = trim((string)($_POST["full_name"] ?? ""));
  $phone = trim((string)($_POST["phone"] ?? ""));
  $email = trim((string)($_POST["email"] ?? ""));
  $event_id = ($_POST["event_id"] ?? "") !== "" ? (int)$_POST["event_id"] : null;
  $attendance_status = (string)($_POST["attendance_status"] ?? "Registered");

  if ($full_name === "") {
    flash_set("Full name is required.", "error");
    redirect("attendees.php");
  }

  if ($mode === "update") {
    $aid = (int)($_POST["id"] ?? 0);
    $stmt = $pdo->prepare("UPDATE attendees SET full_name=?, phone=?, email=?, event_id=?, attendance_status=? WHERE id=?");
    $stmt->execute([$full_name, $phone ?: null, $email ?: null, $event_id, $attendance_status, $aid]);
    flash_set("Attendee updated.");
  } else {
    $stmt = $pdo->prepare("INSERT INTO attendees (full_name, phone, email, event_id, attendance_status) VALUES (?,?,?,?,?)");
    $stmt->execute([$full_name, $phone ?: null, $email ?: null, $event_id, $attendance_status]);
    flash_set("Attendee added.");
  }

  redirect("attendees.php");
}

/* -----------------------
   DELETE
------------------------ */
if ($action === "delete" && $id > 0) {
  $pdo->prepare("DELETE FROM attendees WHERE id=?")->execute([$id]);
  flash_set("Attendee deleted.");
  redirect("attendees.php");
}

/* -----------------------
   EDIT LOAD
------------------------ */
$edit = null;
if ($action === "edit" && $id > 0) {
  $stmt = $pdo->prepare("SELECT * FROM attendees WHERE id=?");
  $stmt->execute([$id]);
  $edit = $stmt->fetch();
}

/* -----------------------
   FILTERS + PAGINATION
------------------------ */
$q = trim((string)($_GET["q"] ?? ""));
$statusF = trim((string)($_GET["status"] ?? ""));
$eventF = trim((string)($_GET["event_id"] ?? ""));
$from = trim((string)($_GET["from"] ?? ""));
$to = trim((string)($_GET["to"] ?? ""));

$page = max(1, (int)($_GET["page"] ?? 1));
$perPage = 8;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($q !== "") {
  $where[] = "(a.full_name LIKE ? OR a.phone LIKE ? OR a.email LIKE ?)";
  $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($statusF !== "") {
  $where[] = "a.attendance_status = ?";
  $params[] = $statusF;
}
if ($eventF !== "") {
  $where[] = "a.event_id = ?";
  $params[] = (int)$eventF;
}
if ($from !== "") {
  $where[] = "a.created_at >= ?";
  $params[] = $from . " 00:00:00";
}
if ($to !== "") {
  $where[] = "a.created_at <= ?";
  $params[] = $to . " 23:59:59";
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// total count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM attendees a $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// list rows
$stmt = $pdo->prepare("
  SELECT a.*, e.title AS event_title, e.event_date
  FROM attendees a
  LEFT JOIN events e ON e.id = a.event_id
  $whereSql
  ORDER BY e.event_date DESC, a.id DESC
  LIMIT $perPage OFFSET $offset
");
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
  <?php if (in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])): ?>
    <!-- Top: Form (Full Width) -->
    <div class="col-12">
      <div class="card">
        <div style="font-weight:950; font-size:1.4rem;">
          <?= $edit ? "Edit Attendee" : "Add Attendee" ?>
        </div>
        <div class="small">Track attendance and strengthen community.</div>

        <form method="post" style="margin-top:20px; display:grid; gap:20px;">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="mode" value="<?= $edit ? "update" : "create" ?>">
          <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit["id"] ?>"><?php endif; ?>

          <div class="grid">
            <div class="col-6">
              <label class="small">Full Name</label>
              <input class="input" name="full_name" required value="<?= e($edit["full_name"] ?? "") ?>" placeholder="e.g. Jane Doe">
            </div>
            <div class="col-6">
              <label class="small">Email</label>
              <input class="input" type="email" name="email" value="<?= e($edit["email"] ?? "") ?>" placeholder="e.g. jane@example.com">
            </div>

            <div class="col-4">
              <label class="small">Phone</label>
              <input class="input" name="phone" value="<?= e($edit["phone"] ?? "") ?>" placeholder="e.g. 0712...">
            </div>
            <div class="col-4">
              <label class="small">Event</label>
              <select class="select" name="event_id">
                <option value="">(No specific event)</option>
                <?php
                  $cur = $edit["event_id"] ?? "";
                  foreach ($events as $ev) {
                    $sel = ((string)$ev["id"] === (string)$cur) ? "selected" : "";
                    $label = $ev["title"] . " • " . $ev["event_date"];
                    echo "<option value='".(int)$ev["id"]."' $sel>".e($label)."</option>";
                  }
                ?>
              </select>
            </div>
            <div class="col-4">
              <label class="small">Status</label>
              <select class="select" name="attendance_status">
                <?php
                  foreach (["Registered","Confirmed","Attended","Cancelled"] as $o) {
                    $sel = ($o === ($edit["attendance_status"] ?? "Registered")) ? "selected" : "";
                    echo "<option $sel>".e($o)."</option>";
                  }
                ?>
              </select>
            </div>
          </div>

          <div style="display:flex; gap:12px;">
            <button class="btn" type="submit" style="min-width:180px; padding:12px;"><?= $edit ? "Save Changes" : "Add Attendee" ?></button>
            <?php if ($edit): ?>
              <a class="btn btn-ghost" href="attendees.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <!-- Bottom: List (Full Width) -->
  <div class="col-12">
    <div class="card">
      <div style="font-weight:950; font-size:1.4rem;">Attendees List</div>
      <div class="small">Search, filter, and manage church attendees.</div>

      <div style="margin-top:20px;">
        <form method="get" class="card" style="background:rgba(255,255,255,.03); border-color:rgba(255,255,255,.05); margin-bottom:20px; padding:20px;">
          <div style="display:flex; gap:20px; flex-wrap:wrap; align-items:flex-end;">
            <div style="flex:1; min-width:200px;">
              <label class="small" style="display:block; margin-bottom:8px;">Search</label>
              <input class="input" name="q" value="<?= e($q) ?>" placeholder="Name, phone...">
            </div>
            <div style="width:180px;">
              <label class="small" style="display:block; margin-bottom:8px;">Status</label>
              <select class="select" name="status">
                <option value="">All Statuses</option>
                <?php foreach (["Registered","Confirmed","Attended","Cancelled"] as $s): ?>
                  <option value="<?= e($s) ?>" <?= $statusF===$s?'selected':'' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div style="width:180px;">
              <label class="small" style="display:block; margin-bottom:8px;">Event</label>
              <select class="select" name="event_id">
                <option value="">All Events</option>
                <?php foreach ($events as $ev): ?>
                  <option value="<?= (int)$ev["id"] ?>" <?= (string)$eventF===(string)$ev["id"]?'selected':'' ?>>
                    <?= e($ev["title"]) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:nowrap;">
              <button class="btn" type="submit" style="padding: 10px 20px;">Apply</button>
              <a class="btn btn-ghost" href="attendees.php" style="padding: 10px 15px;">Reset</a>
              <a class="btn btn-ghost" href="attendees_export.php?<?= e(http_build_query($_GET)) ?>" style="padding: 10px 15px;">CSV</a>
              <a class="btn btn-ghost" target="_blank" href="attendees_report.php?<?= e(http_build_query($_GET)) ?>" style="padding: 10px 15px; white-space:nowrap;">Print List</a>
            </div>
          </div>
        </form>

        <div style="overflow-x:auto;">
          <table class="table">
            <thead>
              <tr>
                <th>Name</th><th>Phone / Email</th><th>Event</th><th>Status</th>
                <?php if (in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])): ?>
                  <th>Actions</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td style="font-weight:700;"><?= e($r["full_name"]) ?></td>
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
                  <td><?= e($r["event_title"] ?? "-") ?> <?= (isset($r["event_date"]) && $r["event_date"]) ? "• ".e(format_date($r["event_date"])) : "" ?></td>
                  <td>
                    <?php
                      $color = ["Registered"=>"var(--brand2)", "Confirmed"=>"var(--brand)", "Attended"=>"var(--brand2)", "Cancelled"=>"var(--danger)"][$r["attendance_status"]] ?? "var(--text)";
                    ?>
                    <span style="color:<?= $color ?>; font-weight:800; font-size:0.85rem;">● <?= e($r["attendance_status"]) ?></span>
                  </td>
                  <?php if (in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])): ?>
                    <td class="actions">
                      <a class="btn btn-ghost" href="attendees.php?action=edit&id=<?= (int)$r["id"] ?>">Edit</a>
                      <a class="btn btn-danger" href="attendees.php?action=delete&id=<?= (int)$r["id"] ?>"
                         onclick="return confirm('Delete this attendee?');">Delete</a>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
              <?php if (!$rows): ?>
                <tr><td colspan="5" style="text-align:center; padding:40px; color:var(--muted);">No attendees found.</td></tr>
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
                echo '<a class="btn btn-ghost" href="attendees.php?' . e(http_build_query($base)) . '">← Prev</a>';
              }
              if ($page < $totalPages) {
                $base["page"] = $page + 1;
                echo '<a class="btn btn-ghost" href="attendees.php?' . e(http_build_query($base)) . '">Next →</a>';
              }
            ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
