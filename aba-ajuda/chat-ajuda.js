let conversationId = null;
let checkNewMessagesInterval = null;
let lastAuthEmail = null;
let lastMessageTimestamp = null;

const modalElement = document.getElementById('chatModal');
const messagesContainer = document.getElementById('chatMessages');
const chatInput = document.getElementById('chatInput');
const enviarBtn = document.getElementById('enviarBtn');
const abrirChatBtn = document.getElementById('abrirChatBtn');
const fecharChatBtn = document.getElementById('fecharChatBtn');

abrirChatBtn.addEventListener('click', abrirChat);
fecharChatBtn.addEventListener('click', fecharChat);
enviarBtn.addEventListener('click', enviarMensagem);
chatInput.addEventListener('keypress', (e) => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    enviarMensagem();
  }
});

function abrirChat() {
  modalElement.classList.add('ativo');

  // Clear the new message notification
  fetch('../api/api_clear_message.php', { credentials: 'include' })
    .then(response => response.json())
    .then(data => {
      if (!data.success) {
        console.warn('Message status may not have been cleared:', data.error);
      }
    })
    .catch(error => console.error('Error clearing message status:', error));

  const currentAuthEmail = localStorage.getItem('auth_email');

  // Se o email mudou, limpar conversação anterior
  if (lastAuthEmail && currentAuthEmail && lastAuthEmail !== currentAuthEmail) {
    localStorage.removeItem('chat_ajuda_conversation_id');
    conversationId = null;
  }

  lastAuthEmail = currentAuthEmail;

  // Se não há conversation_id mas há email, recuperar do servidor
  if (!conversationId && currentAuthEmail) {
    recuperarConversaAnterior();
  } else {
    atualizarHeaderChat();
    carregarConversa();
    iniciarVerificacaoNovasMensagens();
  }
}

function recuperarConversaAnterior() {
  const email = localStorage.getItem('auth_email');
  const params = new URLSearchParams({
    action: 'list_conversations',
    email: email
  });

  fetch(`../api/live_chat.php?${params.toString()}`, {
    credentials: 'include'
  })
    .then(response => response.json())
    .then(result => {
      if (result.success && result.data.conversas.length > 0) {
        conversationId = result.data.conversas[0].id;
        localStorage.setItem('chat_ajuda_conversation_id', conversationId);
      }
      atualizarHeaderChat();
      carregarConversa();
      iniciarVerificacaoNovasMensagens();
    })
    .catch(error => {
      console.error('Erro ao recuperar conversa anterior:', error);
      atualizarHeaderChat();
      carregarConversa();
      iniciarVerificacaoNovasMensagens();
    });
}

function fecharChat() {
  modalElement.classList.remove('ativo');
  pararVerificacaoNovasMensagens();
}

function atualizarHeaderChat() {
  const chatHeader = document.querySelector('.chat-header');
  let fecharConversaBtn = document.getElementById('fecharConversaBtn');

  if (!fecharConversaBtn && conversationId) {
    fecharConversaBtn = document.createElement('button');
    fecharConversaBtn.id = 'fecharConversaBtn';
    fecharConversaBtn.className = 'fechar-conversa-btn';
    fecharConversaBtn.title = 'Fechar conversa permanentemente';
    fecharConversaBtn.textContent = '🗑️ Fechar Conversa';
    fecharConversaBtn.addEventListener('click', fecharConversa);
    chatHeader.appendChild(fecharConversaBtn);
  }
}

function iniciarVerificacaoNovasMensagens() {
  if (checkNewMessagesInterval) clearInterval(checkNewMessagesInterval);
  checkNewMessagesInterval = setInterval(() => {
    if (modalElement.classList.contains('ativo') && conversationId) {
      verificarNovasMensagens();
    }
  }, 5000);
}

function pararVerificacaoNovasMensagens() {
  if (checkNewMessagesInterval) {
    clearInterval(checkNewMessagesInterval);
    checkNewMessagesInterval = null;
  }
}

function verificarNovasMensagens() {
  const email = localStorage.getItem('auth_email');
  const params = new URLSearchParams({
    action: 'check_new_messages',
    conversation_id: conversationId,
    email: email
  });

  fetch(`../api/live_chat.php?${params.toString()}`, {
    credentials: 'include'
  })
    .then(response => response.json())
    .then(result => {
      if (result.success) {
        const ultimaMensagem = result.data.ultima_mensagem;
        const estado = result.data.estado;

        // Se o admin fechou a conversa
        if (estado === 'fechado') {
          if (typeof showToast === 'function') {
            showToast('info', 'Conversa Encerrada', 'Esta conversa foi encerrada pelo administrador.');
          } else {
            alert('Esta conversa foi encerrada pelo administrador.');
          }

          conversationId = null;
          localStorage.removeItem('chat_ajuda_conversation_id');
          fecharChat(); // Fecha o modal
          return;
        }

        if (ultimaMensagem && ultimaMensagem !== lastMessageTimestamp) {
          lastMessageTimestamp = ultimaMensagem;
          carregarConversa();
        }
      } else {
        if (result.message && result.message.includes('fechada')) {
          conversationId = null;
          localStorage.removeItem('chat_ajuda_conversation_id');
          pararVerificacaoNovasMensagens();
        }
      }
    })
    .catch(error => {
      console.error('Erro ao verificar novas mensagens:', error);
    });
}

function enviarMensagem() {
  const conteudo = chatInput.value.trim();

  if (!conteudo) {
    chatInput.focus();
    return;
  }

  enviarBtn.disabled = true;
  enviarBtn.style.opacity = '0.6';

  const dados = {
    action: 'send_message',
    conversation_id: conversationId,
    conteudo: conteudo,
    id_utilizador: localStorage.getItem('auth_id'),
    email: localStorage.getItem('auth_email')
  };

  fetch('../api/live_chat.php', {
    method: 'POST',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(dados)
  })
    .then(response => response.json())
    .then(result => {
      if (result.success) {
        conversationId = result.data.conversation_id;
        localStorage.setItem('chat_ajuda_conversation_id', conversationId);
        chatInput.value = '';
        lastMessageTimestamp = null;
        carregarConversa();
      } else {
        if (result.message && result.message.includes('fechada')) {
          conversationId = null;
          localStorage.removeItem('chat_ajuda_conversation_id');
          messagesContainer.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">Conversa anterior foi fechada. Inicie uma nova conversa.</div>';
        } else {
          console.error('Erro ao enviar:', result.message);
          alert('Erro: ' + result.message);
        }
      }
    })
    .catch(error => {
      console.error('Erro:', error);
      alert('Erro ao enviar mensagem. Tente novamente.');
    })
    .finally(() => {
      enviarBtn.disabled = false;
      enviarBtn.style.opacity = '1';
      chatInput.focus();
    });
}

function carregarConversa() {
  if (!conversationId || conversationId === null || conversationId === 0) {
    messagesContainer.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">Comece uma conversa enviando uma mensagem</div>';
    return;
  }

  const idUtilizador = localStorage.getItem('auth_id');
  const email = localStorage.getItem('auth_email');
  const params = new URLSearchParams({
    action: 'get_conversation',
    conversation_id: conversationId,
    id_utilizador: idUtilizador,
    email: email
  });

  fetch(`../api/live_chat.php?${params.toString()}`, {
    credentials: 'include'
  })
    .then(response => response.json())
    .then(result => {
      if (result.success) {
        exibirMensagens(result.data.mensagens);
        atualizarHeaderChat();
      } else {
        if (result.message && result.message.includes('fechada')) {
          conversationId = null;
          localStorage.removeItem('chat_ajuda_conversation_id');
          messagesContainer.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">Conversa anterior foi fechada. Inicie uma nova conversa.</div>';
        } else {
          console.error('Erro ao carregar conversa:', result.message);
        }
      }
    })
    .catch(error => {
      console.error('Erro:', error);
    });
}

function mostrarPopupConfirmacao(titulo, mensagem, onConfirm, onCancel) {
  // Criar modal de confirmação
  const modal = document.createElement('div');
  modal.className = 'popup-confirmacao';
  modal.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
  `;

  const conteudo = document.createElement('div');
  conteudo.style.cssText = `
    background: white;
    border-radius: 12px;
    padding: 30px;
    max-width: 400px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    animation: slideUp 0.3s ease-out;
  `;

  const style = document.createElement('style');
  style.textContent = `
    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
  `;
  document.head.appendChild(style);

  conteudo.innerHTML = `
    <h2 style="margin: 0 0 10px 0; color: #1e293b; font-size: 20px;">${titulo}</h2>
    <p style="margin: 0 0 25px 0; color: #64748b; font-size: 15px; line-height: 1.5;">${mensagem}</p>
    <div style="display: flex; gap: 10px; justify-content: flex-end;">
      <button class="btn-cancelar" style="
        padding: 10px 20px;
        border: 1px solid #e2e8f0;
        background: white;
        color: #64748b;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s;
      ">Cancelar</button>
      <button class="btn-confirmar" style="
        padding: 10px 20px;
        border: none;
        background: #ef4444;
        color: white;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s;
      ">Confirmar</button>
    </div>
  `;

  modal.appendChild(conteudo);
  document.body.appendChild(modal);

  const btnCancelar = conteudo.querySelector('.btn-cancelar');
  const btnConfirmar = conteudo.querySelector('.btn-confirmar');

  btnCancelar.addEventListener('mouseover', () => {
    btnCancelar.style.background = '#f1f5f9';
  });
  btnCancelar.addEventListener('mouseout', () => {
    btnCancelar.style.background = 'white';
  });

  btnConfirmar.addEventListener('mouseover', () => {
    btnConfirmar.style.background = '#dc2626';
  });
  btnConfirmar.addEventListener('mouseout', () => {
    btnConfirmar.style.background = '#ef4444';
  });

  btnCancelar.addEventListener('click', () => {
    modal.remove();
    if (onCancel) onCancel();
  });

  btnConfirmar.addEventListener('click', () => {
    modal.remove();
    if (onConfirm) onConfirm();
  });
}

function fecharConversa() {
  mostrarPopupConfirmacao(
    'Fechar Conversa',
    'Tem a certeza de que deseja fechar esta conversa? Esta ação não pode ser desfeita.',
    () => {
      // Confirmado
      const dados = {
        action: 'close_conversation_user',
        conversation_id: conversationId,
        email: localStorage.getItem('auth_email')
      };

      fetch('../api/live_chat.php', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(dados)
      })
        .then(response => response.json())
        .then(result => {
          if (result.success) {
            conversationId = null;
            localStorage.removeItem('chat_ajuda_conversation_id');
            messagesContainer.innerHTML = '';
            fecharChat();
            if (typeof showToast === 'function') {
              showToast('success', 'Conversa fechada com sucesso!');
            } else {
              alert('Conversa fechada com sucesso');
            }
          } else {
            if (typeof showToast === 'function') {
              showToast('error', 'Erro ao fechar conversa: ' + result.message);
            } else {
              alert('Erro: ' + result.message);
            }
          }
        })
        .catch(error => {
          console.error('Erro:', error);
          if (typeof showToast === 'function') {
            showToast('error', 'Erro ao fechar conversa');
          } else {
            alert('Erro ao fechar conversa');
          }
        });
    }
  );
}

function exibirMensagens(mensagens) {
  messagesContainer.innerHTML = '';

  mensagens.forEach((msg, index) => {
    const msgElement = document.createElement('div');
    msgElement.className = `mensagem mensagem-${msg.sender}`;

    const senderLabel = msg.sender === 'admin' ? 'Admin' : 'Você';
    const dataFormatada = new Date(msg.enviado_em).toLocaleString('pt-PT', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });

    msgElement.innerHTML = `
      <div class="mensagem-header">
        <span class="mensagem-sender">${senderLabel}</span>
        <span class="mensagem-hora">${dataFormatada}</span>
      </div>
      <div class="mensagem-conteudo"></div>
    `;

    const conteudoDiv = msgElement.querySelector('.mensagem-conteudo');

    // Se for a última mensagem e do admin, aplicar efeito de máquina de escrever
    if (index === mensagens.length - 1 && msg.sender === 'admin') {
      typewriter(conteudoDiv, msg.conteudo);
    } else {
      // Para todas as outras mensagens, exibir instantaneamente
      conteudoDiv.textContent = msg.conteudo;
    }

    messagesContainer.appendChild(msgElement);
  });

  if (mensagens.length > 0) {
    lastMessageTimestamp = mensagens[mensagens.length - 1].enviado_em;
  }

  messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function typewriter(element, text, speed = 20) {
  let i = 0;
  element.textContent = ''; // Limpar o conteúdo para começar a escrever

  function type() {
    if (i < text.length) {
      element.textContent += text.charAt(i);
      i++;
      // Manter o scroll no fundo enquanto a mensagem é escrita
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
      setTimeout(type, speed);
    }
  }
  type();
}

window.addEventListener('beforeunload', () => {
  pararVerificacaoNovasMensagens();
});

document.addEventListener('DOMContentLoaded', () => {
  // Esperar um pouco para garantir que o index.php já atualizou o auth_email
  setTimeout(() => {
    const storedConversationId = localStorage.getItem('chat_ajuda_conversation_id');
    const storedEmail = localStorage.getItem('auth_email');

    if (storedConversationId && storedEmail) {
      conversationId = parseInt(storedConversationId);
      lastAuthEmail = storedEmail;
    } else if (storedConversationId && !storedEmail) {
      // Não há email: limpar conversationId (utilizador fez logout)
      localStorage.removeItem('chat_ajuda_conversation_id');
      conversationId = null;
    }

    if (storedEmail) {
      lastAuthEmail = storedEmail;
    }
  }, 100);
});

window.addEventListener('storage', (e) => {
  if (e.key === 'auth_email') {
    const newEmail = e.newValue;
    const oldEmail = e.oldValue;

    if (oldEmail && newEmail && oldEmail !== newEmail) {
      // Email mudou (outro tab/window)
      localStorage.removeItem('chat_ajuda_conversation_id');
      conversationId = null;
      lastAuthEmail = newEmail;
    }
  }
});

document.addEventListener('click', (e) => {
  if (e.target === modalElement) {
    fecharChat();
  }
});
