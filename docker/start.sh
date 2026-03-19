#!/bin/bash
set -e

# Render define PORT (ex: 10000)
PORT=${PORT:-80}

# Configurar Apache para escutar na porta correta
echo "Listen $PORT" > /etc/apache2/ports.conf
cat > /etc/apache2/sites-available/000-default.conf << EOF
<VirtualHost *:$PORT>
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF

exec apache2-foreground
