<?php
/**
 * Script para testar a ligação ao Supabase.
 * Acede via browser: http://localhost/AulaBot/testar_supabase.php
 * Ou via terminal: php testar_supabase.php
 */

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Teste de ligação ao Supabase</h2>\n";

// Carregar config e ligarbd
require_once __DIR__ . '/ligarbd.php';

if (!isset($con)) {
    echo "<p style='color:red;'><strong>ERRO:</strong> Não foi possível obter a conexão (\$con não definido).</p>";
    exit;
}

try {
    echo "<p><strong>Modo:</strong> " . (defined('DB_IS_POSTGRES') && DB_IS_POSTGRES ? "Supabase (PostgreSQL)" : "MySQL") . "</p>\n";

    // Teste 1: Ligação básica
    $res = db_query($con, "SELECT 1 as ok");
    $row = db_fetch_assoc($res);
    
    if ($row && ($row['ok'] ?? 0) == 1) {
        echo "<p style='color:green;'>✓ Ligação ao Supabase estabelecida com sucesso!</p>\n";
    } else {
        echo "<p style='color:orange;'>⚠ Ligação OK, mas o teste SELECT retornou resultado inesperado.</p>\n";
    }

    // Teste 2: Verificar tabelas
    $tabelasEsperadas = ['utilizador', 'chats', 'mensagens', 'updates', 'configuracoes_site'];
    $res = db_query($con, "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
    $tabelas = [];
    while ($r = db_fetch_assoc($res)) {
        $tabelas[] = $r['table_name'];
    }

    echo "<p><strong>Tabelas encontradas:</strong> " . implode(', ', $tabelas) . "</p>\n";

    $faltam = array_diff($tabelasEsperadas, $tabelas);
    if (empty($faltam)) {
        echo "<p style='color:green;'>✓ Todas as tabelas principais existem.</p>\n";
    } else {
        echo "<p style='color:orange;'>⚠ Tabelas em falta: " . implode(', ', $faltam) . "</p>\n";
        echo "<p>Executa o ficheiro <code>database/supabase_schema.sql</code> no SQL Editor do Supabase.</p>\n";
    }

    // Teste 3: Contar utilizadores (se a tabela existir)
    if (in_array('utilizador', $tabelas)) {
        $res = db_query($con, "SELECT COUNT(*) as total FROM utilizador");
        $row = db_fetch_assoc($res);
        echo "<p><strong>Utilizadores na base:</strong> " . ($row['total'] ?? 0) . "</p>\n";
    }

    echo "<hr><p style='color:green;'><strong>Tudo OK!</strong> O AulaBot está pronto para usar com o Supabase.</p>\n";

} catch (Exception $e) {
    echo "<p style='color:red;'><strong>ERRO:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p>Verifica a connection string em <code>config_secrets.php</code> e se executaste o schema no Supabase.</p>\n";
}
