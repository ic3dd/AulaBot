/**
 * =================================================================================
 * SCRIPT PRINCIPAL - VERSÃO OTIMIZADA (Marked + KaTeX + Streaming)
 * Version: 2.1.0 - Vision Model Updated
 * =================================================================================
 */// 1. CONFIGURAÇÃO DAS BIBLIOTECAS
// ---------------------------------------------------------------------------------
if (typeof marked !== 'undefined') {
    marked.use({
        breaks: true, // Quebra de linha com Enter
        gfm: true     // GitHub Flavored Markdown (tabelas, etc)
    });
} else {
    console.error("ERRO CRÍTICO: Biblioteca 'marked' não encontrada. Adicione o CDN no HTML.");
}

// 2. VARIÁVEIS DE ESTADO
// ---------------------------------------------------------------------------------
let chatAtivoId = null;
let chatAtivoTitulo = null;
let pendingReferencia = null; // Guarda o contexto para botões de ação (Resumo, etc.)
let renderTimer = null;       // Para controlar a fluidez do texto a escrever

// 3. SELEÇÃO DE ELEMENTOS DOM
// ---------------------------------------------------------------------------------
const inputMensagem = document.getElementById('inputMensagem');
const botaoEnviar = document.getElementById('botaoEnviar');
const containerChat = document.getElementById('containerChat');
const barraLateral = document.querySelector('.barra-lateral');
const sobreposicao = document.querySelector('.sobreposicao');
const botaoMenu = document.querySelector('.alternar-menu');
const botaoMenuDesktop = document.querySelector('.botao-menu-desktop');
const conteudoPrincipal = document.querySelector('.conteudo-principal');

// Estado de anexos / visão
let attachedImageFile = null;
let attachedImageDescription = '';
let lastContextImageDescription = '';
let lastContextImageUrl = null;
let lastContextFetchPromise = null;
let attachedImageURL = null;
let attachedOcrBadge = null;
let uploadPromise = null;
let previewContainer = null;
let imgRender = null;
let lastObjectUrl = null;
let helpTooltip = null;
let dragDropZone = null;

// 4. INICIALIZAÇÃO (DOMContentLoaded)
// ---------------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    ajustarLayout();
    attachHistoricoHandlers();
    scrollParaFundo();

    // Eventos de Input
    if (inputMensagem) {
        inputMensagem.addEventListener('input', (e) => {
            // Se o utilizador editar o texto manualmente, remove o contexto pendente
            if (inputMensagem.dataset.pending && inputMensagem.value.trim() === '') {
                delete inputMensagem.dataset.pending;
                pendingReferencia = null;
            }
            autoExpandTextarea(e);
        });

        inputMensagem.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                enviarMensagem();
            }
        });
        // Colar imagem (Ctrl+V / paste)
        inputMensagem.addEventListener('paste', (e) => {
            handlePaste(e);
        });
    }

    // Fallback: escuta paste a nível de documento para capturar imagens coladas fora do textarea
    document.addEventListener('paste', (e) => {
        // Apenas processa se ainda não houver anexos
        if (!attachedImageFile) handlePaste(e);
    });

    if (botaoEnviar) botaoEnviar.addEventListener('click', enviarMensagem);

    // Preparar elementos de pré-visualização e input-ficheiro
    previewContainer = document.getElementById('preview-container');
    imgRender = document.getElementById('img-render');
    const inputFicheiroEl = document.getElementById('input-ficheiro');
    if (inputFicheiroEl) {
        inputFicheiroEl.addEventListener('change', function () { visualizarImagem(this); });
    }

    // Setup Tooltip de Ajuda
    helpTooltip = document.getElementById('input-help-tooltip');
    dragDropZone = document.querySelector('.container-input');

    // Comentado: Tooltip removido conforme solicitado
    // if (inputMensagem && helpTooltip) {
    //     inputMensagem.addEventListener('focus', () => {
    //         if (!attachedImageFile) {
    //             helpTooltip.style.display = '';
    //         }
    //     });
    //     inputMensagem.addEventListener('blur', () => {
    //         helpTooltip.style.display = 'none';
    //     });
    // }

    // Setup Drag & Drop
    if (dragDropZone) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dragDropZone.addEventListener(eventName, preventDefaults, false);
        });
        dragDropZone.addEventListener('dragenter', highlightDropZone, false);
        dragDropZone.addEventListener('dragover', highlightDropZone, false);
        dragDropZone.addEventListener('dragleave', unhighlightDropZone, false);
        dragDropZone.addEventListener('drop', handleDrop, false);
    }

    // Também aceita drag & drop a nível de documento
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        document.addEventListener(eventName, preventDefaults, false);
    });

    // Botão Nova Conversa
    const btnNova = document.querySelector('.botao-nova-conversa');
    if (btnNova) btnNova.addEventListener('click', novaConversa);

    // Atualizar layout ao redimensionar
    window.addEventListener('resize', () => {
        ajustarLayout();
        autoExpandTextarea(inputMensagem);
    });
});

// 5. FUNÇÕES DE UI (INTERFACE)
// ---------------------------------------------------------------------------------
function ajustarLayout() {
    if (window.innerWidth <= 768) {
        // Mobile
        if (barraLateral) barraLateral.classList.remove('oculta');
        if (conteudoPrincipal) conteudoPrincipal.classList.remove('barra-oculta');
    }
}

// Menu Mobile
if (botaoMenu) {
    botaoMenu.addEventListener('click', () => {
        barraLateral?.classList.toggle('activa');
        sobreposicao?.classList.toggle('activa');
    });
}

// Menu Desktop
if (botaoMenuDesktop) {
    botaoMenuDesktop.addEventListener('click', () => {
        barraLateral?.classList.toggle('oculta');
        conteudoPrincipal?.classList.toggle('barra-oculta');
    });
}

// Fechar menu ao clicar fora
if (sobreposicao) {
    sobreposicao.addEventListener('click', () => {
        barraLateral?.classList.remove('activa');
        sobreposicao.classList.remove('activa');
    });
}

function autoExpandTextarea(elem) {
    const el = elem?.target || elem;
    if (!el) return;
    el.style.height = 'auto';
    const maxHeight = 200;
    el.style.height = `${Math.min(el.scrollHeight, maxHeight)}px`;
    el.style.overflow = el.scrollHeight > maxHeight ? 'auto' : 'hidden';
}

function scrollParaFundo() {
    if (!containerChat) return;
    containerChat.scrollTo({
        top: containerChat.scrollHeight,
        behavior: 'smooth'
    });
}

function obterHoraAtual() {
    return `Hoje, ${new Date().toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' })}`;
}

// Funções de Drag & Drop
function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

function highlightDropZone(e) {
    preventDefaults(e);
    if (dragDropZone) {
        dragDropZone.style.backgroundColor = 'rgba(40, 167, 69, 0.05)';
        dragDropZone.style.borderColor = '#28a745';
        dragDropZone.style.boxShadow = '0 0 10px rgba(40, 167, 69, 0.2)';
    }
}

function unhighlightDropZone(e) {
    if (dragDropZone) {
        dragDropZone.style.backgroundColor = '';
        dragDropZone.style.borderColor = '';
        dragDropZone.style.boxShadow = '';
    }
}

function handleDrop(e) {
    preventDefaults(e);
    unhighlightDropZone();
    const dt = e.dataTransfer;
    const files = dt.files;
    if (files && files.length > 0) {
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            if (file.type.indexOf('image') !== -1) {
                processImageBlob(file);
                break; // Processa apenas o primeiro ficheiro de imagem
            }
        }
    }
}

// -------------------------
// FUNÇÕES DE ANEXOS / VISÃO
// -------------------------

function removeOcrFailedBadge() {
    try {
        if (attachedOcrBadge && attachedOcrBadge.parentElement) {
            attachedOcrBadge.remove();
        }
        attachedOcrBadge = null;
    } catch (e) {
        // ignore
    }
}

function showOcrFailedBadge(message) {
    try {
        if (!previewContainer) previewContainer = document.getElementById('preview-container');
        if (!previewContainer) return;
        removeOcrFailedBadge();
        const badge = document.createElement('div');
        badge.className = 'ocr-failed-badge';
        const text = document.createElement('div');
        text.className = 'ocr-failed-text';
        text.textContent = message;
        const actions = document.createElement('div');
        actions.className = 'ocr-failed-actions';
        const copyBtn = document.createElement('button');
        copyBtn.className = 'ocr-failed-copy';
        copyBtn.type = 'button';
        copyBtn.textContent = 'Copiar para caixa de texto';
        copyBtn.addEventListener('click', () => {
            if (inputMensagem) {
                inputMensagem.value = message;
                inputMensagem.focus();
            }
            removeOcrFailedBadge();
        });
        const closeBtn = document.createElement('button');
        closeBtn.className = 'ocr-failed-close';
        closeBtn.type = 'button';
        closeBtn.textContent = 'Fechar';
        closeBtn.addEventListener('click', () => removeOcrFailedBadge());
        actions.appendChild(copyBtn);
        actions.appendChild(closeBtn);
        badge.appendChild(text);
        badge.appendChild(actions);
        previewContainer.appendChild(badge);
        attachedOcrBadge = badge;
    } catch (e) {
        console.warn('showOcrFailedBadge erro', e);
    }
}

function handlePaste(e) {
    try {
        const items = (e.clipboardData || window.clipboardData).items;
        if (!items) return;

        for (let i = 0; i < items.length; i++) {
            const item = items[i];

            // Tipo 1: Ficheiro de imagem (arquivo)
            if (item.kind === 'file' && item.type.indexOf('image') !== -1) {
                e.preventDefault();
                const blob = item.getAsFile();
                if (blob) {
                    processImageBlob(blob);
                }
                break;
            }

            // Tipo 2: Imagem direta do clipboard (ex: print do Windows, cópia de imagem)
            if (item.type.indexOf('image') !== -1) {
                e.preventDefault();
                item.getAsFile((blob) => {
                    if (blob) {
                        processImageBlob(blob);
                    }
                });
                break;
            }

            // Tipo 3: HTML que contém imagem (fallback para algumas fontes)
            if (item.type === 'text/html') {
                item.getAsString((html) => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const img = doc.querySelector('img');
                    if (img && img.src) {
                        e.preventDefault();
                        // Se a imagem vem como data URL, converte em blob
                        if (img.src.indexOf('data:') === 0) {
                            fetch(img.src)
                                .then(res => res.blob())
                                .then(blob => processImageBlob(blob))
                                .catch(err => console.warn('HTML img fetch falhou', err));
                        }
                    }
                });
            }
        }
    } catch (err) {
        console.warn('handlePaste falhou', err);
    }
}

function processImageBlob(blob) {
    showPreview(blob);
    showUploadSpinner();
    showAnalysisStatus('analisando', 'A analisar imagem...');
    uploadPromise = uploadImageToVision(blob).then(result => {
        attachedImageDescription = (result && result.description) ? result.description : '';
        attachedImageURL = (result && result.image_url) ? result.image_url : null;
        const ocrFailed = !!(result && result.ocr_failed);

        if (!ocrFailed && attachedImageDescription && !attachedImageDescription.toLowerCase().includes('não foi possível')) {
            removeOcrFailedBadge();
            if (attachedImageDescription.includes('OCR')) {
                showAnalysisStatus('sucesso', '✓ Texto extraído via OCR');
            } else {
                showAnalysisStatus('sucesso', '✓ Imagem analisada com sucesso');
            }
        } else {
            // OCR fallback or empty result — show persistent badge with action
            showAnalysisStatus('erro', '⚠ Análise parcial - descreve manualmente se necessário');
            showOcrFailedBadge(attachedImageDescription || 'Não foi possível extrair texto legível da imagem.');
        }
    }).catch(err => {
        console.error('Erro ao analisar imagem:', err);
        attachedImageDescription = '';
        showAnalysisStatus('erro', '✗ Erro ao analisar - podes descrever manualmente');
    }).finally(() => {
        hideUploadSpinner();
        // Remove status depois de 4 segundos
        setTimeout(() => removeAnalysisStatus(), 4000);
    });
}

function visualizarImagem(input) {
    try {
        const file = input.files && input.files[0];
        if (!file) return;
        processImageBlob(file);
    } catch (e) {
        console.error('visualizarImagem erro', e);
    }
}

function showPreview(file) {
    attachedImageFile = file;
    if (!previewContainer || !imgRender) {
        previewContainer = document.getElementById('preview-container');
        imgRender = document.getElementById('img-render');
    }
    if (imgRender) {
        try {
            if (lastObjectUrl) {
                try { URL.revokeObjectURL(lastObjectUrl); } catch (e) { }
                lastObjectUrl = null;
            }
            const url = URL.createObjectURL(file);
            lastObjectUrl = url;
            imgRender.src = url;
            imgRender.style.display = '';
            // Animação suave de entrada
            imgRender.style.animation = 'none';
            setTimeout(() => {
                imgRender.style.animation = 'previewFadeIn 0.3s ease-out';
            }, 10);
        } catch (e) {
            console.warn('showPreview URL.createObjectURL falhou', e);
        }
    }
    if (previewContainer) {
        previewContainer.style.animation = 'previewSlideUp 0.3s ease-out';
        previewContainer.style.display = 'block';
    }
}



function showUploadSpinner() {
    try {
        if (!previewContainer) previewContainer = document.getElementById('preview-container');
        let spinner = previewContainer.querySelector('.preview-spinner');
        if (!spinner) {
            spinner = document.createElement('div');
            spinner.className = 'preview-spinner';
            spinner.style.position = 'absolute';
            spinner.style.bottom = '4px';
            spinner.style.right = '4px';
            spinner.style.width = '32px';
            spinner.style.height = '32px';
            spinner.style.background = 'rgba(255,255,255,0.9)';
            spinner.style.border = '3px solid rgba(0,0,0,0.1)';
            spinner.style.borderTop = '3px solid rgba(40,167,69,0.8)';
            spinner.style.borderRadius = '50%';
            spinner.style.animation = 'preview-spin 0.8s linear infinite';
            spinner.style.boxShadow = '0 2px 6px rgba(0,0,0,0.15)';
            spinner.setAttribute('role', 'status');
            spinner.setAttribute('aria-label', 'A analisar imagem');
            previewContainer.style.position = 'relative';

            // Encontra a imagem dentro do container
            const img = previewContainer.querySelector('img');
            if (img) {
                img.parentElement.appendChild(spinner);
            } else {
                previewContainer.appendChild(spinner);
            }
        }
        spinner.style.display = '';
    } catch (e) {
        console.warn('showUploadSpinner erro', e);
    }
}

function hideUploadSpinner() {
    try {
        if (!previewContainer) previewContainer = document.getElementById('preview-container');
        const spinner = previewContainer ? previewContainer.querySelector('.preview-spinner') : null;
        if (spinner) spinner.style.display = 'none';
    } catch (e) {
        console.warn('hideUploadSpinner erro', e);
    }
}

// CSS keyframes para animações (injetado dinamicamente uma vez)
(function injectPreviewStyles() {
    try {
        if (document.getElementById('preview-styles')) return;
        const style = document.createElement('style');
        style.id = 'preview-styles';
        style.innerHTML = `
            @keyframes preview-spin { 
                from { transform: rotate(0deg); } 
                to { transform: rotate(360deg); } 
            }
            @keyframes previewFadeIn {
                from { opacity: 0; transform: scale(0.95); }
                to { opacity: 1; transform: scale(1); }
            }
            @keyframes previewSlideUp {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes fadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    } catch (e) {
        // ignore
    }
})();



function removerAnexo() {
    attachedImageFile = null;
    attachedImageDescription = '';
    uploadPromise = null;
    removeAnalysisStatus();
    removeOcrFailedBadge();
    if (!previewContainer || !imgRender) {
        previewContainer = document.getElementById('preview-container');
        imgRender = document.getElementById('img-render');
    }

    // Animação de saída suave
    if (previewContainer) {
        previewContainer.style.animation = 'previewSlideUp 0.3s ease-out reverse';
        setTimeout(() => {
            previewContainer.style.display = 'none';
            previewContainer.style.animation = '';
        }, 300);
    }

    if (imgRender) {
        imgRender.src = '';
    }
    if (lastObjectUrl) {
        try { URL.revokeObjectURL(lastObjectUrl); } catch (e) { }
        lastObjectUrl = null;
    }
    const inputF = document.getElementById('input-ficheiro');
    if (inputF) inputF.value = '';
}

function showAnalysisStatus(status, message) {
    try {
        if (!previewContainer) previewContainer = document.getElementById('preview-container');
        if (!previewContainer) return;

        let statusEl = previewContainer.querySelector('.analysis-status');
        if (!statusEl) {
            statusEl = document.createElement('div');
            previewContainer.appendChild(statusEl);
        }
        statusEl.textContent = message;
        statusEl.style.display = '';
        statusEl.className = 'analysis-status status-' + (status || 'info');
    } catch (e) {
        console.warn('showAnalysisStatus erro', e);
    }
}

function removeAnalysisStatus() {
    try {
        if (!previewContainer) previewContainer = document.getElementById('preview-container');
        if (!previewContainer) return;

        const statusEl = previewContainer.querySelector('.analysis-status');
        if (statusEl) {
            statusEl.style.animation = 'fadeOut 0.3s ease-out';
            setTimeout(() => {
                if (statusEl.parentElement) {
                    statusEl.remove();
                }
            }, 300);
        }
    } catch (e) {
        console.warn('removeAnalysisStatus erro', e);
    }
}

async function uploadImageToVision(file) {
    // Compress large images client-side to speed upload and reduce server-side failures
    const prepared = await compressImageIfNeeded(file, { maxWidth: 1200, maxHeight: 1200, quality: 0.8, maxSizeBytes: 900000 });
    const fd = new FormData();
    const finalFile = prepared instanceof File ? prepared : file;
    fd.append('image', finalFile, finalFile.name || 'paste.png');

    // Use AbortController to timeout the request client-side faster than server
    const controller = new AbortController();
    const timeoutMs = 60000; // 60s
    const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

    try {
        const res = await fetch('api/api_vision.php', {
            method: 'POST',
            body: fd,
            signal: controller.signal
        });
        clearTimeout(timeoutId);
        if (!res.ok) throw new Error('Falha no upload (HTTP ' + res.status + ')');
        const json = await res.json();
        if (json && json.status === 'success') {
            return { description: json.description || '', ocr_failed: !!json.ocr_failed, image_url: json.image_url || null };
        }
        throw new Error(json && json.message ? json.message : 'Erro na API de visão');
    } catch (err) {
        if (err.name === 'AbortError') {
            console.error('uploadImageToVision timeout', err);
            throw new Error('Timeout no upload da imagem.');
        }
        console.error('uploadImageToVision erro', err);
        throw err;
    }
}

// Compress image client-side when it exceeds thresholds. Returns a File or the original file.
function compressImageIfNeeded(file, opts = {}) {
    const { maxWidth = 1400, maxHeight = 1400, quality = 0.8, maxSizeBytes = 1200 * 1024 } = opts;
    return new Promise((resolve) => {
        try {
            if (!file || !(file.type && file.type.indexOf('image') === 0)) return resolve(file);
            if (file.size <= maxSizeBytes) return resolve(file);

            const img = new Image();
            const url = URL.createObjectURL(file);
            img.onload = () => {
                let width = img.naturalWidth;
                let height = img.naturalHeight;
                let ratio = Math.min(1, maxWidth / width, maxHeight / height);
                // Verifica se o redimensionamento é necessário (se a taxa < 1)
                if (ratio >= 1) {
                    URL.revokeObjectURL(url);
                    return resolve(file); // Imagem já é pequena o suficiente
                }

                // Cria um Canvas invisível para desenhar a imagem redimensionada
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(width * ratio);
                canvas.height = Math.round(height * ratio);
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                // Determina o tipo de saída: mantemos WebP se for original, senão convertemos para JPEG (mais leve)
                const outType = (file.type === 'image/webp') ? 'image/webp' : 'image/jpeg';
                canvas.toBlob((blob) => {
                    URL.revokeObjectURL(url);
                    if (!blob) return resolve(file);

                    // Lógica recursiva: Se após compressão ainda for maior que o limite, reduz qualidade
                    if (blob.size > maxSizeBytes && outType === 'image/jpeg') {
                        // Tenta reduzir a qualidade (quality - 0.1) em loop
                        (function reduce(q) {
                            canvas.toBlob((b2) => {
                                if (!b2) return resolve(file);
                                // Se aceitável ou qualidade já muito baixa, resolve
                                if (b2.size <= maxSizeBytes || q <= 0.45) {
                                    const f = new File([b2], file.name || 'image.jpg', { type: outType });
                                    resolve(f);
                                } else {
                                    reduce(q - 0.15); // Continua a reduzir
                                }
                            }, outType, q);
                        })(quality - 0.1);
                    } else {
                        // Tamanho aceitável, retorna o novo ficheiro
                        const f = new File([blob], file.name || 'image.jpg', { type: outType });
                        resolve(f);
                    }
                }, outType, quality);
            };
            img.onerror = () => {
                try { URL.revokeObjectURL(url); } catch (e) { }
                resolve(file);
            };
            img.src = url;
        } catch (e) {
            console.warn('compressImageIfNeeded failed', e);
            resolve(file);
        }
    });
}


// 6. LÓGICA CORE: ENVIAR MENSAGEM (SEM STREAMING)
// ---------------------------------------------------------------------------------
async function enviarMensagem() {
    if (!inputMensagem) return;
    const texto = inputMensagem.value.trim();

    // Se não houver texto nem anexo, nada a fazer
    if (!texto && !attachedImageFile && !attachedImageDescription) return;

    // Se existe imagem anexada e o upload/descrição está em curso, aguarda
    if (attachedImageFile && uploadPromise) {
        try {
            await uploadPromise;
        } catch (err) {
            console.warn('Upload imagem falhou ou demorou:', err);
        }
    }

    // 1. Mostrar mensagem do utilizador NA INTERFACE
    // Isto dá feedback imediato ao utilizador antes da resposta do servidor.
    if (texto) {
        adicionarMensagemUsuario(texto);
    } else {
        adicionarMensagemUsuario('[Imagem]');
    }

    // Se há pré-visualização, anexar a imagem à bolha do utilizador
    try {
        if (imgRender && imgRender.src) {
            const lastUser = containerChat.querySelector('.mensagem-utilizador:last-child .conteudo-mensagem');
            if (lastUser) {
                const imgEl = document.createElement('img');
                imgEl.src = imgRender.src;
                imgEl.style.maxHeight = '140px';
                imgEl.style.display = 'block';
                imgEl.style.marginTop = '8px';
                imgEl.style.borderRadius = '8px';
                lastUser.appendChild(imgEl);
            }
        }
    } catch (e) {
        console.warn('Não foi possível anexar pré-visualização na UI', e);
    }

    // 2. Preparar Payload - CARREGA DISCIPLINAS DINAMICAMENTE
    // Tenta carregar disciplinas do formulário OU faz um fetch para as obter
    let materias = getSelectedSubjects();

    // Se não conseguiu obter do formulário, tenta fetch assíncrono
    if (materias.length === 0) {
        try {
            const resMaterias = await fetch('scripts/carregar_materias.php');
            const dataMaterias = await resMaterias.json();
            if (dataMaterias.success && dataMaterias.disciplina) {
                materias = dataMaterias.disciplina;
            }
        } catch (e) {
            console.warn('Não foi possível carregar disciplinas:', e);
        }
    }

    const payload = {
        message: texto,
        id_chat: chatAtivoId,
        materias: materias
    };

    if (attachedImageDescription) {
        payload.image_description = attachedImageDescription;
    }
    if (attachedImageURL) {
        payload.image_url = attachedImageURL;
    }

    // Se não há nova imagem mas tínhamos pré-carregado um contexto (imagem anterior), reutilizamos.
    // Isto permite "conversar" sobre a imagem anterior sem a re-enviar.
    if (!attachedImageDescription && lastContextFetchPromise) {
        try {
            await lastContextFetchPromise; // Espera que o fetch de fundo termine
        } catch (e) {
            // Ignora erro
        }
    }
    if (!attachedImageDescription && lastContextImageDescription) {
        payload.image_description = lastContextImageDescription;
        if (lastContextImageUrl) payload.image_url = lastContextImageUrl;
    }

    // Adiciona referência se for uma ação (ex: "Resumir isto")
    if (inputMensagem.dataset.pending && pendingReferencia) {
        payload.referencia = pendingReferencia;
        // Limpa estado pendente
        pendingReferencia = null;
        delete inputMensagem.dataset.pending;
    }

    // 3. Bloquear UI
    inputMensagem.value = '';
    autoExpandTextarea(inputMensagem);
    inputMensagem.disabled = true;
    botaoEnviar.disabled = true;

    // 4. Criar bolha do Bot vazia
    const botContentElement = adicionarMensagemBot('', false);

    // Garantir que o elemento está pronto (pequeno delay)
    await new Promise(resolve => setTimeout(resolve, 10));

    // 5. Iniciar Fetch (POST)
    try {
        const res = await fetch('api/api_chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (!res.ok) {
            let errData = null;
            let bodyText = null;
            try {
                errData = await res.json();
            } catch (e) {
                // Not JSON — read text body for diagnostics
                try { bodyText = await res.text(); } catch (e2) { bodyText = null; }
                errData = { message: bodyText || 'Invalid JSON response from server' };
            }
            // Rate limit (too many requests) -> show a friendly toast advising to wait
            if (res.status === 429) {
                const msg = errData?.message || 'Demasiadas solicitações. Aguarde alguns segundos.';
                if (window.showToast && typeof window.showToast.warning === 'function') {
                    window.showToast.warning('Aguarde', msg, 4000);
                } else {
                    alert(msg);
                }
            }
            // Se a resposta for erro devido a LIMITES DE CONVIDADO (403 Limit Reached)
            if (res.status === 403 && errData && errData.message && String(errData.message).toLowerCase().includes('limite')) {
                if (window.showToast && typeof window.showToast.warning === 'function') {
                    window.showToast.warning('Login necessário', errData.message, 5000);
                }
                // Redireciona para a welcome.php onde o modal de login vai abrir
                setTimeout(() => {
                    window.location.href = 'welcome.php';
                }, 1000);
            }
            // Log para debug se necessário
            if (bodyText && console && console.error) console.error('Server body:', bodyText);
            throw new Error(errData?.message || `Erro HTTP: ${res.status}`);
        }
        const data = await res.json();

        if (data.error) throw new Error(data.error);

        // Atualiza ID se for chat novo
        if (data.id_chat && !chatAtivoId) {
            chatAtivoId = data.id_chat;
            carregarListaHistorico(); // Atualiza sidebar
        }
        // Atualiza o título do chat se ele for alterado/definido
        if (data.titulo_chat) {
            const header = document.querySelector('.cabecalho-chat h1');
            if (header) header.textContent = data.titulo_chat;
        }

        let fullReply = data.reply || '';
        // Normalizar possíveis sequências escapadas recebidas (ex: "\\n"), CRLF e mistura
        try {
            fullReply = String(fullReply);
            fullReply = fullReply.replace(/\\r\\n/g, '\n');
            fullReply = fullReply.replace(/\\n/g, '\n');
            fullReply = fullReply.replace(/\r\n/g, '\n');
        } catch (e) {
            console.warn('Falha a normalizar reply:', e);
        }

        // Desescapar caracteres de markdown que por vezes chegam com backslashes (ex: "\*\*6\*\*")
        try {
            fullReply = fullReply.replace(/\\([*_`$\\])/g, '$1');
        } catch (e) {
            console.warn('Falha a desescapar markdown:', e);
        }

        // 7. NEW: Use typewriter effect
        typewriter(botContentElement, fullReply, () => {
            if (fullReply.trim().length > 0) {
                mostrarBotoesAcao(botContentElement, fullReply);
            }
        });
    } catch (error) {
        console.error('Erro:', error);
        let displayMsg = error.message || 'Falha na conexão.';
        if (String(displayMsg).includes('<!DOCTYPE') || String(displayMsg).toLowerCase().includes('internal server error')) {
            displayMsg = '⚠️ Serviço temporariamente indisponível. Tenta novamente mais tarde.';
        }
        // Escapa texto para evitar injeção de HTML
        const esc = (t) => { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
        botContentElement.innerHTML = `<p style="color: #ef4444;">⚠️ <strong>Erro:</strong> ${esc(displayMsg)}</p>`;
    } finally {
        // 9. Restaurar UI
        inputMensagem.disabled = false;
        botaoEnviar.disabled = false;
        inputMensagem.focus();
        scrollParaFundo();

        // Limpar anexo após envio
        attachedImageFile = null;
        attachedImageDescription = '';
        attachedImageURL = null;
        uploadPromise = null;
        removerAnexo();
    }
}

function getSelectedSubjects() {
    const disciplinaForm = document.getElementById('disciplinaForm');
    if (!disciplinaForm) {
        // Se o formulário não está carregado, tenta recuperar do localStorage ou fetch síncrono
        return [];
    }
    const checkboxes = disciplinaForm.querySelectorAll('input[name="disciplina"]:checked');
    const disciplina = Array.from(checkboxes).map(cb => cb.value);
    return disciplina;
}


function typewriter(element, text, onComplete) {
    let i = 0;
    const speed = 5; // Speed in ms
    let elementRenderTimer = null; // Local timer para este typewriter específico

    function type() {
        if (i < text.length) {
            // Append one character at a time
            const currentText = text.substring(0, i + 1);

            // Use existing streaming function to render intermediate states
            if (!elementRenderTimer) {
                elementRenderTimer = setTimeout(() => {
                    const rawHTML = marked.parse(currentText);
                    const cleanHTML = DOMPurify.sanitize(rawHTML);
                    element.innerHTML = cleanHTML + '<span class="cursor-streaming"></span>';
                    scrollParaFundo();
                    elementRenderTimer = null;
                }, 50); // 50ms delay
            }

            i++;
            setTimeout(type, speed);
        } else {
            // Ensure final, perfect render
            if (elementRenderTimer) clearTimeout(elementRenderTimer);
            renderizarMensagemFinal(element, text);
            // Call completion callback
            if (onComplete) {
                onComplete();
            }
        }
    }
    type();
}

// 7. RENDERIZAÇÃO E FORMATAÇÃO
// ---------------------------------------------------------------------------------

// Renderização final (Perfeita, com Matemática)
function renderizarMensagemFinal(element, text) {
    if (renderTimer) clearTimeout(renderTimer);

    // 1. Markdown -> HTML
    const rawHTML = marked.parse(text);
    // 2. Limpeza XSS
    const cleanHTML = DOMPurify.sanitize(rawHTML);

    element.innerHTML = cleanHTML;    // 3. Renderizar Matemática (KaTeX)
    if (window.renderMathInElement) {
        renderMathInElement(element, {
            delimiters: [
                { left: "$$", right: "$$", display: true },
                { left: "$", right: "$", display: false }
            ],
            throwOnError: false
        });
    }

    // Fallback: se ainda existirem $...$ não renderizados, tenta converter text nodes com KaTeX
    try {
        ensureInlineMathRendered(element);
    } catch (err) {
        console.warn('KaTeX fallback falhou:', err);
    }

    // 4. Adicionar botões de copiar aos blocos de código
    adicionarBotoesCopiarCodigo(element);

    scrollParaFundo();
}

// Percorre text nodes e rendeiza ocorrências inline $...$ com KaTeX como fallback
function ensureInlineMathRendered(rootEl) {
    if (!rootEl) return;
    if (typeof katex === 'undefined' || !katex.renderToString) return;

    const walker = document.createTreeWalker(rootEl, NodeFilter.SHOW_TEXT, null, false);
    const textNodes = [];
    while (walker.nextNode()) {
        textNodes.push(walker.currentNode);
    }

    const inlineRegex = /\$([^\$]+)\$/g;
    textNodes.forEach(node => {
        const parent = node.parentNode;
        if (!parent) return;
        // Don't replace inside code or pre elements
        const tag = parent.nodeName.toLowerCase();
        if (tag === 'code' || tag === 'pre' || parent.closest && parent.closest('code, pre')) return;

        const text = node.nodeValue;
        if (!text || !text.includes('$')) return;

        let lastIndex = 0;
        let match;
        const frag = document.createDocumentFragment();

        while ((match = inlineRegex.exec(text)) !== null) {
            const before = text.substring(lastIndex, match.index);
            if (before) frag.appendChild(document.createTextNode(before));

            const mathContent = match[1];
            let rendered = null;
            try {
                rendered = katex.renderToString(mathContent, { throwOnError: false });
            } catch (e) {
                rendered = null;
            }
            if (rendered) {
                const span = document.createElement('span');
                span.innerHTML = rendered;
                frag.appendChild(span);
            } else {
                frag.appendChild(document.createTextNode(match[0]));
            }
            lastIndex = inlineRegex.lastIndex;
        }

        if (lastIndex === 0) return; // no matches

        const after = text.substring(lastIndex);
        if (after) frag.appendChild(document.createTextNode(after));

        parent.replaceChild(frag, node);
    });
}

// 8. CRIAÇÃO DE BOLHAS DE MENSAGEM
// ---------------------------------------------------------------------------------
function adicionarMensagemUsuario(texto) {
    const div = document.createElement('div');
    div.className = 'mensagem mensagem-utilizador';
    div.innerHTML = `
        <div class="avatar avatar-utilizador"></div>
        <div class="conteudo-mensagem">
            <p>${escapeHtml(texto)}</p>
            <time class="timestamp timestamp-utilizador">${obterHoraAtual()}</time>
        </div>
    `;
    containerChat.appendChild(div);
    ocultarBemVindo();
}

function adicionarMensagemUsuarioComImagem(texto, imageUrl) {
    const div = document.createElement('div');
    div.className = 'mensagem mensagem-utilizador';
    const safeText = escapeHtml(texto);
    const hasText = texto && texto.trim().length > 0;

    let content = '<div class="avatar avatar-utilizador"></div><div class="conteudo-mensagem">';

    // Mostrar texto apenas se existir
    if (hasText) {
        content += `<p>${safeText}</p>`;
    }

    // Mostrar imagem
    if (imageUrl) {
        content += `<img src="${imageUrl}" alt="imagem" loading="lazy" style="max-width:640px; display:block; margin-top:${hasText ? '8px' : '0'}; border-radius:8px;" />`;
    }

    content += `<time class="timestamp timestamp-utilizador">${obterHoraAtual()}</time></div>`;

    div.innerHTML = content;
    containerChat.appendChild(div);
    ocultarBemVindo();
}

function adicionarMensagemBot(textoInicial, isHistory = true) {
    const div = document.createElement('div');
    div.className = 'mensagem mensagem-bot';

    div.innerHTML = `
        <div class="avatar avatar-bot"></div>
        <div class="conteudo-mensagem">
            <div class="bot-response-content markdown-body"></div>
            <div class="acoes-mensagem" style="display:none;"></div>
            <time class="timestamp timestamp-bot">${obterHoraAtual()}</time>
        </div>
    `;

    containerChat.appendChild(div);
    ocultarBemVindo();

    const contentDiv = div.querySelector('.bot-response-content');

    // Se já tiver texto (ex: carregar histórico), renderiza logo
    if (textoInicial) {
        renderizarMensagemFinal(contentDiv, textoInicial);
        if (isHistory) mostrarBotoesAcao(contentDiv, textoInicial);
    } else {
        // Se for stream novo, mostra cursor
        contentDiv.innerHTML = '<p><span class="cursor-streaming"></span></p>';
    }

    return contentDiv; // Retorna o elemento para ser atualizado pelo stream
}

function ocultarBemVindo() {
    const el = document.querySelector('.ecra-bem-vindo');
    if (el) el.style.display = 'none';
}

// 9. BOTÕES DE AÇÃO (Resumo, Simplificar, etc)
// ---------------------------------------------------------------------------------
function mostrarBotoesAcao(elementContent, rawText) {
    // Procura o contentor de ações vizinho
    const container = elementContent.parentElement.querySelector('.acoes-mensagem');
    if (!container) return;

    container.innerHTML = '';
    container.style.display = 'flex';
    container.style.gap = '8px';
    container.style.marginTop = '10px';
    container.style.flexWrap = 'wrap';

    const botoes = [
        { label: '📝 Resumir', acao: 'Resume a resposta anterior em 5 tópicos principais (bullets).' },
        { label: '👶 Simplificar', acao: 'Explica isto de forma mais simples, como se eu fosse um aluno do 7º ano.' },
        { label: '📚 Exemplos', acao: 'Dá 3 exemplos práticos sobre este assunto.' },
        { label: '🇬🇧 English', acao: 'Translate the previous response to English.' }
    ];

    botoes.forEach(btnInfo => {
        const btn = document.createElement('button');
        btn.className = 'btn-acao'; // Certifica-te que tens CSS para esta classe
        btn.textContent = btnInfo.label;
        btn.style.cursor = 'pointer'; // Estilo básico inline caso falte CSS

        btn.onclick = () => {
            // Prepara o input com a ação
            enviarMensagemAcao(btnInfo.acao, rawText);
        };
        container.appendChild(btn);
    });
}

function enviarMensagemAcao(acao, contexto) {
    if (!inputMensagem) return;

    pendingReferencia = contexto; // Guarda o texto original como contexto
    inputMensagem.value = acao;
    inputMensagem.dataset.pending = '1'; // Marca flag
    inputMensagem.focus();
    autoExpandTextarea(inputMensagem);

    // Enviar automaticamente quando clica num botão rápido
    enviarMensagem();
}// 10. HISTÓRICO E NAVEGAÇÃO
// ---------------------------------------------------------------------------------
function abrirChat(id, titulo) {
    chatAtivoId = id;
    chatAtivoTitulo = titulo;

    // Atualizar UI
    containerChat.innerHTML = '';
    const header = document.querySelector('.cabecalho-chat h1');
    if (header) header.textContent = titulo || 'Conversa';

    // Fechar sidebar mobile
    barraLateral?.classList.remove('activa');
    sobreposicao?.classList.remove('activa');

    // Buscar mensagens via AJAX
    fetch(`api/api_get_chat.php?id_chat=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.mensagens) {
                if (data.mensagens.length === 0) {
                    containerChat.innerHTML = '<div class="ecra-bem-vindo"><h2>Chat Vazio</h2></div>';
                } else {
                    data.mensagens.forEach(m => {
                        // Render user message and attached image (if present)
                        if (m.image_url) {
                            adicionarMensagemUsuarioComImagem(m.pergunta, m.image_url);
                        } else {
                            adicionarMensagemUsuario(m.pergunta);
                        }
                        adicionarMensagemBot(m.resposta, true);
                    });

                    // Detect last message that contains an image and prefetch its OCR description
                    const reversed = Array.from(data.mensagens).slice().reverse();
                    const lastWithImage = reversed.find(x => x.image_url);
                    if (lastWithImage && lastWithImage.image_url) {
                        lastContextImageUrl = lastWithImage.image_url;

                        // IGNORAR placeholders ou nomes inválidos
                        if (lastContextImageUrl === 'image.png' || !lastContextImageUrl.includes('/')) {
                            console.warn('Ignorando imagem de contexto inválida:', lastContextImageUrl);
                            return;
                        }

                        // Start background fetch of OCR description (non-blocking)
                        lastContextFetchPromise = (async () => {
                            try {
                                const r = await fetch(lastContextImageUrl);
                                if (!r.ok) throw new Error('Falha ao buscar imagem para contexto');
                                const blob = await r.blob();
                                // Extensão baseada no MIME type para evitar erro "Unable to recognize the file type"
                                const mimeExt = { 'image/png': '.png', 'image/jpeg': '.jpg', 'image/webp': '.webp', 'image/gif': '.gif' };
                                const ext = mimeExt[blob.type] || '.png';
                                const file = new File([blob], 'context_image' + ext, { type: blob.type || 'image/jpeg' });
                                const res = await uploadImageToVision(file);
                                if (res && res.description) {
                                    lastContextImageDescription = res.description;
                                }
                            } catch (err) {
                                console.warn('Falha ao obter descrição da imagem de contexto:', err);
                                lastContextImageDescription = '';
                            } finally {
                                lastContextFetchPromise = null;
                            }
                        })();
                    }
                }
                setTimeout(scrollParaFundo, 100);
            } else {
                console.error("Erro ao carregar chat:", data.error);
            }
        })
        .catch(e => console.error("Erro Fetch Histórico:", e));
}

function novaConversa() {
    chatAtivoId = null;
    chatAtivoTitulo = null;
    containerChat.innerHTML = `
        <div class="ecra-bem-vindo">
            <h2 class="titulo-bem-vindo">Olá, eu sou o AulaBot</h2>
            <p class="subtitulo-bem-vindo">O teu assistente para aprendizagem e educação.</p>
        </div>
    `;
    const header = document.querySelector('.cabecalho-chat h1');
    if (header) header.textContent = 'Novo Chat';

    barraLateral?.classList.remove('activa');
    sobreposicao?.classList.remove('activa');
}

function carregarListaHistorico() {
    // Recarrega apenas a parte da lista da página atual
    fetch(window.location.href)
        .then(r => r.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const novaLista = doc.querySelector('#listaHistorico'); // Certifica-te que o ID existe no HTML
            const listaAtual = document.querySelector('#listaHistorico');

            if (novaLista && listaAtual) {
                listaAtual.innerHTML = novaLista.innerHTML;
                attachHistoricoHandlers(); // Re-anexa os eventos de clique
            }
        })
        .catch(e => console.warn("Erro ao atualizar sidebar:", e));
}

function attachHistoricoHandlers() {
    // Supondo que os itens da lista têm a classe .item-historico e data-id
    document.querySelectorAll('.item-historico').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const id = e.currentTarget.dataset.id;
            const titulo = e.currentTarget.querySelector('.chat-titulo')?.textContent;
            if (id) abrirChat(parseInt(id), titulo);
        });
    });
}

// 11. UTILITÁRIOS
// ---------------------------------------------------------------------------------

// Adiciona botões de copiar aos blocos de código
function adicionarBotoesCopiarCodigo(container) {
    const codeBlocks = container.querySelectorAll('pre');
    codeBlocks.forEach(pre => {
        if (pre.querySelector('.btn-copiar-codigo')) return;
        pre.style.position = 'relative';
        const btn = document.createElement('button');
        btn.className = 'btn-copiar-codigo';
        btn.innerHTML = '<i class="fa-regular fa-copy"></i>';
        btn.title = 'Copiar código';
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            const code = pre.querySelector('code') || pre;
            const texto = code.textContent || code.innerText;
            try {
                await navigator.clipboard.writeText(texto);
                btn.innerHTML = '<i class="fa-solid fa-check"></i>';
                btn.classList.add('copiado');
                setTimeout(() => {
                    btn.innerHTML = '<i class="fa-regular fa-copy"></i>';
                    btn.classList.remove('copiado');
                }, 2000);
            } catch (err) {
                const textarea = document.createElement('textarea');
                textarea.value = texto;
                textarea.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                btn.innerHTML = '<i class="fa-solid fa-check"></i>';
                btn.classList.add('copiado');
                setTimeout(() => {
                    btn.innerHTML = '<i class="fa-regular fa-copy"></i>';
                    btn.classList.remove('copiado');
                }, 2000);
            }
        });
        pre.appendChild(btn);
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}