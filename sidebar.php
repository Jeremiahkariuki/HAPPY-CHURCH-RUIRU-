<?php
declare(strict_types=1);
$active = basename($_SERVER["PHP_SELF"]);
?>
<aside class="sidebar" id="sidebar">
  <div class="brand">
    <div class="brand-mark" style="background: linear-gradient(135deg, #7c5cff, #2ee9a6); color:#07101f; padding: 10px; border-radius: 12px; font-weight: 950;">+</div>
    <div class="brand-text">
      <div class="brand-title"><?= e($appName) ?></div>
      <div class="brand-sub">Events & Community</div>
    </div>
  </div>

  <nav class="nav">
    <a class="nav-item <?= $active==='dashboard.php'?'active':'' ?>" href="dashboard.php">Dashboard</a>
    <a class="nav-item <?= $active==='events.php'?'active':'' ?>" href="events.php">Events</a>
    <a class="nav-item <?= $active==='volunteers.php'?'active':'' ?>" href="volunteers.php">Volunteers</a>
    <a class="nav-item <?= $active==='gallery.php'?'active':'' ?>" href="gallery.php">Gallery</a>
    <?php if (in_array($_SESSION["user"]["role"] ?? "", ["admin", "Receptionist"])): ?>
      <a class="nav-item <?= $active==='attendees.php'?'active':'' ?>" href="attendees.php">Attendees</a>
    <?php endif; ?>
    <a class="nav-item <?= $active==='contacts.php'?'active':'' ?>" href="contacts.php">Contacts</a>
    <?php if (($_SESSION["user"]["role"] ?? "") === "admin"): ?>
      <a class="nav-item <?= $active==='admin_users.php'?'active':'' ?>" href="admin_users.php">Users</a>
    <?php endif; ?>
    <a class="nav-item <?= $active==='about.php'?'active':'' ?>" href="about.php">About</a>
  </nav>

  <div class="sidebar-footer">
    <div class="small">Signed in as</div>
    <div class="pill"><?= e($_SESSION["user"]["username"] ?? "admin") ?></div>
    <a class="btn btn-ghost" href="logout.php">Logout</a>
  </div>
</aside>

<main class="main">
  <header class="topbar">
    <button class="icon-btn" id="toggleBtn" aria-label="Toggle menu">
      <span class="burger"></span>
      <span class="burger"></span>
      <span class="burger"></span>
    </button>
    <div class="topbar-title">Church Events System</div>
    <div class="topbar-right">
      <div class="tag">Secure</div>
    </div>
  </header>

  <?php if ($flash): ?>
    <div class="flash <?= e($flash["type"]) ?>"><?= e($flash["msg"]) ?></div>
  <?php endif; ?>

  <section class="content"></section>
