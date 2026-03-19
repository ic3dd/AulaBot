<?php
// Health check para o Render - responde 200 OK se o servidor está ativo
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'service' => 'AulaBot']);
