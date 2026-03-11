<?php
// Inclui verificação de autenticação para proteger a página
require_once('../auth_check.php');
// Garante que o utilizador está logado antes de mostrar o conteúdo
verificarAutenticacao();
?>
<!DOCTYPE html>
<html lang="pt">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ajuda - Perguntas Frequentes</title>
  <link rel="stylesheet" href="styleajuda.css" />

</head>

<body>
  <!-- Botão de navegação para voltar à dashboard -->
  <a href="../index.php" class="voltar-btn">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
      <polyline points="9,22 9,12 15,12 15,22" />
    </svg>
    Página Inicial
  </a>

  <header class="site-header">
    <div class="logo-container">
      <img src="../nova-logo-removebg.png" alt="AulaBot Logo" class="site-logo">
    </div>
    <h1>Ajuda & Perguntas Frequentes</h1>
  </header>

  <!-- Secção de Perguntas Frequentes (FAQ) -->
  <main class="faq-container">
    <div class="search-container">
      <input type="text" id="searchInput" placeholder="Procurar pergunta..." />
    </div>

    <div class="faq-item">
      <button class="question">Como a IA ajuda-me a resolver dúvidas escolares?</button>
      <div class="answer">A IA guia-te passo a passo até à resposta, explicando o raciocínio por trás da solução. É
        necessário ter conta para guardar o teu progresso.</div>
    </div>

    <div class="faq-item">
      <button class="question">A IA pode ajudar-me a melhorar as minhas notas?</button>
      <div class="answer">Sim! A IA analisa os teus resultados, sugere exercícios personalizados e acompanha a tua
        evolução. Para isso, precisas de estar autenticado.</div>
    </div>

    <div class="faq-item">
      <button class="question">Posso pedir ajuda em qualquer disciplina?</button>
      <div class="answer">Claro! A IA cobre matemática, português, ciências, história e muito mais. Algumas
        funcionalidades exigem login.</div>
    </div>

    <div class="faq-item">
      <button class="question">Como funciona o apoio aos professores?</button>
      <div class="answer">Professores podem criar testes, corrigir exercícios e acompanhar alunos. É necessário ter
        conta de professor para aceder a estas ferramentas.</div>
    </div>

    <div class="faq-item">
      <button class="question">A IA faz os trabalhos por mim?</button>
      <div class="answer">Não! A IA ensina-te a pensar e compreender cada passo. O objetivo é desenvolver autonomia no
        estudo e capacidades de resolução de problemas, não substituir o teu esforço.</div>
    </div>

    <div class="faq-item">
      <button class="question">Preciso de criar conta para usar a IA?</button>
      <div class="answer">Sim. A criação de conta permite guardar progresso, personalizar ajuda, aceder a
        funcionalidades exclusivas e manter um histórico das tuas conversas e aprendizagem.</div>
    </div>

    <div class="faq-item">
      <button class="question">Os meus dados estão seguros?</button>
      <div class="answer">Absolutamente! Seguimos rigorosas políticas de privacidade e segurança. Os teus dados pessoais
        e conversas são protegidos e nunca partilhados com terceiros sem o teu consentimento.</div>
    </div>

    <div class="faq-item">
      <button class="question">Como posso dar feedback ou sugestões?</button>
      <div class="answer">Podes usar a secção de feedback no menu principal ou contactar diretamente um administrador
        através do chat de suporte. O teu feedback é muito importante para melhorarmos o serviço.</div>
    </div>
  </main>

  <!-- Secção de contacto com o Administrador -->
  <section class="chat-ajuda-section">
    <h2>
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
        style="display: inline; margin-right: 8px;">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
      </svg>
      Contactar Admin
    </h2>
    <p>Tem uma dúvida específica? Fale diretamente com um administrador através do nosso chat de suporte.</p>
    <button class="abrir-chat-btn" id="abrirChatBtn">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
      </svg>
      Abrir Chat de Suporte
    </button>
  </section>

  <!-- Modal do Chat de Suporte -->
  <div class="chat-modal" id="chatModal">
    <div class="chat-container">
      <div class="chat-header">
        <h3>💬 Chat de Suporte</h3>
        <button class="fechar-chat-btn" id="fecharChatBtn">✕</button>
      </div>
      <div class="chat-messages" id="chatMessages"></div>
      <div class="chat-input-area">
        <textarea id="chatInput" placeholder="Digite sua mensagem..." maxlength="1000"></textarea>
        <button class="enviar-btn" id="enviarBtn">📤</button>
      </div>
    </div>
  </div>

  <footer class="site-footer">
    <p>&copy; 2025 IA Escolar - Agrupamento de Escolas Damião de Goes</p>
  </footer>

  <!-- Scripts para funcionalidade da FAQ e do Chat -->
  <script src="../toast-notifications.js"></script>
  <script src="ajuda.js"></script>
  <script src="chat-ajuda.js"></script>
  <script src="../monitor_site_bloqueio.js"></script>

  <div id="toast-container" class="toast-container"></div>

</body>

</html>