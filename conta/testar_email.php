<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Email SMTP - AulaBot</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .container {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin: 15px 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .resultado {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
            display: none;
        }
        .sucesso {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }
        .erro {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
            display: block;
        }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Teste de Email SMTP</h1>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email_destino = $_POST['email_destino'] ?? '';
            
            if (!filter_var($email_destino, FILTER_VALIDATE_EMAIL)) {
                echo "<div class='resultado erro'>Email inválido!</div>";
            } else {
                try {
                    require('email_config.php');
                    require_once('../PHPMailer-7.0.0/src/PHPMailer.php');
                    require_once('../PHPMailer-7.0.0/src/SMTP.php');
                    require_once('../PHPMailer-7.0.0/src/Exception.php');
                    
                    $mail = new \PHPMailer\PHPMailer\PHPMailer();
                    $mail->isSMTP();
                    $mail->Host = $email_config['smtp_host'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $email_config['smtp_user'];
                    $mail->Password = $email_config['smtp_password'];
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = $email_config['smtp_port'];
                    
                    $mail->setFrom($email_config['from_email'], $email_config['from_name']);
                    $mail->addAddress($email_destino);
                    
                    $mail->isHTML(true);
                    $mail->Subject = '🧪 Teste de Email - AulaBot';
                    $mail->Body = '
                        <html>
                        <body style="font-family: Arial, sans-serif;">
                            <h2 style="color: #667eea;">Teste de Email com Sucesso!</h2>
                            <p>Se você recebeu este email, significa que o sistema de email está funcionando corretamente.</p>
                            <p><strong>Data/Hora:</strong> ' . date('d/m/Y H:i:s') . '</p>
                            <p><strong>Email de origem:</strong> ' . htmlspecialchars($email_config['from_email']) . '</p>
                            <p style="color: #666; font-size: 12px; margin-top: 30px;">
                                Este é um email de teste do sistema de recuperação de palavra-passe da AulaBot.
                            </p>
                        </body>
                        </html>
                    ';
                    $mail->AltBody = 'Teste de Email - Se recebeu isto, o sistema funciona!';
                    
                    if ($mail->send()) {
                        echo "<div class='resultado sucesso'>";
                        echo "<strong>✓ Email enviado com sucesso!</strong><br>";
                        echo "Email foi enviado para: <strong>" . htmlspecialchars($email_destino) . "</strong>";
                        echo "</div>";
                    } else {
                        echo "<div class='resultado erro'>";
                        echo "<strong>✗ Erro ao enviar email</strong><br>";
                        echo htmlspecialchars($mail->ErrorInfo);
                        echo "</div>";
                    }
                } catch (\Exception $e) {
                    echo "<div class='resultado erro'>";
                    echo "<strong>✗ Erro de Exceção</strong><br>";
                    echo htmlspecialchars($e->getMessage());
                    echo "</div>";
                }
            }
        }
        ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="email_destino">Email para Teste:</label>
                <input type="email" id="email_destino" name="email_destino" placeholder="seu@email.com" required>
            </div>
            
            <button type="submit">🚀 Enviar Email de Teste</button>
        </form>
        
        <div class="resultado info">
            <strong>ℹ️ Instruções:</strong><br>
            1. Introduza um email para receber o teste<br>
            2. Clique em "Enviar Email de Teste"<br>
            3. Verifique se o email foi recebido<br>
            4. Se não recebeu, verifique a pasta de spam
        </div>
        
        <hr style="margin: 30px 0; border: 1px solid #ddd;">
        
        <h2 style="color: #333; margin-top: 30px;">Configuração Atual:</h2>
        <table style="width: 100%; border-collapse: collapse;">
            <tr style="background: #f4f4f4;">
                <td style="padding: 10px; border: 1px solid #ddd; font-weight: 600;">SMTP Host</td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                    <?php 
                    require('email_config.php');
                    echo htmlspecialchars($email_config['smtp_host']); 
                    ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd; font-weight: 600;">SMTP Port</td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                    <?php echo htmlspecialchars($email_config['smtp_port']); ?>
                </td>
            </tr>
            <tr style="background: #f4f4f4;">
                <td style="padding: 10px; border: 1px solid #ddd; font-weight: 600;">SMTP User</td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                    <?php echo htmlspecialchars($email_config['smtp_user']); ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd; font-weight: 600;">From Email</td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                    <?php echo htmlspecialchars($email_config['from_email']); ?>
                </td>
            </tr>
            <tr style="background: #f4f4f4;">
                <td style="padding: 10px; border: 1px solid #ddd; font-weight: 600;">From Name</td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                    <?php echo htmlspecialchars($email_config['from_name']); ?>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
