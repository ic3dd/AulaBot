<?php
// 1. INICIAR SESSÃO
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// 2. COOKIE DE "BOAS VINDAS"
if (empty($_COOKIE['seen_welcome'])) {
  setcookie('seen_welcome', '1', time() + (365 * 24 * 3600), '/');
}

// 3. PROTEÇÃO DE IP (PRIVACIDADE)
if (empty($_SESSION['ip_protected_hash'])) {
  $_SESSION['ip_protected_hash'] = password_hash($_SERVER['REMOTE_ADDR'], PASSWORD_DEFAULT);
}
?>

<!doctype html>
<html lang="pt">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1, maximum-scale=1">
  <title>AulaBot - Estuda com IA Gratuitamente</title>

  <link rel="icon" href="assets/img/nova-logo-removebg.png">
  <link rel="shortcut icon" href="assets/img/nova-logo-removebg.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">

  <style>
    :root {
      /* Cores Globais - Paleta mais vibrante */
      --primary: #6366f1;
      --primary-hover: #4f46e5;
      --primary-light: #818cf8;
      --accent: #10b981;
      --accent-light: #34d399;
      --bg-body: #f8fafc;
      --bg-alt: #f1f5f9;
      --bg-card: #ffffff;
      --text-main: #1e293b;
      --text-muted: #64748b;
      --border: #e2e8f0;
      --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.07), 0 1px 2px 0 rgba(0, 0, 0, 0.05);
      --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
      --shadow-xl: 0 20px 40px -10px rgba(99, 102, 241, 0.25);

      /* Variáveis do Modal */
      --modal-primary: var(--primary);
      --modal-secondary: var(--accent);
      --modal-text: var(--text-main);
      --modal-text-light: var(--text-muted);
      --modal-bg: #ffffff;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html {
      scroll-behavior: smooth;
      width: 100%;
      overflow-x: hidden;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #f8fafc 0%, #e0e7ff 50%, #f8fafc 100%);
      background-attachment: fixed;
      color: var(--text-main);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      animation: fadeIn 0.5s ease-out;
      width: 100%;
      max-width: 100%;
      overflow-x: hidden;
      position: relative;
    }

    /* Decorative background elements */
    body::before {
      content: '';
      position: fixed;
      top: -50%;
      right: -30%;
      width: 80%;
      height: 80%;
      background: radial-gradient(circle, rgba(99, 102, 241, 0.08) 0%, transparent 70%);
      pointer-events: none;
      z-index: -1;
    }

    body::after {
      content: '';
      position: fixed;
      bottom: -40%;
      left: -20%;
      width: 60%;
      height: 60%;
      background: radial-gradient(circle, rgba(16, 185, 129, 0.06) 0%, transparent 70%);
      pointer-events: none;
      z-index: -1;
    }

    img {
      max-width: 100%;
      height: auto;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
      }

      to {
        opacity: 1;
      }
    }

    @keyframes float {

      0%,
      100% {
        transform: translateY(0);
      }

      50% {
        transform: translateY(-10px);
      }
    }

    @keyframes pulse {

      0%,
      100% {
        opacity: 1;
      }

      50% {
        opacity: 0.7;
      }
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
      width: 100%;
      position: relative;
      box-sizing: border-box;
    }

    .section {
      padding: 80px 0;
      transition: padding 0.3s;
      width: 100%;
    }

    .section-alt {
      background-color: rgba(241, 245, 249, 0.8);
      backdrop-filter: blur(10px);
    }

    .section-header {
      text-align: center;
      margin-bottom: 50px;
      padding: 0 10px;
      animation: slideUp 0.6s ease-out;
    }

    .section-header h2 {
      font-size: 32px;
      font-weight: 800;
      margin: 0 0 10px 0;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .section-header p {
      font-size: 18px;
      color: var(--text-muted);
      max-width: 600px;
      margin: 0 auto;
    }

    /* --- TOPBAR --- */
    /* --- TOPBAR --- */
    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px 5px;
      /* Added horizontal padding to safeguard edges */
      flex-wrap: wrap;
      gap: 20px;
      position: sticky;
      top: 0;
      background: rgba(248, 250, 252, 0.9);
      /* Slightly more opaque */
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      z-index: 9999;
      /* Increased z-index greatly to stay on top */
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
      /* Subtle shadow separator */
      border-bottom: 1px solid rgba(255, 255, 255, 0.5);
      transition: all 0.3s ease;
      margin: 0 -20px;
      /* Counteract container padding to extend full width visually if inside container */
      padding-left: 20px;
      padding-right: 20px;
      width: calc(100% + 40px);
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .brand img {
      width: 36px;
      height: 36px;
    }

    .brand h1 {
      margin: 0;
      font-size: 24px;
      font-weight: 800;
    }

    .auth-buttons {
      display: flex;
      gap: 12px;
    }

    .btn {
      padding: 10px 20px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      transition: all 0.2s;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      border: 1px solid transparent;
      cursor: pointer;
    }

    .btn-outline {
      background: white;
      border-color: var(--border);
      color: var(--text-main);
      box-shadow: var(--shadow-sm);
    }

    .btn-outline:hover {
      border-color: var(--primary);
      color: var(--primary);
      box-shadow: var(--shadow-lg);
    }

    .btn-primary {
      background: var(--primary);
      color: #fff;
      box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);
    }

    .btn-primary:hover {
      background: var(--primary-hover);
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(79, 70, 229, 0.3);
    }

    /* --- HERO --- */
    .hero {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 60px;
      align-items: center;
      padding: 60px 0;
      width: 100%;
      animation: slideUp 0.8s ease-out;
    }

    .hero-text {
      min-width: 0;
    }

    .hero-text h2 {
      font-size: 52px;
      font-weight: 800;
      line-height: 1.15;
      margin: 0 0 20px 0;
      letter-spacing: -1.5px;
      color: var(--text-main);
    }

    .hero-text h2 .highlight {
      background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      position: relative;
    }

    .hero-text p {
      font-size: 18px;
      color: var(--text-muted);
      margin-bottom: 30px;
      line-height: 1.7;
    }

    .hero-benefits {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
      margin-top: 30px;
    }

    .benefit {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 14px;
      font-weight: 500;
      padding: 10px 14px;
      background: rgba(255, 255, 255, 0.7);
      backdrop-filter: blur(10px);
      border-radius: 10px;
      border: 1px solid rgba(99, 102, 241, 0.1);
      transition: all 0.3s ease;
    }

    .benefit:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-sm);
      border-color: rgba(99, 102, 241, 0.2);
    }

    .benefit i {
      color: var(--accent);
      font-size: 16px;
    }

    .chat-wrapper-col {
      display: flex;
      flex-direction: column;
      gap: 20px;
      width: 100%;
      min-width: 0;
    }

    .limit-card {
      background: var(--bg-card);
      padding: 15px 20px;
      border-radius: 12px;
      border: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
      box-shadow: var(--shadow-sm);
      transition: all 0.3s;
    }

    .limit-text {
      font-size: 14px;
      font-weight: 600;
      color: var(--text-main);
    }

    .ephemeral-badge {
      background: #fff7ed;
      color: #92400e;
      border: 1px solid #ffedd5;
      padding: 6px 10px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: 600;
      display: inline-block;
      margin-left: 12px;
      white-space: nowrap;
    }

    .limit-sub {
      font-size: 13px;
      color: var(--text-muted);
      font-weight: 500;
    }

    /* --- CHAT INTERFACE --- */
    .chat-container {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      border-radius: 20px;
      box-shadow: var(--shadow-xl), 0 0 0 1px rgba(99, 102, 241, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.8);
      display: flex;
      flex-direction: column;
      height: 500px;
      overflow: hidden;
      position: relative;
      width: 100%;
      animation: float 6s ease-in-out infinite;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .chat-container:hover {
      animation-play-state: paused;
      transform: translateY(-5px);
      box-shadow: 0 25px 50px -10px rgba(99, 102, 241, 0.3), 0 0 0 1px rgba(99, 102, 241, 0.15);
    }

    .messages-area {
      flex: 1;
      overflow-y: auto;
      padding: 24px;
      background: #f8fafc;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .message-row {
      display: flex;
      width: 100%;
    }

    .message-row.user {
      justify-content: flex-end;
    }

    .message-row.bot {
      justify-content: flex-start;
    }

    .bubble {
      max-width: 85%;
      padding: 12px 18px;
      border-radius: 18px;
      font-size: 15px;
      line-height: 1.5;
      position: relative;
      box-shadow: var(--shadow-sm);
      word-wrap: break-word;
    }

    .message-row.user .bubble {
      background: var(--primary);
      color: white;
      border-bottom-right-radius: 4px;
    }

    .message-row.bot .bubble {
      background: #fff;
      color: var(--text-main);
      border: 1px solid var(--border);
      border-bottom-left-radius: 4px;
    }

    .msg-avatar {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      margin-right: 10px;
      background: #e0e7ff;
      color: var(--primary);
      flex-shrink: 0;
    }

    .input-area {
      padding: 16px;
      background: #fff;
      border-top: 1px solid var(--border);
      display: flex;
      gap: 10px;
      align-items: center;
    }

    .input-area input[type=text] {
      flex: 1;
      padding: 14px;
      border: 2px solid var(--border);
      border-radius: 10px;
      font-size: 15px;
      outline: none;
      transition: border-color 0.2s;
      min-width: 0;
    }

    .input-area input[type=text]:focus {
      border-color: var(--primary);
    }

    .btn-icon {
      width: 48px;
      height: 48px;
      border-radius: 10px;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      transition: all 0.2s;
      flex-shrink: 0;
    }

    #send {
      background: var(--primary);
      color: white;
    }

    #send:hover {
      background: var(--primary-hover);
    }

    #upload {
      background: #f1f5f9;
      color: var(--text-muted);
    }

    #upload:hover {
      background: #e2e8f0;
      color: var(--primary);
    }

    /* --- FEATURES SECTION --- */
    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 25px;
    }

    .feature-card {
      background: rgba(255, 255, 255, 0.8);
      backdrop-filter: blur(10px);
      padding: 35px;
      border-radius: 20px;
      border: 1px solid rgba(99, 102, 241, 0.1);
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      transform: translateY(0);
      position: relative;
      overflow: hidden;
    }

    .feature-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--primary), var(--accent));
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .feature-card:hover {
      transform: translateY(-8px);
      box-shadow: var(--shadow-xl);
      border-color: rgba(99, 102, 241, 0.2);
    }

    .feature-card:hover::before {
      opacity: 1;
    }

    .feat-icon {
      width: 56px;
      height: 56px;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      margin-bottom: 20px;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: white;
      box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
      transition: transform 0.3s ease;
    }

    .feature-card:hover .feat-icon {
      transform: scale(1.1) rotate(5deg);
    }

    .feature-card h3 {
      font-size: 19px;
      font-weight: 700;
      margin: 0 0 12px 0;
      color: var(--text-main);
    }

    .feature-card p {
      font-size: 15px;
      color: var(--text-muted);
      line-height: 1.7;
      margin: 0;
    }

    /* --- HOW IT WORKS --- */
    .how-it-works-steps {
      display: flex;
      justify-content: space-around;
      gap: 30px;
      flex-wrap: wrap;
    }

    .step {
      text-align: center;
      max-width: 300px;
      padding: 10px;
    }

    .step-number {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: var(--primary);
      color: white;
      font-size: 24px;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 20px;
      box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);
    }

    .step h3 {
      font-size: 20px;
      margin: 0 0 10px 0;
    }

    .step p {
      font-size: 16px;
      color: var(--text-muted);
      line-height: 1.6;
    }

    /* --- CTA SECTION --- */
    .cta-section {
      text-align: center;
    }

    .cta-section h2 {
      font-size: 36px;
      font-weight: 800;
      margin-bottom: 20px;
    }

    .cta-section p {
      font-size: 18px;
      color: var(--text-muted);
      margin-bottom: 30px;
    }

    /* --- FOOTER --- */
    .footer {
      background: var(--bg-alt);
      padding: 40px 0;
      text-align: center;
      margin-top: auto;
      border-top: 1px solid var(--border);
    }

    .footer .brand {
      justify-content: center;
      margin-bottom: 20px;
    }

    .footer-links {
      list-style: none;
      padding: 0;
      margin: 0 0 20px 0;
      display: flex;
      justify-content: center;
      gap: 25px;
      flex-wrap: wrap;
    }

    .footer-links a {
      text-decoration: none;
      color: var(--text-muted);
      font-weight: 500;
      font-size: 14px;
      transition: color 0.2s;
    }

    .footer-links a:hover {
      color: var(--primary);
    }

    .footer-copy {
      font-size: 13px;
      color: #94a3b8;
    }

    /* --- MODAL AVISO --- */
    .modal {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.5);
      backdrop-filter: blur(4px);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 10000;
      opacity: 0;
      transition: opacity 0.3s;
    }

    .modal.show {
      opacity: 1;
    }

    .modal-box {
      background: #fff;
      padding: 30px;
      border-radius: 16px;
      max-width: 420px;
      width: 90%;
      text-align: center;
      transform: scale(0.95);
      transition: transform 0.3s;
    }

    .modal.show .modal-box {
      transform: scale(1);
    }

    /* --- LOGIN/SIGNUP MODAL --- */
    #login-modal {
      display: none;
      position: fixed;
      z-index: 9999;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.7);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      align-items: center;
      justify-content: center;
    }

    #login-modal.show {
      display: flex;
    }

    html.modal-active,
    body.modal-active {
      overflow: hidden !important;
    }

    body.modal-active .container {
      filter: blur(2px);
      pointer-events: none;
    }

    .modal-content {
      background-color: #ffffff;
      padding: 0;
      border-radius: 20px;
      width: 90%;
      max-width: 380px;
      /* Altura base para desktop e mobile */
      min-height: 600px;
      max-height: 95vh;

      position: relative;
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
      animation: modalSlideIn 0.3s ease-out;
      overflow: hidden;

      /* IMPORTANTE: Flex para distribuir altura corretamente */
      display: flex;
      flex-direction: column;

      margin: 0;
    }

    @keyframes modalSlideIn {
      from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
      }

      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    .modal-header {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 20px;
      padding: 20px 25px;
      background: linear-gradient(135deg, #f7f7f8 0%, #e5e5e6 100%);
      border-bottom: 1px solid var(--border);
      flex-shrink: 0;
    }

    .toggle-label {
      font-size: 15px;
      font-weight: 600;
      color: var(--modal-text-light);
      cursor: pointer;
      transition: all 0.3s ease;
      user-select: none;
    }

    .toggle-label.active {
      color: var(--modal-primary);
    }

    .switch {
      position: relative;
      display: inline-block;
      width: 50px;
      height: 28px;
    }

    .toggle {
      opacity: 0;
      width: 0;
      height: 0;
    }

    .slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #ccc;
      transition: 0.3s;
      border-radius: 32px;
      box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .slider:before {
      position: absolute;
      content: "";
      height: 22px;
      width: 22px;
      left: 3px;
      bottom: 3px;
      background-color: white;
      transition: 0.3s;
      border-radius: 50%;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }

    .toggle:checked+.slider {
      background-color: var(--modal-primary);
    }

    .toggle:checked+.slider:before {
      transform: translateX(22px);
    }

    .form-container {
      position: relative;
      flex: 1;
      /* Ocupa todo o espaço restante abaixo do header */
      overflow: hidden;
      width: 100%;
    }

    .form-card {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      padding: 30px 25px;
      display: flex;
      flex-direction: column;
      align-items: center;
      opacity: 1;
      transform: translateX(0);
      transition: all 0.4s ease;
      box-sizing: border-box;
      overflow-y: auto;
    }

    .form-card.hidden {
      opacity: 0;
      transform: translateX(100%);
      pointer-events: none;
    }

    #signup-form {
      opacity: 0;
      transform: translateX(-100%);
    }

    #signup-form.show {
      opacity: 1;
      transform: translateX(0);
    }

    #login-form.hidden {
      opacity: 0;
      transform: translateX(-100%);
    }

    .title {
      font-size: 24px;
      font-weight: 700;
      color: var(--modal-text);
      margin: 0 0 20px 0;
      text-align: center;
    }

    .flip-card__form {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 15px;
      width: 100%;
      justify-content: flex-start;
    }

    .flip-card__input {
      width: 100%;
      height: 44px;
      border: 2px solid var(--border);
      border-radius: 10px;
      background-color: var(--bg-card);
      font-size: 14px;
      font-weight: 500;
      color: var(--modal-text);
      padding: 0 16px;
      outline: none;
      transition: all 0.3s ease;
    }

    .flip-card__input::placeholder {
      color: var(--modal-text-light);
      font-weight: 400;
    }

    .flip-card__input:focus {
      border-color: var(--modal-primary);
      box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
      transform: translateY(-1px);
    }

    .flip-card__btn {
      width: 100%;
      height: 44px;
      background: linear-gradient(135deg, var(--modal-primary) 0%, var(--modal-secondary) 100%);
      color: white;
      border: none;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 10px;
      box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
      flex-shrink: 0;
    }

    .flip-card__btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(79, 70, 229, 0.4);
    }

    .termos-container {
      width: 100%;
      margin-bottom: 5px;
      margin-top: 5px;
    }

    .checkbox-wrapper {
      display: flex;
      align-items: flex-start;
      gap: 10px;
    }

    .termos-checkbox {
      opacity: 0;
      width: 0;
      height: 0;
      margin: 0;
    }

    .termos-label {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      cursor: pointer;
      user-select: none;
    }

    .checkbox-custom {
      flex-shrink: 0;
      width: 18px;
      height: 18px;
      border: 2px solid var(--border);
      border-radius: 5px;
      transition: all 0.3s ease;
      margin-top: 2px;
    }

    .termos-checkbox:checked+.termos-label .checkbox-custom {
      background-color: var(--modal-primary);
      border-color: var(--modal-primary);
    }

    .termos-text {
      font-size: 12px;
      color: var(--modal-text);
      line-height: 1.4;
    }

    .termos-link {
      color: var(--modal-primary);
      text-decoration: none;
    }

    .termos-link:hover {
      text-decoration: underline;
    }

    /* --- RESPONSIVIDADE --- */
    @media (max-width: 900px) {
      .hero {
        grid-template-columns: 1fr;
        text-align: center;
        gap: 40px;
        padding: 40px 0;
      }

      .hero-benefits {
        justify-content: center;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      }

      .chat-wrapper-col {
        max-width: 600px;
        margin: 0 auto;
        width: 100%;
      }

      .limit-card {
        flex-direction: column;
        text-align: center;
        gap: 10px;
      }

      .ephemeral-badge {
        margin: 5px 0 0 0;
      }
    }

    @media (max-width: 600px) {
      .section {
        padding: 50px 0;
      }

      .topbar {
        flex-direction: column;
        align-items: center;
        gap: 15px;
        padding: 15px 0;
      }

      .brand img {
        width: 48px;
        height: 48px;
      }

      .brand h1 {
        font-size: 24px;
      }

      .auth-buttons .btn {
        flex: 1;
        justify-content: center;
        font-size: 13px;
        padding: 12px 10px;
      }

      .hero-text h2 {
        font-size: 32px;
        line-height: 1.2;
        overflow-wrap: break-word;
        word-break: break-word;
      }

      .hero-text p {
        font-size: 16px;
      }

      .hero-benefits {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
      }

      .chat-container {
        height: 60vh;
        min-height: 400px;
      }

      .features-grid {
        grid-template-columns: 1fr;
        width: 100%;
      }

      .feature-card {
        width: 100%;
      }

      .how-it-works-steps {
        flex-direction: column;
        align-items: center;
        gap: 40px;
      }

      .step {
        width: 100%;
        max-width: 100%;
      }

      .cta-section h2 {
        font-size: 28px;
      }

      .footer-links {
        flex-direction: column;
        gap: 15px;
        align-items: center;
      }

      /* --- CORREÇÃO DO MODAL MOBILE --- */
      .modal-content {
        width: 90%;
        max-width: 350px;
        min-height: 450px;
        /* Garante altura mínima para mostrar o form */
        max-height: 85vh;
        border-radius: 16px;
      }
    }
  </style>
</head>

<body>

  <div class="container">
    <header class="topbar">
      <div class="brand">
        <img src="nova-logo-removebg.png" alt="Logo do AulaBot"
          style="width:36px;height:36px;border-radius:6px;object-fit:contain;">
        <h1>AulaBot</h1>
      </div>
      <div class="auth-buttons">
        <button onclick="openLoginModal()" class="btn btn-outline">Entrar</button>
        <button onclick="openSignupModal()" class="btn btn-primary">Criar Conta Grátis</button>
      </div>
    </header>

    <main>
      <section class="hero">
        <div class="hero-text">
          <h2>O teu <span class="highlight">explicador pessoal</span>, sempre disponível.</h2>
          <p>O AulaBot é a nova forma de aprender. Tira dúvidas, resolve exercícios e prepara-te para os testes com um
            assistente inteligente, 100% gratuito e focado no ensino em Portugal.</p>
          <button onclick="openSignupModal()" class="btn btn-primary btn-lg"
            style="padding: 15px 30px; font-size: 16px;">Começar a Aprender de Graça</button>
          <div class="hero-benefits">
            <div class="benefit"><i class="fa-solid fa-check"></i> Respostas Instantâneas</div>
            <div class="benefit"><i class="fa-solid fa-check"></i> Disponível 24/7</div>
            <div class="benefit"><i class="fa-solid fa-check"></i> Completamente Gratuito</div>
            <div class="benefit"><i class="fa-solid fa-check"></i> Para Todas as Disciplinas</div>
          </div>
        </div>

        <div class="chat-wrapper-col">
          <div class="limit-card">
            <div>
              <div class="limit-text">Modo Visitante <span class="ephemeral-badge">Teste Rápido</span></div>
              <div class="limit-sub">Cria conta para ter acesso ilimitado e guardar o teu progresso.</div>
            </div>
          </div>

          <div class="chat-container">
            <div class="messages-area" id="messages">
              <div class="message-row bot">
                <div class="msg-avatar"><i class="fa-solid fa-robot"></i></div>
                <div class="bubble">Olá! Eu sou o AulaBot. A minha missão é ajudar-te a ter sucesso nos teus estudos.
                  Qual é a tua dúvida?</div>
              </div>
            </div>

            <div class="input-area">
              <button id="upload" class="btn-icon" title="Upload de Imagem (Requer Login)">
                <i class="fa-solid fa-camera"></i>
              </button>
              <input type="text" id="msg" placeholder="Ex: Explica-me o 25 de Abril..." autocomplete="off" />
              <button id="send" class="btn-icon">
                <i class="fa-solid fa-paper-plane"></i>
              </button>
            </div>
          </div>
        </div>
      </section>

      <section class="section section-alt" id="features">
        <div class="container">
          <div class="section-header">
            <h2>Um Explicador Pessoal Para Cada Aluno</h2>
            <p>O AulaBot foi desenhado para se adaptar às tuas necessidades, quer estejas no ensino básico ou
              secundário.</p>
          </div>
          <div class="features-grid">
            <div class="feature-card">
              <div class="feat-icon"><i class="fa-solid fa-atom"></i></div>
              <h3>Tira Dúvidas na Hora</h3>
              <p>Desde a matéria mais complexa de Físico-Química até à análise de um poema de Fernando Pessoa, o AulaBot
                responde às tuas perguntas de forma clara e simples.</p>
            </div>
            <div class="feature-card">
              <div class="feat-icon"><i class="fa-solid fa-pen-to-square"></i></div>
              <h3>Ajuda com os TPC</h3>
              <p>Mostra um exercício de matemática ou uma pergunta de história e o AulaBot guia-te passo a passo até à
                solução, ajudando-te a compreender o raciocínio.</p>
            </div>
            <div class="feature-card">
              <div class="feat-icon"><i class="fa-solid fa-graduation-cap"></i></div>
              <h3>Prepara-te Para os Testes</h3>
              <p>Pede resumos da matéria, quizz rápidos ou exercícios modelo. Com o AulaBot, as tuas sessões de estudo
                tornam-se muito mais produtivas.</p>
            </div>
          </div>
        </div>
      </section>

      <section class="section" id="howitworks">
        <div class="container">
          <div class="section-header">
            <h2>Como Funciona?</h2>
            <p>Começar a usar o AulaBot é tão simples quanto 1, 2, 3.</p>
          </div>
          <div class="how-it-works-steps">
            <div class="step">
              <div class="step-number">1</div>
              <h3>Faz uma Pergunta</h3>
              <p>Escreve a tua dúvida na caixa de chat. Podes ser tão específico quanto quiseres.</p>
            </div>
            <div class="step">
              <div class="step-number">2</div>
              <h3>O AulaBot Responde</h3>
              <p>Em segundos, o nosso sistema inteligente analisa a tua pergunta e formula uma resposta clara e
                detalhada.</p>
            </div>
            <div class="step">
              <div class="step-number">3</div>
              <h3>Cria Conta Para Mais</h3>
              <p>Gostaste do que viste? Cria uma conta gratuita para guardar o teu histórico, usar a câmara e muito
                mais.</p>
            </div>
          </div>
        </div>
      </section>

      <section class="section section-alt" id="cta">
        <div class="container cta-section">
          <h2>Pronto para Melhorar as Tuas Notas?</h2>
          <p>Junta-te a milhares de estudantes que já usam o AulaBot para simplificar os estudos.</p>
          <button onclick="openSignupModal()" class="btn btn-primary btn-lg"
            style="padding: 15px 30px; font-size: 16px;">Criar Conta 100% Grátis</button>
        </div>
      </section>
    </main>
  </div>

  <footer class="footer">
    <div class="container">
      <div class="brand">
        <img src="nova-logo-removebg.png" alt="Logo do AulaBot"
          style="width:32px;height:32px;border-radius:6px;object-fit:contain;">
        <h1>AulaBot</h1>
      </div>
      <ul class="footer-links">
        <li><a href="#features">Funcionalidades</a></li>
        <li><a href="#howitworks">Como Funciona</a></li>
        <li><a href="termos.html">Termos de Uso</a></li>
        <li><a href="privacidade.html">Privacidade</a></li>
      </ul>
      <p class="footer-copy">&copy; <?php echo date("Y"); ?> AulaBot. Todos os direitos reservados.</p>
    </div>
  </footer>

  <div class="modal" id="modal">
    <div class="modal-box">
      <div id="modalIcon" style="font-size:40px; color:var(--primary); margin-bottom:15px"><i
          class="fa-solid fa-user-lock"></i></div>
      <h3 id="modalTitle" style="margin:0 0 10px 0">Login Necessário</h3>
      <p id="modalMsg" style="color:var(--text-muted); margin-bottom:20px">Esta funcionalidade é gratuita, mas precisas
        de ter conta para a usar.</p>
      <div style="display:flex; gap:10px; justify-content:center">
        <button class="btn btn-outline" id="modalClose">Fechar</button>
        <button id="modalActionBtn" onclick="openLoginModal()" class="btn btn-primary">Fazer Login</button>
      </div>
    </div>
  </div>

  <div id="login-modal" class="modal-wrapper">
    <div class="modal-content">
      <div class="modal-header">
        <span class="toggle-label active" id="login-label" onclick="openLoginModal()">Entrar</span>
        <label class="switch">
          <input type="checkbox" class="toggle" id="form-toggle">
          <span class="slider"></span>
        </label>
        <span class="toggle-label" id="signup-label" onclick="openSignupModal()">Criar Conta</span>
      </div>

      <div class="form-container">
        <div class="form-card" id="login-form">
          <div class="title">Entrar</div>
          <form class="flip-card__form" action="conta/login.php" method="POST">
            <input class="flip-card__input" name="email" placeholder="Email" type="email" required>
            <input class="flip-card__input" name="password" placeholder="Palavra-passe" type="password" required>
            <button class="flip-card__btn" type="submit">Entrar</button>
          </form>
          <div style="text-align: center; margin-top: 12px;">
            <a href="conta/pedido_reset.html"
              style="color: var(--primary); text-decoration: none; font-size: 12px; display: inline-block;">Esqueci a
              palavra-passe</a>
          </div>
        </div>

        <div class="form-card" id="signup-form">
          <div class="title">Criar Conta</div>
          <form class="flip-card__form" action="conta/criarconta.php" method="POST"
            onsubmit="return document.getElementById('termosCheck').checked;">
            <input class="flip-card__input" placeholder="Nome" name="nome" type="text" required>
            <input class="flip-card__input" name="email" placeholder="Email" type="email" required>
            <input class="flip-card__input" name="password" placeholder="Palavra-passe" type="password" required>

            <div class="termos-container">
              <div class="checkbox-wrapper">
                <input type="checkbox" id="termosCheck" required class="termos-checkbox">
                <label for="termosCheck" class="termos-label">
                  <span class="checkbox-custom"></span>
                  <span class="termos-text">Aceito os <a href="termos.html" class="termos-link">Termos de Utilização</a>
                    e a <a href="privacidade.html" class="termos-link">Política de privacidade</a></span>
                </label>
              </div>
            </div>

            <button class="flip-card__btn" type="submit">Criar Conta</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/dompurify/dist/purify.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"></script>

  <script>
    // --- LÓGICA DO CHAT ---
    const messagesEl = document.getElementById('messages');
    const msgInput = document.getElementById('msg');
    const sendBtn = document.getElementById('send');
    const modal = document.getElementById('modal');
    const modalMsg = document.getElementById('modalMsg');
    const modalTitle = document.getElementById('modalTitle');
    const modalIcon = document.getElementById('modalIcon');
    const modalActionBtn = document.getElementById('modalActionBtn');
    let anonymousId = localStorage.getItem('anonymousGuestId') || "<?php echo isset($_SESSION['ip_protected_hash']) ? $_SESSION['ip_protected_hash'] : ''; ?>";

    // CORREÇÃO: Forçar scroll para o topo ao recarregar
    if ('scrollRestoration' in history) {
      history.scrollRestoration = 'manual';
    }
    window.scrollTo(0, 0);

    if (typeof marked !== 'undefined') {
      marked.use({ breaks: true, gfm: true });
    }

    // Exibe o modal genérico com uma mensagem e ícone
    function showModal(msg) {
      modalTitle.textContent = 'Login Necessário';
      modalIcon.innerHTML = '<i class="fa-solid fa-user-lock"></i>';
      modalIcon.style.color = 'var(--primary)';
      if (modalActionBtn) modalActionBtn.style.display = 'inline-flex';
      if (msg) modalMsg.textContent = msg;
      modal.style.display = 'flex';
      // Pequeno delay para permitir a animação CSS (classe 'show')
      setTimeout(() => modal.classList.add('show'), 10);
    }

    function showPopup(title, msg, isError) {
      modalTitle.textContent = title;
      modalMsg.textContent = msg;
      if (isError) {
        modalIcon.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i>';
        modalIcon.style.color = '#ef4444';
      } else {
        modalIcon.innerHTML = '<i class="fa-solid fa-circle-check"></i>';
        modalIcon.style.color = '#10b981';
      }
      if (modalActionBtn) modalActionBtn.style.display = 'none';
      modal.style.display = 'flex';
      setTimeout(() => modal.classList.add('show'), 10);
    }

    document.getElementById('modalClose').addEventListener('click', () => {
      modal.classList.remove('show');
      setTimeout(() => modal.style.display = 'none', 300);
    });

    function escapeHtml(text) {
      if (!text) return '';
      return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    }

    function renderizarMensagemFinal(element, text) {
      if (!element) return;
      const rawHTML = marked.parse(text);
      const cleanHTML = DOMPurify.sanitize(rawHTML);
      element.innerHTML = cleanHTML;
      if (window.renderMathInElement) {
        renderMathInElement(element, {
          delimiters: [{ left: "$$", right: "$$", display: true }, { left: "$", right: "$", display: false }],
          throwOnError: false
        });
      }
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function typewriter(element, text, onComplete) {
      let i = 0;
      const speed = 5;
      let buffer = '';
      function type() {
        if (i < text.length) {
          buffer += text[i];
          i++;
          if (i % 10 === 0 || i === text.length) {
            const rawHTML = marked.parse(buffer);
            element.innerHTML = DOMPurify.sanitize(rawHTML);
            messagesEl.scrollTop = messagesEl.scrollHeight;
          }
          setTimeout(type, speed);
        } else {
          renderizarMensagemFinal(element, text);
        }
      }
      type();
    }

    function appendUserMessage(text) {
      const rowDiv = document.createElement('div');
      rowDiv.className = 'message-row user';
      rowDiv.innerHTML = `<div class="bubble">${escapeHtml(text)}</div>`;
      messagesEl.appendChild(rowDiv);
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function appendBotMessage(text) {
      const rowDiv = document.createElement('div');
      rowDiv.className = 'message-row bot';
      rowDiv.innerHTML = `<div class="msg-avatar"><i class="fa-solid fa-robot"></i></div><div class="bubble"></div>`;
      messagesEl.appendChild(rowDiv);
      const bubble = rowDiv.querySelector('.bubble');
      if (text === '<i class="fa-solid fa-ellipsis fa-fade"></i>') {
        bubble.innerHTML = text;
      } else {
        typewriter(bubble, text, null);
      }
      messagesEl.scrollTop = messagesEl.scrollHeight;
      return rowDiv;
    }

    document.getElementById('upload').addEventListener('click', () => {
      showModal('A leitura de imagens é uma funcionalidade grátis para utilizadores registados. Cria a tua conta num minuto!');
    });

    document.getElementById('msg').addEventListener('keypress', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendBtn.click();
      }
    });

    sendBtn.addEventListener('click', async () => {
      const txt = msgInput.value.trim();
      if (!txt) return;

      appendUserMessage(txt);
      msgInput.value = '';
      const loadingMsgRow = appendBotMessage('<i class="fa-solid fa-ellipsis fa-fade"></i>');

      try {
        const res = await fetch('api/api_chat.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ message: txt, anonymousId: anonymousId, modo_demo: true })
        });
        const text = await res.text();
        loadingMsgRow.remove();

        let j;
        try { j = JSON.parse(text); } catch (e) {
          appendBotMessage('Erro: A resposta do servidor não é válida.');
          return;
        }

        if (j.new_anonymous_id) {
          anonymousId = j.new_anonymous_id;
          localStorage.setItem('anonymousGuestId', anonymousId);
        }

        if (j.status === 'success') {
          appendBotMessage(j.reply || '');
        } else if (j.status === 'limit_reached') {
          appendBotMessage(j.reply || '');
          msgInput.disabled = true;
          sendBtn.disabled = true;
          msgInput.placeholder = 'Faz login para continuar.';
          openLoginModal();
        } else {
          appendBotMessage(j.message || 'Ocorreu um erro.');
        }
      } catch (e) {
        loadingMsgRow.remove();
        appendBotMessage('Erro de comunicação com o servidor.');
      }
    });

    // --- LÓGICA DO LOGIN MODAL ---
    const loginModal = document.getElementById('login-modal');
    const formToggle = document.getElementById('form-toggle');
    const loginLabel = document.getElementById('login-label');
    const signupLabel = document.getElementById('signup-label');
    const loginForm = document.getElementById('login-form');
    const signupForm = document.getElementById('signup-form');

    // Abre o Modal de LOGIN e esconde o de registo
    function openLoginModal() {
      // Fecha qualquer modal genérico aberto
      modal.classList.remove('show');
      setTimeout(() => { modal.style.display = 'none'; }, 300);

      // Limpa os campos para evitar dados antigos
      document.querySelectorAll('.flip-card__input').forEach(i => i.value = '');

      loginModal.classList.add('show');
      // Adiciona classes ao body/html para bloquear o scroll da página de fundo
      document.body.classList.add('modal-active');
      document.documentElement.classList.add('modal-active');

      // Ajusta os toggles visuais (Login Ativo)
      formToggle.checked = false;
      loginForm.classList.remove('hidden');
      signupForm.classList.remove('show');
      loginLabel.classList.add('active');
      signupLabel.classList.remove('active');
    }

    function openSignupModal() {
      loginModal.classList.add('show');
      document.body.classList.add('modal-active');
      document.documentElement.classList.add('modal-active');

      // Limpar campos
      document.querySelectorAll('.flip-card__input').forEach(i => i.value = '');

      formToggle.checked = true;
      loginForm.classList.add('hidden');
      signupForm.classList.add('show');
      loginLabel.classList.remove('active');
      signupLabel.classList.add('active');
    }

    function closeLoginModal() {
      loginModal.classList.remove('show');
      document.body.classList.remove('modal-active');
      document.documentElement.classList.remove('modal-active');
    }

    loginModal.addEventListener('click', (e) => {
      // Fecha se clicar na parte escura (fora do modal-content)
      if (e.target === loginModal) closeLoginModal();
    });

    formToggle.addEventListener('change', () => {
      if (formToggle.checked) {
        loginForm.classList.add('hidden');
        signupForm.classList.add('show');
        loginLabel.classList.remove('active');
        signupLabel.classList.add('active');
      } else {
        loginForm.classList.remove('hidden');
        signupForm.classList.remove('show');
        loginLabel.classList.add('active');
        signupLabel.classList.remove('active');
      }
    });

    document.querySelectorAll('.flip-card__form').forEach(form => {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        const action = form.getAttribute('action');
        const btn = form.querySelector('button');
        const originalBtnText = btn.textContent;
        btn.textContent = 'A processar...';
        btn.disabled = true;

        try {
          const res = await fetch(action, { method: 'POST', body: formData });
          const rawText = await res.text();
          let data;
          try { data = JSON.parse(rawText); } catch (e) { throw new Error('Erro JSON'); }

          // Se o servidor retornar sucesso, redireciona para a Dashboard
          if (data.sucesso === true) {
            window.location.replace("index.php");
          } else {
            // Caso contrário, mostra o erro num popup
            showPopup('Atenção', data.erro || 'Erro desconhecido', true);
            btn.textContent = originalBtnText;
            btn.disabled = false;
          }
        } catch (err) {
          showPopup('Erro', 'Não foi possível comunicar com o servidor.', true);
          btn.textContent = originalBtnText;
          btn.disabled = false;
        }
      });
    });
  </script>
</body>

</html>