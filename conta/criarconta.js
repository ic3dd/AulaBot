window.onload = function() {
  // Referências aos elementos do modal de autenticação
  const modal = document.getElementById("popup-modal");
  const closeBtn = document.querySelector(".close-btn");


  // Função para mostrar o modal
  function showModal() {
    modal.style.display = "block";
    document.body.classList.add('modal-active');
    
    // Animação de entrada
    setTimeout(() => {
      modal.style.opacity = '1';
    }, 10);
  }

  // Função para esconder o modal
  function hideModal() {
    modal.style.opacity = '0';
    document.body.classList.remove('modal-active');
    
    setTimeout(() => {
      modal.style.display = "none";
    }, 300);
  }
  
  // Função para logout (pode ser chamada de outros lugares)
  // Limpa dados locais e recarrega a página
  window.logout = function() {
    localStorage.removeItem('user_logged_in');
    localStorage.removeItem('user_name');
    window.location.reload();
  };

  // Adicionar funcionalidade ao toggle
  // Elementos para alternar entre Login e Criar Conta
  const toggle = document.getElementById('form-toggle');
  const loginLabel = document.getElementById('login-label');
  const signupLabel = document.getElementById('signup-label');
  const loginForm = document.getElementById('login-form');
  const signupForm = document.getElementById('signup-form');

  // Verificar se os elementos existem antes de adicionar event listeners
  if (toggle && loginLabel && signupLabel && loginForm && signupForm) {
    // Função para alternar entre formulários
    // Controla a visibilidade baseada no estado do checkbox (toggle)
    function toggleForms() {
      if (toggle.checked) {
        // Mostrar signup
        loginLabel.classList.remove('active');
        signupLabel.classList.add('active');
        loginForm.classList.add('hidden');
        signupForm.classList.remove('hidden');
        signupForm.classList.add('show');
      } else {
        // Mostrar login
        loginLabel.classList.add('active');
        signupLabel.classList.remove('active');
        loginForm.classList.remove('hidden');
        signupForm.classList.add('hidden');
        signupForm.classList.remove('show');
      }
    }

    // Event listeners para o toggle
    toggle.addEventListener('change', toggleForms);
    loginLabel.addEventListener('click', () => {
      toggle.checked = false;
      toggleForms();
    });
    signupLabel.addEventListener('click', () => {
      toggle.checked = true;
      toggleForms();
    });
  }

  // Adicionar funcionalidade aos formulários
  const loginFormElement = loginForm ? loginForm.querySelector('form') : null;
  const signupFormElement = signupForm ? signupForm.querySelector('form') : null;

  // --- LÓGICA DO FORMULÁRIO DE LOGIN ---
  if (loginFormElement) {
    loginFormElement.addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const emailInput = this.querySelector('input[name="email"]');
      const passwordInput = this.querySelector('input[name="password"]');
      
      if (!emailInput || !passwordInput) {
        showAuthMessage.validationError('Erro: Campos de login não encontrados!');
        return;
      }
      
      const email = emailInput.value.trim();
      const password = passwordInput.value;
      
      if (!email || !password) {
        showAuthMessage.validationError('Por favor, preencha todos os campos!');
        return;
      }

      // Desabilitar botão durante a requisição
      const button = this.querySelector('.flip-card__btn');
      const originalText = button ? button.textContent : '';
      
      if (button) {
        button.disabled = true;
        button.textContent = 'A entrar...';
      }

      try {
        // Prepara dados para envio
        const formData = new FormData();
        formData.append('email', email);
        formData.append('palavra_passe', password);

        const response = await fetch('conta/login.php', {
          method: 'POST',
          body: formData,
          credentials: 'include'
        });

        // Seguro: ler o body como texto e verificar Content-Type antes de parsear JSON
        const ctype = response.headers.get('content-type') || '';
        const raw = await response.text();
        let result;
        if (ctype.includes('application/json')) {
          try {
            result = JSON.parse(raw);
          } catch (err) {
            console.error('Resposta JSON inválida do servidor:', raw);
            throw new Error('Resposta inválida do servidor');
          }
        } else {
          console.error('Esperava JSON mas recebeu:', ctype, raw);
          throw new Error('Resposta inesperada do servidor');
        }
        console.log('Resultado do login:', result);

        if (result.sucesso) {
          showAuthMessage.loginSuccess(result.utilizador.nome);
          hideModal();
            
          // Debug: mostrar cookies após o login para verificar PHPSESSID
          try {
            console.log('Cookies após login:', document.cookie);
          } catch (e) {
            console.warn('Não foi possível ler document.cookie', e);
          }
          console.log('Login bem-sucedido, recarregando em 1.5s...');
          
          // Recarregar página para mostrar conteúdo logado
          setTimeout(() => {
            console.log('Recarregando página...');
            window.location.reload();
          }, 1500);
        } else {
          console.error('Erro no login:', result.erro);
          showAuthMessage.loginError(result.erro);
        }
      } catch (error) {
        console.error('Erro na requisição de login:', error);
        showAuthMessage.loginError('Erro de conexão. Tente novamente.');
      } finally {
        // Reabilitar botão
        if (button) {
          button.disabled = false;
          button.textContent = originalText;
        }
      }
    });
  }

  // --- LÓGICA DO FORMULÁRIO DE CRIAÇÃO DE CONTA ---
  if (signupFormElement) {
    signupFormElement.addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const nameInput = this.querySelector('input[placeholder="Nome"]');
      const emailInput = this.querySelector('input[name="email"]');
      const passwordInput = this.querySelector('input[name="password"]');
      
      if (!nameInput || !emailInput || !passwordInput) {
        showAuthMessage.validationError('Erro: Campos de criação de conta não encontrados!');
        return;
      }
      
      const name = nameInput.value.trim();
      const email = emailInput.value.trim();
      const password = passwordInput.value;
      
      if (!name || !email || !password) {
        showAuthMessage.validationError('Por favor, preencha todos os campos!');
        return;
      }

      if (password.length < 6) {
        showAuthMessage.validationError('A palavra-passe deve ter pelo menos 6 caracteres!');
        return;
      }

      // Validação de email com Regex
      // Validar formato do email
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        showAuthMessage.validationError('Por favor, insira um email válido!');
        return;
      }

      // Desabilitar botão durante a requisição
      const button = this.querySelector('.flip-card__btn');
      const originalText = button ? button.textContent : '';
      
      if (button) {
        button.disabled = true;
        button.textContent = 'A criar...';
      }

      try {
        const formData = new FormData();
        formData.append('nome', name);
        formData.append('email', email);
        formData.append('palavra_passe', password);
        

        console.log('Enviando dados para criar conta:', { nome: name, email: email });

        // Envia pedido de registo
        const response = await fetch('conta/criarconta.php', {
          method: 'POST',
          body: formData
        });

        console.log('Status da resposta:', response.status);
        console.log('URL da resposta:', response.url);

        const ctype2 = response.headers.get('content-type') || '';
        const raw2 = await response.text();
        let result;
        if (ctype2.includes('application/json')) {
          try {
            result = JSON.parse(raw2);
          } catch (err) {
            console.error('Resposta JSON inválida do servidor (criar conta):', raw2);
            throw new Error('Resposta inválida do servidor');
          }
        } else {
          console.error('Esperava JSON mas recebeu (criar conta):', ctype2, raw2);
          throw new Error('Resposta inesperada do servidor');
        }

        if (!response.ok) {
          const errorMessage = result.erro || `HTTP error! status: ${response.status}`;
          throw new Error(errorMessage);
        }

        console.log('Resultado da criação de conta:', result);

        if (result.sucesso) {
          showAuthMessage.signupSuccess();
          
          // Voltar para o formulário de login após um pequeno delay
          setTimeout(() => {
            if (toggle) {
              toggle.checked = false;
              // Chamar toggleForms se existir
              if (typeof toggleForms === 'function') {
                toggleForms();
              }
            }
            // Limpar formulário
            signupFormElement.reset();
          }, 2000);
        } else {
          showAuthMessage.signupError(result.erro);
        }
      } catch (error) {
        console.error('Erro na requisição de criação de conta:', error.message);
        showAuthMessage.signupError(error.message);
      } finally {
        // Reabilitar botão
        if (button) {
          button.disabled = false;
          button.textContent = originalText;
        }
      }
    });
  }
};