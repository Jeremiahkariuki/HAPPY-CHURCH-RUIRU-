<?php
declare(strict_types=1);
$active = basename($_SERVER["PHP_SELF"]);
?>
<aside class="sidebar" id="sidebar">
  <div class="brand">
    <div class="brand-mark">✝</div>
    <div class="brand-text">
      <div class="brand-title">Church Admin</div>
      <div class="brand-sub">Events & Community</div>
    </div>
  </div>

  <nav class="nav">
    <a class="nav-item <?= $active==='dashboard.php'?'active':'' ?>" href="dashboard.php">Dashboard</a>
    <a class="nav-item <?= $active==='events.php'?'active':'' ?>" href="events.php">Events</a>
    <a class="nav-item <?= $active==='volunteers.php'?'active':'' ?>" href="volunteers.php">Volunteers</a>
    <a class="nav-item <?= $active==='attendees.php'?'active':'' ?>" href="attendees.php">Attendees</a>
    <a class="nav-item <?= $active==='contacts.php'?'active':'' ?>" href="contacts.php">Contacts</a>
    <a class="nav-item <?= $active==='about.php'?'active':'' ?>" href="about.php">About</a>
  </nav>

  <div class="sidebar-footer">
    <div class="small">Signed in as</div>
    <div class="pill"><?= e($user["username"] ?? "admin") ?></div>
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
