

// Inicialização quando a página carrega
// Configura todos os componentes da dashboard administrativa
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
    initializeCharts();
    initializeModals();
    initializeTables();
    initializeFormSubmissions();
    initializeEventListeners();
    initializeSearch();
    initializeFeedbackButtons();
    
    // Site block button logic
    // Lógica para o botão de bloqueio global do site
    const siteBlockBtn = document.getElementById('siteBlockBtn');
    const siteBlockLabel = document.getElementById('siteBlockLabel');

    // Função assíncrona para verificar o estado atual do bloqueio
    async function fetchBloqueio() {
        try {
            const resp = await fetch('admin_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_bloqueio' })
            });
            const data = await resp.json();
            if (data.success) {
                const blocked = parseInt(data.data.bloqueio) === 1;
                siteBlockLabel.textContent = blocked ? 'Bloqueado' : 'Desbloqueado';
                siteBlockBtn.classList.toggle('blocked', blocked);
            } else {
                siteBlockLabel.textContent = 'Erro';
            }
        } catch (err) {
            console.error('Erro ao obter bloqueio:', err);
            siteBlockLabel.textContent = 'Erro';
        }
    }

    // Função para alternar o estado de bloqueio (Bloquear/Desbloquear)
    async function toggleBloqueio() {
        const confirmMsg = 'Tem a certeza que pretende alternar o estado de bloqueio do site? Esta ação afectará utilizadores não administradores.';
        const confirmed = await ModalSystem.confirm('Confirmar Ação', confirmMsg);
        if (!confirmed) return;
        try {
            // obter estado atual para inverter
            const resp1 = await fetch('admin_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_bloqueio' })
            });
            const data1 = await resp1.json();
            const current = (data1.success && parseInt(data1.data.bloqueio) === 1) ? 1 : 0;
            const newVal = current === 1 ? 0 : 1;

            const resp2 = await fetch('admin_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'set_bloqueio', value: newVal })
            });
            const data2 = await resp2.json();
            if (data2.success) {
                siteBlockLabel.textContent = data2.data.bloqueio == 1 ? 'Bloqueado' : 'Desbloqueado';
                siteBlockBtn.classList.toggle('blocked', data2.data.bloqueio == 1);
                await ModalSystem.success('Sucesso', data2.message || 'Estado de bloqueio alterado');
            } else {
                await ModalSystem.error('Erro', 'Falha ao alterar estado de bloqueio');
            }
        } catch (err) {
            console.error('Erro ao alternar bloqueio:', err);
            await ModalSystem.error('Erro', 'Erro ao comunicar com o servidor');
        }
    }

    if (siteBlockBtn && siteBlockLabel) {
        fetchBloqueio();
        siteBlockBtn.addEventListener('click', toggleBloqueio);
    }
});

// Inicializa a funcionalidade de pesquisa na tabela de utilizadores
function initializeSearch() {
    const searchInput = document.getElementById('userSearch');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function(e) {
            filterTable(e.target.value);
        }, 300));
    }
}

// Filtra as linhas da tabela com base no termo de pesquisa
function filterTable(searchTerm) {
    const table = document.querySelector('.users-table');
    if (!table) return;

    const rows = table.querySelectorAll('tbody tr');
    const searchLower = searchTerm.toLowerCase();

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const shouldShow = text.includes(searchLower);
        
        if (shouldShow) {
            row.style.display = '';
            row.style.animation = 'fadeIn 0.5s ease forwards';
        } else {
            row.style.display = 'none';
        }
    });
}

// Função utilitária para limitar a frequência de execução de funções (performance)
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Inicializar dashboard com funcionalidades modernas
 * Configura animações, atualizações automáticas e tooltips
 */
function initializeDashboard() {
    // Adicionar animações aos cards de estatísticas
    animateStatCards();
    
    // Configurar atualização automática de estatísticas
    setInterval(updateStats, 30000); // Atualizar a cada 30 segundos
    
    // Configurar tooltips
    initializeTooltips();
    
    // Animar outros elementos
    animateElements('.tool-card', 'fade-in-up');
}

/**
 * Inicializar gráficos modernos
 * (Mantido para compatibilidade futura)
 */
function initializeCharts() {
    // Function kept for compatibility but chart removed
}

/**
 * Inicializar event listeners modernos
 * Configura interações de UI como sidebar, dropdowns e filtros
 */
function initializeEventListeners() {
    // Toggle sidebar em mobile
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }
    
    // Fechar sidebar ao clicar fora (mobile)
    document.addEventListener('click', function(e) {
        const sidebar = document.querySelector('.admin-sidebar');
        const toggle = document.querySelector('.sidebar-toggle');
        const body = document.body;

        // Se a sidebar está aberta (body NÃO tem classe sidebar-collapsed) e clicamos fora, colapsa-a
        if (!body.classList.contains('sidebar-collapsed') && sidebar && toggle &&
            !sidebar.contains(e.target) && !toggle.contains(e.target)) {
            body.classList.add('sidebar-collapsed');
        }
    });
    
    // Filtro de tempo
    const timeFilter = document.querySelector('.time-filter select');
    if (timeFilter) {
        timeFilter.addEventListener('change', function() {
            showToast('Filtro Aplicado', `Período alterado para: ${this.value}`, 'info');
            // Aqui pode adicionar lógica para atualizar dados baseado no filtro
        });
    }

    // User dropdown toggle
    const userAvatar = document.getElementById('userAvatar');
    const userDropdown = document.getElementById('userDropdown');
    if (userAvatar && userDropdown) {
        console.log('Admin dropdown elements found. Initializing dropdown functionality.');
        
        userAvatar.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const isOpen = userDropdown.classList.contains('show');
            console.log('Admin avatar clicked. Current state:', isOpen ? 'open' : 'closed');
            
            if (isOpen) {
                userDropdown.classList.remove('show');
                userAvatar.classList.remove('active');
                console.log('Admin dropdown closed');
            } else {
                userDropdown.classList.add('show');
                userAvatar.classList.add('active');
                console.log('Admin dropdown opened');
            }
        });

        userAvatar.addEventListener('mousedown', function(e) {
            console.log('Mousedown event on admin avatar');
        });

        // Close dropdown when clicking elsewhere
        document.addEventListener('click', function(e) {
            if (userDropdown.classList.contains('show') && !userDropdown.contains(e.target) && !userAvatar.contains(e.target)) {
                userDropdown.classList.remove('show');
                userAvatar.classList.remove('active');
                console.log('Admin dropdown closed due to outside click');
            }
        });

        // Close dropdown when pressing Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && userDropdown.classList.contains('show')) {
                userDropdown.classList.remove('show');
                userAvatar.classList.remove('active');
                console.log('Admin dropdown closed due to Escape key');
            }
        });
    } else {
        console.error('Admin dropdown elements not found!');
        console.log('userAvatar element:', userAvatar);
        console.log('userDropdown element:', userDropdown);
        if (!userAvatar) console.error('Element with ID "userAvatar" not found in DOM');
        if (!userDropdown) console.error('Element with ID "userDropdown" not found in DOM');
    }
    
    // Tool cards hover effects
    const toolCards = document.querySelectorAll('.tool-card');
    toolCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
}

/**
 * Toggle sidebar para mobile
 * Alterna a classe CSS que controla a visibilidade da barra lateral
 */
function toggleSidebar() {
    // Toggle a single class on the body so CSS rules drive the layout
    document.body.classList.toggle('sidebar-collapsed');

    // For accessibility, update aria-expanded on the toggle button
    const toggle = document.querySelector('.sidebar-toggle');
    if (toggle) {
        const expanded = document.body.classList.contains('sidebar-collapsed');
        toggle.setAttribute('aria-expanded', (!expanded).toString());
    }
}

/**
 * Animar elementos com atraso
 * Adiciona classes de animação sequencialmente a uma lista de elementos
 */
function animateElements(selector, animationClass) {
    const elements = document.querySelectorAll(selector);
    elements.forEach((el, index) => {
        setTimeout(() => {
            el.classList.add(animationClass);
        }, index * 100);
    });
}

/**
 * Animar cards de estatísticas na entrada (versão melhorada)
 * Efeito visual de entrada para os cartões do topo da dashboard
 */
function animateStatCards() {
    const statCards = document.querySelectorAll('.stat-card');
    
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px) scale(0.95)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0) scale(1)';
        }, index * 150);
    });
}

/**
 * Atualizar estatísticas do dashboard (versão melhorada)
 * Faz um pedido AJAX para obter os números mais recentes sem recarregar a página
 */
async function updateStats() {
    try {
        showToastInfo('A atualizar', 'A atualizar estatísticas em tempo real...');
        
        const response = await fetch('admin_api.php?action=stats');
        const data = await response.json();
        
        if (data.success) {
            updateStatCard('total_utilizadores', data.data.total_utilizadores);
            updateStatCard('total_admins', data.data.total_admins);
            updateStatCard('novos_hoje', data.data.novos_hoje);
            updateStatCard('novos_semana', data.data.novos_semana);
            
            // Adicionar efeito visual de atualização
            const statsSection = document.querySelector('.stats-section');
            if (statsSection) {
                statsSection.classList.add('updated');
                setTimeout(() => statsSection.classList.remove('updated'), 1000);
            }
        }
    } catch (error) {
        console.error('Erro ao atualizar estatísticas:', error);
        showToastError('Erro', 'Não foi possível atualizar as estatísticas');
    }
}

/**
 * Atualizar valor de um card de estatística (versão melhorada)
 * Atualiza o número exibido num cartão específico
 */
function updateStatCard(type, value) {
    const cardMap = {
        'total_utilizadores': 0,
        'total_admins': 1,
        'novos_hoje': 2,
        'novos_semana': 3
    };
    
    const cardIndex = cardMap[type];
    const statCards = document.querySelectorAll('.stat-card');
    
    if (statCards[cardIndex]) {
        const valueElement = statCards[cardIndex].querySelector('h3');
        const currentValue = parseInt(valueElement.textContent.replace(/,/g, ''));
        const newValue = parseInt(value);
        
        if (currentValue !== newValue) {
            animateCounter(valueElement, currentValue, newValue);
        }
    }
}

/**
 * Animar contador com transição suave
 * Faz os números "subirem" gradualmente até ao valor final
 */
function animateCounter(element, start, end) {
    const duration = 1000;
    const startTime = performance.now();
    
    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // Easing function para animação mais suave
        const easeOutQuart = 1 - Math.pow(1 - progress, 4);
        const current = Math.floor(start + (end - start) * easeOutQuart);
        
        element.textContent = current.toLocaleString('pt-PT');
        
        if (progress < 1) {
            requestAnimationFrame(update);
        } else {
            // Efeito visual quando termina
            element.style.color = '#06d6a0';
            setTimeout(() => {
                element.style.color = '';
            }, 500);
        }
    }
    
    requestAnimationFrame(update);
}

/**
 * Inicializar modais (versão melhorada)
 * Configura o comportamento global dos modais (fechar com ESC, clicar fora)
 */
function initializeModals() {
    // Configurar fechamento de modais ao clicar fora
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeAllModals();
        }
    });
    
    // Configurar fechamento com tecla Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            // Fechar modal de confirmação de eliminação se estiver aberto
            const deleteModal = document.getElementById('deleteConfirmModal');
            if (deleteModal && deleteModal.classList.contains('show')) {
                closeDeleteConfirmModal();
            } else {
                closeAllModals();
            }
        }
    });
    
    // Prevenir fechamento ao clicar dentro do modal
    document.querySelectorAll('.modal-content').forEach(modalContent => {
        modalContent.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
}

/**
 * Fechar todos os modais abertos
 * Utilitário para limpar a interface
 */
function closeAllModals() {
    const modals = document.querySelectorAll('.modal.show');
    modals.forEach(modal => {
        modal.classList.remove('show');
    });
}

/**
 * Inicializar tabelas (versão melhorada)
 * Adiciona ordenação e efeitos visuais às tabelas de dados
 */
function initializeTables() {
    // Adicionar funcionalidade de ordenação às tabelas
    const tableHeaders = document.querySelectorAll('.users-table th');
    
    tableHeaders.forEach(header => {
        const tableHeader = header.querySelector('.table-header');
        if (tableHeader && !header.querySelector('.actions')) {
            tableHeader.style.cursor = 'pointer';
            tableHeader.addEventListener('click', () => sortTable(header));
        }
    });
    
    // Adicionar hover effects às linhas
    const tableRows = document.querySelectorAll('.users-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'rgba(67, 97, 238, 0.02)';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
}

/**
 * Ordenar tabela por coluna (versão melhorada)
 * Lógica de ordenação (ascendente/descendente) para números e texto
 */
function sortTable(header) {
    const table = header.closest('table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const columnIndex = Array.from(header.parentNode.children).indexOf(header);
    
    // Alternar entre ordenação ascendente e descendente
    const isAscending = header.classList.contains('sort-asc');
    const sortIcon = header.querySelector('.fa-sort');
    
    // Remover classes de ordenação de todos os cabeçalhos
    table.querySelectorAll('th').forEach(th => {
        th.classList.remove('sort-asc', 'sort-desc');
        const icon = th.querySelector('.fa-sort');
        if (icon) {
            icon.className = 'fas fa-sort';
        }
    });
    
    // Adicionar classe ao cabeçalho atual
    header.classList.add(isAscending ? 'sort-desc' : 'sort-asc');
    
    // Atualizar ícone
    if (sortIcon) {
        sortIcon.className = isAscending ? 'fas fa-sort-down' : 'fas fa-sort-up';
    }
    
    // Ordenar linhas
    rows.sort((a, b) => {
        let aValue = a.children[columnIndex].textContent.trim();
        let bValue = b.children[columnIndex].textContent.trim();
        
        // Remover # do ID para ordenação numérica
        if (columnIndex === 0) {
            aValue = aValue.replace('#', '');
            bValue = bValue.replace('#', '');
        }
        
        // Verificar se são números
        const aNum = parseFloat(aValue);
        const bNum = parseFloat(bValue);
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return isAscending ? bNum - aNum : aNum - bNum;
        } else {
            return isAscending ? 
                bValue.localeCompare(aValue, 'pt', { sensitivity: 'base' }) : 
                aValue.localeCompare(bValue, 'pt', { sensitivity: 'base' });
        }
    });
    
    // Reordenar linhas na tabela com animação
    rows.forEach((row, index) => {
        setTimeout(() => {
            row.style.opacity = '0';
            row.style.transform = 'translateX(20px)';
            
            setTimeout(() => {
                tbody.appendChild(row);
                row.style.opacity = '1';
                row.style.transform = 'translateX(0)';
            }, 50);
        }, index * 50);
    });
    
    showToastInfo('Tabela Ordenada', `Coluna ordenada por ${header.textContent.trim()}`, 'info');
}

/**
 * Inicializar submissões de formulários
 * Configura os handlers de submit para os formulários de criação/edição
 */
function initializeFormSubmissions() {
    const addUserForm = document.getElementById('addUserForm');
    
    if (addUserForm) {
        addUserForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleAddUserSubmit(this);
        });
        
        const inputs = addUserForm.querySelectorAll('input, select');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                clearFieldError(this);
            });
        });
    }

    const createUpdateForm = document.getElementById('createUpdateForm');
    if (createUpdateForm) {
        createUpdateForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleCreateUpdateSubmit(this);
        });
    }
}

// Parser seguro de JSON para respostas fetch
// Retorna o JSON parseado ou lança erro com o texto da resposta (útil para debug)
async function parseJsonSafe(response) {
    const text = await response.text();
    if (!response.ok) {
        console.error('Non-OK response', response.status, text);
        throw new Error(`HTTP error! status: ${response.status}`);
    }
    try {
        return JSON.parse(text);
    } catch (e) {
        console.error('Failed to parse JSON from server:', text);
        throw new Error('Invalid JSON from server');
    }
}

/**
 * Validar campo do formulário
 * Verifica regras de validação (email, tamanho de texto, password)
 */
function validateField(field) {
    const value = field.value.trim();
    let isValid = true;
    let message = '';
    
    // Limpar erros anteriores
    clearFieldError(field);
    
    switch (field.type) {
        case 'email':
            if (!value) {
                isValid = false;
                message = 'Email é obrigatório';
            } else if (!/\S+@\S+\.\S+/.test(value)) {
                isValid = false;
                message = 'Email inválido';
            }
            break;
            
        case 'text':
            if (!value) {
                isValid = false;
                message = 'Nome é obrigatório';
            } else if (value.length < 2) {
                isValid = false;
                message = 'Nome muito curto';
            } else if (value.length > 100) {
                isValid = false;
                message = 'Nome muito longo (máximo 100 caracteres)';
            }
            break;
            
        case 'password':
            if (!value) {
                isValid = false;
                message = 'Password é obrigatória';
            } else if (value.length < 6) {
                isValid = false;
                message = 'Password deve ter pelo menos 6 caracteres';
            } else if (value.length > 255) {
                isValid = false;
                message = 'Password muito longa (máximo 255 caracteres)';
            }
            break;
    }
    
    // Validação especial para select
    if (field.tagName === 'SELECT') {
        if (!value) {
            isValid = false;
            message = 'Tipo de utilizador é obrigatório';
        }
    }
    
    if (!isValid) {
        showFieldError(field, message);
    } else {
        clearFieldError(field);
    }
    
    return isValid;
}

/**
 * Mostrar erro no campo
 * Adiciona feedback visual de erro abaixo do input
 */
function showFieldError(field, message) {
    clearFieldError(field);
    field.style.borderColor = '#ef476f';
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    errorDiv.style.cssText = `
        color: #ef476f;
        font-size: 0.75rem;
        margin-top: 0.25rem;
    `;
    
    field.parentNode.appendChild(errorDiv);
}

/**
 * Limpar erro do campo
 * Remove o feedback visual de erro
 */
function clearFieldError(field) {
    field.style.borderColor = '';
    const errorDiv = field.parentNode.querySelector('.field-error');
    if (errorDiv) {
        errorDiv.remove();
    }
}

/**
 * FUNÇÕES DOS MODAIS
 */

// Abre o modal para criar um novo anúncio de atualização
function openUpdateModal() {
    const modal = document.getElementById('updateModal');
    if (!modal) return;
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    const form = document.getElementById('createUpdateForm');
    if (form) {
        form.reset();
        const submitBtn = form.querySelector('button[type="submit"]');
        hideButtonLoading(submitBtn);
    }
}

// Fecha o modal de anúncio
function closeUpdateModal() {
    const modal = document.getElementById('updateModal');
    if (!modal) return;
    modal.classList.remove('show');
    document.body.style.overflow = '';
}

/**
 * Mostrar modal para adicionar utilizador (versão melhorada)
 * Prepara o formulário de novo utilizador (limpa campos, foca no primeiro input)
 */
function showAddUserModal() {
    const modal = document.getElementById('addUserModal');
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        
        const form = document.getElementById('addUserForm');
        if (form) {
            form.reset();
            
            // Limpar erros anteriores
            document.querySelectorAll('.field-error').forEach(error => error.remove());
            
            // Limpar estilos de erro
            const inputs = form.querySelectorAll('input, select');
            inputs.forEach(input => {
                input.style.borderColor = '';
            });
        }
        
        // Focar no primeiro campo com animação
        const firstInput = document.getElementById('newUserName');
        if (firstInput) {
            setTimeout(() => {
                firstInput.focus();
                firstInput.style.transform = 'scale(1.02)';
                setTimeout(() => firstInput.style.transform = '', 200);
            }, 300);
        }
    } else {
        console.error('Modal addUserModal não encontrado');
    }
}

/**
 * Fechar modal de adicionar utilizador
 * Esconde o modal e restaura o scroll da página
 */
function closeAddUserModal() {
    const modal = document.getElementById('addUserModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

/**
 * FUNÇÕES DE GESTÃO DE UTILIZADORES
 */

/**
 * Editar utilizador (versão melhorada)
 * Preenche o formulário de edição com os dados atuais do utilizador
 */
function openEditUserModal(id, nome, email, tipo) {
    const modal = document.getElementById('editUserModal');
    if (!modal) return;
    document.body.style.overflow = 'hidden';
    modal.style.display = 'block';
    modal.classList.add('show');

    document.getElementById('editUserId').value = id;
    document.getElementById('editUserName').value = nome;
    document.getElementById('editUserEmail').value = email;
    document.getElementById('editUserType').value = tipo;
}

// Fecha o modal de edição
function closeEditUserModal() {
    const modal = document.getElementById('editUserModal');
    if (modal) {
        modal.classList.remove('show');
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

// Configura o envio do formulário de edição via AJAX
document.addEventListener('DOMContentLoaded', function() {
    const editUserForm = document.getElementById('editUserForm');
    if (editUserForm) {
        editUserForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(editUserForm);
            const userData = {
                action: 'update_user',
                id_utilizador: formData.get('id_utilizador'),
                nome: formData.get('nome'),
                email: formData.get('email'),
                tipo: formData.get('tipo')
            };
            const submitBtn = editUserForm.querySelector('button[type="submit"]');
            showButtonLoading(submitBtn);
            fetch('admin_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(userData)
            })
            .then(parseJsonSafe)
            .then(data => {
                if (data.success) {
                    showToast('Utilizador Atualizado', data.message, 'success');
                    closeEditUserModal();
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showToast('Erro', data.message, 'error');
                    hideButtonLoading(submitBtn);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro de Conexão', 'Não foi possível atualizar o utilizador.', 'error');
                hideButtonLoading(submitBtn);
            });
        });
    }
});

// Variáveis globais para o modal de confirmação
let currentDeleteUserId = null;
let currentDeleteUserRow = null;
let currentDeleteButton = null;

/**
 * Eliminar utilizador (versão melhorada com modal personalizado)
 * Inicia o processo de eliminação, pedindo confirmação
 */
function deleteUser(userId, event) {
    // Prevenir comportamento padrão e propagação (evitar submit/reload acidental)
    if (event && typeof event.preventDefault === 'function') {
        event.preventDefault();
        event.stopPropagation();
    }

    const userRow = event.target.closest('tr');
    const userName = userRow.querySelector('td:nth-child(2)').textContent.trim();
    const userType = userRow.querySelector('td:nth-child(4)').textContent.trim().toLowerCase();
    
    // Verificar se é um admin
    if (userType === 'admin') {
        showToast('Ação Bloqueada', 'Não pode eliminar uma conta de administrador', 'error');
        return;
    }
    
    // Armazenar dados para uso posterior
    currentDeleteUserId = userId;
    currentDeleteUserRow = userRow;
    currentDeleteButton = event.target.closest('.btn-icon');
    
    // Mostrar modal de confirmação
    showDeleteConfirmModal(userName);
}

/**
 * Mostrar modal de confirmação de eliminação
 * Exibe o aviso de que a ação é irreversível
 */
function showDeleteConfirmModal(userName) {
    const modal = document.getElementById('deleteConfirmModal');
    const userNameElement = document.getElementById('deleteUserName');
    
    if (modal && userNameElement) {
        userNameElement.textContent = userName;
        modal.classList.add('show');
        
        // Adicionar efeito de blur no fundo
        document.body.style.overflow = 'hidden';
        
        // Focar no botão de cancelar para acessibilidade
        setTimeout(() => {
            const cancelBtn = modal.querySelector('.btn-outline');
            if (cancelBtn) cancelBtn.focus();
        }, 100);
    }
}

/**
 * Fechar modal de confirmação de eliminação
 * Cancela a ação de eliminar
 */
function closeDeleteConfirmModal() {
    const modal = document.getElementById('deleteConfirmModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
        
        // Limpar variáveis
        currentDeleteUserId = null;
        currentDeleteUserRow = null;
        currentDeleteButton = null;
    }
}

/**
 * Confirmar eliminação do utilizador
 * Executa a chamada à API para remover o utilizador da base de dados
 */
function confirmDeleteUser() {
    if (!currentDeleteUserId || !currentDeleteUserRow || !currentDeleteButton) {
        console.error('Dados de eliminação não encontrados');
        return;
    }

    const userId = currentDeleteUserId;
    const userRow = currentDeleteUserRow;
    const button = currentDeleteButton;

    // Fechar modal
    closeDeleteConfirmModal();

    // Mostrar loading no botão
    showButtonLoading(button);

    // Efeito visual inicial
    userRow.style.opacity = '0.5';

    console.log('Eliminando utilizador com ID:', userId);

    fetch('admin_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete_user', user_id: parseInt(userId) })
    })
    .then(parseJsonSafe)
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            // Preparar animação suave
            const rowHeight = userRow.offsetHeight;
            userRow.style.transition = 'all 0.5s ease';
            userRow.style.height = rowHeight + 'px';
            userRow.style.opacity = '0';
            userRow.style.transform = 'translateX(50px)';

            // Depois da transição de opacidade e deslocamento, encolher a altura
            setTimeout(() => {
                userRow.style.height = '0px';
                userRow.style.margin = '0';
                userRow.style.paddingTop = '0';
                userRow.style.paddingBottom = '0';
                userRow.style.border = 'none';
            }, 500);

            // Remover do DOM após animação completa
            setTimeout(() => {
                showToast('Utilizador Eliminado', data.message || 'Utilizador eliminado com sucesso', 'success');
                userRow.remove();
            }, 1000);
            // Recarregar a página depois da animação para atualizar contagens e listas
            setTimeout(() => {
                location.reload();
            }, 1200);

        } else {
            // Reverter efeitos visuais em caso de erro
            userRow.style.opacity = '1';
            userRow.style.transform = 'translateX(0)';
            userRow.style.height = '';
            userRow.style.transition = '';
            showToast('Erro', data.message || 'Erro desconhecido ao eliminar utilizador', 'error');
            hideButtonLoading(button);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        // Reverter efeitos visuais em caso de erro
        userRow.style.opacity = '1';
        userRow.style.transform = 'translateX(0)';
        userRow.style.height = '';
        userRow.style.transition = '';
        showToast('Erro de Conexão', 'Não foi possível eliminar o utilizador.', 'error');
        hideButtonLoading(button);
    });
}

/**
 * Abrir modal para bloquear utilizador
 * Prepara a interface para bloquear um utilizador (pede motivo)
 */
function abrirModalBloqueio(userId, userName) {
    const modal = document.getElementById('blockUserModal');
    if (!modal) {
        // Criar modal se não existir
        criarModalBloqueio();
        abrirModalBloqueio(userId, userName);
        return;
    }
    
    // Atualizar conteúdo do modal
    document.getElementById('blockUserId').value = userId;
    document.getElementById('blockUserNameDisplay').textContent = userName;
    document.getElementById('blockUserMotivo').value = '';
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

/**
 * Fechar modal de bloqueio
 * Cancela a ação de bloqueio
 */
function fecharModalBloqueio() {
    const modal = document.getElementById('blockUserModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

/**
 * Confirmar bloqueio do utilizador
 * Envia o pedido de bloqueio para a API
 */
function confirmarBloqueio() {
    const userId = document.getElementById('blockUserId').value;
    const motivo = document.getElementById('blockUserMotivo').value.trim();
    
    if (!userId) {
        showToast('Erro', 'ID do utilizador não encontrado', 'error');
        return;
    }
    
    fecharModalBloqueio();
    
    fetch('admin_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            action: 'bloquear_user', 
            user_id: parseInt(userId),
            motivo: motivo
        })
    })
    .then(parseJsonSafe)
    .then(data => {
        if (data.success) {
            showToast('Sucesso', data.message || 'Utilizador bloqueado com sucesso', 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('Erro', data.message || 'Erro ao bloquear utilizador', 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showToast('Erro de Conexão', 'Não foi possível bloquear o utilizador', 'error');
    });
}

/**
 * Desbloquear utilizador
 * Remove o bloqueio de um utilizador
 */
function desbloquearUtilizador(userId, userName) {
    const confirmed = confirm(`Tem a certeza que deseja desbloquear ${userName}?`);
    if (!confirmed) return;
    
    fetch('admin_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            action: 'desbloquear_user', 
            user_id: parseInt(userId)
        })
    })
    .then(parseJsonSafe)
    .then(data => {
        if (data.success) {
            showToast('Sucesso', data.message || 'Utilizador desbloqueado com sucesso', 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('Erro', data.message || 'Erro ao desbloquear utilizador', 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showToast('Erro de Conexão', 'Não foi possível desbloquear o utilizador', 'error');
    });
}

/**
 * Criar modal de bloqueio dinamicamente
 * Gera o HTML do modal de bloqueio se ele não existir na página
 */
function criarModalBloqueio() {
    const modalHTML = `
        <div id="blockUserModal" class="modal" style="display: none;">
            <div class="modal-content" style="width: 90%; max-width: 400px; border-radius: 12px;">
                <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #e0e0e0;">
                    <h2 style="margin: 0; font-size: 18px; color: #333;">
                        <i class="fas fa-ban" style="color: #ef476f; margin-right: 10px;"></i>Bloquear Utilizador
                    </h2>
                    <button type="button" class="close-btn" onclick="fecharModalBloqueio()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999;">×</button>
                </div>
                <div class="modal-body" style="padding: 20px;">
                    <p style="margin: 0 0 15px 0; color: #666;">Bloquear utilizador: <strong id="blockUserNameDisplay"></strong></p>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">Motivo do bloqueio (opcional):</label>
                        <textarea id="blockUserMotivo" placeholder="Ex: Comportamento inadequado, violação de termos..." style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; font-family: inherit; resize: vertical; min-height: 80px;"></textarea>
                    </div>
                    
                    <p style="margin: 15px 0 0 0; padding: 10px; background: #fff3cd; border-left: 3px solid #ffc107; color: #856404; font-size: 13px;">
                        <i class="fas fa-exclamation-triangle"></i> O utilizador não conseguirá aceder à plataforma enquanto estiver bloqueado.
                    </p>
                </div>
                <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; padding: 20px; border-top: 1px solid #e0e0e0;">
                    <input type="hidden" id="blockUserId">
                    <button type="button" class="btn btn-outline" onclick="fecharModalBloqueio()" style="padding: 10px 16px; border: 1px solid #e0e0e0; background: white; color: #333; border-radius: 6px; cursor: pointer; font-weight: 500;">Cancelar</button>
                    <button type="button" class="btn btn-danger" onclick="confirmarBloqueio()" style="padding: 10px 16px; border: none; background: #ef476f; color: white; border-radius: 6px; cursor: pointer; font-weight: 500;">Bloquear Utilizador</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Fechar modal ao clicar fora
    const modal = document.getElementById('blockUserModal');
    if (modal) {
        modal.addEventListener('click', function(event) {
            if (event.target === this) {
                fecharModalBloqueio();
            }
        });
    }
}

/**
 * Adicionar utilizador (versão melhorada)
 * Processa o formulário de criação de utilizador
 */
function handleAddUserSubmit(form) {
    // Validar todos os campos primeiro
    const inputs = form.querySelectorAll('input, select');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!validateField(input)) {
            isValid = false;
        }
    });
    
    if (!isValid) {
        showToast('Erro de Validação', 'Por favor, corrija os erros no formulário.', 'error');
        return;
    }
    
    const formData = new FormData(form);
    const userData = {
        action: 'add_user',
        nome: formData.get('nome'),
        email: formData.get('email'),
        password: formData.get('password'),
        tipo: formData.get('tipo')
    };
    
    const submitBtn = form.querySelector('button[type="submit"]');
    showButtonLoading(submitBtn);
    
    console.log('Enviando dados do utilizador:', userData); // Debug log

    fetch('admin_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(userData)
    })
    .then(parseJsonSafe)
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            showToast('Utilizador Adicionado', data.message || 'Utilizador adicionado com sucesso', 'success');
            closeAddUserModal();
            
            // Animação antes de recarregar
            const modal = document.getElementById('addUserModal');
            if (modal) {
                modal.style.opacity = '0';
                modal.style.transform = 'scale(0.9)';
            }
            
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Erro', data.message || 'Erro desconhecido ao adicionar utilizador', 'error');
            hideButtonLoading(submitBtn);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showToast('Erro de Conexão', 'Não foi possível adicionar o utilizador.', 'error');
        hideButtonLoading(submitBtn);
    });
}

// Processa o formulário de criação de anúncio/atualização
function handleCreateUpdateSubmit(form) {
    const nameInput = form.querySelector('#updateName');
    const versionInput = form.querySelector('#updateVersion');
    const descriptionInput = form.querySelector('#updateDescription');
    const themeInput = form.querySelector('#updateTheme');
    const nome = nameInput ? nameInput.value.trim() : '';
    const versao = versionInput ? versionInput.value.trim() : '';
    const descricao = descriptionInput ? descriptionInput.value.trim() : '';
    const tema = themeInput ? themeInput.value : 'atualizacoes';

    if (!nome || !versao || !descricao) {
        showToast('Campos obrigatórios', 'Preencha todos os campos do anúncio.', 'error');
        return;
    }

    if (descricao.length > 1000) {
        showToast('Descrição longa', 'A descrição deve ter até 1000 caracteres.', 'error');
        return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    showButtonLoading(submitBtn);

    fetch('admin_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'create_update',
            nome,
            versao,
            descricao,
            tema
        })
    })
    .then(parseJsonSafe)
    .then(data => {
        if (data.success) {
            showToast('Anúncio publicado', data.message || 'Atualização criada com sucesso.', 'success');
            closeUpdateModal();
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('Erro', data.message || 'Não foi possível criar o anúncio.', 'error');
            hideButtonLoading(submitBtn);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showToast('Erro de Conexão', 'Não foi possível guardar o anúncio.', 'error');
        hideButtonLoading(submitBtn);
    });
}

/**
* Editar utilizador 
* (Função duplicada/alternativa para abrir modal de edição)
*/

function openEditUserModal(id, nome, email, tipo) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editUserName').value = nome || '';
    document.getElementById('editUserEmail').value = email || '';
    document.getElementById('editUserType').value = tipo || 'utilizador';
    document.getElementById('editUserModal').style.display = 'flex';
}

function closeEditUserModal() {
    document.getElementById('editUserModal').style.display = 'none';
}

// Fecha modal ao clicar fora
window.onclick = function(event) {
    const modal = document.getElementById('editUserModal');
    if (event.target === modal) {
        closeEditUserModal();
    }
}

// Edit form submission is handled in DOMContentLoaded (uses JSON + parseJsonSafe)

// Função de notificação
// Exibe uma notificação flutuante simples
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => notification.classList.add('show'), 100);
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

/**
 * Inicializar tooltips (versão melhorada)
 * Cria tooltips personalizados ao passar o rato sobre elementos com atributo 'title'
 */
function initializeTooltips() {
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', function(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip';
            tooltip.textContent = this.title;
            tooltip.style.cssText = `
                position: fixed;
                background: rgba(0, 0, 0, 0.8);
                color: white;
                padding: 0.5rem 0.75rem;
                border-radius: 6px;
                font-size: 0.75rem;
                z-index: 10000;
                pointer-events: none;
                transform: translateY(-10px);
                opacity: 0;
                transition: all 0.2s ease;
            `;
            
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
            
            setTimeout(() => {
                tooltip.style.opacity = '1';
                tooltip.style.transform = 'translateY(0)';
            }, 10);
            
            this._tooltip = tooltip;
        });
        
        element.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                this._tooltip = null;
            }
        });
    });
}

/**
 * Mostrar estado de loading num botão
 * Adiciona spinner e desabilita o botão durante processamento
 */
function showButtonLoading(button) {
    if (!button) return;
    
    const originalText = button.innerHTML;
    button.setAttribute('data-original-text', originalText);
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> A processar...';
    button.disabled = true;
    button.classList.add('loading');
}

/**
 * Esconder estado de loading num botão
 * Restaura o botão ao estado original
 */
function hideButtonLoading(button) {
    if (!button) return;
    
    const originalText = button.getAttribute('data-original-text');
    if (originalText) {
        button.innerHTML = originalText;
    }
    button.disabled = false;
    button.classList.remove('loading');
}

/**
 * Mostrar notificação toast (versão melhorada)
 * Sistema avançado de notificações (Sucesso, Erro, Info, Aviso)
 */
function showToast(title, message, type = 'info') {
    // Criar elemento toast
    const toast = document.createElement('div');
    toast.className = `custom-toast toast-${type}`;
    
    const icons = {
        'success': 'fas fa-check-circle',
        'error': 'fas fa-exclamation-circle',
        'warning': 'fas fa-exclamation-triangle',
        'info': 'fas fa-info-circle'
    };
    
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="${icons[type] || icons.info}"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.body.appendChild(toast);
    
    // Animação de entrada
    setTimeout(() => toast.classList.add('show'), 10);
    
    // Remover automaticamente após 5 segundos
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 300);
    }, 5000);
}

/**
 * Versões específicas do toast para diferentes tipos
 */
function showToastSuccess(title, message) {
    showToast(title, message, 'success');
}

function showToastError(title, message) {
    showToast(title, message, 'error');
}

function showToastInfo(title, message) {
    showToast(title, message, 'info');
}

// Adicionar estilos CSS para os toasts customizados
const toastStyles = `
.custom-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    border-radius: 12px;
    padding: 1rem;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    border-left: 4px solid #4361ee;
    min-width: 300px;
    transform: translateX(400px);
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    z-index: 10000;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.custom-toast.show {
    transform: translateX(0);
    opacity: 1;
}

.custom-toast.toast-success { border-left-color: #06d6a0; }
.custom-toast.toast-error { border-left-color: #ef476f; }
.custom-toast.toast-warning { border-left-color: #ffd166; }
.custom-toast.toast-info { border-left-color: #118ab2; }

.toast-icon {
    font-size: 1.25rem;
    margin-top: 0.125rem;
}

.toast-success .toast-icon { color: #06d6a0; }
.toast-error .toast-icon { color: #ef476f; }
.toast-warning .toast-icon { color: #ffd166; }
.toast-info .toast-icon { color: #118ab2; }

.toast-content {
    flex: 1;
}

.toast-title {
    font-weight: 600;
    color: #2b2d42;
    margin-bottom: 0.25rem;
}

.toast-message {
    color: #6c757d;
    font-size: 0.875rem;
    line-height: 1.4;
}

.toast-close {
    background: none;
    border: none;
    color: #adb5bd;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.toast-close:hover {
    background: #f8f9fa;
    color: #6c757d;
}

@media (max-width: 768px) {
    .custom-toast {
        left: 20px;
        right: 20px;
        min-width: auto;
        transform: translateY(-100px);
    }
    
    .custom-toast.show {
        transform: translateY(0);
    }
}
`;

// Inject styles
const styleSheet = document.createElement('style');
styleSheet.textContent = toastStyles;
document.head.appendChild(styleSheet);

/**
 * Inicializar botões de feedback
 * Configura a lógica para marcar feedbacks como lidos via AJAX
 */
function initializeFeedbackButtons() {
    // Remover handler anterior se existir
    const container = document.querySelector('.feedbacks-container') || document.body;
    if (container._feedbackHandler) {
        container.removeEventListener('click', container._feedbackHandler);
    }

    // Criar novo handler com closure para controle de estado
    const feedbackHandler = function(e) {
        const button = e.target.closest('.marcar-lido');
        if (!button || button.disabled) return; // Ignorar se já estiver processando

        e.preventDefault();
        e.stopPropagation();

        const feedbackId = button.dataset.id;
        const feedbackCard = button.closest('.feedback-card');
        if (!feedbackCard) return;

        // Prevenir múltiplos cliques
        const allButtons = document.querySelectorAll('.marcar-lido');
        allButtons.forEach(btn => btn.disabled = true);

        // Mostrar loading
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';

        // Adicionar overlay para prevenir interação
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: transparent;
            z-index: 9999;
            cursor: wait;
        `;
        document.body.appendChild(overlay);

        fetch('admin_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'marcar_feedback_lido',
                feedback_id: feedbackId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const proximoFeedback = data.data?.proximo_feedback;
                const container = feedbackCard.parentElement;

                // Remover card atual com animação
                feedbackCard.style.transition = 'all 0.3s ease';
                feedbackCard.style.opacity = '0';
                feedbackCard.style.transform = 'translateX(-100px)';

                setTimeout(() => {
                    // Remover card atual
                    feedbackCard.remove();

                    if (proximoFeedback) {
                        // Evitar duplicar: se o próximo feedback já existe no DOM, não o inserimos novamente
                        const existingSelector = `.marcar-lido[data-id="${proximoFeedback.id_feedback}"]`;
                        const existingButton = container.querySelector(existingSelector);

                        if (existingButton) {
                            // O feedback já está presente: apenas opcionalmente destacar / mover para o topo
                            const existingCard = existingButton.closest('.feedback-card');
                            if (existingCard && container.firstElementChild !== existingCard) {
                                // Mover o card existente para o topo (evita duplicação)
                                existingCard.style.transition = 'all 0.25s ease';
                                existingCard.style.opacity = '0.95';
                                container.insertBefore(existingCard, container.firstChild);
                                // pequena animação de destaque
                                existingCard.style.transform = 'translateX(0)';
                            }
                        } else {
                            // Criar elemento para o novo card
                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = criarFeedbackCard(proximoFeedback);
                            const novoCard = tempDiv.firstElementChild;
                            
                            if (novoCard) {
                                // Configurar estado inicial da animação
                                novoCard.style.opacity = '0';
                                novoCard.style.transform = 'translateX(100px)';
                                
                                // Inserir no DOM
                                container.insertBefore(novoCard, container.firstChild);
                                
                                // Forçar reflow
                                novoCard.offsetHeight;
                                
                                // Animar entrada
                                novoCard.style.transition = 'all 0.3s ease';
                                novoCard.style.opacity = '1';
                                novoCard.style.transform = 'translateX(0)';
                            }
                        }
                    } else {
                        // Verificar se não há mais feedbacks
                        const remainingFeedbacks = document.querySelectorAll('.feedback-card');
                        if (remainingFeedbacks.length === 0 && container) {
                            container.innerHTML = `
                                <div class="no-feedback">
                                    <i class="fas fa-inbox"></i>
                                    <p>Nenhum feedback encontrado</p>
                                </div>
                            `;
                        }
                    }

                    showToastSuccess('Sucesso', 'Feedback marcado como lido');
                }, 300);

            } else {
                showToastError('Erro', data.message || 'Erro ao marcar feedback como lido');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showToastError('Erro', 'Não foi possível marcar o feedback como lido');
        })
        .finally(() => {
            // Restaurar estado
            allButtons.forEach(btn => btn.disabled = false);
            button.innerHTML = originalText;
            overlay.remove();
        });
    };

    // Armazenar referência do handler para remoção futura se necessário
    container._feedbackHandler = feedbackHandler;
    
    // Adicionar novo handler
    container.addEventListener('click', feedbackHandler);
}

// Gera o HTML para um cartão de feedback
function criarFeedbackCard(feedback) {
    const data = new Date(feedback.data_feedback);
    const dataFormatada = data.toLocaleString('pt-PT', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });

    return `
        <div class="feedback-card animate-feedback" style="opacity: 0; transform: translateX(100px); transition: all 0.5s ease">
            <div class="feedback-header">
                <div class="feedback-user">
                    <div class="user-avatar gradient-bg">
                        ${feedback.nome.charAt(0).toUpperCase()}
                    </div>
                    <div class="user-info">
                        <h3>${escapeHtml(feedback.nome)}</h3>
                        <span class="feedback-date">
                            <i class="far fa-clock"></i>
                            ${dataFormatada}
                        </span>
                    </div>
                </div>
                <div class="rating-badge rating-${feedback.rating}">
                    ${Array(5).fill(0).map((_, i) => 
                        `<i class="fa${i < feedback.rating ? 's' : 'r'} fa-star"></i>`
                    ).join('')}
                    <span class="rating-text">${feedback.rating}/5</span>
                </div>
            </div>
            
            <div class="feedback-content">
                ${feedback.gostou ? `
                    <div class="feedback-text positive">
                        <i class="fas fa-thumbs-up"></i>
                        <p>${escapeHtml(feedback.gostou)}</p>
                    </div>
                ` : ''}
                
                ${feedback.melhoria ? `
                    <div class="feedback-text suggestion">
                        <i class="fas fa-lightbulb"></i>
                        <p>${escapeHtml(feedback.melhoria)}</p>
                    </div>
                ` : ''}
            </div>
            
            <div class="feedback-actions">
                <button type="button" class="btn btn-success btn-sm marcar-lido" data-id="${feedback.id_feedback}">
                    <i class="fas fa-check"></i> Marcar como Lido
                </button>
            </div>
        </div>
    `;
}

// Escapa caracteres HTML para prevenir XSS
function escapeHtml(string) {
    const div = document.createElement('div');
    div.textContent = string;
    return div.innerHTML;
}