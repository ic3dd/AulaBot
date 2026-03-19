// Mapa de nomes de cores → códigos hexadecimais
const colorMap = {
    'Indigo': '#4f46e5',
    'Verde': '#facc15',
    'Vermelho': '#ef4444',
    'Azul': '#3b82f6',
    'Roxo': '#8b5cf6',
    'Ciano': '#06b6d4'
};

let temaSelect, corBtns;
let currentTheme = 'light';
let currentColor = '#4f46e5';
let currentFont = 'medium';
let hasUnsavedChanges = false;
let inputMensagemEl = null;
let botaoEnviarEl = null;

function showNotification(type, message) {
    if (typeof showToast === 'function') showToast(type, message);
    else console.log(`${type}: ${message}`);
}

// Salvar preferências
async function savePreferences(tema, corNome, fonte = currentFont) {
    try {
        const response = await fetch('scripts/salvar_preferencias.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tema, cor: corNome, fonte })
        });

        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Erro ao salvar preferências');

        console.log('Preferências salvas:', data);
        showNotification('success', 'Preferências salvas com sucesso!');
        // sincronizar fonte retornada pelo servidor se houver
        if (data.fonte) currentFont = data.fonte;
        return true;
    } catch (error) {
        console.error('Erro ao salvar preferências:', error);
        showNotification('error', 'Falha ao salvar preferências.');
        throw error;
    }
}

// Aplicar tema
function applyTheme(theme, save = false) {
    console.log('Aplicando tema:', theme);
    document.body.classList.toggle('theme-dark', theme === 'dark');
    currentTheme = theme;
    if (temaSelect) temaSelect.value = theme;
    hasUnsavedChanges = true;
    if (save) {
        savePreferences(theme, currentColor, currentFont);
        hasUnsavedChanges = false;
    }
}

// Aplicar cor
function hexToRgb(hex) {
    // Remove # se existir
    hex = hex.replace('#', '');
    if (hex.length === 3) {
        hex = hex.split('').map(c => c + c).join('');
    }
    const int = parseInt(hex, 16);
    return [(int >> 16) & 255, (int >> 8) & 255, int & 255];
}

async function checkUnreadMessages() {
    try {
        const response = await fetch('api/api_check_unread.php');
        const data = await response.json();
        const ajudaLink = document.getElementById('ajuda-link');

        if (data.success && data.unread) {
            ajudaLink?.classList.add('has-unread');
        } else {
            ajudaLink?.classList.remove('has-unread');
        }
    } catch (error) {
        console.error('Error checking unread messages:', error);
    }
}

function applyCor(corNome, save = false) {
    console.log('Aplicando cor:', corNome);
    // corNome pode ser um nome (e.g. 'Verde') ou um hex (e.g. '#28a745')
    const corHex = (typeof corNome === 'string' && corNome.startsWith('#')) ? corNome : (colorMap[corNome] || '#4f46e5');

    // Calcular rgba para o anel/halo e sombra
    const [r, g, b] = hexToRgb(corHex);
    const ring = `rgba(${r}, ${g}, ${b}, 0.18)`;
    const shadow = `0 4px 12px rgba(${r}, ${g}, ${b}, 0.26)`;
    document.documentElement.style.setProperty('--accent-color', corHex);
    document.documentElement.style.setProperty('--accent-ring', ring);
    document.documentElement.style.setProperty('--accent-shadow', shadow);

    // Atualizar marca visual dos botões de cor
    corBtns.forEach(btn => {
        btn.style.outline = 'none';
        // usar um indicador de seleção visual (borda) separado
        if (btn.dataset.cor === corNome) {
            btn.style.borderColor = '#00000033';
            btn.classList.add('active');
        } else {
            btn.style.borderColor = 'transparent';
            btn.classList.remove('active');
        }
    });

    // Também atualizar elementos preview que usam --accent-color
    currentColor = corNome;
    hasUnsavedChanges = true;
    if (save) {
        savePreferences(currentTheme, corNome, currentFont).catch(e => console.error(e));
        hasUnsavedChanges = false;
    }
    // Aplicar também inline para garantir visibilidade imediata
    try {
        if (!botaoEnviarEl) botaoEnviarEl = document.getElementById('botaoEnviar');
        if (botaoEnviarEl) botaoEnviarEl.style.boxShadow = shadow;

        if (!inputMensagemEl) inputMensagemEl = document.getElementById('inputMensagem');
        // se o input estiver focado, aplicar o halo inline (assim funciona mesmo se o :focus estiver a ser sobrescrito)
        if (inputMensagemEl && document.activeElement === inputMensagemEl) {
            inputMensagemEl.style.boxShadow = `0 0 0 6px ${ring}`;
            inputMensagemEl.style.borderColor = corHex;
        } else if (inputMensagemEl) {
            // limpar boxShadow inline quando não estiver focado (usa CSS variable para foco)
            inputMensagemEl.style.boxShadow = '';
            inputMensagemEl.style.borderColor = '';
        }
    } catch (e) {
        console.warn('Não foi possível aplicar estilos inline:', e);
    }
}



// wrapper async para chamada de save sem bloquear a função sync
// (removido - usar await diretamente agora)

// Carregar preferências
async function loadPreferences() {
    console.log('Carregando preferências...');
    try {
        const response = await fetch('scripts/carregar_preferencias.php');

        if (!response.ok) {
            throw new Error(`Servidor respondeu com status ${response.status}`);
        }

        const data = await response.json();

        if (data.success && data.preferences) {
            const { tema, cor, fonte } = data.preferences;
            console.log('Preferências recebidas:', tema, cor, fonte);
            applyTheme(tema);
            applyCor(cor);
            currentFont = fonte || 'medium';
            document.body.classList.remove('font-small', 'font-medium', 'font-large');
            document.body.classList.add(`font-${currentFont}`);
        } else {
            console.log('Usuário não autenticado ou preferências inválidas, aplicando padrão');
            applyTheme('light');
            applyCor('#4f46e5');
            currentFont = 'medium';
            document.body.classList.remove('font-small', 'font-medium', 'font-large');
            document.body.classList.add(`font-${currentFont}`);
        }
    } catch (e) {
        console.error('Falha ao carregar preferências, usando padrão:', e.message);
        applyTheme('light');
        applyCor('#4f46e5');
    }
}

// Inicialização
document.addEventListener('DOMContentLoaded', function () {
    temaSelect = document.getElementById('personalizacaoTema');
    corBtns = document.querySelectorAll('.cor-btn');

    if (temaSelect) temaSelect.addEventListener('change', e => applyTheme(e.target.value));
    corBtns.forEach(btn => btn.addEventListener('click', () => applyCor(btn.dataset.cor)));

    loadPreferences();
    checkUnreadMessages();

    setInterval(checkUnreadMessages, 15000);

    // Capturar elementos de input e botão para efeitos inline
    inputMensagemEl = document.getElementById('inputMensagem');
    botaoEnviarEl = document.getElementById('botaoEnviar');

    // Garantir halo inline no foco do input (fallback caso CSS :focus seja sobrescrito)
    if (inputMensagemEl) {
        inputMensagemEl.addEventListener('focus', () => {
            // obter valor atual do ring a partir da variável CSS
            const ring = getComputedStyle(document.documentElement).getPropertyValue('--accent-ring').trim();
            const accent = getComputedStyle(document.documentElement).getPropertyValue('--accent-color').trim() || '#4f46e5';
            inputMensagemEl.style.boxShadow = `0 0 0 6px ${ring}`;
            inputMensagemEl.style.borderColor = accent;
        });
        inputMensagemEl.addEventListener('blur', () => {
            inputMensagemEl.style.boxShadow = '';
            inputMensagemEl.style.borderColor = '';
        });
    }

    const btnAplicar = document.getElementById('aplicarPersonalizacao');
    const btnCancelar = document.getElementById('cancelarPersonalizacao');
    const modalPersonalizacao = document.getElementById('popup-personalizacao');
    const opcoesModal = document.getElementById('popup-opcoes');

    if (btnAplicar) {
        btnAplicar.addEventListener('click', async () => {
            btnAplicar.disabled = true;
            btnAplicar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
            await savePreferences(currentTheme, currentColor, currentFont);
            hasUnsavedChanges = false;
            modalPersonalizacao.style.display = 'none';
            opcoesModal.style.display = 'block';
            btnAplicar.disabled = false;
            btnAplicar.innerHTML = '<i class="fas fa-save"></i> Aplicar Alterações';
        });
    }

    if (btnCancelar) {
        btnCancelar.addEventListener('click', async () => {
            if (hasUnsavedChanges) {
                await loadPreferences();
                hasUnsavedChanges = false;
            }
            modalPersonalizacao.style.display = 'none';
        });
    }
});
