# ⚙️ Worker de Processamento de Ocorrências

Worker assíncrono desenvolvido com **Laravel** para processar comandos de ocorrências e despachos através de filas **RabbitMQ**. Segue os mesmos princípios de **DDD (Domain-Driven Design)** e **Arquitetura Hexagonal** da API.

## 🚀 Como Rodar

### Pré-requisitos
- Docker e Docker Compose instalados
- RabbitMQ e PostgreSQL rodando (geralmente iniciados pela API)

### Executando com Docker Compose

```bash
cd docker
docker-compose up -d
```

Isso irá subir o **Worker** na porta `8014`.

### Configuração Inicial

Após subir o container, execute:

```bash
# Entrar no container do Worker
docker exec -it worker-occurrence bash

# Instalar dependências
composer install

# Configurar ambiente
cp .env.example .env

# O Worker já inicia automaticamente o processamento de filas
```

## 🔄 Como Funciona

### Processamento de Filas

O Worker consome comandos da fila RabbitMQ (`occurrences.jobs`) e processa de forma assíncrona:

1. **Recebe comando da fila** → Worker consome mensagem do RabbitMQ
2. **Valida idempotência** → Verifica se o comando já foi processado
3. **Executa regras de negócio** → Processa o comando através dos serviços de domínio
4. **Atualiza status** → Marca o comando como `success` ou `failed` no `command_inbox`
5. **Invalida cache** → Atualiza cache do Redis quando necessário

### Comandos Processados

O Worker processa os seguintes tipos de comandos:

- `create_occurrence` - Criação de ocorrências
- `start_occurrence` - Início de atendimento de ocorrência
- `resolve_occurrence` - Resolução de ocorrência
- `create_dispatch` - Criação de despachos
- `close_dispatch` - Fechamento de despachos
- `update_dispatch_status` - Atualização de status de despachos

### Arquitetura

- **Domain Layer**: Entidades e regras de negócio puras
- **Application Layer**: Processadores de comandos
- **Infrastructure Layer**: Adaptadores de banco, fila e cache
- **Jobs**: Handlers assíncronos para cada tipo de comando

### Resiliência

- **Idempotência**: Verifica duplicatas antes de processar
- **Retry automático**: Laravel Queue retenta falhas automaticamente
- **Dead Letter Queue**: Comandos com falha são movidos para DLQ
- **Logs estruturados**: Todas as operações são registradas para auditoria

---
