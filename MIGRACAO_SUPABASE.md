# Migração da Base de Dados para Supabase

Este guia explica como transferir a base de dados do AulaBot do MySQL para o Supabase (PostgreSQL).

---

## Opção 1: Migração com pgloader (MySQL em execução)

Se tens o MySQL a correr (XAMPP, servidor escolar, etc.) com os dados já importados:

### 1. Cria um projeto no Supabase
- Vai a [supabase.com/dashboard](https://supabase.com/dashboard)
- Clica em **New Project**
- Guarda a **Database Password** e o **Connection string**

### 2. Obtém a connection string do Supabase
- No dashboard: **Project Settings** → **Database**
- Em **Connection string**, escolhe **URI** e **Session mode** (para connection pooling)
- Formato: `postgresql://postgres.[PROJECT-REF]:[PASSWORD]@aws-0-[REGION].pooler.supabase.com:5432/postgres`

### 3. Cria o ficheiro de configuração do pgloader

Cria `config.load`:

```lisp
LOAD DATABASE
  FROM mysql://aluno19355:bCXaf1CsciCwG5F@localhost/aluno19355
  INTO postgresql://postgres.[PROJECT-REF]:[SUA_PASSWORD]@aws-0-[REGION].pooler.supabase.com:5432/postgres

ALTER SCHEMA 'aluno19355' RENAME TO 'public'
SET wal_buffers = '64MB', work_mem to '256MB';
```

Substitui os valores pelos teus.

### 4. Instala e executa o pgloader

**Windows:** Descarrega de [pgloader.io](https://pgloader.io/) ou usa WSL.

**Linux/Mac:**
```bash
# Ubuntu/Debian
sudo apt install pgloader

# Executar
pgloader config.load
```

---

## Opção 2: Schema novo no Supabase (sem dados)

Se queres começar do zero no Supabase:

### 1. Cria um projeto no Supabase
- [supabase.com/dashboard](https://supabase.com/dashboard) → **New Project**

### 2. Executa o schema no SQL Editor
- No dashboard: **SQL Editor** → **New query**
- Copia o conteúdo de `database/supabase_schema.sql`
- Clica em **Run**

### 3. Configura a ligação no PHP
- Edita `config/config_secrets.php` (ou cria a partir do exemplo)
- Adiciona as credenciais do Supabase (ver secção abaixo)

---

## Configuração da ligação PHP ao Supabase

O Supabase usa **PostgreSQL**, não MySQL. O teu código usa **mysqli** (MySQL). Tens duas opções:

### A) Manter MySQL (mais simples)
Se o teu servidor de hospedagem tiver MySQL, podes manter a base de dados em MySQL e não migrar. O ficheiro `Base_de_Dados.sql` pode ser importado em qualquer MySQL.

### B) Migrar para Supabase (requer alterações no código)
Para usar Supabase, o PHP precisa de ligar a PostgreSQL. O código atual usa `mysqli` em muitos ficheiros. Será necessário:

1. **Usar PDO com PostgreSQL** em vez de mysqli
2. **Alterar** as chamadas `mysqli_*` para PDO em ~40 ficheiros

Posso ajudar a criar um ficheiro de ligação compatível e a planear essa migração.

---

## Credenciais Supabase

Quando criares o projeto, guarda:

| Variável | Onde encontrar |
|----------|----------------|
| **Project URL** | Dashboard → Settings → API |
| **anon key** | Dashboard → Settings → API |
| **service_role key** | Dashboard → Settings → API (para operações server-side) |
| **Database password** | Definida ao criar o projeto |
| **Connection string** | Dashboard → Settings → Database → Connection string |

---

## Ferramenta alternativa: Google Colab

A Supabase tem um [notebook no Google Colab](https://colab.research.google.com/github/mansueli/Supa-Migrate/blob/main/Amazon_RDS_to_Supabase.ipynb) que automatiza a migração MySQL → Supabase. Podes adaptá-lo para a tua base de dados.

---

## Próximos passos

1. Escolhe a opção (1 ou 2) conforme a tua situação
2. Se precisares de migrar o código PHP para PostgreSQL, avisa e preparo os ficheiros necessários
3. Depois da migração, atualiza `config/ligarbd.php` com as novas credenciais
