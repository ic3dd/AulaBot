document.addEventListener('DOMContentLoaded', function() {
    const notificationDot = document.getElementById('notification-dot-ajuda');

    function checkUnreadMessages() {
        if (!notificationDot) {
            console.warn('Elemento #notification-dot-ajuda não encontrado — pulando verificação de notificações.');
            return;
        }

        fetch('../api_check_unread.php')
            .then(response => response.json())
            .then(data => {
                if (data.unread) {
                    notificationDot.style.display = 'inline-block';
                } else {
                    notificationDot.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Erro ao verificar mensagens não lidas:', error);
            });
    }

    // Check for unread messages every 30 seconds
    setInterval(checkUnreadMessages, 30000);

    // Initial check
    checkUnreadMessages();
});
