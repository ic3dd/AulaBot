# Estrutura do Projeto AulaBot

## Organização em Pastas

```
AulaBot/
├── api/                 # Endpoints da API (chat, vision, live_chat, etc.)
├── assets/
│   ├── css/             # Estilos (style.css, perfil.css)
│   ├── js/             # Scripts do frontend (reactive.js, personalizacao.js, etc.)
│   └── img/            # Imagens (logos, ícones)
├── auth/               # Autenticação (logout, auth_check, guest_control, etc.)
├── config/             # Configurações (ligarbd, bloqueio_check, config_secrets)
├── conta/              # Gestão de conta (login, criar conta, reset password)
├── database/           # Scripts SQL e migrações
├── scripts/            # Scripts auxiliares (carregar/salvar preferências, etc.)
├── admin/              # Painel de administração
├── aba-ajuda/          # Ajuda e chat de suporte
├── aba-feedback/       # Sistema de feedback
├── python/             # Scripts Python (OCR, etc.)
├── uploads/            # Ficheiros enviados pelos utilizadores
├── logs/               # Logs da aplicação
├── PHPMailer-7.0.0/    # Biblioteca de email
├── index.php           # Página principal (dashboard)
├── welcome.php         # Página de boas-vindas / login
├── blocked.php         # Conta bloqueada
├── site_bloqueado.php  # Site em manutenção
├── live_chat.php       # (redireciona para api/live_chat.php)
└── termos.html, privacidade.html
```

## Configuração

1. **Base de dados**: Edite `config/ligarbd.php` com as credenciais.
2. **Segredos**: Copie `config/config_secrets.example.php` para `config/config_secrets.php` e preencha as chaves (GROQ_API_KEY, etc.).

## Notas

- Os ficheiros `ligarbd.php` e `bloqueio_check.php` na raiz são redirecionamentos para `config/`.
- O `image.php` na raiz redireciona para `auth/image.php`.
