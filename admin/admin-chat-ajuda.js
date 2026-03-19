// Criar modal de confirmação para fechar conversa se não existir
function criarModalFecharConversa() {
  // Verificar se o modal já existe
  if (document.getElementById('modalFecharConversa')) {
    return;
  }

  const modal = document.createElement('div');
  modal.id = 'modalFecharConversa';
  modal.className = 'modal-fechar-conversa';
  modal.innerHTML = `
    <div class="modal-content-fechar">
      <div class="modal-header-fechar">
        <i class="fas fa-exclamation-circle"></i>
        <h3>Fechar Conversa</h3>
      </div>
      <div class="modal-body-fechar">
        <p>Tem a certeza de que deseja <strong>fechar esta conversa</strong>?</p>
        <p class="modal-info">O utilizador não poderá enviar mais mensagens nesta conversa.</p>
      </div>
      <div class="modal-footer-fechar">
        <button class="btn btn-outline" id="cancelarFecharBtn">Cancelar</button>
        <button class="btn btn-danger" id="confirmarFecharBtn">Fechar Conversa</button>
      </div>
    </div>
  `;

  document.body.appendChild(modal);

  // Fechar modal ao clicar fora
  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      fecharModalFecharConversa();
    }
  });

  // Botão cancelar
  document.getElementById('cancelarFecharBtn').addEventListener('click', fecharModalFecharConversa);
}

function abrirModalFecharConversa(conversaId) {
  criarModalFecharConversa();
  const modal = document.getElementById('modalFecharConversa');
  modal.classList.add('aberto');

  // Armazenar ID para usar na confirmação
  modal.dataset.conversaId = conversaId;

  // Botão confirmar
  document.getElementById('confirmarFecharBtn').onclick = () => {
    const id = modal.dataset.conversaId;
    fecharModalFecharConversa();
    executarFecharConversa(id);
  };
}

function fecharModalFecharConversa() {
  const modal = document.getElementById('modalFecharConversa');
  if (modal) {
    modal.classList.remove('aberto');
  }
}

let conversaAtual = null;
let checkNewMessagesInterval = null;
let lastConversationCount = 0;
let lastMessageTimestamps = {};

const conversasListElement = document.getElementById('conversasList');
const chatAreaAdminElement = document.getElementById('chatAreaAdmin');

function carregarConversas() {
  fetch('admin_api.php?action=get_help_conversations', {
    method: 'GET'
  })
    .then(response => response.json())
    .then(result => {
      if (result.success) {
        exibirConversas(result.data.conversas);
      } else {
        console.error('Erro ao carregar conversas:', result.message);
      }
    })
    .catch(error => {
      console.error('Erro:', error);
    });
}

function exibirConversas(conversas) {
  conversasListElement.innerHTML = '';

  if (conversas.length === 0) {
    conversasListElement.innerHTML = '<div class="no-conversations"><i class="fas fa-inbox"></i><p>Nenhuma conversa ainda</p></div>';
    return;
  }

  conversas.forEach(conversa => {
    const item = document.createElement('div');
    item.className = `conversa-item ${conversaAtual?.id === conversa.id ? 'ativo' : ''}`;

    const dataFormatada = new Date(conversa.criado_em).toLocaleString('pt-PT', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });

    item.innerHTML = `
      <div class="conversa-header">
        <div class="conversa-info">
          <h4>Conversa com ${conversa.nome_utilizador || 'Convidado'}</h4>
          <span class="conversa-data">${dataFormatada}</span>
        </div>
        <div class="conversa-stats">
          <span class="badge badge-info">${conversa.total_mensagens || 0} mensagens</span>
        </div>
      </div>
      <div class="conversa-preview">
        <span class="badge badge-success">${conversa.mensagens_utilizador || 0} do utilizador</span>
        <span class="badge badge-primary">${conversa.mensagens_admin || 0} do admin</span>
      </div>
    `;

    item.addEventListener('click', (event) => {
      abrirConversa(conversa, event.currentTarget);
    });

    conversasListElement.appendChild(item);
  });
}

function abrirConversa(conversa, targetElement) {
  conversaAtual = conversa;

  const allItems = document.querySelectorAll('.conversa-item');
  allItems.forEach(item => item.classList.remove('ativo'));
  targetElement.classList.add('ativo');

  carregarMensagensConversa(conversa.id);
}

function carregarMensagensConversa(conversaId) {
  fetch(`../api/live_chat.php?action=get_conversation&conversation_id=${conversaId}`)
    .then(response => response.json())
    .then(result => {
      if (result.success) {
        exibirMensagensAdmin(result.data.mensagens, conversaId, result.data.estado);
      } else {
        if (result.message && result.message.includes('fechada')) {
          conversaAtual = null;
          chatAreaAdminElement.innerHTML = `
            <div class="chat-welcome">
              <i class="fas fa-lock"></i>
              <p>Conversa fechada. Não pode enviar mensagens.</p>
            </div>
          `;
          carregarConversas();
        } else {
          console.error('Erro ao carregar mensagens:', result.message);
        }
      }
    })
    .catch(error => {
      console.error('Erro:', error);
    });
}

function exibirMensagensAdmin(mensagens, conversaId, estado) {
  const conversaFechada = estado === 'fechado';

  chatAreaAdminElement.innerHTML = `
    <div class="chat-header-admin">
      <div>
        <h3>💬 Conversa com ${conversaAtual.nome_utilizador || 'Convidado'}</h3>
        <span class="badge">${mensagens.length} mensagens</span>
        ${conversaFechada ? '<span class="badge badge-danger" style="margin-left: 10px;"><i class="fas fa-lock"></i> Fechada</span>' : ''}
      </div>
      ${!conversaFechada ? `<button class="btn btn-danger btn-sm" id="fecharConversaBtn" title="Fechar conversa">
        <i class="fas fa-times"></i> Fechar
      </button>` : ''}
    </div>
    <div class="mensagens-admin" id="mensagensAdmin"></div>
    <div class="resposta-area" ${conversaFechada ? 'style="opacity: 0.5;"' : ''}>
      <textarea id="respostaInput" placeholder="${conversaFechada ? 'Conversa fechada' : 'Digite sua resposta...'}" maxlength="1000" ${conversaFechada ? 'disabled' : ''}></textarea>
      <button class="btn btn-primary" id="enviarRespostaBtn" ${conversaFechada ? 'disabled' : ''}>
        <i class="fas fa-paper-plane"></i> Enviar
      </button>
    </div>
  `;

  const mensagensContainer = document.getElementById('mensagensAdmin');
  mensagens.forEach(msg => {
    const msgElement = document.createElement('div');
    msgElement.className = `mensagem-admin mensagem-${msg.sender}`;

    const senderLabel = msg.sender === 'admin' ? 'Admin' : (conversaAtual.nome_utilizador || 'Convidado');
    const dataFormatada = new Date(msg.enviado_em).toLocaleString('pt-PT', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });

    msgElement.innerHTML = `
      <div class="msg-badge">${senderLabel}</div>
      <div class="msg-conteudo">${escapeHtml(msg.conteudo)}</div>
      <div class="msg-hora">${dataFormatada}</div>
    `;

    mensagensContainer.appendChild(msgElement);
  });

  if (mensagens.length > 0) {
    lastMessageTimestamps[conversaId] = mensagens[mensagens.length - 1].enviado_em;
  }

  mensagensContainer.scrollTop = mensagensContainer.scrollHeight;

  document.getElementById('enviarRespostaBtn').addEventListener('click', () => {
    enviarResposta(conversaId);
  });

  document.getElementById('respostaInput').addEventListener('keypress', (e) => {
    if (e.key === 'Enter' && !e.shiftKey && !conversaFechada) {
      e.preventDefault();
      enviarResposta(conversaId);
    }
  });

  if (!conversaFechada && document.getElementById('fecharConversaBtn')) {
    document.getElementById('fecharConversaBtn').addEventListener('click', () => {
      fecharConversa(conversaId);
    });
  }
}

function enviarResposta(conversaId) {
  const inputElement = document.getElementById('respostaInput');
  const botaoElement = document.getElementById('enviarRespostaBtn');
  const conteudo = inputElement.value.trim();

  if (!conteudo) {
    inputElement.focus();
    return;
  }

  if (botaoElement.disabled) {
    return;
  }

  botaoElement.disabled = true;
  botaoElement.style.opacity = '0.6';

  const dados = {
    action: 'send_admin_reply',
    conversation_id: conversaId,
    conteudo: conteudo
  };

  fetch('admin_api.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(dados)
  })
    .then(response => response.json())
    .then(result => {
      if (result.success) {
        inputElement.value = '';
        lastMessageTimestamps[conversaId] = null;
        carregarMensagensConversa(conversaId);
        carregarConversas();
      } else {
        if (result.message && result.message.includes('fechada')) {
          conversaAtual = null;
          chatAreaAdminElement.innerHTML = `
          <div class="chat-welcome">
            <i class="fas fa-lock"></i>
            <p>Conversa fechada. Não pode enviar mensagens.</p>
          </div>
        `;
          carregarConversas();
        } else {
          console.error('Erro ao enviar:', result.message);
          alert('Erro: ' + result.message);
        }
      }
    })
    .catch(error => {
      console.error('Erro:', error);
      alert('Erro ao enviar resposta');
    })
    .finally(() => {
      botaoElement.disabled = false;
      botaoElement.style.opacity = '1';
      if (document.getElementById('respostaInput')) {
        inputElement.focus();
      }
    });
}

function fecharConversa(conversaId) {
  abrirModalFecharConversa(conversaId);
}

function executarFecharConversa(conversaId) {
  const dados = {
    action: 'close_conversation',
    conversation_id: conversaId
  };

  fetch('admin_api.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(dados)
  })
    .then(response => response.json())
    .then(result => {
      if (result.success) {
        conversaAtual = null;
        chatAreaAdminElement.innerHTML = `
        <div class="chat-welcome">
          <i class="fas fa-comments"></i>
          <p>Selecione uma conversa para responder</p>
        </div>
      `;
        carregarConversas();
      } else {
        alert('Erro: ' + result.message);
      }
    })
    .catch(error => {
      console.error('Erro:', error);
      alert('Erro ao fechar conversa');
    });
}

function escapeHtml(texto) {
  const div = document.createElement('div');
  div.textContent = texto;
  return div.innerHTML;
}

function iniciarVerificacaoNovasMensagens() {
  if (checkNewMessagesInterval) clearInterval(checkNewMessagesInterval);
  checkNewMessagesInterval = setInterval(() => {
    verificarNovasConversas();
    if (conversaAtual) {
      verificarNovasMensagens(conversaAtual.id);
    }
  }, 5000);
}

function verificarNovasConversas() {
  fetch('admin_api.php?action=get_help_conversations', {
    method: 'GET'
  })
    .then(response => response.json())
    .then(result => {
      if (result.success) {
        const novoCount = result.data.conversas.length;
        if (novoCount !== lastConversationCount) {
          lastConversationCount = novoCount;
          exibirConversas(result.data.conversas);
        }
      }
    })
    .catch(error => console.error('Erro:', error));
}

function verificarNovasMensagens(conversaId) {
  fetch(`../api/live_chat.php?action=check_new_messages&conversation_id=${conversaId}`)
    .then(response => response.json())
    .then(result => {
      if (result.success) {
        const ultimaMensagem = result.data.ultima_mensagem;
        const estado = result.data.estado;

        // Se a conversa foi fechada pelo utilizador, informar o admin e sair da conversa
        if (estado === 'fechado' && conversaAtual) {
          if (typeof showToast === 'function') {
            showToast('info', 'Conversa Encerrada', 'O utilizador encerrou esta conversa.');
          } else {
            alert('A conversa foi encerrada pelo utilizador.');
          }

          conversaAtual = null;
          lastMessageTimestamps[conversaId] = null;
          chatAreaAdminElement.innerHTML = `
            <div class="chat-welcome">
              <i class="fas fa-comments"></i>
              <p>O utilizador encerrou a conversa ativa.</p>
              <button class="btn btn-outline btn-sm" onclick="carregarConversas()">Atualizar Lista</button>
            </div>
          `;
          carregarConversas(); // Atualizar lista lateral para remover a conversa fechada
          return;
        }

        if (ultimaMensagem && ultimaMensagem !== lastMessageTimestamps[conversaId]) {
          lastMessageTimestamps[conversaId] = ultimaMensagem;
          carregarMensagensConversa(conversaId);
        }
      }
    })
    .catch(error => console.error('Erro:', error));
}

window.addEventListener('beforeunload', () => {
  if (checkNewMessagesInterval) clearInterval(checkNewMessagesInterval);
});

document.addEventListener('DOMContentLoaded', () => {
  carregarConversas();
  iniciarVerificacaoNovasMensagens();
});
