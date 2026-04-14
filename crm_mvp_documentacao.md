# CRM de Gestão de Leads — Documentação Inicial do Projeto

## 1. Visão geral

Este projeto consiste na criação de um **CRM de gestão de leads** com foco comercial, operando inicialmente com um **MVP enxuto**, mas já estruturado para evolução futura.

A proposta é desenvolver o sistema em:

- **PHP puro** no backend
- **JavaScript vanilla** no frontend
- **MySQL** como banco de dados em desenvolvimento e produção
- Interface **moderna, premium, responsiva e orientada a produtividade**

O sistema deve priorizar velocidade operacional do time comercial, visualização clara do pipeline, histórico de interação, qualificação do lead e preparação para integrações futuras.

---

## 2. Objetivo do MVP

O MVP deve resolver o problema central:

> importar leads, organizar em pipeline Kanban, acionar contato rápido via WhatsApp e acompanhar o avanço comercial com histórico, tempo de resposta e conversão.

### Resultado esperado do MVP

- importar leads por CSV
- exibir leads em pipeline Kanban
- registrar primeiro contato via botão `wa.me`
- controlar último contato e próxima ação
- registrar histórico automático e manual
- permitir qualificação básica do lead
- mostrar dashboard básico de conversão
- suportar múltiplas esteiras no futuro

---

## 3. Referências funcionais consideradas

A documentação base do MVP define um **pipeline linear de 7 dias**, com foco em velocidade de operação de vendas, incluindo:

1. Pendentes / Leads Novos  
2. Aguardando Resposta  
3. HOT — respondeu em 24h  
4. WARM — follow-up dias 3–5  
5. COLD — última chance dia 7  
6. Venda Fechada  
7. Perdido / Win-back

Além disso, foram considerados os requisitos de:

- importação CSV
- Kanban com drag and drop
- gatilho rápido de WhatsApp via `wa.me`
- controle de último contato e próxima ação
- histórico cronológico
- dashboard básico de conversão

---

## 4. Escopo inicial do sistema

## 4.1 Funcionalidades do MVP

### Leads
- cadastro manual de lead
- importação de leads por CSV
- validação de duplicidade por telefone
- edição de lead
- visualização detalhada do lead
- filtros por origem, status, produto, tags e responsável

### Pipeline / Kanban
- Kanban horizontal com colunas fixas
- cards arrastáveis entre etapas
- atualização automática de estágio
- contador de leads por coluna
- destaque visual por urgência
- múltiplas esteiras preparadas para versão futura

### WhatsApp
- botão de ação rápida via `wa.me`
- templates de mensagem por etapa
- abertura direta da conversa com texto preenchido
- registro automático de disparo no histórico
- mudança automática de etapa quando aplicável

### Histórico
- histórico cronológico por lead
- notas manuais
- eventos automáticos
- auditoria simples por usuário, data e hora
- registros sem edição retroativa

### Qualificação básica
- etiquetas/tags
- origem do lead
- produto de interesse
- observações
- lead score inicial simples
- temperatura comercial visual

### Agenda comercial
- campo “próxima ação”
- prazo/data da ação
- alerta visual de atraso
- lista de tarefas vencidas / do dia

### Dashboard básico
- total de leads importados
- total por etapa
- taxa de conversão
- leads perdidos
- filtro por período
- filtro por esteira

### Administração
- login
- perfis básicos de acesso
- usuários/vendedores
- templates de mensagens
- configurações gerais

---

## 5. Funcionalidades planejadas para fases seguintes

## 5.1 Versão 2
- integração com Evolution API para mensagens dentro do sistema
- chat interno por lead
- envio de mensagens sem sair do CRM
- mensagens programadas
- disparos em lote
- automações simples por evento

## 5.2 Versão 3
- integração automática com ActiveCampaign ou outra fonte de leads
- importação automática por webhook/API
- esteiras por produto
- regras automatizadas de distribuição de leads
- lead scoring avançado
- agentes de IA
- recomendações automáticas de próxima ação
- insights comerciais e análise preditiva

---

## 6. Recomendação de arquitetura do projeto

Mesmo sendo em PHP puro, a recomendação é **não criar um projeto desorganizado com páginas misturando HTML, regra de negócio e SQL**.

A melhor abordagem para o plano inicial é uma estrutura modular inspirada em MVC leve.

## 6.1 Arquitetura sugerida

```text
/public
  index.php
  /assets
    /css
    /js
    /images

/app
  /Controllers
  /Models
  /Services
  /Repositories
  /Helpers
  /Middleware
  /Views
  /Core

/config
  app.php
  database.php
  routes.php

/storage
  /logs
  /uploads
  /tmp

/database
  schema.sql
  seeds.sql
  migrations/
```

## 6.2 Separação de responsabilidades

### Controllers
Recebem requisições, validam entrada e chamam services.

### Services
Executam regras de negócio.
Exemplo:
- importar CSV
- mover lead no Kanban
- registrar contato via WhatsApp
- calcular score

### Repositories
Concentram queries SQL e acesso ao banco.

### Views
Renderizam telas HTML.

### Core
Roteamento, autenticação, request, response, sessão, helpers.

---

## 7. Sugestões importantes para o plano inicial

### 1. Criar desde o início suporte a múltiplos pipelines
Mesmo que o MVP use apenas uma esteira principal, a modelagem deve prever:
- pipeline
- etapas por pipeline
- ordem das etapas
- tipo da etapa

Isso evita refatoração grande quando entrar “esteiras por produto”.

### 2. Criar uma tabela de eventos do lead
Em vez de salvar tudo apenas em um campo de texto, criar um histórico estruturado:
- tipo do evento
- descrição
- usuário
- data/hora
- metadados em JSON

Isso ajuda muito em auditoria, automação futura e IA.

### 3. Não acoplar WhatsApp diretamente ao card
Crie um módulo de “ações de comunicação”. No MVP ele abre `wa.me`; depois o mesmo ponto evolui para Evolution API sem reescrever a lógica inteira.

### 4. Separar stage atual de status comercial
Exemplo:
- `stage_id`: etapa atual no Kanban
- `status`: ativo, ganho, perdido, arquivado

Isso dá mais flexibilidade para relatórios.

### 5. Adotar soft delete
Nunca apagar lead fisicamente no início.
Use `deleted_at` quando necessário.

### 6. Padronizar telefonia desde a importação
Salvar telefone sempre em formato limpo:
- apenas números
- DDI e DDD padronizados
- campo pronto para WhatsApp

Isso evita falhas futuras na integração.

### 7. Preparar API interna desde já
Mesmo em PHP puro, já vale criar rotas com resposta JSON para:
- mover card
- adicionar nota
- atualizar lead
- filtrar Kanban
- dashboard

Isso melhora o frontend com JS vanilla e facilita futura migração para app ou SPA híbrida.

---

## 8. Fluxo operacional do MVP

## 8.1 Entrada do lead
1. Usuário importa CSV
2. Sistema valida colunas obrigatórias
3. Sistema normaliza telefone
4. Sistema verifica duplicidade
5. Sistema cria lead
6. Sistema posiciona lead na etapa inicial “Pendentes / Leads Novos”
7. Sistema registra evento de importação no histórico

## 8.2 Primeiro contato
1. Vendedor abre card
2. Clica em “Contatar no WhatsApp”
3. Sistema monta link `wa.me`
4. Sistema registra evento “primeiro contato enviado”
5. Sistema atualiza `ultimo_contato_em`
6. Sistema move lead para “Aguardando Resposta”

## 8.3 Follow-up
1. Lead sem resposta avança conforme regra temporal
2. Vendedor pode usar template WARM ou COLD
3. Sistema registra novo evento
4. Sistema atualiza próxima ação

## 8.4 Fechamento
1. Lead convertido vai para “Venda Fechada”
2. Sistema registra data de conversão
3. Sistema permite informar produto, valor e observação

## 8.5 Perda / win-back
1. Lead não convertido vai para “Perdido / Win-back”
2. Sistema registra motivo de perda
3. Lead fica apto para campanhas futuras

---

## 9. Regras de negócio iniciais

### Regras principais
- todo lead importado entra em etapa inicial
- duplicidade prioritariamente por telefone
- clique no botão WhatsApp registra interação
- movimentação manual de card deve gerar histórico
- cada lead deve ter responsável
- última ação e próxima ação devem ser visíveis
- leads atrasados devem ter destaque visual
- notas anteriores não devem ser editadas

### Regras de qualificação
- tags livres e também opcionais pré-cadastradas
- score inicial baseado em critérios simples
- classificação visual: HOT, WARM, COLD

### Regras de métricas
- taxa de conversão = fechados / leads importados no período
- perda = leads encerrados sem conversão
- tempo médio de resposta pode entrar em segunda fase

---

## 10. Módulos do sistema

## 10.1 Módulo de autenticação
- login
- logout
- sessão
- recuperação de acesso em fase futura

## 10.2 Módulo de usuários
- cadastro de usuários
- papéis: admin, gestor, vendedor
- vínculo com leads e histórico

## 10.3 Módulo de leads
- cadastro
- importação
- edição
- detalhe
- filtros
- qualificação

## 10.4 Módulo de pipelines
- cadastro de pipeline
- cadastro de etapas
- ordenação das etapas
- regras visuais

## 10.5 Módulo de Kanban
- listagem por etapa
- drag and drop
- indicadores visuais
- busca rápida

## 10.6 Módulo de histórico
- notas
- eventos automáticos
- timeline do lead

## 10.7 Módulo de comunicação
- templates de mensagens
- gatilho `wa.me`
- futura integração com Evolution API

## 10.8 Módulo de dashboard
- cards de métricas
- funil de conversão
- filtros por período

---

## 11. Modelagem inicial de banco de dados

## 11.1 Tabela `users`
```sql
id
name
email
password_hash
role
status
created_at
updated_at
deleted_at
```

## 11.2 Tabela `pipelines`
```sql
id
name
description
is_active
created_at
updated_at
```

## 11.3 Tabela `pipeline_stages`
```sql
id
pipeline_id
name
slug
stage_type
color_token
position
is_default
is_final
created_at
updated_at
```

## 11.4 Tabela `leads`
```sql
id
pipeline_id
stage_id
assigned_user_id
name
phone
phone_normalized
email
source
product_interest
score
temperature
status
last_contact_at
next_action_at
next_action_description
won_at
lost_at
loss_reason
notes_summary
created_at
updated_at
deleted_at
```

## 11.5 Tabela `lead_tags`
```sql
id
name
color
created_at
updated_at
```

## 11.6 Tabela `lead_tag_items`
```sql
id
lead_id
tag_id
created_at
```

## 11.7 Tabela `lead_events`
```sql
id
lead_id
user_id
event_type
description
metadata_json
created_at
```

## 11.8 Tabela `message_templates`
```sql
id
name
slug
channel
stage_type
content
is_active
created_at
updated_at
```

## 11.9 Tabela `imports`
```sql
id
user_id
filename
source_name
total_rows
imported_rows
duplicated_rows
invalid_rows
created_at
```

## 11.10 Tabela `import_items`
```sql
id
import_id
row_number
raw_data_json
status
error_message
lead_id
created_at
```

## 11.11 Tabela `scheduled_messages` (para fase 2, já prevista)
```sql
id
lead_id
template_id
channel
scheduled_at
status
payload_json
created_at
updated_at
```

---

## 12. Score inicial do lead

Para o MVP, o lead score pode ser simples e manual/semi-automático.

### Exemplo de critérios
- respondeu em menos de 24h: +30
- tem produto de interesse definido: +10
- origem prioritária: +15
- vendedor marcou como muito interessado: +20
- sem resposta após 7 dias: -20

### Faixas
- 0–29: frio
- 30–59: morno
- 60–100: quente

Isso já atende o básico e pode evoluir depois.

---

## 13. Estrutura de telas do sistema

## 13.1 Login
- logo
- formulário simples
- visual premium

## 13.2 Dashboard
- métricas rápidas
- resumo do funil
- atividades pendentes

## 13.3 Kanban principal
- barra lateral
- cabeçalho com filtros
- colunas com scroll horizontal
- cards compactos e clicáveis
- contadores por etapa
- destaque para urgência e atraso

## 13.4 Tela de lead
- dados principais
- tags
- score
- origem
- produto
- timeline/histórico
- ações rápidas
- próxima ação

## 13.5 Importação
- upload de arquivo
- preview do mapeamento
- validação
- relatório da importação

## 13.6 Configurações
- usuários
- pipelines
- templates
- campos auxiliares

---

## 14. Direção de UI/UX

Com base no print anexado e no posicionamento desejado, a interface deve seguir um padrão:

- visual escuro ou neutro com contraste premium
- cards limpos com bordas suaves
- tipografia forte e legível
- menu lateral fixo
- área principal com foco no Kanban
- busca e filtros sempre acessíveis
- responsividade com prioridade para desktop e notebooks

### Recomendações visuais
- usar design tokens para cores, espaçamento e sombras
- criar estados de card: normal, atrasado, quente, selecionado
- limitar excesso de informação no card e deixar detalhes no drawer/modal
- usar microinterações leves em drag and drop

---

## 15. Estrutura técnica sugerida para frontend

### CSS
- arquitetura por camadas
- variables.css
- reset.css
- layout.css
- components.css
- pages.css

### JavaScript vanilla modular
```text
/assets/js
  app.js
  api.js
  kanban.js
  leads.js
  import.js
  dashboard.js
  templates.js
  utils.js
```

### Abordagem sugerida
- fetch API para chamadas assíncronas
- componentes simples reutilizáveis em JS
- renderização parcial sem recarregar tela inteira quando possível

---

## 16. Endpoints sugeridos

```text
GET    /login
POST   /login
POST   /logout

GET    /dashboard
GET    /leads
GET    /leads/{id}
POST   /leads
PUT    /leads/{id}
POST   /leads/import

GET    /pipelines
GET    /pipelines/{id}/kanban
POST   /leads/{id}/move-stage
POST   /leads/{id}/notes
POST   /leads/{id}/whatsapp-trigger

GET    /templates
POST   /templates
PUT    /templates/{id}

GET    /reports/conversion
```

---

## 17. Logs e auditoria

O sistema deve registrar:
- login
- importação
- movimentação de etapas
- cliques no gatilho WhatsApp
- criação de notas
- alterações relevantes do lead

Isso é essencial para operação, suporte e evolução futura.

---

## 18. Segurança mínima desde o MVP

- senhas com hash seguro
- prepared statements / PDO
- proteção CSRF em formulários
- validação server-side de uploads
- controle de sessão
- escaping de saída HTML
- permissões por papel

---

## 19. Plano de execução sugerido

## Fase 1 — Base técnica
- estrutura do projeto
- autenticação
- banco de dados
- CRUD de leads
- configuração de pipelines e etapas

## Fase 2 — Core do MVP
- importação CSV
- Kanban
- drag and drop
- histórico
- gatilho wa.me
- próxima ação

## Fase 3 — Gestão
- dashboard
- filtros
- score simples
- tags
- ajustes de UX

## Fase 4 — Preparação de escala
- API interna mais consistente
- filas e jobs simples
- programação de mensagens
- integração Evolution API
- importação automática

---

## 20. Riscos do projeto

### Risco 1: começar sem arquitetura mínima
Isso gera retrabalho rápido quando entrar chat, automações e IA.

### Risco 2: misturar regra comercial no frontend
As regras precisam ficar no backend.

### Risco 3: importação sem padronização de telefone
Pode quebrar duplicidade e integração com WhatsApp.

### Risco 4: Kanban pesado demais
Evitar carregar todos os detalhes do lead no card.

### Risco 5: histórico não estruturado
Sem eventos claros, relatórios e automações ficam limitados.

---

## 21. Recomendação final de implementação

A melhor decisão para o plano inicial é:

> construir um MVP simples na interface, mas com modelagem de dados e serviços preparados para crescimento.

### Em termos práticos:
- interface enxuta agora
- banco pensado para múltiplas esteiras
- histórico estruturado desde o dia 1
- módulo de comunicação desacoplado
- rotas JSON internas para interações do Kanban

Isso mantém o projeto em PHP puro e JS vanilla, sem perder capacidade de evolução.

---

## 22. Resumo executivo

Este CRM deve nascer como uma ferramenta comercial centrada em execução rápida.

O MVP precisa focar em:
- importação
- Kanban
- WhatsApp rápido
- histórico
- tempo de resposta
- conversão

Já a arquitetura deve nascer preparada para:
- integração com APIs
- mensagens automáticas
- múltiplas esteiras
- lead score avançado
- agentes de IA

Com isso, o sistema começa simples, porém sem travar a evolução do produto.
