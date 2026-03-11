class ModalSystem {
    static show(title, message, type = 'info', buttons = ['OK']) {
        const modalId = 'modal-' + Date.now();
        const modal = document.createElement('div');
        modal.id = modalId;
        modal.className = `custom-modal custom-modal-${type}`;
        
        const iconMap = {
            'info': 'ℹ',
            'warning': '⚠',
            'error': '✕',
            'success': '✓',
            'confirm': '?'
        };
        
        const icon = iconMap[type] || '●';
        
        let buttonsHtml = '';
        buttons.forEach((btn, index) => {
            const isDefault = index === buttons.length - 1;
            const btnClass = isDefault ? 'btn-modal-primary' : 'btn-modal-secondary';
            buttonsHtml += `<button class="btn-modal ${btnClass}" data-action="${btn}">${btn}</button>`;
        });
        
        modal.innerHTML = `
            <div class="modal-overlay"></div>
            <div class="modal-box">
                <div class="modal-header-custom">
                    <span class="modal-icon-custom modal-icon-${type}">${icon}</span>
                    <h2>${title}</h2>
                    <button class="modal-close-btn" aria-label="Fechar">&times;</button>
                </div>
                <div class="modal-message-custom">${message}</div>
                <div class="modal-buttons-custom">
                    ${buttonsHtml}
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        return new Promise((resolve) => {
            const closeModal = () => modal.remove();
            
            modal.querySelector('.modal-close-btn').addEventListener('click', () => {
                closeModal();
                resolve(buttons[0]);
            });
            
            modal.querySelector('.modal-overlay').addEventListener('click', () => {
                closeModal();
                resolve(buttons[0]);
            });
            
            modal.querySelectorAll('.btn-modal').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const action = e.target.dataset.action;
                    closeModal();
                    resolve(action);
                });
            });
        });
    }
    
    static alert(title, message = '') {
        return this.show(title, message, 'info', ['OK']);
    }
    
    static confirm(title, message = '') {
        return this.show(title, message, 'confirm', ['Cancelar', 'Confirmar']).then(result => {
            return result === 'Confirmar';
        });
    }
    
    static success(title, message = '') {
        return this.show(title, message, 'success', ['OK']);
    }
    
    static error(title, message = '') {
        return this.show(title, message, 'error', ['OK']);
    }
    
    static warning(title, message = '') {
        return this.show(title, message, 'warning', ['OK']);
    }
}
