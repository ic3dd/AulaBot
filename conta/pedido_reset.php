<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
ob_clean();

function respondJson($success, $message = '', $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['sucesso' => $success, 'erro' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(false, 'Método HTTP não permitido.', 405);
}

try {
    require('../ligarbd.php');
    require('email_config.php');
    require_once('../PHPMailer-7.0.0/src/PHPMailer.php');
    require_once('../PHPMailer-7.0.0/src/SMTP.php');
    require_once('../PHPMailer-7.0.0/src/Exception.php');

    $email = $_POST['email'] ?? '';

    if (!$email) {
        respondJson(false, 'Por favor, preencha o seu email.', 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respondJson(false, 'O email fornecido não é válido.', 400);
    }

    if (!$con) {
        respondJson(false, 'Erro ao conectar à base de dados. Tente novamente mais tarde.', 500);
    }

    $sql = "SELECT COUNT(*) as total FROM utilizador WHERE email = ?";
    $stmt = db_prepare($con, $sql);
    
    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . db_error($con));
    }
    
    db_stmt_bind_param($stmt, "s", $email);
    db_stmt_execute($stmt);
    $result = db_stmt_get_result($stmt);
    $row = db_fetch_assoc($result);
    
    if ($row['total'] == 0) {
        db_stmt_close($stmt);
        respondJson(false, 'Email não encontrado no sistema.', 404);
    }
    
    db_stmt_close($stmt);

    $reset_token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $reset_token);
    $expiry_time = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $sql_update = "UPDATE utilizador SET reset_token = ?, reset_token_expiry = ? WHERE email = ?";
    $stmt_update = db_prepare($con, $sql_update);
    
    if (!$stmt_update) {
        throw new Exception('Erro ao preparar query de atualização: ' . db_error($con));
    }
    
    db_stmt_bind_param($stmt_update, "sss", $token_hash, $expiry_time, $email);
    
    if (!db_stmt_execute($stmt_update)) {
        db_stmt_close($stmt_update);
        throw new Exception('Erro ao gerar token de reset');
    }
    
    db_stmt_close($stmt_update);

    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['REQUEST_URI']);
    $reset_link = $protocol . "://" . $host . $path . "/reset_password.html?token=" . urlencode($reset_token);

    $mail = new \PHPMailer\PHPMailer\PHPMailer();
    
    try {
        $mail->isSMTP();
        $mail->Host = $email_config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $email_config['smtp_user'];
        $mail->Password = $email_config['smtp_password'];
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $email_config['smtp_port'];

        $mail->setFrom($email_config['from_email'], $email_config['from_name']);
        $mail->addAddress($email);
        
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = '8bit';
        $mail->Subject = 'Recuperacao de Palavra-Passe - AulaBot';
        
        $reset_link_escaped = htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8');
        $mail->Body = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
.wrapper { background: #f5f5f5; padding: 20px; }
.container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }
.header h1 { margin: 0; font-size: 24px; font-weight: bold; }
.content { padding: 30px 20px; }
.content p { margin: 15px 0; line-height: 1.6; color: #333; }
.button-container { text-align: center; margin: 30px 0; }
.button { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 14px 40px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px; }
.link-text { background: #f9f9f9; padding: 15px; border-radius: 5px; word-break: break-all; font-size: 13px; color: #666; border: 1px solid #ddd; }
.warning { color: #d9534f; font-size: 13px; margin: 20px 0; font-style: italic; }
.footer { background: #f9f9f9; padding: 20px; text-align: center; border-top: 1px solid #ddd; font-size: 12px; color: #999; }
</style>
</head>
<body>
<div class="wrapper">
<div class="container">
<div class="header">
<h1>Recuperacao de Palavra-Passe</h1>
</div>
<div class="content">
<p>Ola,</p>
<p>Recebemos um pedido para redefinir a sua palavra-passe. Clique no botao abaixo para continuar:</p>
<div class="button-container">
<a href="' . $reset_link_escaped . '" class="button">Redefinir Palavra-Passe</a>
</div>
<p>Ou copie este link no seu navegador:</p>
<p class="link-text">' . $reset_link_escaped . '</p>
<p class="warning">Este link expira em 1 hora. Se nao solicitou a recuperacao de palavra-passe, ignore este email.</p>
</div>
<div class="footer">
<p>Este eh um email automatico, nao responda.</p>
<p>&copy; 2024 AulaBot. Todos os direitos reservados.</p>
</div>
</div>
</div>
</body>
</html>';
        $mail->AltBody = 'Clique no link para redefinir a sua palavra-passe: ' . $reset_link;

        if ($mail->send()) {
            respondJson(true, 'Email de recuperação enviado com sucesso! Verifique a sua caixa de entrada (e a pasta de spam).', 200);
        } else {
            throw new Exception('Erro ao enviar email: ' . $mail->ErrorInfo);
        }
    } catch (\Exception $e) {
        throw new Exception('Erro ao enviar email: ' . $e->getMessage());
    }

    db_close($con);

} catch (Exception $e) {
    error_log("Erro na recuperação de conta: " . $e->getMessage());
    $errorMsg = 'Erro ao processar o pedido. Verifique os dados e tente novamente.';
    if (strpos($e->getMessage(), 'Email') !== false) {
        $errorMsg = 'Email inválido ou não encontrado.';
    }
    respondJson(false, $errorMsg, 500);
}

exit;
?>
