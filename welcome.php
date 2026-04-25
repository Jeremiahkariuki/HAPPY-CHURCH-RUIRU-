<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";
require_login();

// Retrieve user info for personalization
$username = $_SESSION["user"]["username"] ?? "Valued Member";
$role = $_SESSION["user"]["role"] ?? "Member";

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Welcome Portal • LOVE CHURCH</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
    background-color: #0d1117;
    background-image: 
      radial-gradient(ellipse at top left, rgba(74, 107, 246, 0.12) 0%, transparent 40%),
      radial-gradient(ellipse at bottom right, rgba(235, 175, 117, 0.1) 0%, transparent 45%),
      radial-gradient(circle at 50% 50%, rgba(255, 255, 255, 0.02) 0%, transparent 60%);
    overflow: hidden;
    position: relative;
    color: #f0f3f8;
  }

  /* Professional Subtle Background Glows */
  .bg-ambient {
    position: absolute;
    inset: 0;
    overflow: hidden;
    z-index: 1;
    pointer-events: none;
  }
  .bg-ambient .glow-1, .bg-ambient .glow-2 {
    position: absolute;
    border-radius: 50%;
    filter: blur(120px);
    opacity: 0.6;
    animation: drift 20s ease-in-out infinite alternate;
  }
  .glow-1 {
    width: 60vw; height: 60vw;
    background: radial-gradient(circle, rgba(100, 120, 200, 0.15) 0%, transparent 70%);
    top: -20%; left: -10%;
  }
  .glow-2 {
    width: 50vw; height: 50vw;
    background: radial-gradient(circle, rgba(160, 140, 130, 0.12) 0%, transparent 70%);
    bottom: -10%; right: -5%;
    animation-direction: alternate-reverse;
  }

  @keyframes drift {
    0% { transform: translate(0, 0) scale(1); }
    100% { transform: translate(5%, 5%) scale(1.05); }
  }

  /* Exquisite Glass Card */
  .portal-container {
    width: 100%;
    max-width: 820px;
    padding: 30px;
    position: relative;
    z-index: 10;
  }

  .glass-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-top: 1px solid rgba(255, 255, 255, 0.08); /* slight light catch */
    border-radius: 24px;
    box-shadow:
      0 25px 60px -10px rgba(0,0,0, 0.4),
      inset 0 1px 0 rgba(255,255,255, 0.06);
    backdrop-filter: blur(40px) saturate(120%);
    -webkit-backdrop-filter: blur(40px) saturate(120%);
    padding: 64px 48px;
    text-align: center;
    position: relative;
    animation: revealCard 1.2s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
    opacity: 0;
    transform: translateY(20px);
  }

  @keyframes revealCard {
    to { opacity: 1; transform: translateY(0); }
  }

  /* Elegant Badge */
  .role-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 30px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: #a3b8cc;
    padding: 6px 16px;
    border-radius: 100px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    animation: fadeUp 1s ease forwards 0.4s;
    opacity: 0;
  }
  .role-badge::before {
    content: '';
    display: block;
    width: 6px; height: 6px;
    background: #64748b;
    border-radius: 50%;
  }

  /* Elegant Logo Symbol */
  .brand-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 30px;
    border-radius: 20px;
    background: linear-gradient(145deg, rgba(255,255,255,0.06), rgba(255,255,255,0.01));
    border: 1px solid rgba(255,255,255,0.08);
    display: grid;
    place-items: center;
    font-size: 32px;
    font-weight: 300;
    color: #fff;
    box-shadow: inset 0 2px 10px rgba(255,255,255,0.03);
    animation: fadeUp 1s cubic-bezier(0.2, 0.8, 0.2, 1) 0.5s forwards;
    opacity: 0;
    transform: translateY(15px);
  }

  /* Typography */
  .welcome-title {
    font-family: 'Outfit', sans-serif;
    font-size: 3rem;
    font-weight: 600;
    letter-spacing: -0.5px;
    line-height: 1.2;
    margin-bottom: 20px;
    color: #fff;
    animation: fadeUp 1s cubic-bezier(0.2, 0.8, 0.2, 1) 0.6s forwards;
    opacity: 0;
    transform: translateY(15px);
  }
  
  .welcome-subtitle {
    font-size: 1.15rem;
    font-weight: 400;
    color: #94a3b8;
    max-width: 580px;
    margin: 0 auto 48px;
    line-height: 1.6;
    animation: fadeUp 1s cubic-bezier(0.2, 0.8, 0.2, 1) 0.7s forwards;
    opacity: 0;
    transform: translateY(15px);
  }
  .welcome-subtitle strong {
    color: #e2e8f0;
    font-weight: 600;
  }

  @keyframes fadeUp {
    to { opacity: 1; transform: translateY(0); }
  }

  /* Refined Buttons */
  .action-buttons {
    display: flex;
    gap: 16px;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
    animation: fadeUp 1s cubic-bezier(0.2, 0.8, 0.2, 1) 0.8s forwards;
    opacity: 0;
    transform: translateY(15px);
  }

  /* Primary Button (More professional) */
  .btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 16px 36px;
    border-radius: 100px;
    background: #e2e8f0;
    color: #0f172a;
    font-size: 1.05rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
    box-shadow: 0 4px 15px rgba(255,255,255, 0.05);
  }
  .btn-primary:hover {
    background: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(255,255,255, 0.1);
  }

  /* Secondary Button */
  .btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 16px 36px;
    border-radius: 100px;
    background: rgba(255,255,255, 0.03);
    border: 1px solid rgba(255,255,255, 0.08);
    color: #cbd5e1;
    font-size: 1.05rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
  }
  .btn-secondary:hover {
    background: rgba(255,255,255, 0.06);
    color: #fff;
  }

  @media (max-width: 640px) {
    .glass-card { padding: 48px 24px; }
    .welcome-title { font-size: 2.2rem; }
    .action-buttons { flex-direction: column; width: 100%; }
    .btn-primary, .btn-secondary { width: 100%; justify-content: center; }
  }
</style>
</head>
<body>

<div class="bg-ambient">
  <div class="glow-1"></div>
  <div class="glow-2"></div>
</div>

<div class="portal-container">
  <div class="glass-card">
    
    <div class="role-badge"><?= htmlspecialchars($role) ?> ACCESS</div>

    <div class="brand-icon">✝</div>
    
    <h1 class="welcome-title">Welcome to LOVE CHURCH</h1>
    
    <p class="welcome-subtitle">
      Hello <strong><?= htmlspecialchars($username) ?></strong>, we are so incredibly blessed to have you here. Step inside to manage events, discover volunteer opportunities, and stay connected.
    </p>

    <div class="action-buttons">
      <a href="dashboard.php" class="btn-primary">
        Enter Dashboard 
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M5 12h14"></path>
          <path d="M12 5l7 7-7 7"></path>
        </svg>
      </a>
      
      <a href="logout.php" class="btn-secondary">
        Return to Login
      </a>
    </div>

  </div>
</div>

</body>
</html>
