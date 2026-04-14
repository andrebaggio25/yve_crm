# Yve CRM - Sistema de Gestao de Leads

Sistema CRM completo para gestao de leads comercial, desenvolvido em PHP puro, JavaScript vanilla, MySQL e Tailwind CSS.

## Funcionalidades

### Core (MVP Completo)
- Autenticacao segura com roles (admin, gestor, vendedor)
- Dashboard com metricas e funil de conversao
- Kanban visual (lista por etapa) e detalhes do lead em drawer
- CRUD de leads via API e modal no Kanban
- Importacao de leads via CSV
- Historico e timeline por lead
- Gatilho WhatsApp (wa.me)
- Gerenciamento de usuarios, pipelines e templates (CRUD com modais)
- Sistema de tags e qualificacao
- Interface web de migrations (desabilitavel em producao)

### Tecnologias
- **Backend**: PHP 8+ (sem framework)
- **Frontend**: JavaScript vanilla, **Tailwind CSS** (build com Node)
- **Banco de Dados**: MySQL
- **Arquitetura**: MVC leve

## Instalacao

### Requisitos
- PHP 8.0+
- MySQL 5.7+
- Apache com mod_rewrite
- Node.js 18+ (apenas para compilar o CSS do Tailwind)

### Passos

1. Clone o repositorio e entre na pasta do projeto.

2. **Variaveis de ambiente**
   - Copie [.env.example](.env.example) para `.env` ou use [.env.local](.env.local) (desenvolvimento).
   - Ajuste `DB_*`, `APP_URL` e demais chaves. O carregamento segue a ordem: `.env` e depois `.env.local` (sobrescreve).
   - Para producao, use o modelo [.env.production](.env.production) como referencia e configure as variaveis no servidor (ou copie para `.env` no host).

3. **CSS (Tailwind)**
```bash
npm install
npm run build:css
```
   - Desenvolvimento: `npm run watch:css` para recompilar ao editar templates/JS.

4. Crie o banco MySQL:
```sql
CREATE DATABASE yve_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

5. Acesse `/settings/migrations` (como admin), execute migrations pendentes e seeds (usuario admin e pipeline padrao).

6. Acesse o sistema (ex.: `http://localhost/yve_crm`):
   - Login padrao apos seed: `admin@yve.crm` / `admin123` (altere apos o primeiro login)

## Producao (resumo)

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` com HTTPS.
- `SESSION_COOKIE_SECURE=true` quando usar HTTPS.
- `ALLOW_MIGRATIONS_WEB=false` (padrao em producao) bloqueia execucao de migrations via API web; use deploy/CLI conforme sua operacao.
- Nao commite arquivos `.env` com segredos reais.

### Migrations e seeds automaticos (CLI)

Com `.env` configurado na raiz do projeto (no servidor):

```bash
php scripts/migrate.php
```

Isso aplica **todas as migrations pendentes** e em seguida **executa todos os seeds** (os seeds do projeto sao idempotentes onde possivel).

Opcoes:

- `php scripts/migrate.php --migrations-only` — so migrations
- `php scripts/migrate.php --seeds-only` — so seeds

Via Composer (mesmo diretorio do `composer.json`):

```bash
composer migrate
composer migrate:db
composer migrate:seed
```

**Hostinger:** Advanced → **SSH Access**, entre na pasta do site (onde estao `app/`, `scripts/`, `.env`) e rode `php scripts/migrate.php`. Pode repetir o comando em cada deploy (migrations ja aplicadas sao ignoradas).

**Alternativa (so primeira vez / debug):** definir temporariamente `ALLOW_MIGRATIONS_WEB=true` no `.env`, aceder como admin a `/settings/migrations` e usar os botoes na interface — **volte a `false` depois** por seguranca.

## Estrutura de Diretorios

```
yve_crm/
├── app/
│   ├── Core/           # Database, Router, Session, Env, etc
│   ├── Controllers/
│   ├── Helpers/
│   ├── Middleware/
│   └── Views/
├── config/             # app.php e database.php (leem Env)
├── database/
│   ├── migrations/
│   └── seeds/
├── resources/css/      # Entrada Tailwind (app.css)
├── public/
│   ├── assets/css/     # app.css gerado pelo build
│   └── index.php
└── storage/            # Logs
```

## Rotas Principais

### Paginas
- `/` - Redireciona para kanban ou login
- `/login` - Login
- `/dashboard` - Dashboard
- `/kanban` e `/kanban/{pipeline_id}` - Kanban
- `/leads/import` - Importacao CSV
- `/settings/users`, `/settings/pipelines`, `/settings/templates`, `/settings/migrations`

### API (requer sessao; mutacoes com CSRF)
- `/api/leads`, `/api/pipelines`, `/api/templates`, `/api/users`, etc.
- `/api/migrations/*` - Admin; em producao `run`/`rollback`/`seed` podem estar bloqueados (ver `ALLOW_MIGRATIONS_WEB`).

## Seguranca

- Senhas com bcrypt
- CSRF em formularios HTML e cabecalho `X-CSRF-Token` nas requisicoes AJAX/fetch da API
- Prepared statements (PDO)
- XSS: `htmlspecialchars` nas views
- Erros genericos em producao (`APP_DEBUG=false`)
- Cookie de sessao com flag `Secure` configuravel (`SESSION_COOKIE_SECURE`)

## Proximas Fases

Ver [FASE_4_PLANO_ESCALA.md](FASE_4_PLANO_ESCALA.md).

## Licenca

MIT License.
