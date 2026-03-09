<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_login();

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/csrf.php";
require_once __DIR__ . "/helpers.php";

$action = $_GET["action"] ?? "";
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  csrf_verify();

  $mode = $_POST["mode"] ?? "create";
  $title = trim((string)($_POST["title"] ?? ""));
  $event_date = (string)($_POST["event_date"] ?? "");
  $start_time = $_POST["start_time"] !== "" ? (string)$_POST["start_time"] : null;
  $end_time   = $_POST["end_time"] !== "" ? (string)$_POST["end_time"] : null;
  $location = trim((string)($_POST["location"] ?? ""));
  $category = trim((string)($_POST["category"] ?? "Church Event"));
  $status = (string)($_POST["status"] ?? "Scheduled");
  $description = trim((string)($_POST["description"] ?? ""));

  if ($title === "" || $event_date === "" || $location === "") {
    flash_set("Please fill Title, Date and Location.", "error");
    redirect("events.php");
  }

  if ($mode === "update") {
    $eid = (int)($_POST["id"] ?? 0);
    $stmt = $pdo->prepare("UPDATE events SET title=?, event_date=?, start_time=?, end_time=?, location=?, category=?, status=?, description=? WHERE id=?");
    $stmt->execute([$title,$event_date,$start_time,$end_time,$location,$category,$status,$description,$eid]);
    flash_set("Event updated successfully.");
    redirect("events.php");
  } else {
    $stmt = $pdo->prepare("INSERT INTO events (title,event_date,start_time,end_time,location,category,status,description) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$title,$event_date,$start_time,$end_time,$location,$category,$status,$description]);
    flash_set("Event created successfully.");
    redirect("events.php");
  }
}

if ($action === "delete" && $id > 0) {
  // delete via GET (simple). For stricter security, do delete via POST.
  $pdo->prepare("DELETE FROM events WHERE id=?")->execute([$id]);
  flash_set("Event deleted.");
  redirect("events.php");
}

$edit = null;
if ($action === "edit" && $id > 0) {
  $stmt = $pdo->prepare("SELECT * FROM events WHERE id=?");
  $stmt->execute([$id]);
  $edit = $stmt->fetch();
}

$rows = $pdo->query("SELECT * FROM events ORDER BY created_at DESC, id DESC")->fetchAll();
// --- Filters + Pagination ---
$q = trim((string)($_GET["q"] ?? ""));
$statusF = trim((string)($_GET["status"] ?? ""));
$from = trim((string)($_GET["from"] ?? ""));
$to = trim((string)($_GET["to"] ?? ""));

$page = max(1, (int)($_GET["page"] ?? 1));
$perPage = 8;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($q !== "") {
  $where[] = "(title LIKE ? OR location LIKE ? OR category LIKE ?)";
  $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($statusF !== "") {
  $where[] = "status = ?";
  $params[] = $statusF;
}
if ($from !== "") {
  $where[] = "event_date >= ?";
  $params[] = $from;
}
if ($to !== "") {
  $where[] = "event_date <= ?";
  $params[] = $to;
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// total count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM events $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// list rows
$stmt = $pdo->prepare("SELECT * FROM events $whereSql ORDER BY created_at DESC, id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();

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
        <?= $edit ? "Edit Event" : "Create New Event" ?>
      </div>
      <div class="small">Keep the church calendar organized and inspiring.</div>

      <form method="post" style="margin-top:20px; display:grid; gap:20px;">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="mode" value="<?= $edit ? "update" : "create" ?>">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit["id"] ?>"><?php endif; ?>

        <div class="grid">
          <div class="col-6">
            <label class="small">Title</label>
            <input class="input" name="title" required value="<?= e($edit["title"] ?? "") ?>" placeholder="e.g. Sunday Worship Service">
          </div>
          <div class="col-6">
            <label class="small">Location</label>
            <input class="input" name="location" required value="<?= e($edit["location"] ?? "") ?>" placeholder="e.g. Main Sanctuary">
          </div>

          <div class="col-4">
            <label class="small">Date</label>
            <div class="input-wrap">
              <input id="event_date" class="input" type="date" name="event_date" required value="<?= e($edit["event_date"] ?? "") ?>">
              <button type="button" class="input-icon" onclick="document.getElementById('event_date').showPicker?.(); document.getElementById('event_date').focus();">
                <svg viewBox="0 0 24 24" fill="none">
                  <path d="M7 2v3M17 2v3M3.5 9h17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                  <path d="M6.5 5h11A3.5 3.5 0 0 1 21 8.5v11A3.5 3.5 0 0 1 17.5 23h-11A3.5 3.5 0 0 1 3 19.5v-11A3.5 3.5 0 0 1 6.5 5Z" stroke="currentColor" stroke-width="2"/>
                </svg>
              </button>
            </div>
          </div>
          <div class="col-4">
            <label class="small">Category</label>
            <input class="input" name="category" value="<?= e($edit["category"] ?? "Church Event") ?>" placeholder="e.g. Worship, Fellowship">
          </div>
          <div class="col-4">
            <label class="small">Status</label>
            <select class="select" name="status">
              <?php
                foreach (["Scheduled","Ongoing","Completed","Cancelled"] as $o) {
                  $sel = ($o === ($edit["status"] ?? "Scheduled")) ? "selected" : "";
                  echo "<option $sel>".e($o)."</option>";
                }
              ?>
            </select>
          </div>

          <div class="col-3">
            <label class="small">Start Time</label>
            <input class="input" type="time" name="start_time" value="<?= e($edit["start_time"] ?? "") ?>">
          </div>
          <div class="col-3">
            <label class="small">End Time</label>
            <input class="input" type="time" name="end_time" value="<?= e($edit["end_time"] ?? "") ?>">
          </div>
          <div class="col-6">
            <label class="small">Description</label>
            <textarea class="textarea" name="description" placeholder="Event details..." style="min-height:46px;"><?= e($edit["description"] ?? "") ?></textarea>
          </div>
        </div>

        <div style="display:flex; gap:12px;">
          <button class="btn" type="submit" style="min-width:180px; padding:12px;"><?= $edit ? "Save Changes" : "Create Event" ?></button>
          <?php if ($edit): ?>
            <a class="btn btn-ghost" href="events.php">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Bottom: List (Full Width) -->
  <div class="col-12">
    <div class="card">
      <div style="font-weight:950; font-size:1.4rem;">Events List</div>
      <div class="small">Displaying upcoming and past church events.</div>

      <div style="margin-top:20px;">
        <form method="get" class="card" style="background:rgba(255,255,255,.03); border-color:rgba(255,255,255,.05); margin-bottom:20px; padding:20px;">
          <div style="display:flex; gap:20px; flex-wrap:wrap; align-items:flex-end;">
            <div style="flex:1; min-width:200px;">
              <label class="small" style="display:block; margin-bottom:8px;">Search</label>
              <input class="input" name="q" value="<?= e($q) ?>" placeholder="Title, location...">
            </div>
            <div style="width:180px;">
              <label class="small" style="display:block; margin-bottom:8px;">Status</label>
              <select class="select" name="status">
                <option value="">All Statuses</option>
                <?php foreach (["Scheduled","Ongoing","Completed","Cancelled"] as $s): ?>
                  <option value="<?= e($s) ?>" <?= $statusF===$s?'selected':'' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:nowrap;">
              <button class="btn" type="submit" style="padding: 10px 20px;">Apply</button>
              <a class="btn btn-ghost" href="events.php" style="padding: 10px 15px;">Reset</a>
              <a class="btn btn-ghost" href="events_export.php?<?= http_build_query($_GET) ?>" style="padding: 10px 15px;">CSV</a>
              <a class="btn btn-ghost" target="_blank" href="events_report.php?<?= http_build_query($_GET) ?>" style="padding: 10px 15px; white-space:nowrap;">Print Report</a>
            </div>
          </div>
        </form>

        <div style="overflow-x:auto;">
          <table class="table">
            <thead>
              <tr>
                <th>Date</th><th>Title</th><th>Location</th><th>Category</th><th>Status</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td style="white-space:nowrap;"><?= e($r["event_date"]) ?></td>
                  <td style="font-weight:700;"><?= e($r["title"]) ?></td>
                  <td><?= e($r["location"]) ?></td>
                  <td><span class="pill" style="font-size:0.7rem; margin:0;"><?= e($r["category"]) ?></span></td>
                  <td>
                     <?php
                       $color = ["Scheduled"=>"var(--brand)", "Ongoing"=>"var(--brand2)", "Completed"=>"var(--muted)", "Cancelled"=>"var(--danger)"][$r["status"]] ?? "var(--text)";
                     ?>
                     <span style="color:<?= $color ?>; font-weight:800; font-size:0.85rem;">● <?= e($r["status"]) ?></span>
                  </td>
                  <td class="actions">
                    <a class="btn btn-ghost" href="events.php?action=edit&id=<?= (int)$r["id"] ?>">Edit</a>
                    <a class="btn btn-danger" href="events.php?action=delete&id=<?= (int)$r["id"] ?>"
                       onclick="return confirm('Delete this event?');">Delete</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$rows): ?>
                <tr><td colspan="6" style="text-align:center; padding:40px; color:var(--muted);">No events found matching your criteria.</td></tr>
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
                echo '<a class="btn btn-ghost" href="events.php?' . e(http_build_query($base)) . '">← Prev</a>';
              }
              if ($page < $totalPages) {
                $base["page"] = $page + 1;
                echo '<a class="btn btn-ghost" href="events.php?' . e(http_build_query($base)) . '">Next →</a>';
              }
            ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . "/footer.php"; ?>
