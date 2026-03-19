# Onde obter as credenciais do Supabase

## 1. Criar projeto

1. Acede a **https://supabase.com/dashboard**
2. Clica em **New Project** (ou **Create a new project**)
3. Escolhe um nome (ex: `aulabot`)
4. Define uma **Database Password** (guarda-a bem – vais precisar)
5. Escolhe a região e clica em **Create new project**

---

## 2. Connection string (para o PHP)

**Método A – Botão Connect (mais fácil):**

1. Entra no teu projeto no dashboard
2. No topo da página, procura e clica no botão **Connect** (ou **Connect to your project**)
3. No painel que abrir, escolhe **Session mode** (ou **Supavisor Session mode**)
4. Copia a connection string que aparece (formato URI)
5. Substitui `[YOUR-PASSWORD]` pela password que definiste ao criar o projeto

postgresql://postgres:[twlruaZApG5rKJQb]@db.zdoycrhztbebhbmzjfom.supabase.co:5432/postgres

**Método B – Definições do projeto:**

1. No dashboard do projeto, clica no ícone de **engrenagem** (Settings) no menu lateral esquerdo
2. No menu que aparece, escolhe **Project Settings** (Definições do projeto)
3. No submenu à esquerda, clica em **Database**
4. Desce até à secção **Connection string**
5. Seleciona **URI** e depois **Session mode**
6. Copia a string e substitui `[YOUR-PASSWORD]` pela tua password

**Formato esperado da string:**
```
postgresql://postgres.[PROJECT-REF]:[YOUR-PASSWORD]@aws-0-[REGIAO].pooler.supabase.com:5432/postgres
```

---

## 3. Configurar no AulaBot

1. Copia `config/config_secrets.example.php` para `config/config_secrets.php`
2. No final de `config/config_secrets.php`, adiciona:
   ```php
   define('USE_SUPABASE', true);
   define('SUPABASE_DB_URL', 'postgresql://postgres.XXXXX:TUAPASSWORD@aws-0-eu-central-1.pooler.supabase.com:5432/postgres');
   ```
3. Cola a tua connection string completa no lugar do exemplo

---

## 4. Criar as tabelas no Supabase

1. No menu lateral esquerdo do dashboard, clica em **SQL Editor** (ícone de código/terminal)
2. Clica em **New query** (ou **+ New query**)
3. Copia todo o conteúdo de `database/supabase_schema.sql`
4. Cola no editor e clica em **Run** (ou **Execute**)

---

## 5. Se esqueceres a password

1. Clica no ícone de **engrenagem** (Settings) no menu lateral
2. Escolhe **Project Settings** → **Database**
3. Na secção **Database password**, clica em **Reset database password**
4. Define uma nova password e guarda-a
5. Atualiza `SUPABASE_DB_URL` em `config/config_secrets.php` com a nova password

---

## Resumo

| O que precisas | Onde está |
|----------------|-----------|
| **Connection string** | Botão **Connect** no topo do projeto → Session mode, ou Settings → Database → Connection string |
| **Password** | A que definiste ao criar o projeto (ou em Settings → Database → Reset password) |
| **SQL Editor** | Menu lateral esquerdo → **SQL Editor** |

---

## Se não encontrares algo

- **Connect** – Botão no canto superior direito ou no topo da página do projeto
- **Settings / Project Settings** – Ícone de engrenagem no menu lateral esquerdo
- **Database** – Dentro de Project Settings, no submenu à esquerda
- A interface pode estar em inglês: *Connect*, *Settings*, *Database*, *SQL Editor*
