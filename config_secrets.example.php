<?php
// Copie este ficheiro para config_secrets.php e preencha com as suas chaves.
// Nunca commite config_secrets.php (está no .gitignore).

if (!defined('GROQ_API_KEY')) {
    define('GROQ_API_KEY', 'sua-groq-api-key-aqui');
}
if (!defined('IP_SECRET_PEPPER')) {
    define('IP_SECRET_PEPPER', 'uma_string_secreta_aleatoria_para_hash_de_IP');
}
if (!defined('DEBUG_ENDPOINT_TOKEN')) {
    define('DEBUG_ENDPOINT_TOKEN', 'token_para_endpoints_de_debug');
}
?>
