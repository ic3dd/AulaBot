<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de Configuração - Sistema de Recuperação de Password</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .verificacao {
            background: white;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .sucesso {
            border-left-color: #28a745;
            background-color: #f0f8f0;
        }
        .sucesso::before {
            content: "✓ ";
            color: #28a745;
            font-weight: bold;
            margin-right: 8px;
        }
        .erro {
            border-left-color: #dc3545;
            background-color: #fff0f0;
        }
        .erro::before {
            content: "✗ ";
            color: #dc3545;
            font-weight: bold;
            margin-right: 8px;
        }
        .aviso {
            border-left-color: #ffc107;
            background-color: #fffbf0;
        }
        .aviso::before {
            content: "⚠ ";
            color: #ffc107;
            font-weight: bold;
            margin-right: 8px;
        }
        .info {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 12px;
            margin: 15px 0;
            border-radius: 4px;
            font-size: 14px;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f4f4f4;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>🔍 Verificação de Configuração - Sistema de Recuperação de Password</h1>
    
    <?php
    $problemas = 0;
    $avisos = 0;
    $sucessos = 0;
    
    // Verificar ficheiros necessários
    echo "<h2>1. Verificação de Ficheiros</h2>";
    
    $ficheiros_necessarios = [
        'email_config.php' => 'Configuração de email',
        'pedido_reset.html' => 'Página de solicitação de reset',
        'pedido_reset.php' => 'Backend de solicitação de reset',
        'reset_password.html' => 'Página de redefinição de password',
        'reset_password.php' => 'Backend de redefinição de password',
        'update_database.sql' => 'Script de atualização de BD',
        '../PHPMailer-7.0.0/src/PHPMailer.php' => 'Biblioteca PHPMailer'
    ];
    
    foreach ($ficheiros_necessarios as $ficheiro => $descricao) {
        $caminho = __DIR__ . '/' . $ficheiro;
        if (file_exists($caminho)) {
            echo "<div class='verificacao sucesso'>$descricao - Ficheiro encontrado: <code>$ficheiro</code></div>";
            $sucessos++;
        } else {
            echo "<div class='verificacao erro'>$descricao - Ficheiro NÃO encontrado: <code>$ficheiro</code></div>";
            $problemas++;
        }
    }
    
    // Verificar configuração de email
    echo "<h2>2. Verificação de Configuração de Email</h2>";
    
    if (file_exists(__DIR__ . '/email_config.php')) {
        $config = include(__DIR__ . '/email_config.php');
        
        if (isset($email_config)) {
            echo "<table>";
            echo "<tr><th>Parâmetro</th><th>Valor</th><th>Status</th></tr>";
            
            $params = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_password', 'from_email', 'from_name'];
            foreach ($params as $param) {
                if (isset($email_config[$param]) && !empty($email_config[$param])) {
                    $valor = $param === 'smtp_password' ? '***' : $email_config[$param];
                    echo "<tr>";
                    echo "<td><code>$param</code></td>";
                    echo "<td>$valor</td>";
                    echo "<td><span style='color: #28a745;'>✓ Configurado</span></td>";
                    echo "</tr>";
                    $sucessos++;
                } else {
                    echo "<tr>";
                    echo "<td><code>$param</code></td>";
                    echo "<td>-</td>";
                    echo "<td><span style='color: #dc3545;'>✗ Falta</span></td>";
                    echo "</tr>";
                    $problemas++;
                }
            }
            echo "</table>";
        }
    } else {
        echo "<div class='verificacao erro'>Ficheiro email_config.php não encontrado</div>";
        $problemas++;
    }
    
    // Verificar conexão BD
    echo "<h2>3. Verificação de Base de Dados</h2>";
    
    if (file_exists(__DIR__ . '/../ligarbd.php')) {
        require(__DIR__ . '/../ligarbd.php');
        
        if ($con) {
            echo "<div class='verificacao sucesso'>Conexão com BD estabelecida</div>";
            $sucessos++;
            
            // Verificar colunas na tabela utilizador
            $resultado = mysqli_query($con, "SHOW COLUMNS FROM utilizador");
            $colunas = [];
            while ($linha = mysqli_fetch_assoc($resultado)) {
                $colunas[] = $linha['Field'];
            }
            
            $colunas_necessarias = ['reset_token', 'reset_token_expiry'];
            foreach ($colunas_necessarias as $coluna) {
                if (in_array($coluna, $colunas)) {
                    echo "<div class='verificacao sucesso'>Coluna <code>$coluna</code> existe na tabela utilizador</div>";
                    $sucessos++;
                } else {
                    echo "<div class='verificacao erro'>Coluna <code>$coluna</code> NÃO existe. Execure o script <code>update_database.sql</code></div>";
                    $avisos++;
                }
            }
            
            mysqli_close($con);
        } else {
            echo "<div class='verificacao erro'>Não conseguiu conectar à base de dados</div>";
            $problemas++;
        }
    }
    
    // Verificar permissões
    echo "<h2>4. Verificação de Permissões</h2>";
    
    $ficheiros_escrever = [
        '../admin/admin_php_errors.log' => 'Log de erros'
    ];
    
    foreach ($ficheiros_escrever as $ficheiro => $descricao) {
        $caminho = __DIR__ . '/' . $ficheiro;
        if (is_writable(dirname($caminho))) {
            echo "<div class='verificacao sucesso'>$descricao - Permissões OK</div>";
            $sucessos++;
        } else {
            echo "<div class='verificacao aviso'>$descricao - Possível problema de permissões</div>";
            $avisos++;
        }
    }
    
    // Verificar extensões PHP
    echo "<h2>5. Verificação de Extensões PHP</h2>";
    
    $extensoes = ['mysqli', 'openssl', 'json'];
    foreach ($extensoes as $ext) {
        if (extension_loaded($ext)) {
            echo "<div class='verificacao sucesso'>Extensão <code>$ext</code> está ativa</div>";
            $sucessos++;
        } else {
            echo "<div class='verificacao erro'>Extensão <code>$ext</code> NÃO está ativa</div>";
            $problemas++;
        }
    }
    
    // Resumo
    echo "<h2>📊 Resumo da Verificação</h2>";
    echo "<div class='info'>";
    echo "<strong>✓ Sucessos:</strong> $sucessos<br>";
    echo "<strong>⚠ Avisos:</strong> $avisos<br>";
    echo "<strong>✗ Problemas:</strong> $problemas<br>";
    
    if ($problemas === 0 && $avisos === 0) {
        echo "<br><strong style='color: #28a745;'>✓ Tudo está configurado corretamente!</strong>";
    } elseif ($problemas === 0) {
        echo "<br><strong style='color: #ffc107;'>⚠ Há alguns avisos que devem ser verificados.</strong>";
    } else {
        echo "<br><strong style='color: #dc3545;'>✗ Há problemas que devem ser resolvidos antes de usar o sistema.</strong>";
    }
    echo "</div>";
    
    // Próximos passos
    echo "<h2>📝 Próximos Passos</h2>";
    echo "<div class='info'>";
    echo "1. Se houver problemas, resolva-os conforme indicado acima.<br>";
    echo "2. Execute o script <code>update_database.sql</code> se as colunas não existirem.<br>";
    echo "3. Teste o sistema acessando: <a href='pedido_reset.html' target='_blank'>Página de Recuperação</a><br>";
    echo "4. Verifique os logs em <code>admin/admin_php_errors.log</code> se houver erros.";
    echo "</div>";
    ?>
</body>
</html>
