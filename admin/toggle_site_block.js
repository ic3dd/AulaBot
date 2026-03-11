// Script para gerir o botão de bloqueio/desbloqueio global do site
document.addEventListener('DOMContentLoaded', function() {
    const toggleButton = document.getElementById('toggleSiteBlock');
    
    if (toggleButton) {
        toggleButton.addEventListener('click', async function() {
            // Determina o estado atual baseado na classe do botão
            const isCurrentlyBlocked = toggleButton.classList.contains('btn-danger');
            const confirmMessage = isCurrentlyBlocked ? 
                'Tem certeza que deseja desbloquear o acesso ao site para todos os usuários?' :
                'Tem certeza que deseja bloquear o acesso ao site para todos os usuários? Apenas administradores poderão acessar.';
            
            // Pede confirmação ao administrador
            const confirmed = await ModalSystem.confirm('Confirmar Ação', confirmMessage);
            if (!confirmed) {
                return;
            }
            
            try {
                // Envia o pedido para o servidor
                const response = await fetch('toggle_site_block.php', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    // Atualizar o botão
                    toggleButton.classList.toggle('btn-danger');
                    toggleButton.classList.toggle('btn-success');
                    
                    // Atualizar o ícone
                    const icon = toggleButton.querySelector('i');
                    icon.classList.toggle('fa-lock');
                    icon.classList.toggle('fa-lock-open');
                    
                    // Atualizar o texto
                    const span = toggleButton.querySelector('span');
                    span.textContent = data.bloqueado ? 'Desbloquear Site' : 'Bloquear Site';
                    
                    // Mostrar notificação
                    showToast(
                        data.bloqueado ? 'Site Bloqueado' : 'Site Desbloqueado',
                        data.bloqueado ? 
                            'O acesso ao site foi bloqueado para usuários não administradores.' :
                            'O acesso ao site foi liberado para todos os usuários.',
                        data.bloqueado ? 'warning' : 'success'
                    );
                } else {
                    showToast('Erro', data.message || 'Não foi possível alterar o estado do site', 'error');
                }
            } catch (error) {
                console.error('Erro ao alternar bloqueio:', error);
                let errorMessage = 'Ocorreu um erro ao tentar alterar o estado do site';
                
                // Tentar extrair mensagem de erro mais específica
                if (error.message) {
                    errorMessage += ': ' + error.message;
                }
                
                showToast('Erro', errorMessage, 'error');
            }
        });
    }
});