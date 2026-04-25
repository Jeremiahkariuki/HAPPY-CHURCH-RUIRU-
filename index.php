<?php
declare(strict_types=1);

// Immediate health check response for Render
if ($_SERVER['REQUEST_URI'] === '/health' || $_SERVER['REQUEST_URI'] === '/health.php' || (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Render') !== false)) {
    http_response_code(200);
    echo "OK";
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Happy Church Ruiru • Welcome</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
    background:
      radial-gradient(1200px 800px at 30% 10%, rgba(124,92,255,.18), transparent 60%),
      radial-gradient(800px 600px at 80% 80%, rgba(46,233,166,.10), transparent 50%),
      #07101f;
    padding: 20px;
    position: relative;
    overflow-x: hidden;
    color: #fff;
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
    z-index: 1;
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

  /* Navigation */
  header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 40px;
    position: relative;
    z-index: 10;
    max-width: 1200px;
    margin: 0 auto;
    width: 100%;
    animation: slideDown 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
  }
  .logo {
    font-size: 1.5rem;
    font-weight: 900;
    letter-spacing: -0.5px;
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: #fff;
  }
  .logo-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, #7c5cff, #2ee9a6);
    display: grid;
    place-items: center;
    font-size: 20px;
    color: #07101f;
    box-shadow: 0 4px 15px rgba(124,92,255,.4);
  }
  .auth-links {
    display: flex;
    gap: 16px;
    align-items: center;
  }
  .auth-links a {
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.3s;
  }
  .btn-login {
    color: #a9b7d0;
  }
  .btn-login:hover {
    color: #fff;
  }
  .btn-register {
    padding: 10px 24px;
    background: rgba(124,92,255,.1);
    color: #b09cff;
    border: 1px solid rgba(124,92,255,.3);
    border-radius: 20px;
    backdrop-filter: blur(10px);
  }
  .btn-register:hover {
    background: rgba(124,92,255,.2);
    box-shadow: 0 5px 15px rgba(124,92,255,.2);
    transform: translateY(-2px);
  }

  @keyframes slideDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
  }

  /* Hero Section */
  main {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    position: relative;
    z-index: 10;
    max-width: 900px;
    margin: 0 auto;
    padding: 40px 20px;
  }

  .pill-badge {
    display: inline-block;
    padding: 8px 20px;
    background: rgba(46,233,166,.1);
    border: 1px solid rgba(46,233,166,.3);
    color: #2ee9a6;
    border-radius: 30px;
    font-size: 0.85rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-bottom: 24px;
    animation: fadeIn 1s ease forwards 0.2s;
    opacity: 0;
  }

  h1 {
    font-size: 4.5rem;
    font-weight: 900;
    line-height: 1.1;
    letter-spacing: -1.5px;
    margin-bottom: 24px;
    background: linear-gradient(135deg, #ffffff 30%, #a9b7d0 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: fadeInUp 1s cubic-bezier(0.16, 1, 0.3, 1) forwards 0.3s;
    opacity: 0;
    transform: translateY(20px);
  }

  p.subtitle {
    font-size: 1.25rem;
    color: #a9b7d0;
    max-width: 600px;
    margin: 0 auto 40px;
    line-height: 1.6;
    animation: fadeInUp 1s cubic-bezier(0.16, 1, 0.3, 1) forwards 0.4s;
    opacity: 0;
    transform: translateY(20px);
  }

  .cta-group {
    display: flex;
    gap: 20px;
    justify-content: center;
    animation: fadeInUp 1s cubic-bezier(0.16, 1, 0.3, 1) forwards 0.5s;
    opacity: 0;
    transform: translateY(20px);
  }

  .btn-primary, .btn-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 16px 36px;
    border-radius: 30px;
    font-weight: 700;
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  }

  .btn-primary {
    background: linear-gradient(135deg, #7c5cff, #2ee9a6);
    color: #07101f;
    box-shadow: 0 15px 30px rgba(124,92,255,.3);
    border: none;
  }
  .btn-primary:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px rgba(124,92,255,.4), 0 0 20px rgba(46,233,166,.3);
  }

  .btn-secondary {
    background: rgba(15, 26, 46, 0.6);
    color: #fff;
    border: 1px solid rgba(255,255,255,.1);
    backdrop-filter: blur(10px);
  }
  .btn-secondary:hover {
    background: rgba(255,255,255,.05);
    border-color: rgba(255,255,255,.2);
    transform: translateY(-4px);
  }

  @keyframes fadeInUp {
    to { opacity: 1; transform: translateY(0); }
  }
  @keyframes fadeIn {
    to { opacity: 1; }
  }

  /* Glassmorphic Features */
  .features {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    width: 100%;
    margin-top: 60px;
    animation: fadeInUp 1s cubic-bezier(0.16, 1, 0.3, 1) forwards 0.6s;
    opacity: 0;
    transform: translateY(20px);
  }

  .feature-card {
    background: rgba(15, 26, 46, 0.4);
    border: 1px solid rgba(255,255,255,.05);
    padding: 24px;
    border-radius: 20px;
    backdrop-filter: blur(10px);
    text-align: left;
    transition: all 0.3s ease;
  }
  .feature-card:hover {
    background: rgba(15, 26, 46, 0.7);
    border-color: rgba(124,92,255,.2);
    transform: translateY(-5px);
  }
  .feature-icon {
    font-size: 24px;
    margin-bottom: 16px;
    display: inline-block;
    padding: 12px;
    background: rgba(255,255,255,.03);
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,.05);
  }
  .feature-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 8px;
    color: #fff;
  }
  .feature-text {
    font-size: 0.9rem;
    color: #8c9baf;
    line-height: 1.5;
  }

  @media (max-width: 768px) {
    h1 { font-size: 3rem; }
    .cta-group { flex-direction: column; }
    header { padding: 15px 20px; }
  }
</style>
</head>
<body>

  <header>
    <a href="index.php" class="logo">
      <div class="logo-icon">✝</div>
      <span>Happy Church</span>
    </a>
    <div class="auth-links">
      <a href="login.php" class="btn-login">Sign In</a>
      <a href="register.php" class="btn-register">Join Us</a>
    </div>
  </header>

  <main>
    <div class="pill-badge">Welcome to the Family</div>
    <h1>Connect, Grow & Serve with Us</h1>
    <p class="subtitle">Join Happy Church Ruiru to seamlessly manage your events, volunteer opportunities, and connect with a vibrant community.</p>
    
    <div class="cta-group">
      <a href="register.php" class="btn-primary">Become a Member</a>
      <a href="login.php" class="btn-secondary">Member Portal</a>
    </div>

    <div class="features">
      <div class="feature-card">
        <div class="feature-icon">🗓️</div>
        <h3 class="feature-title">Events</h3>
        <p class="feature-text">Stay updated on upcoming services and community gatherings.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🤝</div>
        <h3 class="feature-title">Volunteering</h3>
        <p class="feature-text">Join various ministries and serve your church family actively.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">✨</div>
        <h3 class="feature-title">Community</h3>
        <p class="feature-text">Connect with leaders, members, and stay engagingly informed.</p>
      </div>
    </div>
  </main>

</body>
</html>
