// Sistema de Notificações Toast
// Classe responsável por gerir notificações flutuantes na interface
class ToastNotifications {
    constructor() {
        // Tentar obter o container ou criar se não existir
        let container = document.getElementById('toast-container');
        
        // Garante que o container existe e está no final do body
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            if (document.body) document.body.appendChild(container);
        } else if (document.body && container.parentNode !== document.body) {
            // Se já existe mas não está no body (está preso noutro div), move-o para o body
            document.body.appendChild(container);
        }

        this.container = container;
        this.toasts = [];
    }

    // Ícones SVG para diferentes tipos de notificação
    // Retorna o código SVG baseado no tipo (sucesso, erro, aviso, info)
    getIcon(type) {
        const icons = {
            success: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22,4 12,14.01 9,11.01"></polyline>
            </svg>`,
            error: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>`,
            warning: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>`,
            info: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>`
        };
        return icons[type] || icons.info;
    }

    // Criar uma nova notificação toast
    // Constrói o HTML da notificação, adiciona ao DOM e configura a remoção automática
    show(title, message, type = 'info', duration = 5000) {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <div class="toast-icon ${type}">
                ${this.getIcon(type)}
            </div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" onclick="toastNotifications.remove(this.closest('.toast'))">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        `;

        this.container.appendChild(toast);
        this.toasts.push(toast);

        // Animar entrada
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);

        // Auto-remover após duração especificada
        if (duration > 0) {
            setTimeout(() => {
                this.remove(toast);
            }, duration);
        }

        return toast;
    }

    // Remover notificação
    // Remove o elemento do DOM com uma animação de saída
    remove(toast) {
        if (!toast || !toast.parentNode) return;

        toast.classList.remove('show');
        
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
            
            // Remover da lista
            const index = this.toasts.indexOf(toast);
            if (index > -1) {
                this.toasts.splice(index, 1);
            }
        }, 300);
    }

    // Limpar todas as notificações
    // Útil ao mudar de página ou limpar o ecrã
    clear() {
        this.toasts.forEach(toast => this.remove(toast));
    }

    // Métodos de conveniência
    // Atalhos para criar notificações com tipos específicos
    success(title, message, duration = 4000) {
        return this.show(title, message, 'success', duration);
    }

    error(title, message, duration = 6000) {
        return this.show(title, message, 'error', duration);
    }

    warning(title, message, duration = 5000) {
        return this.show(title, message, 'warning', duration);
    }

    info(title, message, duration = 4000) {
        return this.show(title, message, 'info', duration);
    }
}

// Criar instância global
const toastNotifications = new ToastNotifications();

// Funções de conveniência globais
// Permite chamar showToast() de qualquer lugar da aplicação
window.showToast = function(arg1, arg2, arg3, arg4) {
    const types = ['success', 'error', 'warning', 'info'];
    
    // Suporte para chamada estilo: showToast('success', 'Mensagem') ou showToast('success', 'Titulo', 'Mensagem')
    if (types.includes(arg1)) {
        const type = arg1;
        // Se arg3 existe, arg2 é titulo e arg3 é mensagem
        if (arg3) {
            return toastNotifications[type](arg2, arg3, arg4);
        }
        // Se apenas arg2 existe, usamos como título (ou mensagem principal)
        return toastNotifications[type](arg2, ''); 
    }
    
    // Suporte para chamada estilo antigo: showToast('Titulo', 'Mensagem', 'tipo')
    return toastNotifications.show(arg1, arg2, arg3 || 'info', arg4);
};

// Anexar métodos para compatibilidade com chamadas showToast.success(...)
window.showToast.success = (t, m, d) => toastNotifications.success(t, m, d);
window.showToast.error = (t, m, d) => toastNotifications.error(t, m, d);
window.showToast.warning = (t, m, d) => toastNotifications.warning(t, m, d);
window.showToast.info = (t, m, d) => toastNotifications.info(t, m, d);

// Função para mostrar mensagens de login/signup
// Centraliza a lógica de feedback de autenticação
window.showAuthMessage = {
    loginSuccess: (nome) => {
        toastNotifications.success(
            'Bem-vindo! 🎉',
            `Olá ${nome}! A sua sessão foi iniciada com sucesso.`,
            4000
        );
    },
    
    // Trata diferentes erros de login com mensagens amigáveis
    loginError: (erro) => {
        if (erro.includes('Conta bloqueada')) {
            window.showAuthMessage.blockedAccount(erro);
            return;
        }
        
        let title = 'Erro no Login';
        let message = erro;
        
        if (erro.includes('Email não encontrado')) {
            title = 'Email não encontrado';
            message = 'Verifique se o email está correto ou crie uma nova conta.';
        } else if (erro.includes('Palavra-passe incorreta')) {
            title = 'Palavra-passe incorreta';
            message = 'Verifique a sua palavra-passe e tente novamente.';
        } else if (erro.includes('conexão')) {
            title = 'Erro de Conexão';
            message = 'Verifique a sua ligação à internet e tente novamente.';
        }
        
        toastNotifications.error(title, message, 6000);
    },
    
    signupSuccess: () => {
        toastNotifications.success(
            'Conta Criada! ✅',
            'A sua conta foi criada com sucesso. Agora pode fazer login.',
            4000
        );
    },
    
    signupError: (erro) => {
        let title = 'Erro ao Criar Conta';
        let message = erro;
        
        // Personalizar mensagens baseado no tipo de erro
        if (erro.includes('já existe')) {
            title = 'Email já registado';
            message = 'Este email já está associado a uma conta. Tente fazer login.';
        } else if (erro.includes('Email inválido')) {
            title = 'Email inválido';
            message = 'Por favor, insira um email válido.';
        } else if (erro.includes('obrigatórios')) {
            title = 'Campos obrigatórios';
            message = 'Por favor, preencha todos os campos.';
        } else if (erro.includes('conexão')) {
            title = 'Erro de Conexão';
            message = 'Verifique a sua ligação à internet e tente novamente.';
        }
        
        toastNotifications.error(title, message, 6000);
    },
    
    validationError: (erro) => {
        toastNotifications.warning(
            'Atenção! ⚠️',
            erro,
            4000
        );
    },
    
    // Exibe modal específico para contas bloqueadas
    blockedAccount: (erro) => {
        const modal = document.getElementById('blockedAccountModal');
        const messageElement = document.getElementById('blockedMessage');
        const closeBtn = document.getElementById('closeBlockedModal');
        
        if (!modal || !messageElement) return;
        
        const motivo = erro.replace('Conta bloqueada. Motivo: ', '').trim();
        messageElement.textContent = motivo || 'Bloqueio administrativo';
        
        modal.style.display = 'block';
        document.body.classList.add('modal-active');
        
        if (closeBtn && !closeBtn.hasListener) {
            closeBtn.addEventListener('click', () => {
                modal.style.display = 'none';
                document.body.classList.remove('modal-active');
            });
            closeBtn.hasListener = true;
        }
        
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
                document.body.classList.remove('modal-active');
            }
        });
    }
};

// Adicionar Favicon automaticamente se não existir
// Garante que o site tem sempre um ícone na aba do navegador
(function() {
    let link = document.querySelector("link[rel~='icon']");
    if (!link) {
        link = document.createElement('link');
        link.rel = 'icon';
        document.head.appendChild(link);
    }
    link.href = 'nova-logo-removebg.png';
})();
