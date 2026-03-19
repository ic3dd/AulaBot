<?php
/**
 * API Vision - Integração OCR (Otimizada para Servidores Escolares)
 */

// 1. CONFIGURAÇÕES
ini_set('display_errors', 0); // JSON limpo
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/api_vision_debug.log');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
header("Access-Control-Allow-Origin: *");

// 2. CHAVES
if (file_exists(__DIR__ . '/../config/config_secrets.php')) {
    require_once(__DIR__ . '/../config/config_secrets.php');
} elseif (file_exists(__DIR__ . '/../config_secrets.php')) {
    require_once(__DIR__ . '/../config_secrets.php');
}
if (!defined('GROQ_API_KEY')) define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: '');
if (!defined('OCR_API_KEY')) define('OCR_API_KEY', 'K82634633988957');
define('OCR_API_URL', 'https://api.ocr.space/parse/image');
define('GROQ_VISION_MODEL', 'meta-llama/llama-4-scout-17b-16e-instruct');

// Função de resposta rápida
function respondWithError($message, $code = 400)
{
    error_log("API Vision Error: " . $message);
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

try {
    // 3. VALIDAÇÃO DO UPLOAD
    // Verificar se o POST está vazio (indica que o limite do servidor foi excedido)
    if (empty($_FILES) && empty($_POST)) {
        respondWithError("Arquivo demasiado grande. O limite do servidor foi excedido.", 413);
    }

    if (!isset($_FILES['image']))
        respondWithError("Nenhuma imagem recebida.");

    $file = $_FILES['image'];

    // Verificar erros de upload PHP
    if ($file['error'] !== UPLOAD_ERR_OK) {
        respondWithError("Erro no upload: Código " . $file['error']);
    }

    // Verificar tipo
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        respondWithError("Formato inválido. Usa JPG, PNG ou WEBP.");
    }

    // VERIFICAÇÃO DE TAMANHO (IMPORTANTE PARA A ESCOLA)
    // A API OCR Free tem limite de ~1MB. Se for maior, avisamos ou tentamos na mesma.
    $tamanhoMB = $file['size'] / 1024 / 1024;
    if ($tamanhoMB > 5) { // Limite absoluto de 5MB para não bloquear o servidor
        respondWithError("A imagem é demasiado grande (>5MB). Tenta uma menor.");
    }

    $imageTmpPath = $file['tmp_name'];
    $contextoExtraido = null;
    $ocr_failed = false;
    $usedGroqVision = false;

    // 4. TENTAR GROQ VISION PRIMEIRO (mais preciso para números e matemática)
    if (GROQ_API_KEY && $file['size'] < 3 * 1024 * 1024) { // Base64 limit ~4MB, deixamos margem
        $imageData = file_get_contents($imageTmpPath);
        $base64 = base64_encode($imageData);
        $mime = $file['type'];
        $dataUrl = "data:{$mime};base64,{$base64}";

        $groqPrompt = "Transcreve EXATAMENTE o texto e os números desta imagem, linha a linha. " .
            "Se for um exercício de matemática (contas, somas, subtrações), copia cada número e cada sinal (+, -, ×, ÷) com precisão total. " .
            "Não inventes nem interpretes - transcreve literalmente o que vês. Responde apenas com o texto transcrito, sem explicações.";

        $payload = [
            'model' => GROQ_VISION_MODEL,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $groqPrompt],
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]]
                    ]
                ]
            ],
            'temperature' => 0.1,
            'max_tokens' => 2048
        ];

        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . GROQ_API_KEY
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25
        ]);
        $groqRes = curl_exec($ch);
        $groqCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($groqRes && $groqCode === 200) {
            $groqJson = json_decode($groqRes, true);
            $text = $groqJson['choices'][0]['message']['content'] ?? null;
            if ($text && trim($text) !== '') {
                $contextoExtraido = trim($text);
                $usedGroqVision = true;
                $ocr_failed = false;
            }
        }
    }

    // 5. FALLBACK: OCR.space (quando Groq Vision falha ou não está disponível)
    if (empty($contextoExtraido) || trim($contextoExtraido) === '') {
        $curlFile = function_exists('curl_file_create')
            ? curl_file_create($imageTmpPath, $file['type'], $file['name'])
            : new CURLFile($imageTmpPath, $file['type'], $file['name']);

        $mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $fileType = $mimeToExt[$file['type']] ?? 'png';

        $postFields = [
            'apikey' => OCR_API_KEY,
            'file' => $curlFile,
            'filetype' => $fileType,
            'language' => 'por', // Português
            'OCREngine' => '2', // Melhor para números e caracteres especiais (+, -, etc.)
            'isTable' => 'false',
            'scale' => 'true',
            'detectOrientation' => 'true',
            'isOverlayRequired' => 'false'
        ];

        $ch = curl_init(OCR_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        $resRaw = curl_exec($ch);
        curl_close($ch);

        if ($resRaw) {
            $res = json_decode($resRaw, true);
            if (isset($res['ParsedResults'][0]['ParsedText'])) {
                $contextoExtraido = trim($res['ParsedResults'][0]['ParsedText']);
            }
        }
        if (empty($contextoExtraido)) {
            $ocr_failed = true;
        }
    }

    // Fallback final
    if (empty($contextoExtraido) || trim($contextoExtraido) === '') {
        $contextoExtraido = "Não consegui ler o texto da imagem. Por favor descreve-a.";
        $ocr_failed = true;
    }

    // 6. GUARDAR IMAGEM LOCALMENTE (Backup e Histórico)
    // Guardamos o ficheiro numa pasta pública para que possa ser mostrado no chat depois.
    // Usamos um nome aleatório (hash) para evitar conflitos e proteger a privacidade.
    $imageUrl = null;
    $uploadDirRel = '/uploads/vision/';
    $uploadDir = __DIR__ . $uploadDirRel;

    // Criar diretoria se não existir
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            error_log("Erro ao criar pasta uploads/vision");
        }
    }

    // Move o ficheiro temporário para a pasta final
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $newName = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . $newName;

    if (move_uploaded_file($imageTmpPath, $targetPath)) {
        // Gera o URL público completo para devolver ao frontend
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $baseDir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $imageUrl = $protocol . '://' . $host . $baseDir . $uploadDirRel . $newName;
    } else {
        error_log("Falha ao mover ficheiro final.");
    }

    error_log("Vision " . ($usedGroqVision ? "Groq" : "OCR") . ": " . substr($contextoExtraido, 0, 300));

    // 7. RESPOSTA JSON FINAL
    echo json_encode([
        'status' => 'success',
        'description' => $contextoExtraido,
        'ocr_failed' => $ocr_failed,
        'image_url' => $imageUrl
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    respondWithError("Erro Interno: " . $e->getMessage(), 500);
}
?>