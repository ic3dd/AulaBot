<?php
require_once('../auth_check.php');
require_once('../ligarbd.php');
verificarAutenticacao();

// Obter dados do usuário logado
$dadosUtilizador = obterDadosUtilizador();
$nomeUtilizador = htmlspecialchars($dadosUtilizador['nome']);
$emailUtilizador = htmlspecialchars($dadosUtilizador['email']);
?>
<!DOCTYPE html>
<html lang="pt">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Feedback</title>
  <link rel="stylesheet" href="stylefeedback.css" />
</head>

<body>
  <main class="container">

    <!-- Botão para voltar à página principal -->
    <a href="../index.php">
      <button class="voltar-button">Voltar à Página Principal</button>
    </a>

    <!-- Logo do Site -->
    <div class="logo-container">
      <img src="../nova-logo-removebg.png" alt="AulaBot Logo" class="site-logo">
    </div>

    <!-- Cabeçalho e descrição do formulário -->
    <h1>📣 Deixe o seu Feedback</h1>
    <p>A sua opinião é essencial para melhorarmos o nosso site.</p>

    <!-- Formulário de feedback -->
    <form id="feedbackForm" action="feedback.php" method="POST">

      <!-- Campo de nome -->
      <label for="nome">Nome:</label>
      <input type="text" id="nome" name="nome" value="<?php echo $nomeUtilizador; ?>" required readonly />

      <!-- Campo de email -->
      <label for="email">Email:</label>
      <input type="email" id="email" name="email" value="<?php echo $emailUtilizador; ?>" required readonly />

      <!-- Classificação com estrelas (radio buttons) -->
      <label>Classificação:</label>
      <div class="radio">
        <input id="rating-5" type="radio" name="rating" value="5" />
        <label for="rating-5" title="5 stars">
          <svg viewBox="0 0 576 512" height="1em" xmlns="http://www.w3.org/2000/svg">
            <path
              d="M316.9 18C311.6 7 300.4 0 288.1 0s-23.4 7-28.8 18L195 150.3 51.4 171.5c-12 1.8-22 10.2-25.7 21.7s-.7 24.2 7.9 32.7L137.8 329 113.2 474.7c-2 12 3 24.2 12.9 31.3s23 8 33.8 2.3l128.3-68.5 128.3 68.5c10.8 5.7 23.9 4.9 33.8-2.3s14.9-19.3 12.9-31.3L438.5 329 542.7 225.9c8.6-8.5 11.7-21.2 7.9-32.7s-13.7-19.9-25.7-21.7L381.2 150.3 316.9 18z">
            </path>
          </svg>
        </label>

        <input id="rating-4" type="radio" name="rating" value="4" />
        <label for="rating-4" title="4 stars">
          <svg viewBox="0 0 576 512" height="1em" xmlns="http://www.w3.org/2000/svg">
            <path
              d="M316.9 18C311.6 7 300.4 0 288.1 0s-23.4 7-28.8 18L195 150.3 51.4 171.5c-12 1.8-22 10.2-25.7 21.7s-.7 24.2 7.9 32.7L137.8 329 113.2 474.7c-2 12 3 24.2 12.9 31.3s23 8 33.8 2.3l128.3-68.5 128.3 68.5c10.8 5.7 23.9 4.9 33.8-2.3s14.9-19.3 12.9-31.3L438.5 329 542.7 225.9c8.6-8.5 11.7-21.2 7.9-32.7s-13.7-19.9-25.7-21.7L381.2 150.3 316.9 18z">
            </path>
          </svg>
        </label>

        <input id="rating-3" type="radio" name="rating" value="3" />
        <label for="rating-3" title="3 stars">
          <svg viewBox="0 0 576 512" height="1em" xmlns="http://www.w3.org/2000/svg">
            <path
              d="M316.9 18C311.6 7 300.4 0 288.1 0s-23.4 7-28.8 18L195 150.3 51.4 171.5c-12 1.8-22 10.2-25.7 21.7s-.7 24.2 7.9 32.7L137.8 329 113.2 474.7c-2 12 3 24.2 12.9 31.3s23 8 33.8 2.3l128.3-68.5 128.3 68.5c10.8 5.7 23.9 4.9 33.8-2.3s14.9-19.3 12.9-31.3L438.5 329 542.7 225.9c8.6-8.5 11.7-21.2 7.9-32.7s-13.7-19.9-25.7-21.7L381.2 150.3 316.9 18z">
            </path>
          </svg>
        </label>

        <input id="rating-2" type="radio" name="rating" value="2" />
        <label for="rating-2" title="2 stars">
          <svg viewBox="0 0 576 512" height="1em" xmlns="http://www.w3.org/2000/svg">
            <path
              d="M316.9 18C311.6 7 300.4 0 288.1 0s-23.4 7-28.8 18L195 150.3 51.4 171.5c-12 1.8-22 10.2-25.7 21.7s-.7 24.2 7.9 32.7L137.8 329 113.2 474.7c-2 12 3 24.2 12.9 31.3s23 8 33.8 2.3l128.3-68.5 128.3 68.5c10.8 5.7 23.9 4.9 33.8-2.3s14.9-19.3 12.9-31.3L438.5 329 542.7 225.9c8.6-8.5 11.7-21.2 7.9-32.7s-13.7-19.9-25.7-21.7L381.2 150.3 316.9 18z">
            </path>
          </svg>
        </label>

        <input id="rating-1" type="radio" name="rating" value="1" />
        <label for="rating-1" title="1 star">
          <svg viewBox="0 0 576 512" height="1em" xmlns="http://www.w3.org/2000/svg">
            <path
              d="M316.9 18C311.6 7 300.4 0 288.1 0s-23.4 7-28.8 18L195 150.3 51.4 171.5c-12 1.8-22 10.2-25.7 21.7s-.7 24.2 7.9 32.7L137.8 329 113.2 474.7c-2 12 3 24.2 12.9 31.3s23 8 33.8 2.3l128.3-68.5 128.3 68.5c10.8 5.7 23.9 4.9 33.8-2.3s14.9-19.3 12.9-31.3L438.5 329 542.7 225.9c8.6-8.5 11.7-21.2 7.9-32.7s-13.7-19.9-25.7-21.7L381.2 150.3 316.9 18z">
            </path>
          </svg>
        </label>
      </div>

      <!-- Campo de feedback sobre o que gostou -->
      <label for="gostou">O que gostou no site?</label>
      <textarea id="gostou" name="gostou" rows="4" required></textarea>

      <!-- Campo de sugestões de melhoria -->
      <label for="melhoria">O que podemos melhorar?</label>
      <textarea id="melhoria" name="melhoria" rows="4"></textarea>

      <!-- Checkbox de autorização -->
      <label>
        <input type="checkbox" name="autorizacao" value="sim" required />
        Autorizo o uso deste feedback para fins de melhoria interna.
      </label>

      <!-- Botão de envio com ícone SVG decorativo -->
      <button type="submit" class="send-button">
        <div class="svg-wrapper-1">
          <div class="svg-wrapper">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
              <path fill="none" d="M0 0h24v24H0z"></path>
              <path fill="currentColor"
                d="M1.946 9.315c-.522-.174-.527-.455.01-.634l19.087-6.362c.529-.176.832.12.684.638l-5.454 19.086c-.15.529-.455.547-.679.045L12 14l6-8-8 6-8.054-2.685z">
              </path>
            </svg>
          </div>
        </div>
        <span>Enviar</span>
      </button>
    </form>

    <!-- Div para exibir mensagens de sucesso ou erro após envio -->
    <div id="mensagem" class="mensagem"></div>
  </main>
  <script src="feedback.js"></script>
  <script src="../monitor_site_bloqueio.js"></script>

</body>

</html>