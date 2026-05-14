<?php
require_once 'includes/auth.php';

// If already logged in, go straight to their dashboard
if (isLoggedIn()) {
    redirectBasedOnRole($_SESSION['role']);
}
// Otherwise fall through to the landing page below
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PetMate — Trusted Pet Care</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/Petmate/assets/css/style.css">
  <style>
    /* ── Landing-page-only overrides ───────────────────────── */

    /* Reset body top padding so navbar sits flush */
    body { padding-top: 0; }

    /* ── Navbar ─────────────────────────────────────────────── */
    .lp-nav {
      position: fixed;
      top: 0; left: 0; right: 0;
      z-index: 900;
      height: 64px;
      display: flex;
      align-items: center;
      padding: 0 48px;
      transition: background 0.25s, box-shadow 0.25s;
    }

    .lp-nav.scrolled {
      background: #fff;
      box-shadow: 0 2px 16px rgba(61,43,31,0.08);
    }

    .lp-nav-logo {
      display: flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      flex-shrink: 0;
    }

    .lp-nav-logo i {
      font-size: 26px;
      color: var(--color-accent);
    }

    .lp-nav-logo span {
      font-family: 'Playfair Display', serif;
      font-size: 20px;
      font-weight: 700;
      color: var(--color-espresso);
    }

    .lp-nav-links {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 32px;
      list-style: none;
    }

    .lp-nav-links a {
      font-size: 14px;
      font-weight: 500;
      color: var(--color-text);
      text-decoration: none;
      transition: color 0.15s;
    }

    .lp-nav-links a:hover { color: var(--color-accent); }

    .lp-nav-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-shrink: 0;
    }

    .btn-nav-login {
      padding: 7px 20px;
      border-radius: var(--radius-pill);
      border: 1.5px solid var(--color-espresso);
      background: transparent;
      color: var(--color-espresso);
      font-size: 13px;
      font-weight: 600;
      font-family: 'Inter', sans-serif;
      cursor: pointer;
      text-decoration: none;
      transition: background 0.15s, color 0.15s;
    }

    .btn-nav-login:hover {
      background: var(--color-blush-light);
      color: var(--color-espresso);
    }

    .btn-nav-register {
      padding: 7px 20px;
      border-radius: var(--radius-pill);
      border: none;
      background: var(--color-espresso);
      color: var(--color-bg);
      font-size: 13px;
      font-weight: 600;
      font-family: 'Inter', sans-serif;
      cursor: pointer;
      text-decoration: none;
      transition: background 0.15s;
    }

    .btn-nav-register:hover {
      background: var(--color-brown);
      color: var(--color-bg);
    }

    /* ── Hero ───────────────────────────────────────────────── */
    .lp-hero {
      min-height: 100vh;
      background: var(--color-bg);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 120px 24px 60px;
    }

    .lp-hero-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: var(--color-blush-light);
      color: var(--color-tan);
      font-size: 12px;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      padding: 5px 14px;
      border-radius: var(--radius-pill);
      margin-bottom: 24px;
    }

    .lp-hero h1 {
      font-family: 'Playfair Display', serif;
      font-size: clamp(40px, 6vw, 72px);
      font-weight: 700;
      color: var(--color-espresso);
      line-height: 1.15;
      max-width: 760px;
      margin-bottom: 20px;
    }

    .lp-hero h1 em {
      font-style: italic;
      color: var(--color-accent);
    }

    .lp-hero-sub {
      font-size: 17px;
      color: var(--color-muted);
      max-width: 480px;
      line-height: 1.7;
      margin-bottom: 36px;
    }

    .lp-hero-ctas {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      justify-content: center;
    }

    .btn-hero-primary {
      padding: 13px 32px;
      border-radius: var(--radius-pill);
      background: var(--color-espresso);
      color: var(--color-bg);
      font-size: 14px;
      font-weight: 600;
      font-family: 'Inter', sans-serif;
      text-decoration: none;
      border: none;
      cursor: pointer;
      transition: background 0.15s;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .btn-hero-primary:hover {
      background: var(--color-brown);
      color: var(--color-bg);
    }

    .btn-hero-outline {
      padding: 13px 32px;
      border-radius: var(--radius-pill);
      background: transparent;
      border: 1.5px solid var(--color-espresso);
      color: var(--color-espresso);
      font-size: 14px;
      font-weight: 600;
      font-family: 'Inter', sans-serif;
      text-decoration: none;
      cursor: pointer;
      transition: background 0.15s;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .btn-hero-outline:hover {
      background: var(--color-blush-light);
      color: var(--color-espresso);
    }

    /* ── Marquee ticker ─────────────────────────────────────── */
    .lp-ticker {
      width: 100%;
      background: var(--color-blush-mid);
      overflow: hidden;
      padding: 12px 0;
      margin-top: 56px;
    }

    .lp-ticker-track {
      display: flex;
      gap: 0;
      animation: ticker-scroll 28s linear infinite;
      white-space: nowrap;
    }

    .lp-ticker-track:hover { animation-play-state: paused; }

    .lp-ticker-item {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 0 28px;
      font-size: 13px;
      font-weight: 600;
      color: var(--color-espresso);
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }

    .lp-ticker-item i {
      font-size: 16px;
      color: var(--color-accent);
    }

    @keyframes ticker-scroll {
      0%   { transform: translateX(0); }
      100% { transform: translateX(-50%); }
    }

    /* ── About section ──────────────────────────────────────── */
    .lp-about {
      background: var(--color-bg);
      padding: 100px 48px;
    }

    .lp-about-inner {
      max-width: 1100px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 72px;
      align-items: center;
    }

    /* Left: blush image placeholder */
    .lp-about-img {
      position: relative;
      border-radius: 24px;
      overflow: hidden;
      background: var(--color-blush-light);
      aspect-ratio: 4/3;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .lp-about-img-bg {
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, var(--color-blush-light) 0%, var(--color-blush-mid) 60%, var(--color-blush-deep) 100%);
    }

    .lp-about-paw-overlay {
      position: relative;
      z-index: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 12px;
    }

    .lp-about-paw-overlay i {
      font-size: 80px;
      color: rgba(196,133,106,0.35);
    }

    .lp-about-badge {
      position: absolute;
      bottom: 24px;
      right: 24px;
      background: var(--color-espresso);
      color: var(--color-bg);
      border-radius: 14px;
      padding: 14px 20px;
      text-align: center;
      z-index: 2;
    }

    .lp-about-badge-num {
      font-family: 'Playfair Display', serif;
      font-size: 28px;
      font-weight: 700;
      line-height: 1;
      display: block;
    }

    .lp-about-badge-label {
      font-size: 11px;
      font-weight: 500;
      color: var(--color-blush-mid);
      display: block;
      margin-top: 2px;
    }

    /* Right: text */
    .lp-about-text .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: var(--color-blush-light);
      color: var(--color-tan);
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      padding: 4px 12px;
      border-radius: var(--radius-pill);
      margin-bottom: 16px;
    }

    .lp-about-text h2 {
      font-family: 'Playfair Display', serif;
      font-size: clamp(28px, 3.5vw, 42px);
      font-weight: 700;
      color: var(--color-espresso);
      line-height: 1.2;
      margin-bottom: 20px;
    }

    .lp-about-text h2 em {
      font-style: italic;
      color: var(--color-accent);
    }

    .lp-about-text p {
      font-size: 15px;
      color: var(--color-muted);
      line-height: 1.8;
      margin-bottom: 32px;
    }

    .lp-about-features {
      display: flex;
      flex-direction: column;
      gap: 14px;
      margin-bottom: 36px;
    }

    .lp-about-feature {
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }

    .lp-about-feature-icon {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      background: var(--color-blush-light);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .lp-about-feature-icon i {
      font-size: 18px;
      color: var(--color-accent);
    }

    .lp-about-feature-text strong {
      display: block;
      font-size: 14px;
      font-weight: 600;
      color: var(--color-text);
      margin-bottom: 2px;
    }

    .lp-about-feature-text span {
      font-size: 13px;
      color: var(--color-muted);
    }

    /* ── Footer ─────────────────────────────────────────────── */
    .lp-footer {
      background: var(--color-espresso);
      padding: 64px 48px 32px;
    }

    .lp-footer-grid {
      max-width: 1100px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 1fr;
      gap: 48px;
      padding-bottom: 48px;
      border-bottom: 1px solid var(--color-brown);
    }

    .lp-footer-brand .logo {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 16px;
      text-decoration: none;
    }

    .lp-footer-brand .logo i {
      font-size: 26px;
      color: var(--color-accent);
    }

    .lp-footer-brand .logo span {
      font-family: 'Playfair Display', serif;
      font-size: 20px;
      font-weight: 700;
      color: var(--color-bg);
    }

    .lp-footer-brand p {
      font-size: 13px;
      color: #A08070;
      line-height: 1.7;
      max-width: 240px;
      margin-bottom: 24px;
    }

    .lp-footer-socials {
      display: flex;
      gap: 10px;
    }

    .lp-footer-social-btn {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: var(--color-brown);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #A08070;
      font-size: 18px;
      text-decoration: none;
      transition: background 0.15s, color 0.15s;
    }

    .lp-footer-social-btn:hover {
      background: var(--color-accent);
      color: var(--color-bg);
    }

    .lp-footer-col h4 {
      font-family: 'Inter', sans-serif;
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--color-bg);
      margin-bottom: 16px;
    }

    .lp-footer-col ul {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .lp-footer-col ul li a {
      font-size: 13px;
      color: #A08070;
      text-decoration: none;
      transition: color 0.15s;
    }

    .lp-footer-col ul li a:hover { color: var(--color-bg); }

    .lp-footer-contact-item {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      margin-bottom: 12px;
    }

    .lp-footer-contact-item i {
      font-size: 16px;
      color: var(--color-accent);
      flex-shrink: 0;
      margin-top: 1px;
    }

    .lp-footer-contact-item span {
      font-size: 13px;
      color: #A08070;
      line-height: 1.5;
    }

    .lp-footer-bottom {
      max-width: 1100px;
      margin: 0 auto;
      padding-top: 28px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
    }

    .lp-footer-bottom p {
      font-size: 12px;
      color: #6B5040;
    }

    .lp-footer-bottom-links {
      display: flex;
      gap: 20px;
    }

    .lp-footer-bottom-links a {
      font-size: 12px;
      color: #6B5040;
      text-decoration: none;
      transition: color 0.15s;
    }

    .lp-footer-bottom-links a:hover { color: #A08070; }

    /* ── Responsive ─────────────────────────────────────────── */
    @media (max-width: 900px) {
      .lp-nav { padding: 0 24px; }
      .lp-nav-links { display: none; }
      .lp-about { padding: 64px 24px; }
      .lp-about-inner { grid-template-columns: 1fr; gap: 40px; }
      .lp-footer { padding: 48px 24px 24px; }
      .lp-footer-grid { grid-template-columns: 1fr 1fr; gap: 32px; }
    }

    @media (max-width: 560px) {
      .lp-footer-grid { grid-template-columns: 1fr; }
      .lp-footer-bottom { flex-direction: column; align-items: flex-start; }
    }
  </style>
</head>
<body style="padding-top:0; margin:0;">

<!-- ══════════════════════════════════════════════════════════
     NAVBAR
══════════════════════════════════════════════════════════ -->
<nav class="lp-nav" id="lpNav">
  <a href="/Petmate/" class="lp-nav-logo">
    <i class="bx bx-paw"></i>
    <span>PetMate</span>
  </a>

  <ul class="lp-nav-links">
    <li><a href="#hero">Home</a></li>
    <li><a href="#about">About</a></li>
    <li><a href="/Petmate/login.php">Services</a></li>
    <li><a href="#contact">Contact</a></li>
  </ul>

  <div class="lp-nav-actions">
    <a href="/Petmate/login.php" class="btn-nav-login">Log In</a>
    <a href="/Petmate/register.php" class="btn-nav-register">Register</a>
  </div>
</nav>

<!-- ══════════════════════════════════════════════════════════
     HERO
══════════════════════════════════════════════════════════ -->
<section class="lp-hero" id="hero">
  <div class="lp-hero-eyebrow">
    <i class="bx bx-paw"></i>
    Trusted Pet Care Platform
  </div>

  <h1><em>Pawsome Care,</em> — Just a Tap Away</h1>



  <div class="lp-hero-ctas">
    <a href="/Petmate/register.php" class="btn-hero-primary">
      <i class="bx bx-paw"></i> Get Started
    </a>
    <a href="#about" class="btn-hero-outline">
      Learn More <i class="bx bx-chevron-down"></i>
    </a>
  </div>

  <!-- Marquee ticker -->
  <div class="lp-ticker">
    <div class="lp-ticker-track">
      <!-- First copy -->
      <span class="lp-ticker-item"><i class="bx bx-paw"></i> Trusted Veterinary Care</span>
      <span class="lp-ticker-item"><i class="bx bx-paw"></i> Secure Health Records</span>
      <span class="lp-ticker-item"><i class="bx bx-paw"></i> Easy Appointment Booking</span>
      <span class="lp-ticker-item"><i class="bx bx-paw"></i> Real-Time Notifications</span>
      <span class="lp-ticker-item"><i class="bx bx-paw"></i> Multi-Role Dashboard</span>
      <span class="lp-ticker-item"><i class="bx bx-paw"></i> Pet Wellness Tracking</span>
      <span class="lp-ticker-item"><i class="bx bx-paw"></i> Warm & Caring Community</span>
      <span class="lp-ticker-item"><i class="bx bx-paw"></i> Vaccination Reminders</span>
      <!-- Duplicate for seamless loop -->
      <span class="lp-ticker-item"><i class="bx bx-paw"></i> Trusted Veterinary Care</span>
      <span class="lp-ticker-item"><i class="bx bx-paw"></i> Secure Health Records</span>
      <span class="lp-ticker-item"><i class="bx bx-paw"></i> Easy Appointment Booking</span>
      <span class="lp-ticker-item"><i class="bx bx-paw"></i> Real-Time Notifications</span>
      <span class="lp-ticker-item"><i class="bx bx-paw"></i> Multi-Role Dashboard</span>
      <span class="lp-ticker-item"><i class="bx bx-paw"></i> Pet Wellness Tracking</span>
      <span class="lp-ticker-item"><i class="bx bx-paw"></i> Warm & Caring Community</span>
      <span class="lp-ticker-item"><i class="bx bx-paw"></i> Vaccination Reminders</span>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     ABOUT
══════════════════════════════════════════════════════════ -->
<section class="lp-about" id="about">
  <div class="lp-about-inner">

    <!-- Left: blush image placeholder -->
    <div class="lp-about-img">
      <div class="lp-about-img-bg"></div>
      <div class="lp-about-paw-overlay">
        <i class="bx bx-paw"></i>
      </div>
      <!-- Stats badge removed — no DB connection -->
    </div>

    <!-- Right: text -->
    <div class="lp-about-text">
      <span class="eyebrow"><i class="bx bx-heart"></i> About PetMate</span>

      <h2>We Care for Your Pets <em>Like Family</em></h2>

      <p>
        PetMate is a comprehensive veterinary management platform built to
        bridge the gap between pet owners and healthcare professionals.
        From registration to discharge, every step is streamlined, secure,
        and designed with your pet's wellbeing in mind.
      </p>

      <div class="lp-about-features">
        <div class="lp-about-feature">
          <div class="lp-about-feature-icon">
            <i class="bx bx-shield-quarter"></i>
          </div>
          <div class="lp-about-feature-text">
            <strong>Secure Health Records</strong>
            <span>All pet data is encrypted and accessible only to authorized roles.</span>
          </div>
        </div>
        <div class="lp-about-feature">
          <div class="lp-about-feature-icon">
            <i class="bx bx-calendar-check"></i>
          </div>
          <div class="lp-about-feature-text">
            <strong>Seamless Visit Management</strong>
            <span>From check-in to discharge, every step is tracked in real time.</span>
          </div>
        </div>
        <div class="lp-about-feature">
          <div class="lp-about-feature-icon">
            <i class="bx bx-group"></i>
          </div>
          <div class="lp-about-feature-text">
            <strong>Multi-Role Collaboration</strong>
            <span>CSR, Vet Assistants, Technicians, and Owners — all in one place.</span>
          </div>
        </div>
      </div>

      <a href="/Petmate/register.php" class="btn-hero-primary">
        <i class="bx bx-right-arrow-alt"></i> Get Started Today
      </a>
    </div>

  </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     FOOTER
══════════════════════════════════════════════════════════ -->
<footer class="lp-footer" id="contact">
  <div class="lp-footer-grid">

    <!-- Brand column -->
    <div class="lp-footer-brand">
      <a href="/Petmate/" class="logo">
        <i class="bx bx-paw"></i>
        <span>PetMate</span>
      </a>
      <p>
        A warm, trusted platform connecting pet owners with veterinary
        professionals. Because every pet deserves the best care.
      </p>
      <div class="lp-footer-socials">
        <a href="#" class="lp-footer-social-btn" aria-label="Facebook"><i class="bx bxl-facebook"></i></a>
        <a href="#" class="lp-footer-social-btn" aria-label="Instagram"><i class="bx bxl-instagram"></i></a>
        <a href="#" class="lp-footer-social-btn" aria-label="Twitter"><i class="bx bxl-twitter"></i></a>
        <a href="#" class="lp-footer-social-btn" aria-label="LinkedIn"><i class="bx bxl-linkedin"></i></a>
      </div>
    </div>

    <!-- Quick links -->
    <div class="lp-footer-col">
      <h4>Quick Links</h4>
      <ul>
        <li><a href="#hero">Home</a></li>
        <li><a href="#about">About Us</a></li>
        <li><a href="/Petmate/login.php">Services</a></li>
        <li><a href="/Petmate/register.php">Register</a></li>
        <li><a href="/Petmate/login.php">Log In</a></li>
      </ul>
    </div>

    <!-- Services -->
    <div class="lp-footer-col">
      <h4>Services</h4>
      <ul>
        <li><a href="#">Pet Registration</a></li>
        <li><a href="#">Health Records</a></li>
        <li><a href="#">Appointment Booking</a></li>
        <li><a href="#">Vaccination Tracking</a></li>
        <li><a href="#">Billing & Payments</a></li>
      </ul>
    </div>

    <!-- Contact -->
    <div class="lp-footer-col">
      <h4>Contact</h4>
      <p style="font-size:13px; color:#A08070; line-height:1.7;">
        For inquiries, please reach out through the platform after registering.
      </p>
    </div>

  </div>

  <!-- Bottom bar -->
  <div class="lp-footer-bottom">
    <p>© <?= date('Y') ?> PetMate. All rights reserved.</p>
    <div class="lp-footer-bottom-links">
      <a href="#">Privacy Policy</a>
      <a href="#">Terms of Service</a>
      <a href="#">Cookie Policy</a>
    </div>
  </div>
</footer>

<!-- Navbar scroll effect -->
<script>
  const nav = document.getElementById('lpNav');
  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 20);
  });

  // Smooth scroll for anchor links
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
      const target = document.querySelector(a.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth' });
      }
    });
  });
</script>

</body>
</html>
