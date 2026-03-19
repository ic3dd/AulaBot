document.addEventListener('DOMContentLoaded', function() {
    const notificationDot = document.getElementById('notification-dot-ajuda');

    function checkUnreadMessages() {
        if (!notificationDot) {
            console.warn('Elemento #notification-dot-ajuda não encontrado — pulando verificação de notificações.');
            return;
        }

        // Use safeFetchJson to handle non-JSON responses or HTTP errors gracefully
        (async function() {
            try {
                const res = await fetch('api/api_check_unread.php', { cache: 'no-store' });
                if (!res.ok) {
                    const txt = await res.text().catch(() => '');
                    console.error('api_check_unread.php HTTP', res.status, txt);
                    notificationDot.style.display = 'none';
                    return;
                }
                let data;
                try {
                    data = await res.json();
                } catch (e) {
                    const txt = await res.text().catch(() => '');
                    console.error('api_check_unread.php returned invalid JSON', e, txt);
                    notificationDot.style.display = 'none';
                    return;
                }

                if (data.unread) {
                    notificationDot.style.display = 'inline-block';
                } else {
                    notificationDot.style.display = 'none';
                }
            } catch (error) {
                console.error('Erro ao verificar mensagens não lidas:', error);
            }
        })();
    }

    // Check for unread messages every 30 seconds
    setInterval(checkUnreadMessages, 30000);

    // Initial check
    checkUnreadMessages();
});
