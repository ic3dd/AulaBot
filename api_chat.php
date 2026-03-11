<?php

// ---------------------------------------------------------------------------------
// 1. CONFIGURAÇÕES E TRATAMENTO DE ERROS
// ---------------------------------------------------------------------------------

// Desativa a exibição de erros no output para garantir que a resposta seja sempre JSON puro
ini_set('display_errors', 0);
// Ativa o log de erros em ficheiro para depuração
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/api_errors.log');
error_reporting(E_ALL);

// Aumentar limites para processamento de respostas longas da IA
ini_set('memory_limit', '512M');
set_time_limit(120);

// Cabeçalhos HTTP para JSON e CORS (permitir acesso de outros domínios)
header('Content-Type: application/json; charset=UTF-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");

// Tratamento de erros fatais (Shutdown Function)
// Se o script falhar (ex: falta de memória), envia um JSON válido em vez de cortar a ligação.
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (!headers_sent())
            header('Content-Type: application/json');
        // Mensagem amigável para o frontend
        echo json_encode(['status' => 'success', 'reply' => '⚠️ Ocorreu um erro interno no servidor (provavelmente memória ou imagem muito grande), mas recuperei. Tenta enviar apenas texto.']);
    }
});

// ---------------------------------------------------------------------------------
// 2. SESSÃO E SEGREDOS
// ---------------------------------------------------------------------------------

session_set_cookie_params(0, '/');
if (session_status() === PHP_SESSION_NONE)
    session_start();

// Carrega segredos (obrigatório em produção). Copie config_secrets.example.php para config_secrets.php.
if (file_exists(__DIR__ . '/config_secrets.php'))
    require_once(__DIR__ . '/config_secrets.php');

// Chaves devem vir de config_secrets.php (nunca commitar chaves no código)
if (!defined('GROQ_API_KEY'))
    define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: '');
if (!defined('IP_SECRET_PEPPER'))
    define('IP_SECRET_PEPPER', getenv('IP_SECRET_PEPPER') ?: 'change_me_in_config_secrets');

// Configurações do Modelo AI
define('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions');
define('GROQ_MODEL', 'llama-3.3-70b-versatile');
define('AI_MAX_TOKENS', 8000);
define('AI_TEMPERATURE', 0.1); // Mais determinístico para seguir regras
define('GUEST_WEEKLY_LIMIT', 3);

// ---------------------------------------------------------------------------------
// 3. FUNÇÕES AUXILIARES
// ---------------------------------------------------------------------------------

/**
 * Cria um hash do IP do utilizador usando o "Pepper" secreto.
 * Serve para limitar visitantes sem guardar o IP real (Privacidade).
 */
function getIpHash()
{
    $ipReal = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    return hash('sha256', $ipReal . IP_SECRET_PEPPER);
}

/**
 * Gera um UUID v4 para identificar sessões anónimas.
 */
function gen_uuid()
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}

/**
 * Envia a resposta JSON e encerra a execução.
 */
function exitWithJson($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Gera um título simples para o chat (corta o texto aos 40 chars).
 */
function gerarTituloComGroq($userMessage, $imageDescription)
{
    $topico = !empty($userMessage) ? $userMessage : $imageDescription;
    if (empty($topico))
        return 'Nova Conversa';
    return mb_substr($topico, 0, 40) . '...';
}

/**
 * Cria a estrutura da Base de Dados automaticamente se não existir.
 */
function criarTabelasSeNaoExistir($con)
{
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS `chats` (`id_chat` int AUTO_INCREMENT PRIMARY KEY, `id_utilizador` int NOT NULL, `titulo` varchar(255), `data_criacao_chat` datetime, `data_atualizacao` datetime) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS `mensagens` (`id_mensagem` int AUTO_INCREMENT PRIMARY KEY, `id_chat` int NOT NULL, `pergunta` text, `resposta` text, `data_conversa` datetime, `id_imagem` int DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS `mensagens_imagem` (`id_imagem` int AUTO_INCREMENT PRIMARY KEY, `id_mensagem` int NOT NULL, `filename` varchar(255), `mime` varchar(100), `content` longblob, `data_insercao` datetime DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS `uso_convidado` (`id` int AUTO_INCREMENT PRIMARY KEY, `ip_hash` varchar(64), `id_anonimo` varchar(100), `total_pedidos` int DEFAULT 0, `data_primeiro_pedido` datetime, `data_ultimo_pedido` datetime, `data_expiracao` datetime, `bloqueado` tinyint DEFAULT 0, KEY `id_anonimo` (`id_anonimo`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

try {
    // ---------------------------------------------------------------------------------
    // 4. LIGAÇÃO BD E INPUTS
    // ---------------------------------------------------------------------------------

    if (file_exists('ligarbd.php'))
        require_once('ligarbd.php');
    if (!isset($con) && isset($conn))
        $con = $conn;

    if (!isset($con) || !$con instanceof mysqli) {
        throw new Exception("Falha de conexão BD.");
    }

    mysqli_set_charset($con, "utf8mb4");
    criarTabelasSeNaoExistir($con);

    // Recebe o JSON do frontend
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true);

    if (json_last_error() !== JSON_ERROR_NONE && !empty($inputRaw)) {
        throw new Exception("JSON inválido recebido.");
    }

    $userMessage = trim($input['message'] ?? '');
    $anonymousId = trim($input['anonymousId'] ?? '');
    $imageUrl = trim($input['image_url'] ?? '');
    $imageDescription = trim($input['image_description'] ?? '');
    $materias = $input['materias'] ?? [];

    if (empty($userMessage) && empty($imageDescription)) {
        exitWithJson(['status' => 'success', 'reply' => 'Olá! Em que posso ajudar nos teus estudos?']);
    }

    // ---------------------------------------------------------------------------------
    // 5. IDENTIFICAÇÃO UTILIZADOR
    // ---------------------------------------------------------------------------------

    $idUtilizador = 0;
    if (isset($_SESSION['id_utilizador'])) {
        $idUtilizador = (int) $_SESSION['id_utilizador'];
    }
    // Fallback: Procura por email se a sessão de ID não estiver definida
    if ($idUtilizador === 0 && isset($_SESSION['email'])) {
        // USO DE PREPARED STATEMENT (Melhoria de Segurança)
        $stmt = mysqli_prepare($con, "SELECT id_utilizador FROM utilizador WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $_SESSION['email']);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        if ($res && $row = mysqli_fetch_assoc($res)) {
            $idUtilizador = (int) $row['id_utilizador'];
            $_SESSION['id_utilizador'] = $idUtilizador;
        }
        mysqli_stmt_close($stmt);
    }

    // LOG DE DEPURAÇÃO (api_errors.log)
    error_log("AULABOT DEBUG: UserID=" . $idUtilizador . " | Materias=" . json_encode($materias) . " | Msg=" . mb_substr($userMessage, 0, 50));

    $textoPrincipal = $userMessage ?: $imageDescription;

    $callAI = true;
    $newAnonymousId = null;

    // ---------------------------------------------------------------------------------
    // 6. GESTÃO DE VISITANTES (RATE LIMITING)
    // ---------------------------------------------------------------------------------

    // Se não estiver logado ($idUtilizador 0)
    if ($idUtilizador === 0) {
        $userIpHash = getIpHash();
        $guestRecord = null;

        // Verifica registo pelo Hash de IP
        $stmtIp = mysqli_prepare($con, "SELECT * FROM uso_convidado WHERE ip_hash = ? ORDER BY id DESC LIMIT 1");
        mysqli_stmt_bind_param($stmtIp, 's', $userIpHash);
        mysqli_stmt_execute($stmtIp);
        $resIp = mysqli_stmt_get_result($stmtIp);
        $guestRecord = mysqli_fetch_assoc($resIp);

        // Lógica para manter o ID anónimo consistente
        if ($guestRecord) {
            $anonymousId = $guestRecord['id_anonimo'];
            $newAnonymousId = $anonymousId;
        } else {
            if (!empty($anonymousId)) {
                $stmt = mysqli_prepare($con, "SELECT * FROM uso_convidado WHERE id_anonimo = ? LIMIT 1");
                mysqli_stmt_bind_param($stmt, 's', $anonymousId);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $guestRecord = mysqli_fetch_assoc($res);
            }
        }

        if ($guestRecord) {
            // Verificar Reset Semanal
            if (time() > strtotime($guestRecord['data_expiracao'])) {
                // UPDATE seguro
                $stmtReset = mysqli_prepare($con, "UPDATE uso_convidado SET total_pedidos = 0, data_expiracao = DATE_ADD(NOW(), INTERVAL 7 DAY), bloqueado = 0 WHERE id = ?");
                mysqli_stmt_bind_param($stmtReset, 'i', $guestRecord['id']);
                mysqli_stmt_execute($stmtReset);
                mysqli_stmt_close($stmtReset);

                $guestRecord['total_pedidos'] = 0;
                $guestRecord['bloqueado'] = 0;
            }

            // Verifica bloqueio ou limite atingido
            if ($guestRecord['bloqueado'] || $guestRecord['total_pedidos'] >= GUEST_WEEKLY_LIMIT) {
                $callAI = false;
                exitWithJson([
                    'status' => 'limit_reached',
                    'reply' => 'Atingiste o limite gratuito de 3 perguntas. Cria conta (é grátis) para continuares!',
                    'id_chat' => null,
                    'new_anonymous_id' => $guestRecord['id_anonimo']
                ]);
            }

            // Incrementar contador
            $stmtInc = mysqli_prepare($con, "UPDATE uso_convidado SET total_pedidos = total_pedidos + 1, data_ultimo_pedido = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($stmtInc, 'i', $guestRecord['id']);
            mysqli_stmt_execute($stmtInc);
            mysqli_stmt_close($stmtInc);

        } else {
            // Novo Visitante: Cria registo
            $newAnonymousId = gen_uuid();
            $stmt = mysqli_prepare($con, "INSERT INTO uso_convidado (ip_hash, id_anonimo, total_pedidos, data_primeiro_pedido, data_ultimo_pedido, data_expiracao) VALUES (?, ?, 1, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))");
            mysqli_stmt_bind_param($stmt, 'ss', $userIpHash, $newAnonymousId);
            mysqli_stmt_execute($stmt);
        }
    }

    // ---------------------------------------------------------------------------------
    // 7. CHAMADA À IA (GROQ / LLAMA 3)
    // ---------------------------------------------------------------------------------

    $fullReply = 'Erro no serviço de IA.';
    $chatId = isset($input['id_chat']) ? (int) $input['id_chat'] : null;

    if ($callAI) {
        // CONSTRÓI O PROTOCOLO DE DISCIPLINAS (Prioridade Máxima)
        $protocoloDisciplinas = "";
        if ($idUtilizador > 0) {
            $protocoloDisciplinas = "### PROTOCOLO DE DISCIPLINAS (OBRIGATÓRIO) ###\n";
            if (!empty($materias)) {
                $lista = implode(', ', $materias);
                $protocoloDisciplinas .= "DISCIPLINAS ATIVAS NO PERFIL: [ " . mb_strtoupper($lista) . " ].\n";
                $protocoloDisciplinas .= "INSTRUÇÃO CRÍTICA: Se a pergunta for sobre qualquer matéria que NÃO esteja na lista acima (ex: perguntar História se 'HISTÓRIA' não estiver na lista), deves PARAR a resposta imediatamente e recusar.\n";
                $protocoloDisciplinas .= "PROIBIÇÃO: É expressamente PROIBIDO responder a perguntas de disciplinas não listadas, mesmo que saibas a resposta.\n";
                $protocoloDisciplinas .= "RESPOSTA PADRÃO: 'Lamento, mas de momento apenas te posso ajudar com as disciplinas que escolheste no teu perfil: " . $lista . ". Se precisares de ajuda com História ou outra matéria, ativa-a nas definições.'\n\n";
            } else {
                $protocoloDisciplinas .= "ESTADO: O aluno ainda não selecionou NENHUMA disciplina no seu perfil.\n";
                $protocoloDisciplinas .= "REGRA DE BLOQUEIO: Deves recusar responder a qualquer pergunta escolar/académica até que o aluno selecione as disciplinas.\n";
                $protocoloDisciplinas .= "RESPOSTA AO BLOQUEIO: 'Vejo que ainda não selecionaste as tuas disciplinas! Para te poder ajudar com questões escolares, primeiro precisas de escolher as tuas matérias no menu Perfil -> Escolher Disciplinas.'\n\n";
            }
        }

        // System Prompt Final (Limpo de tags de template manuais)
        $systemContent = "### IDENTIDADE
Tu és o **AulaBot**, um tutor escolar especializado em ajudar estudantes portugueses.

" . $protocoloDisciplinas . "

### REGRAS OBRIGATÓRIAS
1. Usa SEMPRE Português de Portugal (PT-PT), nunca português do Brasil.
2. RECUSA temas não-escolares (fofocas, celebridades, jogos de video).
3. Sê pedagógico: explica passo a passo, como um bom professor.

### ESTRATÉGIA DE PENSAMENTO (Chain of Thought)
Antes de responderes, pensa passo a passo:
1. PENSAR: Identifica o objetivo da pergunta do aluno e a disciplina correspondente.
2. VERIFICAR: Confirma se podes responder com base no Protocolo de Disciplinas acima.
3. PLANEJAR: Estrutura a tua explicação de forma lógica e pedagógica.
4. EXPLICAR: Usa exemplos claros e analogias quando possível.
5. VERIFICAR: Confirma se a tua resposta está matematicamente correta antes de a gerares.

### FORMATAÇÃO DAS RESPOSTAS
Dá prioridade a uma estética visual premium, organizada e pedagógica:
1. **Emojis Estruturantes**: Usa emojis no início de secções e listas para tornar a leitura dinâmica (ex: 💡 Dica, 📝 Explicação, ✅ Conclusão, 🚀 Desafio).
2. **Hierarquia Clara**: Usa ## para títulos principais e ### para sub-secções. Nunca uses apenas texto corrido.
3. **Markdown Rico**:
   - Usa **negrito** para termos técnicos ou conceitos fundamentais.
   - Usa > blocos de citação para destacar fórmulas importantes ou definições 'chave'.
   - Usa tabelas | | | para comparar conceitos ou organizar dados.
4. **Espaçamento**: Deixa sempre linhas em branco entre parágrafos e secções.

**Matemática (OBRIGATÓRIO):**
- Equações inline: usa $equação$ (ex: $x = \frac{-b \pm \sqrt{b^2-4ac}}{2a}$)
- Equações em bloco: usa $$equação$$
- NUNCA uses texto simples para fórmulas. Usa sempre a sintaxe LaTeX entre símbolos de $.

### QUANDO RECEBERES UMA IMAGEM
Se receberes contexto de uma imagem (texto extraído via OCR):
1. Analisa cuidadosamente o conteúdo e reconstrói o enunciado se houver erros.
2. Resolve passo a passo, explicando o raciocínio por trás de cada operação.
3. ATENÇÃO: O OCR falha frequentemente em símbolos matemáticos. Verifica a coerência lógica.";

        $messages = [["role" => "system", "content" => $systemContent]];

        // Recupera Histórico de Conversa (Context Window)
        if (!empty($chatId) && $idUtilizador > 0) {
            // AUMENTO DE CONTEXTO: 20 mensagens (aprox. 10 trocas) para melhor memória
            $stmtH = mysqli_prepare($con, "SELECT pergunta, resposta FROM mensagens WHERE id_chat = ? ORDER BY data_conversa DESC LIMIT 20");
            mysqli_stmt_bind_param($stmtH, 'i', $chatId);
            mysqli_stmt_execute($stmtH);
            $resH = mysqli_stmt_get_result($stmtH);
            $h = [];
            while ($r = mysqli_fetch_assoc($resH)) {
                $h[] = ["role" => "assistant", "content" => $r['resposta']];
                $h[] = ["role" => "user", "content" => $r['pergunta']];
            }
            if (!empty($h))
                $messages = array_merge($messages, array_reverse($h));
        }


        // INJEÇÃO DE SEGURANÇA NO USER MESSAGE (Máxima Prioridade)
        $materiaAtivasStr = !empty($materias) ? implode(', ', $materias) : 'NENHUMA (BLOQUEIO TOTAL)';

        $msgContent = "### PROTOCOLO DE DISCIPLINAS ###\n" .
            "DISCIPLINAS AUTORIZADAS: [ " . mb_strtoupper($materiaAtivasStr) . " ]\n" .
            "REGRA: Se a pergunta abaixo não for de uma destas disciplinas, recusa responder e pede para ativar no perfil.\n" .
            "--------------------------------------------------\n\n" .
            $userMessage;

        if (!empty($imageDescription)) {
            $msgContent .= "\n\n---\n**📷 CONTEÚDO DA IMAGEM (extraído via OCR):**\n```\n" . $imageDescription . "\n```\n---\nPor favor, analisa o conteúdo acima e responde à minha pergunta."
                . (!empty($userMessage) ? "" : " Se for um exercício, resolve-o passo a passo.");
        }
        $messages[] = ["role" => "user", "content" => $msgContent];

        // Executa pedido cURL à API
        $ch = curl_init(GROQ_API_URL);
        $payloadJson = json_encode(["model" => GROQ_MODEL, "messages" => $messages, "temperature" => AI_TEMPERATURE, "max_tokens" => AI_MAX_TOKENS]);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payloadJson,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Authorization: Bearer " . GROQ_API_KEY],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 50
        ]);
        $resCurl = curl_exec($ch);
        curl_close($ch);

        if ($resCurl) {
            $jsonResponse = json_decode($resCurl, true);
            $fullReply = $jsonResponse['choices'][0]['message']['content'] ?? 'Erro IA.';
        }
    }

    // ---------------------------------------------------------------------------------
    // 8. GRAVAR NA BD
    // ---------------------------------------------------------------------------------

    $sqlError = null;

    if ($idUtilizador > 0) {
        // A. Tabela CHATS
        if (empty($chatId)) {
            $titulo = gerarTituloComGroq($userMessage, $imageDescription);
            $stmt = mysqli_prepare($con, "INSERT INTO chats (id_utilizador, titulo, data_criacao_chat, data_atualizacao) VALUES (?, ?, NOW(), NOW())");

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'is', $idUtilizador, $titulo);
                if (mysqli_stmt_execute($stmt)) {
                    $chatId = mysqli_insert_id($con);
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            // UPDATA seguro
            $stmtUpdate = mysqli_prepare($con, "UPDATE chats SET data_atualizacao = NOW() WHERE id_chat = ?");
            mysqli_stmt_bind_param($stmtUpdate, 'i', $chatId);
            mysqli_stmt_execute($stmtUpdate);
            mysqli_stmt_close($stmtUpdate);
        }

        // B. Tabela MENSAGENS
        if ($chatId) {
            $stmt = mysqli_prepare($con, "INSERT INTO mensagens (id_chat, pergunta, resposta, data_conversa) VALUES (?, ?, ?, NOW())");

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'iss', $chatId, $textoPrincipal, $fullReply);
                if (mysqli_stmt_execute($stmt)) {
                    $idMsg = mysqli_insert_id($con);

                    // C. IMAGENS (Vincula a imagem à mensagem se existir URL)
                    if (!empty($imageUrl) && $idMsg) {
                        try {
                            $stmtImg = mysqli_prepare($con, "INSERT INTO mensagens_imagem (id_mensagem, filename, mime) VALUES (?, ?, ?)");
                            if ($stmtImg) {
                                $m = 'image/jpeg';
                                mysqli_stmt_bind_param($stmtImg, 'iss', $idMsg, $imageUrl, $m);

                                if (mysqli_stmt_execute($stmtImg)) {
                                    $idImagem = mysqli_insert_id($con);
                                    // Atualiza a tabela mensagens com o ID da imagem inserida (Prepared Statement)
                                    $stmtUpdateMsg = mysqli_prepare($con, "UPDATE mensagens SET id_imagem = ? WHERE id_mensagem = ?");
                                    mysqli_stmt_bind_param($stmtUpdateMsg, 'ii', $idImagem, $idMsg);
                                    mysqli_stmt_execute($stmtUpdateMsg);
                                    mysqli_stmt_close($stmtUpdateMsg);
                                }
                                mysqli_stmt_close($stmtImg);
                            }
                        } catch (Throwable $e) {
                            error_log("Erro ao salvar referência de imagem (ignorado): " . $e->getMessage());
                        }
                    }

                } else {
                    $sqlError = "Erro Gravar Msg: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            }
        }
    }

    // ---------------------------------------------------------------------------------
    // 9. RESPOSTA FINAL
    // ---------------------------------------------------------------------------------

    $finalResponse = [
        'status' => 'success',
        'id_chat' => $chatId,
        'reply' => $fullReply,
        'user_identified' => ($idUtilizador > 0),
        'db_error' => $sqlError
    ];
    if ($newAnonymousId)
        $finalResponse['new_anonymous_id'] = $newAnonymousId;

    exitWithJson($finalResponse);

} catch (Exception $e) {
    http_response_code(200);
    exitWithJson(['status' => 'error', 'message' => 'Ocorreu um erro interno. Tenta novamente.']);
}
?>