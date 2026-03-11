<?php

// Verificar se o utilizador é administrador
// Inclui o script de verificação de autenticação
require_once('../auth_check.php');
// Executa a função que garante que apenas admins acedem a esta página
verificarPermissoesAdmin();

// Obter dados do utilizador
// Recupera informações do administrador logado (nome, email, etc.)
$dadosUtilizador = obterDadosUtilizador();

// Incluir configuração da base de dados
// Estabelece a conexão com o MySQL
require_once('../ligarbd.php');

// --- AJAX HANDLER: Marcar Feedback como Lido ---
// Este bloco processa pedidos AJAX para atualizar o estado de um feedback sem recarregar a página
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'marcar_lido') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id > 0) {
        // Atualiza a base de dados marcando o feedback como lido ('sim')
        $stmt = mysqli_prepare($con, "UPDATE feedback SET lido = 'sim' WHERE id_feedback = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $success = mysqli_stmt_execute($stmt);
        
        // Recalcular total de não lidos
        // Necessário para atualizar o contador na interface imediatamente
        $res = mysqli_query($con, "SELECT COUNT(*) as total FROM feedback WHERE (lido = 'nao' OR lido IS NULL OR lido = '')");
        $row = mysqli_fetch_assoc($res);
        $total = $row ? $row['total'] : 0;
        
        echo json_encode(['success' => $success, 'total' => $total]);
        exit;
    }
}

// Obter estatísticas do sistema
$estatisticas = obterEstatisticasSistema($con);
$utilizadores = obterListaUtilizadores($con);
$feedbacks = obterFeedbacks($con);

// Garantir que $feedbacks é um array válido
// Previne erros caso a função retorne null ou falhe
if (!is_array($feedbacks)) {
    $feedbacks = ['lista' => [], 'total' => 0];
}

// Debug: verificar se os feedbacks foram carregados
// Regista no log de erros do servidor para fins de diagnóstico
error_log('Dashboard - Feedbacks carregados: ' . json_encode($feedbacks));

$ultimaAtualizacao = null;
if (isset($con)) {
    // Busca a atualização mais recente do sistema para exibir no widget de novidades
    $resultadoAtualizacao = @mysqli_query($con, "SELECT id_update, nome, versao, descricao, data_update FROM updates ORDER BY data_update DESC LIMIT 1");
    if ($resultadoAtualizacao && mysqli_num_rows($resultadoAtualizacao) > 0) {
        $ultimaAtualizacao = mysqli_fetch_assoc($resultadoAtualizacao);
    }
}

/**
 * Função para obter estatísticas do sistema
 * Retorna um array com contagens de utilizadores, admins e registos recentes
 */
function obterEstatisticasSistema($conexao) {
    $stats = [];
    
    // Total de utilizadores
    $result = mysqli_query($conexao, "SELECT COUNT(*) as total FROM utilizador");
    $stats['total_utilizadores'] = mysqli_fetch_assoc($result)['total'];
    
    // Total de administradores
    $result = mysqli_query($conexao, "SELECT COUNT(*) as total FROM utilizador WHERE tipo = 'admin'");
    $stats['total_admins'] = mysqli_fetch_assoc($result)['total'];
    
    // Utilizadores registados hoje
    $result = mysqli_query($conexao, "SELECT COUNT(*) as total FROM utilizador WHERE DATE(data_criacao) = CURDATE()");
    $stats['novos_hoje'] = mysqli_fetch_assoc($result)['total'];
    
    // Utilizadores registados esta semana
    $result = mysqli_query($conexao, "SELECT COUNT(*) as total FROM utilizador WHERE data_criacao >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['novos_semana'] = mysqli_fetch_assoc($result)['total'];
    
    return $stats;
}

/**
 * Função para obter lista de utilizadores
 * Retorna os 10 utilizadores mais recentes para a tabela principal
 */
function obterListaUtilizadores($conexao) {
    $utilizadores = [];
    
    $result = mysqli_query($conexao, "SELECT id_utilizador, nome, email, tipo, data_criacao, bloqueado, motivo_bloqueio FROM utilizador ORDER BY data_criacao DESC LIMIT 10");
    
    while ($row = mysqli_fetch_assoc($result)) {
        $utilizadores[] = $row;
    }
    
    return $utilizadores;
}

/**
 * Obter feedbacks recentes
 * Retorna feedbacks não lidos e o contador total
 */
function obterFeedbacks($conexao) {
    global $con;
    
    // Estrutura para retornar tanto a lista quanto o total
    $retorno = [
        'lista' => [],
        'total' => 0
    ];
    
    // Usar a conexão global se a passada for falsa
    if (!$conexao) {
        $conexao = $con;
    }
    
    if (!$conexao) {
        error_log('obterFeedbacks: conexão à base de dados falhou');
        return $retorno;
    }
    
    // Contar total de feedbacks não lidos
    $sqlTotal = "SELECT COUNT(*) as total FROM feedback WHERE (lido = 'nao' OR lido IS NULL OR lido = '')";
    $resultTotal = mysqli_query($conexao, $sqlTotal);
    if ($resultTotal) {
        $row = mysqli_fetch_assoc($resultTotal);
        $retorno['total'] = $row ? $row['total'] : 0;
    } else {
        error_log('obterFeedbacks: Erro ao contar feedbacks: ' . mysqli_error($conexao));
    }
    
    // Buscar os 8 feedbacks mais recentes
    $sql = "SELECT id_feedback, nome, email, rating, gostou, melhoria, autorizacao, data_feedback, lido 
            FROM feedback 
            WHERE (lido = 'nao' OR lido IS NULL OR lido = '') 
            ORDER BY data_feedback DESC 
            LIMIT 8";
    
    $result = mysqli_query($conexao, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $retorno['lista'][] = $row;
        }
        error_log('obterFeedbacks: ' . count($retorno['lista']) . ' feedbacks carregados');
    } else {
        error_log('obterFeedbacks: Erro ao buscar feedbacks: ' . mysqli_error($conexao));
    }
    
    return $retorno;
}

/**
 * Função para obter os registos de atividade com paginação
 * Permite navegar pelo histórico de logins/logouts
 */
function obterRegistosPaginados($conexao, $pagina_atual, $items_por_pagina = 8) {
    // 1. Obter o número total de registos
    $total_result = mysqli_query($conexao, "SELECT COUNT(*) as total FROM registo");
    $total_registos = mysqli_fetch_assoc($total_result)['total'] ?? 0;

    // 2. Calcular o offset
    $offset = ($pagina_atual - 1) * $items_por_pagina;

    // 3. Obter os registos para a página atual
    $registos = [];
    $sql = "SELECT r.id_registo, r.reg, r.data, u.nome, u.email 
            FROM registo r 
            LEFT JOIN utilizador u ON r.id_utilizador = u.id_utilizador 
            ORDER BY r.data DESC 
            LIMIT ? OFFSET ?";
    
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $items_por_pagina, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $registos[] = $row;
    }
    mysqli_stmt_close($stmt);

    // 4. Retornar dados e contagem total
    return [
        'registos' => $registos,
        'total' => $total_registos
    ];
}

// --- LÓGICA DE PAGINAÇÃO ---
// Obter dados de paginação para os registos
$pagina_atual_registos = isset($_GET['page_registos']) ? (int)$_GET['page_registos'] : 1;
$items_por_pagina_registos = 8;
$dados_registos = obterRegistosPaginados($con, $pagina_atual_registos, $items_por_pagina_registos);
$registos = $dados_registos['registos'];
$total_registos = $dados_registos['total'];
$total_paginas_registos = ceil($total_registos / $items_por_pagina_registos);

?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Dashboard Administrativo - AulaBot">
    <title>Dashboard Admin | AulaBot</title>
    
    <!-- Folhas de estilo -->
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
    
    <!-- Ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .btn.disabled {
            pointer-events: none;
            opacity: 0.5;
        }
        /* CORREÇÃO CRÍTICA DE VISIBILIDADE */
        .feedbacks-section {
            isolation: isolate; /* Cria novo contexto de empilhamento */
        }
        .feedbacks-container {
            position: relative;
            z-index: 100; /* Garante que fica acima do fundo branco/vidro */
            min-height: 100px;
        }
        .feedback-card {
            opacity: 1 !important; /* Força visibilidade ignorando animações */
            position: relative;
            z-index: 101;
            background: #fff; /* Garante fundo sólido */
        }
    </style>
</head>
<body>
    <!-- CONTEÚDO PRINCIPAL -->
    <div class="admin-main">
        
        <!-- TOP BAR -->
        <header class="admin-topbar">
                <div class="topbar-left">
                    <h1>Dashboard Administrativo</h1>
                </div>

                <div class="topbar-right">
                    <div class="user-menu">
                        <div class="user-info">
                            <span class="user-name">Olá, <?php echo htmlspecialchars($dadosUtilizador['nome']); ?></span>
                            <span class="user-role">Administrador</span>
                        </div>
                        <div class="user-avatar" id="userAvatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="user-dropdown" id="userDropdown" aria-hidden="true">
                            <a href="../index.php" class="dropdown-item">Voltar ao Início</a>
                            <a href="../logout.php<?php echo isset($_SESSION['id_utilizador']) ? '?id=' . urlencode($_SESSION['id_utilizador']) : ''; ?>" class="dropdown-item logout-item">Terminar Sessão</a>
                        </div>
                    </div>
                </div>
        </header>

        <!-- CONTEÚDO -->
        <div class="admin-content">
            <!-- CARDS DE ESTATÍSTICAS -->
            <!-- Exibe métricas principais do sistema (Users, Admins, Novos Registos) -->
            <section class="stats-section">
                <div class="section-header">
                    <h2><i class="fas fa-chart-line"></i> Visão Geral do Sistema</h2>
                    <div class="controls">
                        <div class="site-block-control">
                            <?php
                            // Verificar estado atual do bloqueio do site
                            $query = "SELECT site_bloqueado FROM configuracoes_site WHERE id = 1";
                            $result = mysqli_query($con, $query);
                            $bloqueado = false;
                            if (mysqli_num_rows($result) > 0) {
                                $row = mysqli_fetch_assoc($result);
                                $bloqueado = (bool)$row['site_bloqueado'];
                            }
                            ?>
                            <button id="toggleSiteBlock" class="btn <?php echo $bloqueado ? 'btn-danger' : 'btn-success'; ?>">
                                <i class="fas <?php echo $bloqueado ? 'fa-lock' : 'fa-lock-open'; ?>"></i>
                                <span><?php echo $bloqueado ? 'Desbloquear Site' : 'Bloquear Site'; ?></span>
                            </button>
                        </div>
                        <div class="time-filter">
                            <select>
                                <option>Hoje</option>
                                <option>Esta Semana</option>
                                <option>Este Mês</option>
                                <option>Este Ano</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card primary animate-card">
                        <div class="stat-icon pulse">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-header">
                                <h3 class="counter"><?php echo $estatisticas['total_utilizadores']; ?></h3>
                                <span class="stat-label">Total de Utilizadores</span>
                            </div>
                            <?php if ($estatisticas['total_utilizadores'] > 0): ?>
                            <div class="stat-trend positive">
                                <i class="fas fa-chart-line"></i>
                                <span>Sistema em Crescimento</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="stat-card success animate-card">
                        <div class="stat-icon shield-pulse">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-header">
                                <h3 class="counter"><?php echo $estatisticas['total_admins']; ?></h3>
                                <span class="stat-label">Administradores</span>
                            </div>
                            <div class="stat-badge">
                                <i class="fas fa-lock"></i>
                                <span>Acesso Privilegiado</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card warning animate-card">
                        <div class="stat-icon bounce">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-header">
                                <h3 class="counter highlight"><?php echo $estatisticas['novos_hoje']; ?></h3>
                                <span class="stat-label">Novos Hoje</span>
                            </div>
                            <?php if ($estatisticas['novos_hoje'] > 0): ?>
                            <div class="stat-trend positive">
                                <i class="fas fa-clock"></i>
                                <span>Última atualização: Agora</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="stat-card info animate-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-header">
                                <div class="stat-value-group">
                                    <h3 class="counter"><?php echo $estatisticas['novos_semana']; ?></h3>
                                    <span class="stat-period">últimos 7 dias</span>
                                </div>
                                <span class="stat-label">Novos Registros</span>
                            </div>
                            <div class="stat-details">
                                <div class="stat-progress">
                                    <div class="progress-bar">
                                        <?php 
                                        $maxDias = 7;
                                        $percentagem = ($estatisticas['novos_semana'] / $maxDias) * 100;
                                        ?>
                                        <div class="progress-fill" style="width: <?php echo min(100, $percentagem); ?>%"></div>
                                    </div>
                                    <div class="progress-text">
                                        <span>
                                            <i class="fas fa-user-plus"></i>
                                            <?php echo number_format($estatisticas['novos_semana'] / 7, 1); ?> por dia
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECÇÃO DE ATUALIZAÇÕES DO SISTEMA -->
            <section class="updates-section glass-effect">
                <div class="section-header modern">
                    <div class="header-left">
                        <h2><i class="fas fa-bullhorn pulse-icon"></i> Anúncios de Atualização</h2>
                        <?php if ($ultimaAtualizacao): ?>
                            <p class="updates-subtitle">Última publicação em <?php echo date('d/m/Y \à\s H:i', strtotime($ultimaAtualizacao['data_update'])); ?></p>
                        <?php else: ?>
                            <p class="updates-subtitle">Ainda não existem anúncios publicados.</p>
                        <?php endif; ?>
                    </div>
                    <div class="header-right">
                        <button class="btn btn-primary modern-button" onclick="openUpdateModal()">
                            <i class="fas fa-plus-circle"></i>
                            <span>Novo Anúncio</span>
                            <div class="button-effect"></div>
                        </button>
                    </div>
                </div>
                <?php if ($ultimaAtualizacao): ?>
                <div class="update-card">
                    <div class="update-card-header">
                        <div class="update-card-title">
                            <span class="update-icon"><i class="fas fa-bolt"></i></span>
                            <div>
                                <h3><?php echo htmlspecialchars($ultimaAtualizacao['nome']); ?></h3>
                                <span class="update-version">Versão <?php echo htmlspecialchars($ultimaAtualizacao['versao']); ?></span>
                            </div>
                        </div>
                        <span class="update-date"><i class="far fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($ultimaAtualizacao['data_update'])); ?></span>
                    </div>
                    <div class="update-card-body">
                        <p><?php echo nl2br(htmlspecialchars($ultimaAtualizacao['descricao'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </section>

            <!-- CHAT DE AJUDA -->
            <!-- Interface para administradores responderem a pedidos de suporte -->
            <section class="chat-ajuda-admin glass-effect">
                <div class="section-header modern">
                    <div class="header-left">
                        <h2><i class="fas fa-comments pulse-icon"></i> Chat de Ajuda</h2>
                        <p class="updates-subtitle">Converse com utilizadores que pedem apoio</p>
                    </div>
                </div>
                <div class="chat-admin-container">
                    <div class="conversas-list" id="conversasList">
                        <div class="loading-spinner">
                            <i class="fas fa-spinner fa-spin"></i> Carregando conversas...
                        </div>
                    </div>
                    <div class="chat-area-admin" id="chatAreaAdmin">
                        <div class="chat-welcome">
                            <i class="fas fa-comments"></i>
                            <p>Selecione uma conversa para responder</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- TABELA DE UTILIZADORES -->
            <!-- Listagem e gestão de utilizadores (Editar, Bloquear, Eliminar) -->
            <section class="users-section glass-effect">
                    <div class="section-header modern">
                        <div class="header-left">
                            <h2><i class="fas fa-users-cog pulse-icon"></i> Utilizadores Recentes</h2>
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="userSearch" placeholder="Procurar utilizador..." 
                                       onkeyup="filterTable(this.value)">
                            </div>
                        </div>
                        <div class="header-right">
                            <button class="btn btn-primary modern-button" onclick="showAddUserModal()">
                                <i class="fas fa-plus-circle"></i>
                                <span>Novo Utilizador</span>
                                <div class="button-effect"></div>
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-responsive modern-table">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>
                                        <div class="table-header modern-header">
                                            <span>ID</span>
                                            <i class="fas fa-sort"></i>
                                        </div>
                                    </th>
                                    <th>
                                        <div class="table-header modern-header">
                                            <span>Nome</span>
                                            <i class="fas fa-sort"></i>
                                        </div>
                                    </th>
                                    <th>
                                        <div class="table-header modern-header">
                                            <span>Email</span>
                                            <i class="fas fa-sort"></i>
                                        </div>
                                    </th>
                                    <th>
                                        <div class="table-header modern-header">
                                            <span>Tipo</span>
                                            <i class="fas fa-sort"></i>
                                        </div>
                                    </th>
                                    <th>
                                        <div class="table-header modern-header">
                                            <span>Data de Registo</span>
                                            <i class="fas fa-sort"></i>
                                        </div>
                                    </th>
                                    <th>
                                        <div class="table-header modern-header">
                                            <span>Estado</span>
                                            <i class="fas fa-sort"></i>
                                        </div>
                                    </th>
                                    <th>
                                        <div class="table-header modern-header">
                                            <span>Ações</span>
                                        </div>
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($utilizadores as $utilizador): ?>
                                <tr>
                                    <td>#<?php echo str_pad($utilizador['id_utilizador'], 3, '0', STR_PAD_LEFT); ?></td>
                                    <td>
                                        <span class="user-name"><?php echo htmlspecialchars($utilizador['nome']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($utilizador['email']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $utilizador['tipo'] === 'admin' ? 'badge-admin' : 'badge-user'; ?>">
                                            <?php echo ucfirst($utilizador['tipo']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($utilizador['data_criacao'])); ?></td>
                                    <td>
                                        <?php if ($utilizador['bloqueado']): ?>
                                            <span class="badge badge-danger" title="<?php echo htmlspecialchars($utilizador['motivo_bloqueio']); ?>">
                                                <i class="fas fa-ban"></i> Bloqueado
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check-circle"></i> Ativo
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn-icon btn-edit" 
                                                    onclick="openEditUserModal(<?php echo $utilizador['id_utilizador']; ?>, '<?php echo htmlspecialchars(addslashes($utilizador['nome'])); ?>', '<?php echo htmlspecialchars(addslashes($utilizador['email'])); ?>', '<?php echo $utilizador['tipo']; ?>')" 
                                                    title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($utilizador['tipo'] === 'admin'): ?>
                                                <span class="btn-icon btn-disabled" title="Não pode bloquear um admin" style="opacity: 0.5; cursor: not-allowed;">
                                                    <i class="fas fa-ban"></i>
                                                </span>
                                            <?php elseif ($utilizador['bloqueado']): ?>
                                                <button type="button" class="btn-icon btn-warning" 
                                                        onclick="desbloquearUtilizador(<?php echo $utilizador['id_utilizador']; ?>, '<?php echo htmlspecialchars($utilizador['nome']); ?>')" 
                                                        title="Desbloquear">
                                                    <i class="fas fa-lock-open"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn-icon btn-warning" 
                                                        onclick="abrirModalBloqueio(<?php echo $utilizador['id_utilizador']; ?>, '<?php echo htmlspecialchars($utilizador['nome']); ?>')" 
                                                        title="Bloquear">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($utilizador['tipo'] === 'admin'): ?>
                                                <span class="btn-icon btn-delete" style="opacity: 0.5; cursor: not-allowed;" title="Não pode eliminar um admin">
                                                    <i class="fas fa-trash"></i>
                                                </span>
                                            <?php else: ?>
                                                <button type="button" class="btn-icon btn-delete" onclick="deleteUser(<?php echo $utilizador['id_utilizador']; ?>, event)" title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="table-footer">
                        <div class="table-info">
                            Mostrando <?php echo count($utilizadores); ?> de <?php echo $estatisticas['total_utilizadores']; ?> utilizadores
                        </div>
                        <div class="table-pagination">
                            <button class="btn btn-outline" disabled>
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <span class="pagination-info">Página 1 de 1</span>
                            <button class="btn btn-outline">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </section>

            <!-- TABELA DE REGISTOS DE ATIVIDADE -->
            <!-- Log de logins e logouts do sistema -->
            <section id="registos-atividade" class="activity-section glass-effect">
                    <div class="section-header modern">
                        <div class="header-left">
                            <h2><i class="fas fa-history pulse-icon"></i> Registos de Atividade Recentes</h2>
                        </div>
                    </div>
                    
                    <div class="table-responsive modern-table">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>ID Registo</th>
                                    <th>Ação</th>
                                    <th>Data e Hora</th>
                                    <th>Utilizador</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($registos)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center;">Nenhum registo encontrado.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($registos as $registo): ?>
                                    <tr>
                                        <td>#<?php echo htmlspecialchars($registo['id_registo']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $registo['reg'] === 'login' ? 'badge-success' : ($registo['reg'] === 'logout' ? 'badge-warning' : 'badge-secondary'); ?>" style="display: inline-flex; align-items: center; gap: 5px;">
                                                <?php if ($registo['reg'] === 'login'): ?>
                                                    <i class="fas fa-sign-in-alt"></i>
                                                <?php elseif ($registo['reg'] === 'logout'): ?>
                                                    <i class="fas fa-sign-out-alt"></i>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars(ucfirst($registo['reg'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i:s', strtotime($registo['data'])); ?></td>
                                        <td><?php echo htmlspecialchars($registo['nome'] ?? 'Utilizador Anónimo'); ?></td>
                                        <td><?php echo htmlspecialchars($registo['email'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="table-footer">
                        <div class="table-info">
                            Mostrando <?php echo count($registos); ?> de <?php echo $total_registos; ?> registos
                        </div>
                        <?php if ($total_paginas_registos > 1): ?>
                        <div class="table-pagination">
                            <a href="?page_registos=<?php echo max(1, $pagina_atual_registos - 1); ?>#registos-atividade" class="btn btn-outline <?php if($pagina_atual_registos <= 1){ echo 'disabled'; } ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <span class="pagination-info">Página <?php echo $pagina_atual_registos; ?> de <?php echo $total_paginas_registos; ?></span>
                            <a href="?page_registos=<?php echo min($total_paginas_registos, $pagina_atual_registos + 1); ?>#registos-atividade" class="btn btn-outline <?php if($pagina_atual_registos >= $total_paginas_registos){ echo 'disabled'; } ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
            </section>
            
            <!-- FEEDBACKS RECENTES -->
            <!-- Mostra opiniões dos utilizadores que ainda não foram tratadas -->
            <section id="feedbacks-section" class="feedbacks-section glass-effect">
                <div class="section-header modern">
                    <div class="header-left" style="display:flex; align-items:center; gap:12px;">
                        <h2 style="display:flex; align-items:center; gap:8px;"><i class="fas fa-comment-dots pulse-icon"></i> Feedbacks Recentes</h2>
                        <span id="feedbackCounter" class="feedback-counter"><?php echo $feedbacks['total']; ?> feedbacks por ler</span>
                        <!-- Botão para atualizar (mostrar mais feedbacks) -->
                        <button type="button" id="refreshFeedbacksBtn" class="btn btn-outline" title="Atualizar feedbacks" onclick="window.location.hash = 'feedbacks-section'; window.location.reload();" style="margin-left:8px;">
                            <i class="fas fa-sync"></i>
                        </button>
                    </div>
                </div>
                <div class="feedbacks-container">
                    <!-- Debug: verify feedbacks variable -->
                    <!-- Feedbacks data: <?php echo json_encode($feedbacks); ?> -->
                    <?php if (empty($feedbacks['lista'])): ?>
                        <div class="no-feedback">
                            <i class="fas fa-inbox"></i>
                            <p>Nenhum feedback encontrado</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($feedbacks['lista'] as $index => $f): ?>
                            <div class="feedback-card animate-feedback" style="animation-delay: <?php echo $index * 0.1; ?>s">
                                <div class="feedback-header">
                                    <div class="feedback-user">
                                        <div class="user-avatar gradient-bg">
                                            <?php echo strtoupper(substr($f['nome'],0,1)); ?>
                                        </div>
                                        <div class="user-info">
                                            <h3><?php echo htmlspecialchars($f['nome']); ?></h3>
                                            <span class="feedback-date">
                                                <i class="far fa-clock"></i>
                                                <?php echo date('d/m/Y H:i', strtotime($f['data_feedback'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="rating-badge rating-<?php echo intval($f['rating']); ?>">
                                        <?php
                                            $rating = intval($f['rating']);
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo '<i class="fa' . ($i <= $rating ? 's' : 'r') . ' fa-star"></i>';
                                            }
                                        ?>
                                        <span class="rating-text"><?php echo $rating; ?>/5</span>
                                    </div>
                                </div>
                                
                                <div class="feedback-content" onclick="openFeedbackModal(<?php echo $f['id_feedback']; ?>, '<?php echo htmlspecialchars(addslashes($f['nome'])); ?>', '<?php echo htmlspecialchars(addslashes($f['gostou'])); ?>', '<?php echo htmlspecialchars(addslashes($f['melhoria'])); ?>', '<?php echo date('d/m/Y H:i', strtotime($f['data_feedback'])); ?>', <?php echo intval($f['rating']); ?>)">
                                    <?php if (!empty($f['gostou'])): ?>
                                        <div class="feedback-text positive">
                                            <i class="fas fa-thumbs-up"></i>
                                            <p><?php echo htmlspecialchars($f['gostou']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($f['melhoria'])): ?>
                                        <div class="feedback-text suggestion">
                                            <i class="fas fa-lightbulb"></i>
                                            <p><?php echo htmlspecialchars($f['melhoria']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="feedback-actions">
                                    <?php if ($f['lido'] === 'nao'): ?>
                                    <button type="button" class="btn btn-success btn-sm marcar-lido" data-id="<?php echo $f['id_feedback']; ?>">
                                        <i class="fas fa-check"></i> Marcar como Lido
                                    </button>
                                    <?php else: ?>
                                    <span class="badge badge-secondary" style="opacity: 0.8;">
                                        <i class="fas fa-check-double"></i> Lido
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>

    <!-- MODAL: CRIAR NOVO ANÚNCIO/ATUALIZAÇÃO -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-bullhorn"></i> Novo Anúncio</h3>
                <button class="close-btn" onclick="closeUpdateModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="createUpdateForm" class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="updateName">Título da atualização</label>
                        <input type="text" id="updateName" name="nome" maxlength="120" required>
                    </div>
                    <div class="form-group">
                        <label for="updateVersion">Versão</label>
                        <input type="text" id="updateVersion" name="versao" maxlength="40" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="updateTheme">Tema do Anúncio</label>
                        <select id="updateTheme" name="tema" required>
                            <option value="atualizacoes">Atualizações Gerais</option>
                            <option value="manutencao">Manutenção e Correções</option>
                            <option value="novidades">Novidades e Eventos</option>
                            <option value="seguranca">Alertas de Segurança</option>
                            <option value="performance">Melhorias de Performance</option>
                        </select>
                    </div>
                </div>
                <div class="form-group textarea-group">
                    <label for="updateDescription">Descrição</label>
                    <textarea id="updateDescription" name="descricao" maxlength="1000" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeUpdateModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Publicar Anúncio
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DE CONFIRMAÇÃO DE ELIMINAÇÃO -->
    <!-- Pede confirmação antes de apagar um utilizador permanentemente -->
    <div id="deleteConfirmModal" class="modal">
        <div class="modal-content delete-confirm-modal">
            <div class="modal-header">
                <div class="modal-icon danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3>Confirmar Eliminação</h3>
            </div>
            
            <div class="modal-body">
                <p class="confirm-message">
                    Tem a certeza que deseja eliminar o utilizador 
                    <strong id="deleteUserName"></strong>?
                </p>
                <p class="confirm-warning">
                    <i class="fas fa-info-circle"></i>
                    Esta ação não pode ser desfeita e todos os dados do utilizador serão permanentemente removidos.
                </p>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeDeleteConfirmModal()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteUser()">
                    <i class="fas fa-trash"></i> Eliminar Utilizador
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL ADICIONAR UTILIZADOR -->
    <!-- Formulário para criar novos utilizadores manualmente -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Adicionar Novo Utilizador</h3>
                <button class="close-btn" onclick="closeAddUserModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="addUserForm" class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="newUserName">Nome</label>
                        <input type="text" id="newUserName" name="nome" placeholder="Introduza o nome" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="newUserEmail">Email</label>
                        <input type="email" id="newUserEmail" name="email" placeholder="exemplo@email.com" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="newUserPassword">Palavra-passe</label>
                        <input type="password" id="newUserPassword" name="password" placeholder="Mínimo 6 caracteres" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="newUserType">Tipo de Utilizador</label>
                        <select id="newUserType" name="tipo" required>
                            <option value="utilizador">Utilizador Normal</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeAddUserModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Criar Utilizador
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DE EDIÇÃO -->
    <!-- Formulário para alterar dados de um utilizador existente -->
    <div id="editUserModal" class="modal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Editar Utilizador</h3>
                <button class="close-btn" onclick="closeEditUserModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="editUserForm" class="modal-body">
                <input type="hidden" id="editUserId" name="id_utilizador">
                <input type="hidden" name="action" value="update_user">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="editUserName">Nome</label>
                        <input type="text" id="editUserName" name="nome" required>
                    </div>
                    <div class="form-group">
                        <label for="editUserEmail">Email</label>
                        <input type="email" id="editUserEmail" name="email" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="editUserType">Tipo de Utilizador</label>
                        <select id="editUserType" name="tipo" required>
                            <option value="utilizador">Utilizador Normal</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeEditUserModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DE BLOQUEIO -->
    <!-- Interface para bloquear acesso de um utilizador com motivo -->
    <div id="blockUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-ban"></i> Bloquear Utilizador</h3>
                <button class="close-btn" onclick="fecharModalBloqueio()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="blockUserForm" class="modal-body">
                <input type="hidden" id="blockUserId">
                <input type="hidden" id="blockUserNameDisplay">
                
                <div class="form-group">
                    <label>Utilizador</label>
                    <p id="blockUserNameText" style="padding: 8px 12px; background-color: #f5f5f5; border-radius: 4px; margin-top: 4px;"></p>
                </div>
                
                <div class="form-group textarea-group">
                    <label for="blockReason">Motivo do Bloqueio (obrigatório)</label>
                    <textarea id="blockReason" name="motivo" maxlength="255" placeholder="Introduza o motivo do bloqueio..." required></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="fecharModalBloqueio()">Cancelar</button>
                    <button type="button" class="btn btn-danger" onclick="confirmarBloqueio()">
                        <i class="fas fa-ban"></i> Bloquear Utilizador
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DETALHES DO FEEDBACK -->
    <!-- Mostra o conteúdo completo do feedback em um pop-up -->
    <div id="feedbackModal" class="modal">
        <div class="modal-content feedback-modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-comment-dots"></i> Detalhes do Feedback</h3>
                <button class="close-btn" onclick="closeFeedbackModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="feedback-modal-header">
                    <div class="feedback-user-info">
                        <div class="user-avatar gradient-bg" id="feedbackUserAvatar"></div>
                        <div class="user-details">
                            <h4 id="feedbackUserName"></h4>
                            <span class="feedback-date" id="feedbackDate"></span>
                        </div>
                    </div>
                    <div class="rating-badge" id="feedbackRating"></div>
                </div>
                <div class="feedback-modal-content">
                    <div id="feedbackPositive" class="feedback-text positive" style="display: none;">
                        <i class="fas fa-thumbs-up"></i>
                        <p id="feedbackPositiveText"></p>
                    </div>
                    <div id="feedbackSuggestion" class="feedback-text suggestion" style="display: none;">
                        <i class="fas fa-lightbulb"></i>
                        <p id="feedbackSuggestionText"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeFeedbackModal()">Fechar</button>
            </div>
        </div>
    </div>

    <!-- SCRIPTS -->
    <!-- Inclusão de ficheiros JavaScript para funcionalidades dinâmicas -->
    <script src="modal-system.js"></script>
    <script src="admin.js"></script>
    <script src="toggle_site_block.js"></script>
    <script src="admin-chat-ajuda.js"></script>
    <script>
        // Script para gerir feedbacks via AJAX (Atualização instantânea)
        document.addEventListener('DOMContentLoaded', function() {
            const botoesMarcarLido = document.querySelectorAll('.marcar-lido');
            
            // Adiciona evento de clique a todos os botões "Marcar como Lido"
            botoesMarcarLido.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const id = this.getAttribute('data-id');
                    const card = this.closest('.feedback-card');
                    const btnOriginal = this.innerHTML;
                    
                    // Feedback visual de carregamento
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    
                    const formData = new FormData();
                    formData.append('action', 'marcar_lido');
                    formData.append('id', id);
                    
                    // Envia pedido ao servidor
                    fetch('dashboard.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // 1. Atualizar o contador
                            const counter = document.getElementById('feedbackCounter');
                            if (counter) counter.textContent = data.total + ' feedbacks por ler';
                            
                            // 2. Remover o cartão com animação
                            card.style.transition = 'all 0.5s ease';
                            card.style.opacity = '0';
                            card.style.transform = 'translateX(20px)';
                            setTimeout(() => {
                                card.remove();
                                // Se não houver mais cartões, mostrar mensagem de vazio
                                const container = document.querySelector('.feedbacks-container');
                                if (container && container.querySelectorAll('.feedback-card').length === 0) {
                                    container.innerHTML = '<div class="no-feedback"><i class="fas fa-inbox"></i><p>Nenhum feedback encontrado</p></div>';
                                }
                            }, 500);
                        } else {
                            alert('Erro ao processar pedido.');
                            this.disabled = false;
                            this.innerHTML = btnOriginal;
                        }
                    })
                    .catch(err => {
                        console.error('Erro:', err);
                        this.disabled = false;
                        this.innerHTML = btnOriginal;
                    });
                });
            });
        });
    </script>
    <script>
        // Funções para controlo do Modal de Bloqueio
        function abrirModalBloqueio(idUtilizador, nomeUtilizador) {
            document.getElementById('blockUserId').value = idUtilizador;
            document.getElementById('blockUserNameDisplay').value = nomeUtilizador;
            document.getElementById('blockUserNameText').textContent = nomeUtilizador;
            document.getElementById('blockReason').value = '';
            document.getElementById('blockUserModal').style.display = 'flex';
        }

        function fecharModalBloqueio() {
            document.getElementById('blockUserModal').style.display = 'none';
        }

        // Envia o bloqueio para o servidor via AJAX
        async function confirmarBloqueio() {
            const idUtilizador = document.getElementById('blockUserId').value;
            const motivo = document.getElementById('blockReason').value.trim();
            
            if (!motivo) {
                await ModalSystem.error('Aviso', 'Por favor, introduza um motivo para o bloqueio.');
                return;
            }
            
            const formData = new FormData();
            formData.append('acao', 'bloquear');
            formData.append('id_utilizador', idUtilizador);
            formData.append('motivo', motivo);
            
            fetch('gerenciar_bloqueios.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(async (data) => {
                if (data.sucesso) {
                    await ModalSystem.success('Sucesso', data.mensagem);
                    fecharModalBloqueio();
                    location.reload();
                } else {
                    await ModalSystem.error('Erro', 'Erro: ' + (data.erro || 'Falha ao bloquear utilizador'));
                }
            })
            .catch(async (error) => {
                await ModalSystem.error('Erro', 'Erro ao processar pedido: ' + error.message);
            });
        }

        // Envia o desbloqueio para o servidor via AJAX
        async function desbloquearUtilizador(idUtilizador, nomeUtilizador) {
            const confirmed = await ModalSystem.confirm('Confirmar Desbloqueio', 'Tem a certeza que deseja desbloquear ' + nomeUtilizador + '?');
            if (!confirmed) {
                return;
            }
            
            const formData = new FormData();
            formData.append('acao', 'desbloquear');
            formData.append('id_utilizador', idUtilizador);
            
            fetch('gerenciar_bloqueios.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(async (data) => {
                if (data.sucesso) {
                    await ModalSystem.success('Sucesso', data.mensagem);
                    location.reload();
                } else {
                    await ModalSystem.error('Erro', 'Erro: ' + (data.erro || 'Falha ao desbloquear utilizador'));
                }
            })
            .catch(async (error) => {
                await ModalSystem.error('Erro', 'Erro ao processar pedido: ' + error.message);
            });
        }

        // Fecha o modal se clicar fora dele
        window.onclick = function(event) {
            const modal = document.getElementById('blockUserModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
            const feedbackModal = document.getElementById('feedbackModal');
            if (event.target == feedbackModal) {
                feedbackModal.style.display = 'none';
            }
        }
    </script>

    <script>
        // Funções para o modal de feedback
        function openFeedbackModal(id, nome, gostou, melhoria, data, rating) {
            // Preencher os dados do modal
            document.getElementById('feedbackUserName').textContent = nome;
            document.getElementById('feedbackDate').innerHTML = '<i class="far fa-clock"></i> ' + data;
            document.getElementById('feedbackUserAvatar').textContent = nome.charAt(0).toUpperCase();
            
            // Rating
            let ratingHtml = '';
            for (let i = 1; i <= 5; i++) {
                ratingHtml += '<i class="fa' + (i <= rating ? 's' : 'r') + ' fa-star"></i>';
            }
            ratingHtml += '<span class="rating-text">' + rating + '/5</span>';
            document.getElementById('feedbackRating').innerHTML = ratingHtml;
            document.getElementById('feedbackRating').className = 'rating-badge rating-' + rating;
            
            // Conteúdo positivo
            if (gostou) {
                document.getElementById('feedbackPositiveText').textContent = gostou;
                document.getElementById('feedbackPositive').style.display = 'block';
            } else {
                document.getElementById('feedbackPositive').style.display = 'none';
            }
            
            // Sugestões
            if (melhoria) {
                document.getElementById('feedbackSuggestionText').textContent = melhoria;
                document.getElementById('feedbackSuggestion').style.display = 'block';
            } else {
                document.getElementById('feedbackSuggestion').style.display = 'none';
            }
            
            // Mostrar modal
            document.getElementById('feedbackModal').style.display = 'flex';
        }
        
        function closeFeedbackModal() {
            document.getElementById('feedbackModal').style.display = 'none';
        }
    </script>
    
</body>
</html>