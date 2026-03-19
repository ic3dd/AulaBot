<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Site em Manutenção</title>
  <style>
    :root {
      --primary: #0066ff;
      --bg1: #f8fafc;
      --bg2: #eef2f7;
      --text: #222;
      --muted: #666;
      --radius: 14px;
      --shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    }

    * {
      box-sizing: border-box;
      font-family: "Inter", "Poppins", sans-serif;
    }

    body {
      margin: 0;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, var(--bg1), var(--bg2));
      background-size: 200% 200%;
      animation: bgShift 10s ease infinite;
      color: var(--text);
    }

    @keyframes bgShift {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }

    .card {
      background: white;
      padding: 48px 40px;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      width: 90%;
      max-width: 420px;
      text-align: center;
      animation: fadeIn 1s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .icon {
      font-size: 58px;
      margin-bottom: 20px;
      animation: pulse 3s ease-in-out infinite;
    }

    @keyframes pulse {
      0%, 100% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.08); opacity: 0.85; }
    }

    h1 {
      font-size: 24px;
      margin: 10px 0;
      font-weight: 600;
    }

    p {
      color: var(--muted);
      font-size: 15px;
      line-height: 1.6;
      margin-bottom: 30px;
    }

    .toggle-login {
      font-size: 14px;
      color: var(--primary);
      text-decoration: none;
      cursor: pointer;
      transition: opacity 0.2s;
    }

    .toggle-login:hover {
      opacity: 0.7;
    }

    /* Login */
    .admin-login {
      display: none;
      margin-top: 25px;
      text-align: left;
      animation: fadeIn 0.4s ease;
    }

    .admin-login label {
      display: block;
      font-size: 13px;
      margin-bottom: 6px;
      color: var(--muted);
    }

    .admin-login input {
      width: 100%;
      padding: 10px 12px;
      border-radius: 8px;
      border: 1px solid #ccc;
      margin-bottom: 15px;
      font-size: 14px;
      transition: border-color 0.2s;
    }

    .admin-login input:focus {
      outline: none;
      border-color: var(--primary);
    }

    .admin-login button {
      width: 100%;
      padding: 10px;
      border: none;
      border-radius: 8px;
      background: var(--primary);
      color: #fff;
      font-size: 15px;
      font-weight: 500;
      cursor: pointer;
      transition: background 0.3s;
    }

    .admin-login button:hover {
      background: #004ed4;
    }

    #bd-msg {
      text-align: center;
      margin-top: 8px;
      color: #d00;
      font-size: 14px;
      display: none;
    }

    /* Admin area */
    .admin-area {
      display: none;
      text-align: center;
      animation: fadeIn 0.6s ease;
    }

    .admin-area h2 {
      color: var(--primary);
      font-size: 20px;
      margin-bottom: 10px;
    }

    .admin-area p {
      color: var(--muted);
      margin-bottom: 20px;
    }

    .admin-area button {
      background: #e74c3c;
      color: white;
      border: none;
      padding: 10px 16px;
      border-radius: 8px;
      cursor: pointer;
      transition: 0.3s;
    }

    .admin-area button:hover {
      background: #c0392b;
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">🔧</div>
    <h1>Site em Manutenção</h1>
    <p>Estamos a atualizar o sistema.<br> Voltamos em breve.</p>

    <div class="toggle-login" onclick="toggleAdmin()">Sou administrador</div>

    <div id="admin-backdoor" class="admin-login">
      <form id="backdoor-form">
        <label for="bd-email">Email</label>
        <input type="email" id="bd-email" name="email" placeholder="admin@site.com" required>

        <label for="bd-pass">Palavra-passe</label>
        <input type="password" id="bd-pass" name="palavra_passe" placeholder="********" required>

        <button type="submit">Entrar</button>
      </form>
      <div id="bd-msg"></div>
    </div>

    <div id="admin-area" class="admin-area">
      <h2>Bem-vindo, Administrador 👋</h2>
      <p>Podes gerir o site diretamente aqui.</p>
      <button onclick="logout()">Terminar Sessão</button>
    </div>
  </div>

  <script>
    // Mostrar/Ocultar login
    function toggleAdmin() {
      const form = document.getElementById("admin-backdoor");
      form.style.display = form.style.display === "block" ? "none" : "block";
    }

    // Logout: agora redireciona ao servidor para terminar sessão e registar o evento
    function logout() {
      // limpar UI local
      document.getElementById("admin-area").style.display = "none";
      document.getElementById("admin-backdoor").style.display = "none";
      document.getElementById("bd-email").value = "";
      document.getElementById("bd-pass").value = "";

      // limpar localStorage e redirecionar para logout.php (com fallback id se disponível)
      try { localStorage.removeItem('auth_email'); localStorage.removeItem('auth_nome'); } catch(e) {}
      const authId = (function(){ try { return localStorage.getItem('auth_id'); } catch(e){ return null; } })();
      if (authId) {
        window.location.href = 'auth/logout.php?id=' + encodeURIComponent(authId);
      } else {
        window.location.href = 'auth/logout.php';
      }
    }

    // Fazer login via AJAX
    (function(){
      const form = document.getElementById('backdoor-form');
      const msg = document.getElementById('bd-msg');

      form.addEventListener('submit', async function(e){
        e.preventDefault();
        msg.style.display = 'none';
        const email = document.getElementById('bd-email').value;
        const pass = document.getElementById('bd-pass').value;

        try {
          const resp = await fetch('conta/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ email: email, palavra_passe: pass }),
            credentials: 'include'
          });
          const data = await resp.json();
          if (data.sucesso) {
            if (data.utilizador && data.utilizador.tipo === 'admin') {
              // Pequeno atraso para garantir que o cookie de sessão foi gravado
              setTimeout(function(){ window.location.href = 'index.php'; }, 250);
            } else {
              msg.textContent = 'Credenciais válidas, mas não é administrador.';
              msg.style.display = 'block';
            }
          } else {
            msg.textContent = data.erro || 'Erro a autenticar';
            msg.style.display = 'block';
          }
        } catch (err) {
          msg.textContent = 'Erro de rede ou servidor';
          msg.style.display = 'block';
        }
      });
    })();
  </script>
</body>
</html>
