<?php
// Página exibida quando o site está bloqueado para utilizadores não-admin
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Site em Manutenção</title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; background:#f5f7fa; color:#222; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; }
    .card { background:#fff; padding:28px; border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,0.08); max-width:520px; text-align:center; }
    h1 { margin:0 0 8px; font-size:22px; }
    p { margin:0 0 16px; color:#555; }
    .actions a { display:inline-block; padding:10px 16px; border-radius:6px; text-decoration:none; color:#fff; background:#007bff; }
  </style>
</head>
<body>
  <div class="card">
    <h1>O site encontra-se temporariamente bloqueado</h1>
    <p>O acesso foi limitado pelos administradores. Se for um administrador, por favor inicie sessão ou contacte outro administrador.</p>
    <div class="actions">
      <a href="index.php">Voltar à página inicial</a>
    </div>
  </div>
</body>
</html>
