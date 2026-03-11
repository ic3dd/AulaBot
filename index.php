<?php
// =================================================================================
// INDEX.PHP - CONTROLADOR PRINCIPAL (CORRIGIDO)
// =================================================================================

// 1. CONFIGURAÇÕES DE SESSÃO E ERROS
// Definir cookie para a raiz do site '/' para evitar problemas entre pastas (conta/ vs raiz)
session_set_cookie_params(0, '/'); 
if (session_status() === PHP_SESSION_NONE) session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. VERIFICAÇÃO DE AUTENTICAÇÃO
// Se não houver email na sessão, redireciona para o welcome.php e ENCERRA.
if (empty($_SESSION['email'])) {
    header("Location: welcome.php");
    exit; // Este exit é obrigatório para impedir que o resto da página carregue
}

// Define que o utilizador está autenticado
$autenticado = true;


// =================================================================================
// UTILIZADOR LOGADO - CARREGAR DADOS E LÓGICA
// =================================================================================

// 3. CONEXÃO À BASE DE DADOS E SEGURANÇA
// Carrega verificações de bloqueio (se existir)
if (file_exists(__DIR__ . '/bloqueio_check.php')) {
    require_once __DIR__ . '/bloqueio_check.php';
}

// Garante conexão à BD
if (!isset($con) || !$con) {
    if (file_exists(__DIR__ . '/ligarbd.php')) {
        require_once __DIR__ . '/ligarbd.php';
    } else {
        die("Erro Crítico: Ficheiro ligarbd.php não encontrado.");
    }
}

// 4. VERIFICAR MENSAGENS NÃO LIDAS (SUPORTE)
$tem_mensagem_nao_lida = false;
$emailUtilizador = mysqli_real_escape_string($con, $_SESSION['email']);

// Criar coluna se não existir
$checkMsgColumn = mysqli_query($con, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='utilizador' AND COLUMN_NAME='mensagem'");
if ($checkMsgColumn && mysqli_num_rows($checkMsgColumn) === 0) {
    mysqli_query($con, "ALTER TABLE utilizador ADD COLUMN mensagem TINYINT DEFAULT 0");
}

$queryMsg = "SELECT mensagem FROM utilizador WHERE email = '$emailUtilizador' LIMIT 1";
$resultadoMsg = mysqli_query($con, $queryMsg);
if ($resultadoMsg && mysqli_num_rows($resultadoMsg) > 0) {
    $rowMsg = mysqli_fetch_assoc($resultadoMsg);
    if (isset($rowMsg['mensagem']) && $rowMsg['mensagem'] == 1) {
        $tem_mensagem_nao_lida = true;
    }
}

// 5. INICIALIZAR NOTIFICAÇÕES (AJAX/JS)
if (file_exists(__DIR__ . '/init_notificacoes.php')) {
    @include_once __DIR__ . '/init_notificacoes.php';
}

// 6. SISTEMA DE ANÚNCIOS E ATUALIZAÇÕES
$ultimaAtualizacaoPublica = null;
$mostrarAnuncio = false;

// 6.1 Verificar colunas de preferências
$colunas = [
    'notif_atualizacoes' => 'TINYINT DEFAULT 1',
    'notif_manutencao' => 'TINYINT DEFAULT 1',
    'notif_novidades' => 'TINYINT DEFAULT 1',
    'notif_seguranca' => 'TINYINT DEFAULT 1',
    'notif_performance' => 'TINYINT DEFAULT 0',
    'tema_escola' => 'VARCHAR(100) DEFAULT NULL'
];

foreach ($colunas as $coluna => $tipo) {
    $checkColumn = mysqli_query($con, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='utilizador' AND COLUMN_NAME='$coluna'");
    if ($checkColumn && mysqli_num_rows($checkColumn) === 0) {
        mysqli_query($con, "ALTER TABLE utilizador ADD COLUMN $coluna $tipo");
    }
}

// 6.2 Buscar preferências do utilizador
$queryPreferencias = "SELECT notif_atualizacoes, notif_manutencao, notif_novidades, notif_seguranca, notif_performance FROM utilizador WHERE email = '$emailUtilizador' LIMIT 1";
$resultadoPreferencias = mysqli_query($con, $queryPreferencias);

$preferencias = [
    'notif_atualizacoes' => 1, 'notif_manutencao' => 1, 'notif_novidades' => 1, 
    'notif_seguranca' => 1, 'notif_performance' => 0
];

if ($resultadoPreferencias && mysqli_num_rows($resultadoPreferencias) > 0) {
    $rowPref = mysqli_fetch_assoc($resultadoPreferencias);
    foreach ($preferencias as $key => $val) {
        if (isset($rowPref[$key])) $preferencias[$key] = (int)$rowPref[$key];
    }
}

$mapa_tema_preferencia = [
    'atualizacoes' => 'notif_atualizacoes',
    'manutencao' => 'notif_manutencao',
    'novidades' => 'notif_novidades',
    'seguranca' => 'notif_seguranca',
    'performance' => 'notif_performance'
];

// 6.3 Buscar Updates Recentes (Últimas 24h)
$checkTemaColumn = mysqli_query($con, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='updates' AND COLUMN_NAME='tema'");
$temTema = ($checkTemaColumn && mysqli_num_rows($checkTemaColumn) > 0);

$sqlUpdates = "SELECT id_update, nome, versao, descricao, data_update " . ($temTema ? ", tema" : "") . " FROM updates WHERE data_update > DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY data_update DESC LIMIT 20";
$resultadoAtualizacaoPublica = mysqli_query($con, $sqlUpdates);

if ($resultadoAtualizacaoPublica && mysqli_num_rows($resultadoAtualizacaoPublica) > 0) {
    // Buscar ID numérico do utilizador para a tabela de vistos
    $resId = mysqli_query($con, "SELECT id_utilizador FROM utilizador WHERE email = '$emailUtilizador' LIMIT 1");
    $idUtilizador = ($resId && mysqli_num_rows($resId) > 0) ? (int)mysqli_fetch_assoc($resId)['id_utilizador'] : 0;
    
    while ($row = mysqli_fetch_assoc($resultadoAtualizacaoPublica)) {
        $idUpdate = (int)$row['id_update'];
        $temaDaAtualizacao = isset($row['tema']) ? $row['tema'] : 'atualizacoes';
        $colunaDaTema = $mapa_tema_preferencia[$temaDaAtualizacao] ?? 'notif_atualizacoes';
        
        // Se a preferência estiver desligada (0), salta
        if (empty($preferencias[$colunaDaTema])) continue;
        
        // Verificar se já viu este anúncio
        $queryVerificar = "SELECT data_visualizacao FROM anuncios_vistos WHERE id_utilizador = $idUtilizador AND id_update = $idUpdate";
        $resultadoVerificar = mysqli_query($con, $queryVerificar);
        
        // Se não houver registo de visualização, mostra o anúncio
        if ($resultadoVerificar && mysqli_num_rows($resultadoVerificar) === 0) {
            $ultimaAtualizacaoPublica = $row;
            $mostrarAnuncio = true;
            break; // Mostra apenas 1 (o mais recente)
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <!-- Configurações básicas da página HTML -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="AulaBot - Assistente Educacional para estudantes">
    <title>AulaBot - Assistente Educacional</title>
    <link rel="icon" href="nova-logo-removebg.png">
    <link rel="shortcut icon" href="data:image/x-icon;base64,AAABAAEAEBAQAAEABACoBAAA">
    
    <!-- Folhas de estilo CSS para o design da aplicação -->
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="conta/criarconta.css">
    <link rel="stylesheet" href="perfil.css">
    <!-- FontAwesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Correção para centralizar modais em dispositivos móveis */
        .modal {
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto; /* Centraliza vertical e horizontalmente */
            padding: 20px;
            border: 1px solid #888;
            width: 90%; /* Largura responsiva para mobile */
            max-width: 600px;
            border-radius: 8px;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    

    <?php if ($ultimaAtualizacaoPublica): ?>
    <!-- Modal para exibir anúncios de atualização do sistema -->
    <div id="updateAnnouncementModal" class="modal" style="display: none;">
        <div class="modal-content update-announcement-modal">
            <div class="update-announcement-header">
                <div>
                    <h3><i class="fas fa-bullhorn"></i> <?php echo htmlspecialchars($ultimaAtualizacaoPublica['nome']); ?></h3>
                    <span class="update-announcement-version">Versão <?php echo htmlspecialchars($ultimaAtualizacaoPublica['versao']); ?></span>
                </div>
                <button class="close-btn" id="closeUpdateAnnouncement">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="update-announcement-body">
                <span class="update-announcement-date"><i class="far fa-clock"></i> <?php echo date('d/m/Y \à\s H:i', strtotime($ultimaAtualizacaoPublica['data_update'])); ?></span>
                <p><?php echo nl2br(htmlspecialchars($ultimaAtualizacaoPublica['descricao'])); ?></p>
            </div>
            <div class="update-announcement-actions">
                <button type="button" class="update-announcement-button" id="ackUpdateAnnouncement">
                    <i class="fas fa-check"></i> Continuar
                </button>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- DEBUG: Nenhum anúncio disponível ($ultimaAtualizacaoPublica é null) -->
    <?php endif; ?>

    <!--
    =============================================
    MODAL DE CONTA BLOQUEADA
    =============================================
    -->
    <!-- Modal para informar ao usuário que sua conta foi bloqueada -->
    <div id="blockedAccountModal" class="modal" style="display: none;">
        <div class="modal-content blocked-account-modal">
            <div class="blocked-account-header">
                <i class="fas fa-lock"></i>
                <h3>Conta Bloqueada</h3>
            </div>
            <div class="blocked-account-body">
                <p class="blocked-message" id="blockedMessage"></p>
            </div>
            <div class="blocked-account-actions">
                <button type="button" class="blocked-account-button" id="closeBlockedModal">
                    <i class="fas fa-arrow-left"></i> Voltar
                </button>
            </div>
        </div>
    </div>

    <!--
    =============================================
    MODAL DE OPÇÕES DA CONTA
    =============================================
    -->
    <!-- Modal com opções de configuração da conta do usuário -->
    <div id="popup-opcoes" class="modal" style="display: none;">
        <div class="modal-content opcoes-modal">
            <div class="opcoes-header">
                <h3>Opções da Conta</h3>
                <button class="fechar-opcoes" id="fecharOpcoes">&times;</button>
            </div>
            <div class="opcoes-lista">
                <div class="opcao-item" id="personalizacaoBtn" data-emoji="🎨">
                    <i class="fas fa-cog"></i>
                    <span>Personalização</span>
                </div>
                    <div class="opcao-item" id="segurancaBtn" data-emoji="🔒">
                        <i class="fas fa-shield-alt"></i>
                        <span>Segurança</span>
                    </div>
                <div class="opcao-item" id="trocarEmailBtn" data-emoji="✉️">
                    <i class="fas fa-envelope"></i>
                    <span>Trocar Email</span>
                </div>
                <div class="opcao-item" id="notificacoesBtn" data-emoji="🔔">
                    <i class="fas fa-bell"></i>
                    <span>Notificação</span>
                </div>
                <div class="opcao-item" id="escolherDisciplinaBtn" data-emoji="📚">
                    <i class="fas fa-book"></i>
                    <span>Disciplinas</span>
                </div>
                <div class="opcao-item opcao-deletar" id="deletarContaBtn" data-emoji="🗑️">
                    <i class="fas fa-trash"></i>
                    <span>Deletar Conta</span>
                </div>
            </div>
        </div>
    </div>

    <!--
    =============================================
    MODAL PARA TROCAR EMAIL
    =============================================
    -->
    <!-- Modal para alterar o email da conta -->
    <div id="popup-trocar-email" class="modal" style="display: none;">
        <div class="modal-content opcoes-modal">
            <div class="opcoes-header">
                <h3>Trocar Email</h3>
                <button class="fechar-opcoes" id="fecharTrocarEmail">&times;</button>
            </div>
            <div class="opcoes-lista" style="padding: 8px 16px 16px 16px;">
                <form id="trocarEmailForm" class="trocar-email-form" aria-labelledby="trocarEmailTitulo">
                    <div>
                        <label for="email_atual">Email atual</label>
                        <input id="email_atual" name="email_atual" type="email" placeholder="Seu email atual" disabled>
                    </div>
                    <div>
                        <label for="novo_email">Novo email</label>
                        <input id="novo_email" name="novo_email" type="email" placeholder="Insira o novo email" required>
                    </div>
                    <div>
                        <label for="confirmar_novo_email">Confirmar novo email</label>
                        <input id="confirmar_novo_email" name="confirmar_novo_email" type="email" placeholder="Reescreva o novo email" required>
                    </div>
                    <div>
                        <label for="password_para_email">Palavra-passe</label>
                        <input id="password_para_email" name="password_para_email" type="password" placeholder="Insira sua palavra-passe para confirmar" required>
                    </div>
                    <div class="trocar-email-actions">
                        <button type="button" class="btn-cancelar" id="cancelTrocarEmail">Cancelar</button>
                        <button type="submit" class="btn-confirmar">Trocar Email</button>
                    </div>
                </form>
            </div>
        </div>
    </div>



    <!--
    =============================================
    MODAL DE CONFIRMAÇÃO DE EXCLUSÃO DE CONTA
    =============================================
    -->
    <!-- Modal para confirmar a exclusão permanente da conta -->
    <div id="popup-confirmacao" class="modal" style="display: none;">
        <div class="modal-content confirmacao-modal">
            <div class="confirmacao-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Confirmar Exclusão</h3>
            </div>
            <div class="confirmacao-body">
                <p>Tem certeza que deseja deletar sua conta?</p>
                <p class="aviso-importante">Esta ação não pode ser desfeita e todos os seus dados serão permanentemente removidos.</p>
            </div>
            <div class="confirmacao-botoes">
                <button class="btn-cancelar" id="cancelarExclusao">Cancelar</button>
                <button class="btn-confirmar" id="confirmarExclusao">Sim, Deletar Conta</button>
            </div>
        </div>
    </div>

    <!--
    =============================================
    CONTÊINER PARA NOTIFICAÇÕES TOAST
    =============================================
    -->
    <!-- Área onde as notificações temporárias (toast) são exibidas -->
    <div id="toast-container" class="toast-container"></div>
 
    <!--
    =============================================
    NAVEGAÇÃO PARA DISPOSITIVOS MÓVEIS
    =============================================
    -->
    <!-- Botão para alternar o menu lateral em dispositivos móveis -->
    <?php if (!empty($autenticado)): ?>
    <button class="alternar-menu" id="alternarMenu" aria-label="Alternar menu">
        <span class="sr-only">Menu</span>
    </button>
    <?php endif; ?>

    <!-- Sobreposição escura que aparece quando o menu mobile está ativo -->
    <?php if (!empty($autenticado)): ?>
    <div class="sobreposicao" id="sobreposicao" aria-hidden="true"></div>
    
    <!--
    =============================================
    BARRA LATERAL DE NAVEGAÇÃO
    =============================================
    -->
    <!-- Menu lateral com histórico de conversas e opções da conta -->
    <aside class="barra-lateral" id="barraLateral">
        <!-- Cabeçalho da barra lateral com logo e botão de nova conversa -->
        <div class="cabecalho-lateral">
            <div class="logo">
                <img src="nova-logo-removebg.png" alt="Logo do AulaBot" class="logo-imagem">
                <h2>AulaBot</h2>
            </div>
            <button class="botao-nova-conversa" aria-label="Iniciar nova conversa">
                Nova conversa
            </button>
        </div>
        
        <!-- Histórico de conversas anteriores -->
        <nav class="historico-conversas">
            <h3 class="titulo-historico">Histórico de conversas</h3>
            <ul class="lista-historico" id="listaHistorico">
                <?php
                // Listar chats do utilizador (ou chats anónimos se id_utilizador = 0)
                $idUtilizadorQuery = 0;
                if (isset($_SESSION['email'])) {
                    $emailEsc = mysqli_real_escape_string($con, $_SESSION['email']);
                    $resId = mysqli_query($con, "SELECT id_utilizador FROM utilizador WHERE email = '$emailEsc' LIMIT 1");
                    if ($resId && mysqli_num_rows($resId) > 0) {
                        $rowId = mysqli_fetch_assoc($resId);
                        $idUtilizadorQuery = (int)$rowId['id_utilizador'];
                    }
                }

                $sqlChats = "SELECT id_chat, titulo, data_atualizacao FROM chats WHERE id_utilizador = $idUtilizadorQuery ORDER BY data_atualizacao DESC LIMIT 50";
                $resChats = mysqli_query($con, $sqlChats);
                if ($resChats && mysqli_num_rows($resChats) > 0) {
                    while ($chat = mysqli_fetch_assoc($resChats)) {
                        $idc = (int)$chat['id_chat'];
                        $titulo = htmlspecialchars($chat['titulo']);
                        echo "<li class=\"item-historico\" data-id=\"$idc\">";
                        echo "<div class=\"chat-item-container\">";
                        echo "<span class=\"chat-titulo\">$titulo</span>";
                        echo "<div class=\"chat-actions-container\">";
                        echo "<button class=\"chat-btn-action\" onclick=\"renomearChat($idc)\" title=\"Mudar nome\">✎</button>";
                        echo "<button class=\"chat-btn-action delete\" onclick=\"eliminarChat($idc)\" title=\"Eliminar\">🗑️</button>";
                        echo "</div>";
                        echo "</div>";
                        echo "</li>";
                    }
                } else {
                    echo '<li class="item-historico vazio">Sem conversas ainda</li>';
                }
                ?>
            </ul>
        </nav>
        
        <!-- Rodapé da barra lateral com links de navegação secundária -->
        <footer class="rodape-lateral">
            <nav class="menu-rodape">
                <div class="acao-lateral conta-dropdown">
                    <button class="conta-btn" id="contaBtn">
                        <span><?php echo htmlspecialchars($_SESSION['nome']); ?></span>
                        <svg class="dropdown-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none">
                            <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <div class="dropdown-menu" id="contaDropdown">
                        <a href="#" class="dropdown-item">Ver Perfil</a>
                        <?php if (isset($_SESSION['tipo']) && $_SESSION['tipo'] === 'admin'): ?>
                        <a href="admin/dashboard.php" class="dropdown-item admin-item">
                            <i class="fas fa-cogs"></i> Dashboard Admin
                        </a>
                        <?php endif; ?>
                        <a href="#" class="dropdown-item" id="opcoesBtn">Opções</a>
                        <a href="#" class="dropdown-item logout-item" onclick="fazerLogout(event)">Terminar Sessão</a>  
                    </div>
                </div>
                <div class="acao-lateral" id="ajuda-container">
                    <a href="aba-ajuda/ajuda.php" class="link-acao" id="ajuda-link">
                        Ajuda e FAQ
                        <span class="notification-dot" id="notification-dot-ajuda" <?php echo $tem_mensagem_nao_lida ? '' : 'style="display: none;"'; ?>></span>
                    </a>
                </div>
                <div class="acao-lateral">
                    <a href="aba-feedback/feedback.php" class="link-acao">Feedback</a>
                </div>
            </nav>
        </footer>
    </aside>
    <?php endif; ?>
    
    <!--
    =============================================
    ÁREA PRINCIPAL DO CONTEÚDO
    =============================================
    -->
    <!-- Conteúdo principal da aplicação, visível apenas para usuários logados -->
    <?php if (!empty($autenticado)): ?>
    <main class="conteudo-principal">
        <!-- Cabeçalho da área principal com título e botão de menu -->
        <header class="cabecalho-chat">
            <button class="botao-menu-desktop" id="botaoMenuDesktop" aria-label="Alternar barra lateral">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 12H21M3 6H21M3 18H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <h1>AulaBot - O Teu Assistente Educacional</h1>
        </header>
        
        <!--
        =============================================
        ÁREA DE CONVERSAÇÃO COM O CHATBOT
        =============================================
        -->
        <!-- Container principal onde as mensagens do chat são exibidas -->
        <section class="container-chat" id="containerChat">
            <!-- Ecrã de boas-vindas - Mostrado quando não há conversas ativas -->
            <div class="ecra-bem-vindo">
                <h2 class="titulo-bem-vindo">Olá, eu sou o AulaBot</h2>
                <p class="subtitulo-bem-vindo">O teu assistente para aprendizagem e educação</p>
                
                <!-- Exemplos de perguntas para ajudar o utilizador a começar -->
                <div class="exemplos">
                    <div class="cartao-exemplo">
                        <p>"Explica os conceitos básicos de física quântica"</p>
                    </div>
                    <div class="cartao-exemplo">
                        <p>"Ajuda-me com os trabalhos de casa de matemática"</p>
                    </div>
                    <div class="cartao-exemplo">
                        <p>"Escreve um poema sobre o oceano"</p>
                    </div>
                </div>
            </div>
            
            <!-- Exemplo de mensagem do utilizador -->
            <div class="mensagem mensagem-utilizador">
                <div class="avatar avatar-utilizador" aria-hidden="true"></div>
                <div class="conteudo-mensagem">
                    <p>Podes explicar-me o que é a fotossíntese?</p>
                    <time class="timestamp timestamp-utilizador">Hoje, 14:30</time>
                </div>
            </div>
            
            <!-- Exemplo de resposta do bot -->
            <div class="mensagem mensagem-bot">
                <div class="avatar avatar-bot" aria-hidden="true"></div>
                <div class="conteudo-mensagem">
                    <p>Claro! A fotossíntese é o processo através do qual as plantas, algas e algumas bactérias convertem a luz solar, dióxido de carbono e água em glucose e oxigénio. Este processo ocorre principalmente nos cloroplastos das células vegetais e é essencial para a vida na Terra, pois fornece oxigénio para a respiração dos seres vivos.</p>
                    <time class="timestamp timestamp-bot">Hoje, 14:32</time>
                </div>
            </div>
        </section>
        
        <!--
        =============================================
        ÁREA DE ENTRADA DE TEXTO
        =============================================
        -->
        <!-- Campo de texto para o usuário digitar mensagens e botão de envio -->
        <div class="area-input">
            <!-- Pré-visualização de imagem anexada (estilo ChatGPT melhorado) -->
            <div id="preview-container" style="display:none; width: 100%; padding: 16px 20px; background: linear-gradient(135deg, rgba(255,255,255,0.5) 0%, rgba(248,248,248,0.8) 100%); border-bottom: 1px solid rgba(0,0,0,0.05);">
                <div style="display: flex; gap: 12px; align-items: flex-start;">
                    <div style="position: relative; flex-shrink: 0;">
                        <img id="img-render" src="" style="width: 140px; height: 140px; object-fit: cover; border-radius: 12px; border: 1px solid #ddd; box-shadow: 0 4px 12px rgba(0,0,0,0.12); transition: transform 0.2s ease, box-shadow 0.2s ease;">
                        <button type="button" onclick="removerAnexo()" style="position: absolute; top: -12px; right: -12px; background: #ef4444; color: white; border: none; border-radius: 50%; width: 32px; height: 32px; cursor: pointer; font-size: 20px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(239,68,68,0.3); transition: all 0.2s ease; font-weight: bold;" onmouseover="this.style.background='#dc2626'; this.style.transform='scale(1.1)';" onmouseout="this.style.background='#ef4444'; this.style.transform='scale(1)';">×</button>
                    </div>
                </div>
            </div>

            <div class="container-input" style="display: flex; align-items: center; padding: 10px 15px; gap: 10px;">
                <button type="button" onclick="document.getElementById('input-ficheiro').click()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #666;">
                    📎
                </button>
                <input type="file" id="input-ficheiro" style="display:none" accept="image/*" onchange="visualizarImagem(this)">

                <textarea 
                    class="input-mensagem" 
                    id="inputMensagem"
                    placeholder="Escreve uma mensagem ou cola um print..." 
                    rows="1"
                    aria-label="Mensagem para o AulaBot"
                    style="flex: 1; border: none; outline: none; padding: 5px; max-height: 200px; resize: none;"></textarea>
                <button class="botao-enviar" id="botaoEnviar" aria-label="Enviar mensagem">
                    <div class="svg-wrapper-1">
                        <div class="svg-wrapper">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
                                <path fill="none" d="M0 0h24v24H0z"></path>
                                <path fill="currentColor" d="M1.946 9.315c-.522-.174-.527-.455.01-.634l19.087-6.362c.529-.176.832.12.684.638l-5.454 19.086c-.15.529-.455.547-.679.045L12 14l6-8-8 6-8.054-2.685z"></path>
                            </svg>
                        </div>
                    </div>
                    <span>Enviar</span>
                </button>
            </div>
            
            
        </div>
    </main>
    <?php endif; ?>

    <!--
    =============================================
    MODAL PARA ALTERAR SENHA
    =============================================
    -->
    <!-- Modal para alteração de senha da conta -->
    <div id="popup-trocar-password" class="modal" style="display: none;">
        <div class="modal-content opcoes-modal">
            <div class="opcoes-header">
                <h3>Segurança — Trocar Palavra-Passe</h3>
                <button class="fechar-opcoes" id="fecharTrocarPassword">&times;</button>
            </div>
            <div class="opcoes-lista" style="padding: 8px 16px 16px 16px;">
                <form id="trocarPasswordForm" class="trocar-senha-form" aria-labelledby="trocarSenhaTitulo">
                    <div>
                        <label for="current_password">Palavra-passe atual</label>
                        <input id="current_password" name="current_password" type="password" placeholder="Insira a palavra-passe atual" required>
                    </div>
                    <div>
                        <label for="new_password">Nova palavra-passe</label>
                        <input id="new_password" name="new_password" type="password" placeholder="Mínimo 6 caracteres" minlength="6" required>
                    </div>
                    <div>
                        <label for="confirm_password">Confirmar nova palavra-passe</label>
                        <input id="confirm_password" name="confirm_password" type="password" placeholder="Reescreva a nova palavra-passe" required>
                    </div>
                    <div class="trocar-senha-actions">
                        <button type="button" class="btn-cancelar" id="cancelTrocarPassword">Cancelar</button>
                        <button type="submit" class="btn-confirmar">Alterar palavra-passe</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!--
    =============================================
    MODAL DO PERFIL DO USUÁRIO
    =============================================
    -->
    <!-- Modal para visualizar informações do perfil do usuário -->
    <div id="popup-perfil" class="modal" style="display: none;">
        <div class="modal-content opcoes-modal">
            <div class="opcoes-header">
                <h3>Perfil do Usuário</h3>
                <button class="fechar-opcoes" id="fecharPerfil">&times;</button>
            </div>
            <div class="perfil-content">
                <div class="perfil-info">
                    <div class="perfil-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="perfil-detalhes">
                        <h4><?php echo htmlspecialchars($_SESSION['nome']); ?></h4>
                        <p class="perfil-email"><?php echo htmlspecialchars($_SESSION['email']); ?></p>
                        <?php if (isset($_SESSION['tipo'])): ?>
                        <p class="perfil-tipo">Tipo de conta: <?php echo htmlspecialchars($_SESSION['tipo']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="perfil-links">
                    <a href="termos.html" class="perfil-link" id="verTermos">
                        <i class="fas fa-file-alt"></i>
                        Termos de Utilização
                    </a>
                    <a href="privacidade.html" class="perfil-link" id="verPrivacidade">
                        <i class="fas fa-shield-alt"></i>
                        Política de Privacidade
                    </a>
                </div>"
            </div>
        </div>
    </div>
                <!--
                =============================================
                MODAL DE PERSONALIZAÇÃO DA INTERFACE
                =============================================
                -->
                <!-- Modal para personalizar aparência, tema e cores da aplicação -->
                <div id="popup-personalizacao" class="modal" style="display: none;">
                    <div class="modal-content personalizacao-modal">
                        <div class="personalizacao-header">
                            <h3>
                                <i class="fas fa-paint-brush"></i>
                                Personalização
                            </h3>
                            <button class="fechar-personalizacao" id="fecharPersonalizacao">&times;</button>
                        </div>
                        
                        <div class="personalizacao-body">
                            <!-- Aspeto -->
                            <div class="personalizacao-section">
                                <div class="section-label">
                                    <i class="fas fa-sun"></i>
                                    Aspeto
                                </div>
                                <select id="personalizacaoTema" class="select-tema">
                                    <option value="light">Claro</option>
                                    <option value="dark">Escuro</option>
                                </select>
                            </div>

                            <!-- Cor de destaque -->
                            <div class="personalizacao-section">
                                <div class="section-label">
                                    <i class="fas fa-palette"></i>
                                    Cor de destaque
                                </div>
                                <div class="cor-grid">
                                    <button class="cor-btn" data-cor="#4f46e5" title="Indigo (Padrão)"></button>
                                    <button class="cor-btn" data-cor="#007bff" title="Azul"></button>
                                    <button class="cor-btn" data-cor="#ffb400" title="Amarelo"></button>
                                    <button class="cor-btn" data-cor="#ef4444" title="Vermelho"></button>
                                    <button class="cor-btn" data-cor="#8b5cf6" title="Roxo"></button>
                                    <button class="cor-btn" data-cor="#06b6d4" title="Ciano"></button>
                                </div>
                            </div>

                            <!-- Preview -->
                            <div class="preview-section">
                                <div class="preview-label">Pré-visualização:</div>
                                <div class="preview-content">
                                    <button class="preview-btn">
                                        <i class="fas fa-paper-plane"></i>
                                        Enviar
                                    </button>
                                    <div class="preview-input"></div>
                                    <div class="preview-avatar"></div>
                                </div>
                            </div>

                            <!-- Botões de ação -->
                            <div class="personalizacao-actions">
                                <button class="btn-cancelar" id="cancelarPersonalizacao">Cancelar</button>
                                <button class="btn-confirmar" id="aplicarPersonalizacao">
                                    <i class="fas fa-save"></i>
                                    Aplicar Alterações
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

    <!--
    =============================================
    MODAL DOS TERMOS DE UTILIZAÇÃO
    =============================================
    -->
    <!-- Modal para exibir os termos de utilização do serviço -->
    <div id="popup-termos" class="modal" style="display: none;">
        <div class="modal-content opcoes-modal">
            <div class="opcoes-header">
                <h3>Termos de Utilização</h3>
                <button class="fechar-opcoes" id="fecharTermos">&times;</button>
            </div>
            </div>
        </div>
    </div>

    <!--
    =============================================
    MODAL DA POLÍTICA DE PRIVACIDADE
    =============================================
    -->
    <!-- Modal para exibir a política de privacidade do serviço -->
    <div id="popup-privacidade" class="modal" style="display: none;">
        <div class="modal-content opcoes-modal">
            <div class="opcoes-header">
                <h3>Política de Privacidade</h3>
                <button class="fechar-opcoes" id="fecharPrivacidade">&times;</button>
            </div>
        </div>
    </div>

    <!--
    =============================================
    MODAL DE PREFERÊNCIAS DE NOTIFICAÇÕES
    =============================================
    -->
    <!-- Modal para configurar preferências de notificações do usuário -->
    <div id="popup-notificacoes" class="modal" style="display: none;">
        <div class="modal-content notificacoes-modal">
            <div class="notificacoes-header">
                <div class="notificacoes-title-wrapper">
                    <i class="fas fa-bell notificacoes-icon"></i>
                    <h3>Preferências de Notificações</h3>
                </div>
                <button class="fechar-notificacoes" id="fecharNotificacoes">&times;</button>
            </div>
            <div class="notificacoes-content">
                <p class="notificacoes-subtitulo">Personalize quais atualizações do site você deseja receber</p>
                
                <form id="notificacoesForm" class="notificacoes-form">
                    <div class="notificacao-item notificacao-atualizacoes">
                        <div class="notificacao-checkbox-wrapper">
                            <input type="checkbox" id="notif-atualizacoes" name="notif_atualizacoes" class="notificacao-checkbox" checked>
                            <label for="notif-atualizacoes" class="notificacao-label">
                                <span class="checkbox-custom"></span>
                                <span class="notificacao-info">
                                    <span class="notificacao-icon"><i class="fas fa-star"></i></span>
                                    <span class="notificacao-texto">
                                        <strong>Atualizações Gerais</strong>
                                        <span class="notificacao-descricao">Novas funcionalidades e melhorias</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="notificacao-item notificacao-manutencao">
                        <div class="notificacao-checkbox-wrapper">
                            <input type="checkbox" id="notif-manutencao" name="notif_manutencao" class="notificacao-checkbox" checked>
                            <label for="notif-manutencao" class="notificacao-label">
                                <span class="checkbox-custom"></span>
                                <span class="notificacao-info">
                                    <span class="notificacao-icon"><i class="fas fa-tools"></i></span>
                                    <span class="notificacao-texto">
                                        <strong>Manutenção e Correções</strong>
                                        <span class="notificacao-descricao">Manutenção programada e correções</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="notificacao-item notificacao-novidades">
                        <div class="notificacao-checkbox-wrapper">
                            <input type="checkbox" id="notif-novidades" name="notif_novidades" class="notificacao-checkbox" checked>
                            <label for="notif-novidades" class="notificacao-label">
                                <span class="checkbox-custom"></span>
                                <span class="notificacao-info">
                                    <span class="notificacao-icon"><i class="fas fa-sparkles"></i></span>
                                    <span class="notificacao-texto">
                                        <strong>Novidades e Eventos</strong>
                                        <span class="notificacao-descricao">Eventos, webinars e novidades</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="notificacao-item notificacao-seguranca">
                        <div class="notificacao-checkbox-wrapper">
                            <input type="checkbox" id="notif-seguranca" name="notif_seguranca" class="notificacao-checkbox" checked>
                            <label for="notif-seguranca" class="notificacao-label">
                                <span class="checkbox-custom"></span>
                                <span class="notificacao-info">
                                    <span class="notificacao-icon"><i class="fas fa-shield-alt"></i></span>
                                    <span class="notificacao-texto">
                                        <strong>Alertas de Segurança</strong>
                                        <span class="notificacao-descricao">Avisos sobre segurança (recomendado)</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="notificacao-item notificacao-performance">
                        <div class="notificacao-checkbox-wrapper">
                            <input type="checkbox" id="notif-performance" name="notif_performance" class="notificacao-checkbox">
                            <label for="notif-performance" class="notificacao-label">
                                <span class="checkbox-custom"></span>
                                <span class="notificacao-info">
                                    <span class="notificacao-icon"><i class="fas fa-bolt"></i></span>
                                    <span class="notificacao-texto">
                                        <strong>Melhorias de Performance</strong>
                                        <span class="notificacao-descricao">Otimizações e melhorias</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="notificacoes-actions">
                        <button type="button" class="btn-cancelar" id="cancelarNotificacoes">Cancelar</button>
                        <button type="submit" class="btn-confirmar">
                            <i class="fas fa-check"></i>
                            Salvar Preferências
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para configurar preferências de disciplinas escolares do usuário -->
    <div id="popup-disciplina" class="modal" style="display: none;">
        <div class="modal-content disciplina-modal">
            <div class="disciplina-header">
                <div class="disciplina-title-wrapper">
                    <i class="fas fa-book disciplina-icon"></i>
                    <h3>Preferências de Disciplinas</h3>
                </div>
                <button class="fechar-disciplina" id="fecharDisciplina">&times;</button>
            </div>
            <div class="disciplina-content">
                <p class="disciplina-subtitulo">Selecione as disciplinas que deseja acompanhar</p>
                
                <form id="disciplinaForm" class="disciplina-form">
                    <div class="select-all-disciplina">
                        <input type="checkbox" id="selectAllDisciplina" class="disciplina-check">
                        <label for="selectAllDisciplina"><strong>Selecionar Todas</strong></label>
                    </div>
                    <div class="disciplina-grid-modal">
                        <label class="materia-checkbox-item">
                            <input type="checkbox" name="disciplina" value="portugues" class="materia-check">
                            <span class="materia-box-modal">
                                <span class="materia-check-custom"></span>
                                <span class="materia-info">
                                    <span class="materia-icon"><i class="fas fa-feather"></i></span>
                                    <span class="materia-texto">
                                        <strong>Português</strong>
                                        <span class="materia-descricao">Leitura e escrita</span>
                                    </span>
                                </span>
                            </span>
                        </label>

                        <label class="materia-checkbox-item">
                            <input type="checkbox" name="disciplina" value="matematica" class="materia-check">
                            <span class="materia-box-modal">
                                <span class="materia-check-custom"></span>
                                <span class="materia-info">
                                    <span class="materia-icon"><i class="fas fa-calculator"></i></span>
                                    <span class="materia-texto">
                                        <strong>Matemática</strong>
                                        <span class="materia-descricao">Cálculos e lógica</span>
                                    </span>
                                </span>
                            </span>
                        </label>

                        <label class="materia-checkbox-item">
                            <input type="checkbox" name="disciplina" value="fisica" class="materia-check">
                            <span class="materia-box-modal">
                                <span class="materia-check-custom"></span>
                                <span class="materia-info">
                                    <span class="materia-icon"><i class="fas fa-atom"></i></span>
                                    <span class="materia-texto">
                                        <strong>Física</strong>
                                        <span class="materia-descricao">Leis do movimento</span>
                                    </span>
                                </span>
                            </span>
                        </label>

                        <label class="materia-checkbox-item">
                            <input type="checkbox" name="disciplina" value="quimica" class="materia-check">
                            <span class="materia-box-modal">
                                <span class="materia-check-custom"></span>
                                <span class="materia-info">
                                    <span class="materia-icon"><i class="fas fa-flask"></i></span>
                                    <span class="materia-texto">
                                        <strong>Química</strong>
                                        <span class="materia-descricao">Elementos e reações</span>
                                    </span>
                                </span>
                            </span>
                        </label>

                        <label class="materia-checkbox-item">
                            <input type="checkbox" name="disciplina" value="biologia" class="materia-check">
                            <span class="materia-box-modal">
                                <span class="materia-check-custom"></span>
                                <span class="materia-info">
                                    <span class="materia-icon"><i class="fas fa-dna"></i></span>
                                    <span class="materia-texto">
                                        <strong>Biologia</strong>
                                        <span class="materia-descricao">Seres vivos</span>
                                    </span>
                                </span>
                            </span>
                        </label>

                        <label class="materia-checkbox-item">
                            <input type="checkbox" name="disciplina" value="historia" class="materia-check">
                            <span class="materia-box-modal">
                                <span class="materia-check-custom"></span>
                                <span class="materia-info">
                                    <span class="materia-icon"><i class="fas fa-scroll"></i></span>
                                    <span class="materia-texto">
                                        <strong>História</strong>
                                        <span class="materia-descricao">Eventos passados</span>
                                    </span>
                                </span>
                            </span>
                        </label>

                        <label class="materia-checkbox-item">
                            <input type="checkbox" name="disciplina" value="geografia" class="materia-check">
                            <span class="materia-box-modal">
                                <span class="materia-check-custom"></span>
                                <span class="materia-info">
                                    <span class="materia-icon"><i class="fas fa-globe"></i></span>
                                    <span class="materia-texto">
                                        <strong>Geografia</strong>
                                        <span class="materia-descricao">Terras e população</span>
                                    </span>
                                </span>
                            </span>
                        </label>

                        <label class="materia-checkbox-item">
                            <input type="checkbox" name="disciplina" value="ingles" class="materia-check">
                            <span class="materia-box-modal">
                                <span class="materia-check-custom"></span>
                                <span class="materia-info">
                                    <span class="materia-icon"><i class="fas fa-language"></i></span>
                                    <span class="materia-texto">
                                        <strong>Inglês</strong>
                                        <span class="materia-descricao">Idioma estrangeiro</span>
                                    </span>
                                </span>
                            </span>
                        </label>

                        <label class="materia-checkbox-item">
                            <input type="checkbox" name="disciplina" value="francés" class="materia-check">
                            <span class="materia-box-modal">
                                <span class="materia-check-custom"></span>
                                <span class="materia-info">
                                    <span class="materia-icon"><i class="fas fa-language"></i></span>
                                    <span class="materia-texto">
                                        <strong>Francês</strong>
                                        <span class="materia-descricao">Idioma estrangeiro</span>
                                    </span>
                                </span>
                            </span>
                        </label>

                        <label class="materia-checkbox-item">
                            <input type="checkbox" name="disciplina" value="artes" class="materia-check">
                            <span class="materia-box-modal">
                                <span class="materia-check-custom"></span>
                                <span class="materia-info">
                                    <span class="materia-icon"><i class="fas fa-palette"></i></span>
                                    <span class="materia-texto">
                                        <strong>Artes</strong>
                                        <span class="materia-descricao">Expressão criativa</span>
                                    </span>
                                </span>
                            </span>
                        </label>

                        <label class="materia-checkbox-item">
                            <input type="checkbox" name="disciplina" value="educacao_fisica" class="materia-check">
                            <span class="materia-box-modal">
                                <span class="materia-check-custom"></span>
                                <span class="materia-info">
                                    <span class="materia-icon"><i class="fas fa-running"></i></span>
                                    <span class="materia-texto">
                                        <strong>Educação Física</strong>
                                        <span class="materia-descricao">Atividade física</span>
                                    </span>
                                </span>
                            </span>
                        </label>

                        <label class="materia-checkbox-item">
                            <input type="checkbox" name="disciplina" value="cidadania" class="materia-check">
                            <span class="materia-box-modal">
                                <span class="materia-check-custom"></span>
                                <span class="materia-info">
                                    <span class="materia-icon"><i class="fas fa-handshake"></i></span>
                                    <span class="materia-texto">
                                        <strong>Cidadania</strong>
                                        <span class="materia-descricao">Educação cívica</span>
                                    </span>
                                </span>
                            </span>
                        </label>
                    </div>

                    <div class="disciplina-actions">
                        <button type="button" class="btn-cancelar" id="cancelarDisciplina">Cancelar</button>
                        <button type="submit" class="btn-confirmar">
                            <i class="fas fa-check"></i>
                            Salvar Preferências
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify/dist/purify.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.css">
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/katex.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/contrib/auto-render.min.js"></script>

    <!-- Scripts JavaScript externos para funcionalidades da aplicação -->
    <script src="toast-notifications.js?v=<?php echo time(); ?>"></script>
    <script src="reactive.js?v=<?php echo time(); ?>"></script>
    <script src="conta/criarconta.js?v=<?php echo time(); ?>"></script>
    <script src="personalizacao.js?v=<?php echo time(); ?>"></script>
    <script src="aba-ajuda/check_unread.js?v=<?php echo time(); ?>"></script>
    
    <script>
        // Verificar se a conta foi deletada com sucesso e mostrar notificação
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('deleted') === '1') {
            if (typeof showToast === 'function') {
                showToast('success', 'Conta deletada com sucesso!', 'Sua conta foi permanentemente removida.');
            }
            // Limpar a URL
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        function fazerLogout(event) {
            event.preventDefault();
            localStorage.removeItem('auth_email');
            localStorage.removeItem('auth_nome');
            const authId = localStorage.getItem('auth_id');
            localStorage.removeItem('auth_id');
            localStorage.removeItem('chat_ajuda_conversation_id');
            // Se possível, enviar id via querystring para garantir que o servidor sabe quem fez logout
            if (authId) {
                window.location.href = 'logout.php?id=' + encodeURIComponent(authId);
            } else {
                window.location.href = 'logout.php';
            }
        }

        // Inicializar controles da interface quando o DOM estiver carregado
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($autenticado) && isset($_SESSION['email']) && isset($_SESSION['nome'])): ?>
            localStorage.setItem('auth_email', <?php echo json_encode($_SESSION['email']); ?>);
            localStorage.setItem('auth_nome', <?php echo json_encode($_SESSION['nome']); ?>);
            localStorage.setItem('auth_id', <?php echo json_encode($_SESSION['id_utilizador'] ?? null); ?>);
            <?php endif; ?>
            
            // Notificação de mensagem de suporte não lida
            <?php if (isset($tem_mensagem_nao_lida) && $tem_mensagem_nao_lida): ?>
            setTimeout(function() {
                console.log('Sistema: Mensagem de suporte não lida detetada.');
                if (typeof showToast === 'function') {
                    showToast('info', 'Nova mensagem de suporte', 'Tens uma mensagem por ler no chat de ajuda.');
                } else if (typeof showToast === 'object' && showToast.info) {
                    showToast.info('Nova mensagem de suporte', 'Tens uma mensagem por ler no chat de ajuda.');
                } else {
                    console.error('Erro: O sistema de notificações (showToast) não foi carregado corretamente.');
                }
            }, 1000); // Pequeno delay para garantir que o utilizador vê a notificação
            <?php endif; ?>

            <?php if (!empty($autenticado)): ?>
            // Controle do menu dropdown da conta do usuário
            const contaBtn = document.getElementById('contaBtn');
            const contaDropdown = document.getElementById('contaDropdown');
            
            if (contaBtn && contaDropdown) {
                contaBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const isOpen = contaDropdown.classList.contains('show');
                    
                    if (isOpen) {
                        contaDropdown.classList.remove('show');
                        contaBtn.classList.remove('active');
                    } else {
                        contaDropdown.classList.add('show');
                        contaBtn.classList.add('active');
                    }
                });
                
                contaBtn.addEventListener('mousedown', function(e) {
                    // Mousedown handler
                });
                
                // Fechar dropdown quando clicar fora
                document.addEventListener('click', function(e) {
                    if (contaDropdown.classList.contains('show') && !contaBtn.contains(e.target) && !contaDropdown.contains(e.target)) {
                        contaDropdown.classList.remove('show');
                        contaBtn.classList.remove('active');
                    }
                });
                
                // Fechar dropdown quando pressionar Escape
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && contaDropdown.classList.contains('show')) {
                        contaDropdown.classList.remove('show');
                        contaBtn.classList.remove('active');
                    }
                });
            }
            <?php endif; ?>

            // Controle do modal de opções da conta
            const opcoesBtn = document.getElementById('opcoesBtn');
            const popupOpcoes = document.getElementById('popup-opcoes');
            const fecharOpcoes = document.getElementById('fecharOpcoes');
            const deletarContaBtn = document.getElementById('deletarContaBtn');
            const popupConfirmacao = document.getElementById('popup-confirmacao');
            const cancelarExclusao = document.getElementById('cancelarExclusao');
            const confirmarExclusao = document.getElementById('confirmarExclusao');

            // Abrir popup de opções
            if (opcoesBtn && popupOpcoes) {
                opcoesBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    popupOpcoes.style.display = 'block';
                    // Fechar dropdown da conta
                    if (contaDropdown) {
                        contaDropdown.classList.remove('show');
                        contaBtn.classList.remove('active');
                    }
                });
            }

            // Fechar popup de opções
            if (fecharOpcoes && popupOpcoes) {
                fecharOpcoes.addEventListener('click', function() {
                    popupOpcoes.style.display = 'none';
                });
            }

            // Fechar popup de opções ao clicar fora
            if (popupOpcoes) {
                popupOpcoes.addEventListener('click', function(e) {
                    if (e.target === popupOpcoes) {
                        popupOpcoes.style.display = 'none';
                    }
                });
            }

            // Abrir popup de confirmação de exclusão
            if (deletarContaBtn && popupConfirmacao) {
                deletarContaBtn.addEventListener('click', function() {
                    popupOpcoes.style.display = 'none';
                    popupConfirmacao.style.display = 'block';
                });
            }

            // Cancelar exclusão
            if (cancelarExclusao && popupConfirmacao) {
                cancelarExclusao.addEventListener('click', function() {
                    popupConfirmacao.style.display = 'none';
                });
            }

            // Fechar popup de confirmação ao clicar fora
            if (popupConfirmacao) {
                popupConfirmacao.addEventListener('click', function(e) {
                    if (e.target === popupConfirmacao) {
                        popupConfirmacao.style.display = 'none';
                    }
                });
            }

            // Confirmar exclusão da conta
            if (confirmarExclusao) {
                confirmarExclusao.addEventListener('click', function() {
                    console.log('Botão de confirmar exclusão clicado');
                    
                    // Mostrar loading
                    confirmarExclusao.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deletando...';
                    confirmarExclusao.disabled = true;
                    
                    // Redirecionar diretamente para o arquivo de exclusão
                    window.location.href = 'deletar_conta.php';
                });
            } else {
                console.error('Botão de confirmar exclusão não encontrado');
            }

            // Controle do modal de personalização da interface
            const personalizacaoBtn = document.getElementById('personalizacaoBtn');
            const popupPersonalizacao = document.getElementById('popup-personalizacao');
            const fecharPersonalizacao = document.getElementById('fecharPersonalizacao');

            if (personalizacaoBtn && popupPersonalizacao) {
                personalizacaoBtn.addEventListener('click', function() {
                    // fechar opcoes e abrir personalização
                    if (popupOpcoes) popupOpcoes.style.display = 'none';
                    popupPersonalizacao.style.display = 'block';
                });
            }

            if (fecharPersonalizacao) {
                fecharPersonalizacao.addEventListener('click', function() {
                    popupPersonalizacao.style.display = 'none';
                    if (popupOpcoes) popupOpcoes.style.display = 'block';
                });
            }

            if (popupPersonalizacao) {
                popupPersonalizacao.addEventListener('click', function(e) {
                    if (e.target === popupPersonalizacao) {
                        popupPersonalizacao.style.display = 'none';
                        if (popupOpcoes) popupOpcoes.style.display = 'block';
                    }
                });
            }

            // Controle do modal de segurança (trocar palavra-passe)
            const segurancaBtn = document.getElementById('segurancaBtn');
            const popupTrocarPassword = document.getElementById('popup-trocar-password');
            const fecharTrocarPassword = document.getElementById('fecharTrocarPassword');
            const cancelTrocarPassword = document.getElementById('cancelTrocarPassword');

            if (segurancaBtn && popupTrocarPassword) {
                segurancaBtn.addEventListener('click', function() {
                    // fechar opcoes e abrir segurança
                    if (popupOpcoes) popupOpcoes.style.display = 'none';
                    popupTrocarPassword.style.display = 'block';
                });
            }

            if (fecharTrocarPassword) {
                fecharTrocarPassword.addEventListener('click', function() {
                    popupTrocarPassword.style.display = 'none';
                    if (popupOpcoes) popupOpcoes.style.display = 'block';
                });
            }

            if (cancelTrocarPassword) {
                cancelTrocarPassword.addEventListener('click', function() {
                    popupTrocarPassword.style.display = 'none';
                    if (popupOpcoes) popupOpcoes.style.display = 'block';
                });
            }

            if (popupTrocarPassword) {
                popupTrocarPassword.addEventListener('click', function(e) {
                    if (e.target === popupTrocarPassword) {
                        popupTrocarPassword.style.display = 'none';
                        if (popupOpcoes) popupOpcoes.style.display = 'block';
                    }
                });
            }

            // Controle do modal de perfil do usuário
            const verPerfilBtn = document.querySelector('.dropdown-item');
            const popupPerfil = document.getElementById('popup-perfil');
            const fecharPerfil = document.getElementById('fecharPerfil');

            // Abrir modal de perfil
            if (verPerfilBtn && popupPerfil) {
                verPerfilBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    popupPerfil.style.display = 'block';
                    // Fechar dropdown da conta
                    if (contaDropdown) {
                        contaDropdown.classList.remove('show');
                        contaBtn.classList.remove('active');
                    }
                });
            }

            // Fechar modal de perfil
            if (fecharPerfil && popupPerfil) {
                fecharPerfil.addEventListener('click', function() {
                    popupPerfil.style.display = 'none';
                });
            }

            // Fechar modal de perfil ao clicar fora
            if (popupPerfil) {
                popupPerfil.addEventListener('click', function(e) {
                    if (e.target === popupPerfil) {
                        popupPerfil.style.display = 'none';
                    }
                });
            }

            // Controle dos modais de termos de utilização e política de privacidade
            const verTermos = document.getElementById('verTermos');
            const verPrivacidade = document.getElementById('verPrivacidade');
            const popupTermos = document.getElementById('popup-termos');
            const popupPrivacidade = document.getElementById('popup-privacidade');
            const fecharTermos = document.getElementById('fecharTermos');
            const fecharPrivacidade = document.getElementById('fecharPrivacidade');

            // Abrir páginas de Termos e Privacidade: navegar para as páginas dedicadas
            if (verTermos) {
                verTermos.addEventListener('click', function() {
                    // Fecha o popup de perfil (visual) e permite que o link faça a navegação
                    if (popupPerfil) popupPerfil.style.display = 'none';
                    // A navegação para termos.html é feita pelo próprio link
                });
            }

            // Fechar modal de termos
            if (fecharTermos && popupTermos) {
                fecharTermos.addEventListener('click', function() {
                    popupTermos.style.display = 'none';
                    popupPerfil.style.display = 'block';
                });
            }

            // Fechar modal de termos ao clicar fora
            if (popupTermos) {
                popupTermos.addEventListener('click', function(e) {
                    if (e.target === popupTermos) {
                        popupTermos.style.display = 'none';
                        popupPerfil.style.display = 'block';
                    }
                });
            }

            if (verPrivacidade) {
                verPrivacidade.addEventListener('click', function() {
                    if (popupPerfil) popupPerfil.style.display = 'none';
                    // A navegação para privacidade.html é feita pelo próprio link
                });
            }

            // Fechar modal de privacidade
            if (fecharPrivacidade && popupPrivacidade) {
                fecharPrivacidade.addEventListener('click', function() {
                    popupPrivacidade.style.display = 'none';
                    popupPerfil.style.display = 'block';
                });
            }

            // Fechar modal de privacidade ao clicar fora
            if (popupPrivacidade) {
                popupPrivacidade.addEventListener('click', function(e) {
                    if (e.target === popupPrivacidade) {
                        popupPrivacidade.style.display = 'none';
                        popupPerfil.style.display = 'block';
                    }
                });
            }

            // Controle do modal de preferências de notificações
            const notificacoesBtn = document.getElementById('notificacoesBtn');
            const popupNotificacoes = document.getElementById('popup-notificacoes');
            const fecharNotificacoes = document.getElementById('fecharNotificacoes');
            const cancelarNotificacoes = document.getElementById('cancelarNotificacoes');
            const notificacoesForm = document.getElementById('notificacoesForm');

            // Abrir modal de notificações
            if (notificacoesBtn && popupNotificacoes) {
                notificacoesBtn.addEventListener('click', function() {
                    // fechar opcoes e abrir notificações
                    if (popupOpcoes) popupOpcoes.style.display = 'none';
                    popupNotificacoes.style.display = 'block';
                    // Carregar preferências salvas
                    carregarPreferenciasNotificacoes();
                });
            }

            // Fechar modal de notificações
            if (fecharNotificacoes && popupNotificacoes) {
                fecharNotificacoes.addEventListener('click', function() {
                    popupNotificacoes.style.display = 'none';
                    if (popupOpcoes) popupOpcoes.style.display = 'block';
                });
            }

            // Fechar ao clicar em cancelar
            if (cancelarNotificacoes && popupNotificacoes) {
                cancelarNotificacoes.addEventListener('click', function() {
                    popupNotificacoes.style.display = 'none';
                    if (popupOpcoes) popupOpcoes.style.display = 'block';
                });
            }

            // Fechar modal de notificações ao clicar fora
            if (popupNotificacoes) {
                popupNotificacoes.addEventListener('click', function(e) {
                    if (e.target === popupNotificacoes) {
                        popupNotificacoes.style.display = 'none';
                        if (popupOpcoes) popupOpcoes.style.display = 'block';
                    }
                });
            }

            // Enviar formulário de notificações
            if (notificacoesForm) {
                notificacoesForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    salvarPreferenciasNotificacoes();
                });
            }

            // Função para salvar preferências de notificações
            async function salvarPreferenciasNotificacoes() {
                const botaoSubmit = notificacoesForm.querySelector('button[type="submit"]');
                const botaoOriginal = botaoSubmit.innerHTML;
                botaoSubmit.disabled = true;
                botaoSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';

                try {
                    const preferencias = {
                        notif_atualizacoes: document.getElementById('notif-atualizacoes').checked,
                        notif_manutencao: document.getElementById('notif-manutencao').checked,
                        notif_novidades: document.getElementById('notif-novidades').checked,
                        notif_seguranca: document.getElementById('notif-seguranca').checked,
                        notif_performance: document.getElementById('notif-performance').checked
                    };

                    const response = await fetch('salvar_notificacoes.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(preferencias)
                    });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        if (typeof showToast === 'function') {
                            showToast('success', 'Preferências de notificações salvas com sucesso!');
                        }
                        popupNotificacoes.style.display = 'none';
                        if (popupOpcoes) popupOpcoes.style.display = 'block';
                    } else {
                        throw new Error(data.error || 'Erro ao salvar preferências');
                    }
                } catch (error) {
                    console.error('Erro ao salvar preferências de notificações:', error);
                    if (typeof showToast === 'function') {
                        showToast('error', 'Erro ao salvar preferências de notificações');
                    }
                } finally {
                    botaoSubmit.disabled = false;
                    botaoSubmit.innerHTML = botaoOriginal;
                }
            }

            // Função para carregar preferências de notificações
            async function carregarPreferenciasNotificacoes() {
                try {
                    const response = await fetch('carregar_notificacoes.php');
                    const data = await response.json();

                    if (response.ok && data.success) {
                        const prefs = data.preferencias;
                        document.getElementById('notif-atualizacoes').checked = prefs.notif_atualizacoes;
                        document.getElementById('notif-manutencao').checked = prefs.notif_manutencao;
                        document.getElementById('notif-novidades').checked = prefs.notif_novidades;
                        document.getElementById('notif-seguranca').checked = prefs.notif_seguranca;
                        document.getElementById('notif-performance').checked = prefs.notif_performance;
                    }
                } catch (error) {
                    console.error('Erro ao carregar preferências de notificações:', error);
                }
            }

            // Controle do modal de preferências de disciplinas
            const popupDisciplina = document.getElementById('popup-disciplina');
            const fecharDisciplina = document.getElementById('fecharDisciplina');
            const cancelarDisciplina = document.getElementById('cancelarDisciplina');
            const disciplinaForm = document.getElementById('disciplinaForm');

            // Fechar modal de disciplinas
            if (fecharDisciplina && popupDisciplina) {
                fecharDisciplina.addEventListener('click', function() {
                    popupDisciplina.style.display = 'none';
                    // Reabilitar scroll da página
                    document.body.classList.remove('modal-active');
                    document.documentElement.classList.remove('modal-active');
                    if (popupOpcoes) popupOpcoes.style.display = 'block';
                });
            }

            // Fechar ao clicar em cancelar
            if (cancelarDisciplina && popupDisciplina) {
                cancelarDisciplina.addEventListener('click', function() {
                    popupDisciplina.style.display = 'none';
                    // Reabilitar scroll da página
                    document.body.classList.remove('modal-active');
                    document.documentElement.classList.remove('modal-active');
                    if (popupOpcoes) popupOpcoes.style.display = 'block';
                });
            }

            // Fechar modal de disciplinas ao clicar fora
            if (popupDisciplina) {
                popupDisciplina.addEventListener('click', function(e) {
                    if (e.target === popupDisciplina) {
                        popupDisciplina.style.display = 'none';
                        // Reabilitar scroll da página
                        document.body.classList.remove('modal-active');
                        document.documentElement.classList.remove('modal-active');
                        if (popupOpcoes) popupOpcoes.style.display = 'block';
                    }
                });
            }

            // Enviar formulário de disciplinas
            if (disciplinaForm) {
                disciplinaForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    salvarDisciplinasSelecionadas();
                });

                // "Select All" para disciplinas
                const selectAllDisciplina = document.getElementById('selectAllDisciplina');
                if (selectAllDisciplina) {
                    selectAllDisciplina.addEventListener('click', function() {
                        const checkboxes = disciplinaForm.querySelectorAll('input[name="disciplina"]');
                        checkboxes.forEach(cb => {
                            cb.checked = this.checked;
                        });
                    });
                }

                // Desmarcar "Selecionar Todos" se uma disciplina for desmarcada individualmente
                disciplinaForm.addEventListener('change', function(e) {
                    if (e.target.name === 'disciplina') {
                        const checkboxes = disciplinaForm.querySelectorAll('input[name="disciplina"]');
                        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                        if(selectAllDisciplina) selectAllDisciplina.checked = allChecked;
                    }
                });
            }

            // Função para salvar disciplinas selecionadas
            async function salvarDisciplinasSelecionadas() {
                const botaoSubmit = disciplinaForm.querySelector('button[type="submit"]');
                const botaoOriginal = botaoSubmit.innerHTML;
                botaoSubmit.disabled = true;
                botaoSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';

                try {
                    const checkboxes = disciplinaForm.querySelectorAll('input[name="disciplina"]:checked');
                    const disciplina = Array.from(checkboxes).map(cb => cb.value);

                    const response = await fetch('salvar_materias.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ disciplina: disciplina })
                    });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        if (typeof showToast !== 'undefined' && showToast.success) {
                            showToast.success('Sucesso!', 'Disciplinas salvas com sucesso!');
                        }
                        popupDisciplina.style.display = 'none';
                        // Reabilitar scroll da página
                        document.body.classList.remove('modal-active');
                        if (popupOpcoes) popupOpcoes.style.display = 'block';
                    } else {
                        throw new Error(data.error || 'Erro ao salvar disciplinas');
                    }
                } catch (error) {
                    console.error('Erro ao salvar disciplinas:', error);
                    if (typeof showToast !== 'undefined' && showToast.error) {
                        showToast.error('Erro!', 'Erro ao salvar disciplinas');
                    }
                } finally {
                    botaoSubmit.disabled = false;
                    botaoSubmit.innerHTML = botaoOriginal;
                }
            }

            // Função para carregar disciplinas selecionadas
            async function carregarDisciplinasSelecionadas() {
                try {
                    const response = await fetch('carregar_materias.php');
                    const data = await response.json();

                    if (response.ok && data.success) {
                        const disciplina = data.disciplina || [];
                        const checkboxes = disciplinaForm.querySelectorAll('input[name="disciplina"]');
                        checkboxes.forEach(cb => {
                            cb.checked = disciplina.includes(cb.value);
                        });
                    }
                } catch (error) {
                    console.error('Erro ao carregar disciplinas:', error);
                }
            }


            const trocarEmailBtn = document.getElementById('trocarEmailBtn');
            const popupTrocarEmail = document.getElementById('popup-trocar-email');
            const fecharTrocarEmail = document.getElementById('fecharTrocarEmail');
            const cancelTrocarEmail = document.getElementById('cancelTrocarEmail');
            const trocarEmailForm = document.getElementById('trocarEmailForm');
            const emailAtual = document.getElementById('email_atual');

            if (trocarEmailBtn && popupTrocarEmail) {
                trocarEmailBtn.addEventListener('click', function() {
                    if (popupOpcoes) popupOpcoes.style.display = 'none';
                    popupTrocarEmail.style.display = 'block';
                    if (emailAtual) emailAtual.value = localStorage.getItem('auth_email') || '';
                });
            }

            if (fecharTrocarEmail && popupTrocarEmail) {
                fecharTrocarEmail.addEventListener('click', function() {
                    popupTrocarEmail.style.display = 'none';
                    if (popupOpcoes) popupOpcoes.style.display = 'block';
                });
            }

            if (cancelTrocarEmail && popupTrocarEmail) {
                cancelTrocarEmail.addEventListener('click', function() {
                    popupTrocarEmail.style.display = 'none';
                    if (popupOpcoes) popupOpcoes.style.display = 'block';
                });
            }

            if (popupTrocarEmail) {
                popupTrocarEmail.addEventListener('click', function(e) {
                    if (e.target === popupTrocarEmail) {
                        popupTrocarEmail.style.display = 'none';
                        if (popupOpcoes) popupOpcoes.style.display = 'block';
                    }
                });
            }

            if (trocarEmailForm) {
                trocarEmailForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const novoEmail = document.getElementById('novo_email').value;
                    const confirmarNovoEmail = document.getElementById('confirmar_novo_email').value;
                    const password = document.getElementById('password_para_email').value;
                    
                    if (!novoEmail || !confirmarNovoEmail || !password) {
                        if (typeof showToast === 'function') {
                            showToast('error', 'Por favor, preencha todos os campos');
                        }
                        return;
                    }
                    
                    if (novoEmail !== confirmarNovoEmail) {
                        if (typeof showToast === 'function') {
                            showToast('error', 'Os emails não coincidem');
                        }
                        return;
                    }
                    
                    const botaoSubmit = trocarEmailForm.querySelector('button[type="submit"]');
                    const botaoOriginal = botaoSubmit.innerHTML;
                    botaoSubmit.disabled = true;
                    botaoSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Alterando...';
                    
                    try {
                        const formData = new FormData();
                        formData.append('novo_email', novoEmail);
                        formData.append('confirmar_novo_email', confirmarNovoEmail);
                        formData.append('password_para_email', password);
                        
                        const response = await fetch('trocar_email.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.sucesso) {
                            localStorage.setItem('auth_email', novoEmail);
                            if (typeof showToast === 'function') {
                                showToast('success', 'Email alterado com sucesso!');
                            }
                            trocarEmailForm.reset();
                            popupTrocarEmail.style.display = 'none';
                            if (popupOpcoes) popupOpcoes.style.display = 'block';
                        } else {
                            if (typeof showToast === 'function') {
                                showToast('error', data.erro || 'Erro ao trocar email');
                            }
                        }
                    } catch (error) {
                        console.error('Erro ao trocar email:', error);
                        if (typeof showToast === 'function') {
                            showToast('error', 'Erro ao trocar email');
                        }
                    } finally {
                        botaoSubmit.disabled = false;
                        botaoSubmit.innerHTML = botaoOriginal;
                    }
                });
            }

            const escolherDisciplinaBtn = document.getElementById('escolherDisciplinaBtn');
            if (escolherDisciplinaBtn && popupDisciplina) {
                escolherDisciplinaBtn.addEventListener('click', function() {
                    if (popupOpcoes) popupOpcoes.style.display = 'none';
                    popupDisciplina.style.display = 'block';
                    // Prevenir scroll da página enquanto o modal está aberto
                    document.body.classList.add('modal-active');
                    document.documentElement.classList.add('modal-active');
                    carregarDisciplinasSelecionadas();
                });
            }

        });
    </script>


<?php if ($ultimaAtualizacaoPublica): ?>
<script>
// Script para controlar a exibição do modal de anúncios de atualização
window.__AULABOT_UPDATE__ = <?php echo json_encode([
    'id' => (int)$ultimaAtualizacaoPublica['id_update'],
    'versao' => $ultimaAtualizacaoPublica['versao'],
    'data' => $ultimaAtualizacaoPublica['data_update'],
    'mostrar' => $mostrarAnuncio
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('updateAnnouncementModal');
    const closeBtn = document.getElementById('closeUpdateAnnouncement');
    const confirmBtn = document.getElementById('ackUpdateAnnouncement');
    const updateData = window.__AULABOT_UPDATE__;
    console.log('DEBUG ANUNCIO:', updateData);
    if (!modal || !updateData) {
        console.log('Modal ou updateData não encontrado:', {modal: !!modal, updateData: !!updateData});
        return;
    }
    const signature = `${updateData.id}-${updateData.versao}-${updateData.data}`;
    const storageKey = 'aulabot_update_seen';
    let storedSignature = null;
    try {
        storedSignature = localStorage.getItem(storageKey);
    } catch (e) {
        storedSignature = null;
    }
    if (updateData.mostrar) {
        modal.style.display = 'block';
        document.body.classList.add('modal-active');
    }
    function closeAnnouncement() {
        modal.style.display = 'none';
        document.body.classList.remove('modal-active');
        try {
            localStorage.setItem(storageKey, signature);
        } catch (e) {}
        
        fetch('marcar_anuncio_visto.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id_update=' + encodeURIComponent(updateData.id)
        }).catch(function(error) {
            console.error('Erro ao marcar anúncio como visto:', error);
        });
    }
    if (closeBtn) closeBtn.addEventListener('click', closeAnnouncement);
    if (confirmBtn) confirmBtn.addEventListener('click', closeAnnouncement);
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeAnnouncement();
        }
    });
});
</script>
<?php endif; ?>

<script>
// =============================
// GERENCIAMENTO DE CHATS
// =============================

// Renomear chat
function renomearChat(chatId) {
    // Pegar o título atual
    const titleElement = document.querySelector(`[data-id="${chatId}"] .chat-titulo`);
    const tituloAtual = titleElement ? titleElement.textContent : '';
    
    // Criar modal de renomeação
    const modal = document.createElement('div');
    modal.className = 'rename-modal';
    modal.innerHTML = `
        <div class="rename-modal-content">
            <div class="rename-modal-header">✎ Renomear Chat</div>
            <input 
                type="text" 
                class="rename-modal-input" 
                placeholder="Digite o novo nome..." 
                value="${tituloAtual}"
                id="renameInput"
                maxlength="255"
            />
            <div class="rename-modal-actions">
                <button class="rename-modal-btn rename-modal-btn-cancel" onclick="this.closest('.rename-modal').remove()">Cancelar</button>
                <button class="rename-modal-btn rename-modal-btn-confirm" id="confirmRenameBtn">Confirmar</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Focar no input e selecionar texto
    const input = modal.querySelector('#renameInput');
    input.focus();
    input.select();
    
    // Fechar ao clicar fora
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    });
    
    // Confirmar renomeação
    const confirmBtn = modal.querySelector('#confirmRenameBtn');
    confirmBtn.addEventListener('click', function() {
        const novoNome = input.value.trim();
        
        if (novoNome === '') {
            if (typeof showToast === 'function') {
                showToast('error', 'O nome não pode estar vazio');
            }
            return;
        }
        
        // Fazer requisição
        fetch('api_renomear_chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_chat: chatId,
                novo_nome: novoNome
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Atualizar o título na interface
                const item = document.querySelector(`[data-id="${chatId}"] .chat-titulo`);
                if (item) {
                    item.textContent = novoNome;
                }
                if (typeof showToast === 'function') {
                    showToast('success', 'Chat renomeado com sucesso! ✓');
                }
            } else {
                if (typeof showToast === 'function') {
                    showToast('error', 'Erro: ' + (data.error || 'Desconhecido'));
                }
            }
            modal.remove();
        })
        .catch(err => {
            console.error('Erro na requisição:', err);
            if (typeof showToast === 'function') {
                showToast('error', 'Erro de rede ao renomear chat');
            }
            modal.remove();
        });
    });
    
    // Permitir Enter para confirmar
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            confirmBtn.click();
        } else if (e.key === 'Escape') {
            modal.remove();
        }
    });
}

// Eliminar chat
function eliminarChat(chatId) {
    // Criar modal de confirmação
    const modal = document.createElement('div');
    modal.className = 'delete-confirm-modal';
    modal.innerHTML = `
        <div class="delete-confirm-content">
            <div class="delete-confirm-icon">🗑️</div>
            <div class="delete-confirm-header">Eliminar Chat?</div>
            <div class="delete-confirm-text">
                Esta ação é permanente e não pode ser desfeita. Todos os dados desta conversa serão eliminados.
            </div>
            <div class="delete-confirm-actions">
                <button class="delete-confirm-btn delete-confirm-btn-cancel" onclick="this.closest('.delete-confirm-modal').remove()">Cancelar</button>
                <button class="delete-confirm-btn delete-confirm-btn-delete" id="confirmDeleteBtn">Eliminar</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Fechar ao clicar fora
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    });
    
    // Confirmar eliminação
    const confirmBtn = modal.querySelector('#confirmDeleteBtn');
    confirmBtn.addEventListener('click', function() {
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Eliminando...';
        
        fetch('api_eliminar_chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_chat: chatId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Remover o item da lista com animação
                const item = document.querySelector(`[data-id="${chatId}"]`);
                if (item) {
                    item.style.animation = 'fadeOut 0.3s ease';
                    setTimeout(() => item.remove(), 300);
                }
                if (typeof showToast === 'function') {
                    showToast('success', 'Chat eliminado com sucesso! ✓');
                }
                // Abrir novo chat
                if (typeof novaConversa === 'function') {
                    novaConversa();
                }
            } else {
                if (typeof showToast === 'function') {
                    showToast('error', 'Erro: ' + (data.error || 'Desconhecido'));
                }
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Eliminar';
            }
            modal.remove();
        })
        .catch(err => {
            console.error('Erro na requisição:', err);
            if (typeof showToast === 'function') {
                showToast('error', 'Erro de rede ao eliminar chat');
            }
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Eliminar';
        });
    });
    
    // Permitir Escape para cancelar
    document.addEventListener('keydown', function handler(e) {
        if (e.key === 'Escape' && modal.parentNode) {
            modal.remove();
            document.removeEventListener('keydown', handler);
        }
    });
}
</script>
    <script src="conta/criarconta.js"></script>
    <script>
        // Helper: fetch JSON safely, returns fallback on errors and logs useful diagnostics
        async function safeFetchJson(url, options = {}, fallback = {}) {
            try {
                const res = await fetch(url, options);
                if (!res.ok) {
                    const txt = await res.text().catch(() => '');
                    console.error(url + ' returned HTTP ' + res.status + ' — body:', txt);
                    return Object.assign({}, fallback, { _error: true, _status: res.status, _text: txt });
                }
                try {
                    return await res.json();
                } catch (e) {
                    const txt = await res.text().catch(() => '');
                    console.error(url + ' returned invalid JSON:', e, 'body:', txt);
                    return Object.assign({}, fallback, { _error: true, _status: res.status, _text: txt });
                }
            } catch (e) {
                console.error('Network error fetching ' + url + ':', e);
                return Object.assign({}, fallback, { _error: true, _exception: String(e) });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const ajudaLink = document.querySelector('a[href="aba-ajuda/ajuda.php"]');

            if (ajudaLink) {
                setInterval(async function() {
                    const data = await safeFetchJson('api_check_message.php', { cache: 'no-store' }, { mensagem: 0 });
                    const existingBadge = ajudaLink.querySelector('.notification-badge');
                    if (data.mensagem == 1) {
                        if (!existingBadge) {
                            const badge = document.createElement('span');
                            badge.classList.add('notification-badge');
                            ajudaLink.style.position = 'relative';
                            ajudaLink.appendChild(badge);
                        }
                    } else {
                        if (existingBadge) {
                            existingBadge.remove();
                        }
                    }
                    if (data._error) {
                        // Opcional: podes mostrar um pequeno aviso visual, por agora apenas log
                        console.warn('safeFetchJson reported an error while checking messages', data);
                    }
                }, 5000);
            }
        });
    </script>
    
</body>
</html>
