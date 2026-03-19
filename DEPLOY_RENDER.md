# Deploy do AulaBot no Render

Guia para colocar o AulaBot online no Render usando Docker e Supabase.

---

## Pré-requisitos

- Conta no [Render](https://render.com)
- Conta no [Supabase](https://supabase.com) (base de dados)
- Repositório no GitHub com o código do AulaBot

---

## 1. Preparar o Supabase

1. Cria um projeto no [Supabase Dashboard](https://supabase.com/dashboard)
2. Executa o schema: **SQL Editor** → cola o conteúdo de `database/supabase_schema.sql` → **Run**
3. Obtém a connection string: **Connect** → **Session mode** → copia a URI
4. Substitui `[YOUR-PASSWORD]` pela tua password

---

## 2. Criar o Web Service no Render

1. Entra em [dashboard.render.com](https://dashboard.render.com)
2. Clica em **New +** → **Web Service**
3. Liga o teu repositório GitHub (autoriza o Render se for a primeira vez)
4. Seleciona o repositório **AulaBot**
5. Configura:
   - **Name:** `aulabot` (ou outro nome)
   - **Region:** escolhe a mais próxima
   - **Branch:** `main`
   - **Runtime:** **Docker**
   - **Instance Type:** Free (ou pago para mais recursos)

---

## 3. Variáveis de Ambiente

No Render, vai ao teu serviço → **Environment** e adiciona:

| Variável | Valor |
|----------|-------|
| `USE_SUPABASE` | `true` |
| `SUPABASE_DB_URL` | Connection string do **pooler** (ver abaixo) |
| `GROQ_API_KEY` | `gsk_...` (tua chave da API Groq) |
| `IP_SECRET_PEPPER` | `uma_string_aleatoria_secreta` |
| `DEBUG_ENDPOINT_TOKEN` | `token_seguro_para_debug` |

**SUPABASE_DB_URL – usa o pooler, não a conexão direta:**
- Supabase → **Connect** → **Session pooler** ou **Transaction pooler**
- Formato: `postgresql://postgres.PROJECT_REF:PASSWORD@aws-1-eu-west-1.pooler.supabase.com:6543/postgres`
- A conexão direta (`db.xxx.supabase.co:5432`) falha no Render com "Network unreachable"

---

## 4. Deploy

1. Clica em **Create Web Service**
2. O Render vai construir a imagem Docker e fazer o deploy
3. Aguarda alguns minutos
4. O teu site ficará em `https://aulabot.onrender.com` (ou o nome que escolheste)

---

## 5. Notas

- **Free tier:** O serviço pode "adormecer" após inatividade. O primeiro acesso pode demorar ~30 segundos
- **Sessões:** As sessões PHP são guardadas no servidor. Se o serviço reiniciar, os utilizadores terão de fazer login novamente
- **Logs:** Em **Logs** no Render podes ver erros e mensagens do PHP
- **Domínio próprio:** Em **Settings** → **Custom Domain** podes associar um domínio teu

---

## Resumo das variáveis

| Onde obter |
|------------|
| **SUPABASE_DB_URL** | Supabase → Connect → Session mode |
| **GROQ_API_KEY** | [console.groq.com](https://console.groq.com) → API Keys |
