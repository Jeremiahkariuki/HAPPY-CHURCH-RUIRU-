<?php
declare(strict_types=1);

session_start();
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}

if (!function_exists('e')) {
    function e(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, "UTF-8");
    }
}

$user_name = $_SESSION["user"]["username"];
$user_role = $_SESSION["user"]["role"] ?? 'user';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Welcome • LOVE CHURCH</title>
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
    background:
      radial-gradient(1200px 800px at 30% 10%, rgba(124,92,255,.18), transparent 60%),
      radial-gradient(800px 600px at 80% 80%, rgba(46,233,166,.10), transparent 50%),
      #07101f;
    padding: 40px 20px;
    position: relative;
    overflow-x: hidden;
  }

  /* Animated floating orbs */
  body::before, body::after {
    content: '';
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.4;
    animation: float 12s ease-in-out infinite;
    pointer-events: none;
  }
  body::before {
    width: 600px; height: 600px;
    background: rgba(124,92,255,.20);
    top: -150px; left: -150px;
  }
  body::after {
    width: 500px; height: 500px;
    background: rgba(46,233,166,.15);
    bottom: -100px; right: -100px;
    animation-delay: -6s;
    animation-direction: reverse;
  }
  @keyframes float {
    0%, 100% { transform: translate(0,0) scale(1); }
    25% { transform: translate(50px, -40px) scale(1.05); }
    50% { transform: translate(-30px, 60px) scale(0.95); }
    75% { transform: translate(40px, 30px) scale(1.02); }
  }

  .home-container {
    width: 100%;
    max-width: 1000px;
    position: relative;
    z-index: 2;
  }

  /* Hero Banner */
  .hero-banner {
    background: rgba(15, 26, 46, 0.6);
    border: 1px solid rgba(255,255,255,.08);
    border-top: 1px solid rgba(124,92,255,.3);
    border-radius: 32px;
    box-shadow: 0 30px 60px rgba(0,0,0,.4);
    backdrop-filter: blur(20px);
    padding: 60px 40px;
    text-align: center;
    position: relative;
    overflow: hidden;
    animation: cardIn 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    opacity: 0;
    transform: translateY(30px);
    margin-bottom: 40px;
  }
  .hero-banner::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; height: 100%;
    background: linear-gradient(180deg, rgba(124,92,255,.1), transparent 50%);
    pointer-events: none;
  }
  @keyframes cardIn {
    to { opacity: 1; transform: translateY(0); }
  }

  .welcome-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 24px;
    border-radius: 24px;
    background: linear-gradient(135deg, #7c5cff, #2ee9a6);
    display: grid;
    place-items: center;
    font-size: 36px;
    color: #07101f;
    box-shadow: 0 15px 35px rgba(124,92,255,.4), 0 0 0 5px rgba(124,92,255,.1);
    animation: bounceIn 1s cubic-bezier(0.16, 1, 0.3, 1) 0.2s both;
  }
  @keyframes bounceIn {
    0% { transform: scale(0.5); opacity: 0; }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); opacity: 1; }
  }

  .welcome-title {
    font-size: 3rem;
    font-weight: 900;
    color: #fff;
    margin-bottom: 12px;
    letter-spacing: -0.5px;
    background: linear-gradient(135deg, #fff, #a9b7d0);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }
  .welcome-subtitle {
    font-size: 1.15rem;
    color: #a9b7d0;
    font-weight: 500;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.6;
  }

  /* Role Badge */
  .role-badge {
    display: inline-block;
    margin-top: 24px;
    padding: 8px 20px;
    background: rgba(46,233,166,.15);
    border: 1px solid rgba(46,233,166,.3);
    color: #2ee9a6;
    border-radius: 20px;
    font-weight: 700;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    position: relative;
    z-index: 2;
  }
  
  .role-badge.admin {
    background: rgba(124,92,255,.15);
    border-color: rgba(124,92,255,.3);
    color: #b09cff;
  }

  /* Cards Grid */
  .cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
    opacity: 0;
    animation: fadeIn 0.8s ease forwards 0.3s;
  }
  @keyframes fadeIn {
    to { opacity: 1; }
  }

  .nav-card {
    background: rgba(15, 26, 46, 0.7);
    border: 1px solid rgba(255,255,255,.06);
    border-radius: 24px;
    padding: 32px;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(10px);
    display: flex;
    flex-direction: column;
    align-items: flex-start;
  }
  .nav-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: radial-gradient(circle at 100% 100%, rgba(124,92,255,.1), transparent 60%);
    opacity: 0;
    transition: opacity 0.3s;
    pointer-events: none;
  }
  .nav-card:hover {
    transform: translateY(-8px);
    border-color: rgba(124,92,255,.3);
    box-shadow: 0 20px 40px rgba(0,0,0,.3), 0 0 20px rgba(124,92,255,.1);
  }
  .nav-card:hover::before {
    opacity: 1;
  }

  .nav-card-icon {
    width: 60px;
    height: 60px;
    border-radius: 18px;
    background: rgba(255,255,255,.05);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    margin-bottom: 20px;
    border: 1px solid rgba(255,255,255,.1);
    transition: all 0.3s ease;
  }
  .nav-card:hover .nav-card-icon {
    background: rgba(124,92,255,.15);
    border-color: rgba(124,92,255,.3);
    transform: scale(1.1) rotate(5deg);
  }

  .nav-card.green:hover .nav-card-icon {
    background: rgba(46,233,166,.15);
    border-color: rgba(46,233,166,.3);
  }
  .nav-card.green:hover {
    border-color: rgba(46,233,166,.3);
    box-shadow: 0 20px 40px rgba(0,0,0,.3), 0 0 20px rgba(46,233,166,.1);
  }

  .nav-card-title {
    color: #fff;
    font-size: 1.4rem;
    font-weight: 800;
    margin-bottom: 12px;
    letter-spacing: 0.5px;
  }
  .nav-card-desc {
    color: #a9b7d0;
    font-size: 0.95rem;
    line-height: 1.5;
    flex-grow: 1;
  }
  .nav-card-action {
    margin-top: 24px;
    font-size: 0.9rem;
    font-weight: 700;
    color: #7c5cff;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 1px;
    transition: gap 0.2s;
  }
  .nav-card:hover .nav-card-action {
    gap: 12px;
  }
  .nav-card.green .nav-card-action {
    color: #2ee9a6;
  }

  /* Log out link */
  .logout-link {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 40px;
    color: #a9b7d0;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.2s;
    font-size: 0.95rem;
    opacity: 0;
    animation: fadeIn 0.8s ease forwards 0.5s;
    position: relative;
    z-index: 2;
  }
  .logout-link:hover {
    color: #ff6b8a;
  }

  @media (max-width: 768px) {
    .welcome-title { font-size: 2.2rem; }
    .hero-banner { padding: 40px 20px; }
  }
</style>
</head>
<body>

<div class="home-container">
  
  <div class="hero-banner">
    <div class="welcome-icon">✨</div>
    <h1 class="welcome-title">Welcome back, <?= e($user_name) ?>!</h1>
    <p class="welcome-subtitle">
      We are blessed to have you here at LOVE CHURCH. Connect, grow, and manage your events efficiently.
    </p>
    <div class="role-badge <?= strtolower($user_role) === 'admin' ? 'admin' : '' ?>">
      ◆ <?= e(ucfirst($user_role)) ?> Access
    </div>
  </div>

  <div class="cards-grid">
    <!-- Primary Dashboard Action -->
    <a href="dashboard.php" class="nav-card">
      <div class="nav-card-icon">📊</div>
      <h3 class="nav-card-title">My Dashboard</h3>
      <p class="nav-card-desc">
        Access your primary control center. View recent activities, manage your account, and see overall progress.
      </p>
      <div class="nav-card-action">
        Enter Dashboard <span>→</span>
      </div>
    </a>

    <!-- Events Action -->
    <a href="events.php" class="nav-card green">
      <div class="nav-card-icon">🗓️</div>
      <h3 class="nav-card-title">Discover Events</h3>
      <p class="nav-card-desc">
        Explore upcoming LOVE CHURCH, service times, and special gatherings happening within the community.
      </p>
      <div class="nav-card-action">
        View Events <span>→</span>
      </div>
    </a>

    <!-- Profile Action -->
    <a href="notifications.php" class="nav-card">
      <div class="nav-card-icon">🔔</div>
      <h3 class="nav-card-title">Notifications</h3>
      <p class="nav-card-desc">
        Stay updated with recent announcements, profile alerts, and important messages from church leadership.
      </p>
      <div class="nav-card-action">
        Check Alerts <span>→</span>
      </div>
    </a>
  </div>

  <a href="logout.php" class="logout-link">
    <span>⇦</span> Securely Log Out
  </a>

</div>

</body>
</html>
