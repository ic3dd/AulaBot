<?php
/**
 * Copie este ficheiro para config_secrets.php e preencha com as suas chaves.
 * Nunca commite config_secrets.php (está no .gitignore).
 */

// Chave da API Groq (para o chat com IA)
if (!defined('GROQ_API_KEY')) {
    define('GROQ_API_KEY', 'sua-groq-api-key-aqui');
}

// Pepper para hash de IP (privacidade de convidados)
if (!defined('IP_SECRET_PEPPER')) {
    define('IP_SECRET_PEPPER', 'uma_string_secreta_aleatoria_para_hash_de_IP');
}

// Token para endpoints de debug
if (!defined('DEBUG_ENDPOINT_TOKEN')) {
    define('DEBUG_ENDPOINT_TOKEN', 'token_para_endpoints_de_debug');
}

// ========== SUPABASE (opcional) ==========
// Para usar Supabase em vez de MySQL, descomente e preencha:
//
// define('USE_SUPABASE', true);
// define('SUPABASE_DB_URL', 'postgresql://postgres.[PROJECT-REF]:[PASSWORD]@aws-0-[REGION].pooler.supabase.com:5432/postgres');
//
// Onde encontrar:
// 1. Supabase Dashboard: https://supabase.com/dashboard
// 2. Selecione o seu projeto (ou crie um novo)
// 3. Project Settings → Database
// 4. Em "Connection string" escolha "URI" e "Session mode"
// 5. Copie a string e substitua [PASSWORD] pela password da base de dados
//    (a password que definiu ao criar o projeto; pode redefinir em Database → Reset password)
?>
