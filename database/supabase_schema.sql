-- ============================================================
-- AulaBot - Schema PostgreSQL para Supabase
-- Convertido de MySQL (Base_de_Dados.sql)
-- ============================================================

-- Extensão para UUID (se necessário)
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Tabela: anuncios_vistos
CREATE TABLE anuncios_vistos (
  id_anuncios_vistos SERIAL PRIMARY KEY,
  id_utilizador INT NOT NULL,
  id_update INT NOT NULL,
  data_visualizacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(id_utilizador, id_update)
);

-- Tabela: utilizador (criar primeiro por causa das FKs)
CREATE TABLE utilizador (
  id_utilizador SERIAL PRIMARY KEY,
  nome VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  palavra_passe VARCHAR(255) NOT NULL,
  tipo VARCHAR(20) NOT NULL DEFAULT 'utilizador' CHECK (tipo IN ('utilizador','admin','ai')),
  tema VARCHAR(10) NOT NULL DEFAULT 'claro' CHECK (tema IN ('claro','escuro')),
  cor VARCHAR(20) DEFAULT '#28a745',
  data_criacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notif_atualizacoes SMALLINT DEFAULT 1,
  notif_manutencao SMALLINT DEFAULT 1,
  notif_novidades SMALLINT DEFAULT 1,
  notif_seguranca SMALLINT DEFAULT 1,
  notif_performance SMALLINT DEFAULT 0,
  reset_token VARCHAR(255) DEFAULT NULL,
  reset_token_expiry TIMESTAMP DEFAULT NULL,
  bloqueado INT DEFAULT 0,
  motivo_bloqueio VARCHAR(255) DEFAULT NULL,
  data_bloqueio TIMESTAMP DEFAULT NULL,
  tema_escola TEXT,
  mensagem VARCHAR(1) NOT NULL DEFAULT '0' CHECK (mensagem IN ('0','1')),
  fonte VARCHAR(10) DEFAULT 'medium'
);

-- Tabela: chats
CREATE TABLE chats (
  id_chat SERIAL PRIMARY KEY,
  id_utilizador INT NOT NULL,
  titulo TEXT NOT NULL,
  data_criacao_chat TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Tabela: chat_ajuda
CREATE TABLE chat_ajuda (
  id SERIAL PRIMARY KEY,
  id_utilizador INT NOT NULL,
  estado VARCHAR(10) NOT NULL DEFAULT 'aberto' CHECK (estado IN ('aberto','fechado')),
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela: configuracoes_site
CREATE TABLE configuracoes_site (
  id SERIAL PRIMARY KEY,
  site_bloqueado SMALLINT DEFAULT 0
);

-- Tabela: bloqueio (usada pelo admin para bloquear o site)
CREATE TABLE bloqueio (
  id_bloqueio SERIAL PRIMARY KEY,
  bloqueio SMALLINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO bloqueio (bloqueio) VALUES (0);

-- Tabela: feedback
CREATE TABLE feedback (
  id_feedback SERIAL PRIMARY KEY,
  nome VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  rating VARCHAR(1) NOT NULL CHECK (rating IN ('1','2','3','4','5')),
  gostou VARCHAR(200) DEFAULT NULL,
  melhoria VARCHAR(255) DEFAULT NULL,
  autorizacao VARCHAR(3) NOT NULL CHECK (autorizacao IN ('sim','nao')),
  data_feedback TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  lido VARCHAR(3) DEFAULT NULL CHECK (lido IN ('sim','nao'))
);

-- Tabela: mensagens_imagem (antes de mensagens por causa da FK)
CREATE TABLE mensagens_imagem (
  id_imagem SERIAL PRIMARY KEY,
  id_mensagem INT NOT NULL,
  filename VARCHAR(255),
  mime VARCHAR(100),
  content BYTEA,
  data_insercao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela: mensagens
CREATE TABLE mensagens (
  id_mensagem SERIAL PRIMARY KEY,
  id_chat INT DEFAULT NULL,
  id_imagem INT DEFAULT NULL,
  pergunta TEXT,
  resposta TEXT,
  data_conversa TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Tabela: mensagens_chat_ajuda
CREATE TABLE mensagens_chat_ajuda (
  id SERIAL PRIMARY KEY,
  conversation_id INT NOT NULL,
  sender VARCHAR(20) NOT NULL CHECK (sender IN ('utilizador','admin')),
  conteudo TEXT NOT NULL,
  enviado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela: registo
CREATE TABLE registo (
  id_registo SERIAL PRIMARY KEY,
  reg VARCHAR(10) NOT NULL CHECK (reg IN ('login','logout')),
  data TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_utilizador INT NOT NULL
);

-- Tabela: updates
CREATE TABLE updates (
  id_update SERIAL PRIMARY KEY,
  nome VARCHAR(255) NOT NULL,
  versao VARCHAR(100) DEFAULT NULL,
  descricao TEXT NOT NULL,
  data_update TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tema VARCHAR(50) DEFAULT 'atualizacoes'
);

-- Tabela: uso_convidado
CREATE TABLE uso_convidado (
  id BIGSERIAL PRIMARY KEY,
  ip_hash VARCHAR(64) NOT NULL,
  id_anonimo CHAR(36) NOT NULL,
  total_pedidos INT NOT NULL DEFAULT 0,
  data_primeiro_pedido TIMESTAMP NOT NULL,
  data_ultimo_pedido TIMESTAMP NOT NULL,
  data_expiracao TIMESTAMP NOT NULL,
  bloqueado SMALLINT NOT NULL DEFAULT 0,
  data_criacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(ip_hash, id_anonimo)
);

-- Dados iniciais
INSERT INTO configuracoes_site (id, site_bloqueado) VALUES (1, 0);

-- Sequências (para manter IDs após importação de dados)
-- Se importar dados, pode precisar: SELECT setval('utilizador_id_utilizador_seq', (SELECT MAX(id_utilizador) FROM utilizador));
