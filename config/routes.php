<?php

use App\Core\Router;
use App\Core\Session;
use App\Core\Response;
use App\Core\Request;

return function (Router $router) {
    $authMiddleware = [\App\Middleware\AuthMiddleware::class];
    $tenantMiddleware = [\App\Middleware\TenantMiddleware::class];
    $authTenant = array_merge($authMiddleware, $tenantMiddleware);

    $adminMiddleware = [\App\Middleware\AuthMiddleware::class, \App\Middleware\TenantMiddleware::class, \App\Middleware\RoleMiddleware::class . ':admin'];
    $superadminMiddleware = [\App\Middleware\AuthMiddleware::class, \App\Middleware\RoleMiddleware::class . ':superadmin'];
    $superadminTenantMiddleware = [\App\Middleware\AuthMiddleware::class, \App\Middleware\TenantMiddleware::class, \App\Middleware\RoleMiddleware::class . ':superadmin'];
    $csrfMiddleware = [\App\Middleware\CsrfMiddleware::class];

    $router->get('/', function (Request $request, Response $response) {
        if (Session::isAuthenticated()) {
            $response->redirect('/kanban');
        } else {
            $response->redirect('/login');
        }
    });

    $router->get('/login', 'AuthController@showLogin', 'login');

    $router->group('', function ($router) {
        $router->post('/login', 'AuthController@login', 'login.post');
    }, $csrfMiddleware);

    $router->get('/password/forgot', 'PasswordController@showForgot', 'password.forgot');
    $router->get('/password/reset/{token}', 'PasswordController@showReset', 'password.reset');
    $router->group('', function ($router) {
        $router->post('/password/forgot', 'PasswordController@sendLink', 'password.forgot.post');
        $router->post('/password/reset', 'PasswordController@reset', 'password.reset.post');
    }, $csrfMiddleware);

    $router->post('/webhook/evolution/{token}', 'WebhookController@evolution', 'webhook.evolution');

    $router->group('', function ($router) {
        $router->post('/logout', 'AuthController@logout', 'logout');
    }, array_merge($authTenant, $csrfMiddleware));

    $router->group('dashboard', function ($router) {
        $router->get('/', 'DashboardController@index', 'dashboard');
    }, $authTenant);

    $router->group('kanban', function ($router) {
        $router->get('/', 'KanbanController@index', 'kanban');
        $router->get('/{pipeline_id}', 'KanbanController@show', 'kanban.show');
    }, $authTenant);

    $router->group('inbox', function ($router) {
        $router->get('/', 'InboxController@index', 'inbox');
    }, $authTenant);

    $router->group('profile', function ($router) {
        $router->get('/', 'ProfileController@index', 'profile');
        $router->post('/', 'ProfileController@update', 'profile.update');
    }, array_merge($authMiddleware, $csrfMiddleware));

    // Super Admin - Configuracoes globais (sem necessidade de tenant)
    $router->group('superadmin', function ($router) {
        $router->get('/settings', 'SuperAdminSettingsController@index', 'superadmin.settings');
        $router->get('/tenants', 'SuperAdminTenantController@index', 'superadmin.tenants');
    }, $superadminMiddleware);

    $apiMiddleware = array_merge($authTenant, $csrfMiddleware);

    $router->group('api', function ($router) use ($superadminMiddleware, $superadminTenantMiddleware, $csrfMiddleware, $authTenant) {
        $router->group('pipelines', function ($router) {
            $router->get('/', 'PipelineController@apiList', 'api.pipelines.list');
            $router->get('/{id}', 'PipelineController@apiShow', 'api.pipelines.show');
            $router->post('/', 'PipelineController@apiCreate', 'api.pipelines.create');
            $router->post('/{id}/stages', 'PipelineController@apiCreateStage', 'api.pipelines.stages.create');
            $router->delete('/{id}/stages/{stageId}', 'PipelineController@apiDeleteStage', 'api.pipelines.stages.delete');
            $router->put('/{id}', 'PipelineController@apiUpdate', 'api.pipelines.update');
            $router->delete('/{id}', 'PipelineController@apiDelete', 'api.pipelines.delete');
            $router->get('/{id}/kanban', 'KanbanController@apiGetKanban', 'api.kanban.data');
            $router->put('/{id}/stages', 'PipelineController@apiUpdateStages', 'api.pipelines.stages');
        });

        $router->group('leads', function ($router) {
            $router->get('/', 'LeadController@apiList', 'api.leads.list');
            $router->post('/', 'LeadController@apiCreate', 'api.leads.create');
            $router->post('/import/parse', 'ImportController@apiParse', 'api.leads.import.parse');
            $router->post('/import/commit', 'ImportController@apiCommit', 'api.leads.import.commit');
            $router->post('/bulk-schedule', 'BulkLeadController@apiSchedule', 'api.leads.bulk');
            $router->get('/bulk-jobs', 'BulkLeadController@apiJobs', 'api.leads.bulk.jobs');
            $router->get('/{id}', 'LeadController@apiShow', 'api.leads.show');
            $router->put('/{id}', 'LeadController@apiUpdate', 'api.leads.update');
            $router->delete('/{id}', 'LeadController@apiDelete', 'api.leads.delete');
            $router->post('/{id}/move-stage', 'LeadController@apiMoveStage', 'api.leads.move');
            $router->post('/{id}/link-existing', 'LeadController@apiLinkExisting', 'api.leads.link');
            $router->post('/{id}/accept-entry', 'LeadController@apiAcceptEntry', 'api.leads.accept');
            $router->post('/{id}/discard-entry', 'LeadController@apiDiscardEntry', 'api.leads.discard');
            $router->post('/{id}/notes', 'LeadController@apiAddNote', 'api.leads.note');
            $router->post('/{id}/followup', 'LeadController@apiLogFollowup', 'api.leads.followup');
            $router->get('/{id}/events', 'LeadController@apiEvents', 'api.leads.events');
            $router->get('/{id}/templates', 'LeadController@apiGetTemplatesForLead', 'api.leads.templates');
            $router->post('/{id}/whatsapp-trigger', 'LeadController@apiWhatsAppTrigger', 'api.leads.whatsapp');
        });

        $router->group('tags', function ($router) {
            $router->get('/', 'TagController@apiList', 'api.tags.list');
            $router->post('/', 'TagController@apiCreate', 'api.tags.create');
            $router->put('/{id}', 'TagController@apiUpdate', 'api.tags.update');
            $router->delete('/{id}', 'TagController@apiDelete', 'api.tags.delete');
        });

        $router->group('templates', function ($router) {
            $router->get('/', 'TemplateController@apiList', 'api.templates.list');
            $router->post('/', 'TemplateController@apiCreate', 'api.templates.create');
            $router->put('/{id}', 'TemplateController@apiUpdate', 'api.templates.update');
            $router->delete('/{id}', 'TemplateController@apiDelete', 'api.templates.delete');
        });

        $router->group('users', function ($router) {
            $router->get('/', 'UserController@apiList', 'api.users.list');
            $router->post('/', 'UserController@apiCreate', 'api.users.create');
            $router->get('/{id}', 'UserController@apiShow', 'api.users.show');
            $router->put('/{id}', 'UserController@apiUpdate', 'api.users.update');
            $router->delete('/{id}', 'UserController@apiDelete', 'api.users.delete');
        }, array_merge($authTenant, $csrfMiddleware, [\App\Middleware\RoleMiddleware::class . ':admin']));

        $router->group('dashboard', function ($router) {
            $router->get('/metrics', 'DashboardController@apiMetrics', 'api.dashboard.metrics');
            $router->get('/team-users', 'DashboardController@apiTeamUsers', 'api.dashboard.team-users');
        });

        $router->group('conversations', function ($router) {
            $router->get('/', 'ChatController@apiList', 'api.conversations.list');
            $router->get('/by-lead/{lead_id}', 'ChatController@apiByLead', 'api.conversations.by-lead');
            $router->get('/{id}/messages', 'ChatController@apiMessages', 'api.conversations.messages');
            $router->post('/{id}/messages/{message_id}/retry', 'ChatController@apiRetryMessage', 'api.conversations.retry');
            $router->post('/{id}/messages', 'ChatController@apiSend', 'api.conversations.send');
            $router->post('/{id}/media', 'ChatController@apiSendMedia', 'api.conversations.send-media');
        });

        $router->get('messages/{id}/media', 'MediaController@apiMessageMedia', 'api.messages.media');

        $router->group('settings/whatsapp', function ($router) {
            $router->get('/instances', 'WhatsAppInstanceController@apiList', 'api.wa.list');
            $router->post('/instances', 'WhatsAppInstanceController@apiCreate', 'api.wa.create');
            $router->get('/instances/{id}/connection', 'WhatsAppInstanceController@apiConnection', 'api.wa.connection');
            $router->get('/instances/{id}/qr-code', 'WhatsAppInstanceController@apiQrCode', 'api.wa.qr');
            $router->get('/instances/{id}/check-status', 'WhatsAppInstanceController@apiCheckStatus', 'api.wa.check');
            $router->post('/instances/{id}/disconnect', 'WhatsAppInstanceController@apiDisconnect', 'api.wa.disconnect');
            $router->post('/instances/{id}/configure-webhook', 'WhatsAppInstanceController@apiConfigureWebhook', 'api.wa.webhook');
        });

        $router->group('settings/tenant', function ($router) {
            $router->put('/', 'TenantSettingsController@apiUpdate', 'api.tenant.update');
        });

        $router->group('automations', function ($router) {
            $router->get('/', 'AutomationController@apiList', 'api.automations.list');
            $router->post('/', 'AutomationController@apiSave', 'api.automations.save');
            // Rotas especificas devem vir ANTES de rotas com parametros como /{id}
            $router->get('/scheduled-actions/list', 'AutomationController@apiScheduledActions', 'api.automations.scheduled');
            $router->get('/{id}', 'AutomationController@apiGet', 'api.automations.get');
            $router->put('/{id}', 'AutomationController@apiSave', 'api.automations.update');
            $router->delete('/{id}', 'AutomationController@apiDelete', 'api.automations.delete');
            $router->put('/{id}/toggle', 'AutomationController@apiToggle', 'api.automations.toggle');
            $router->get('/{id}/logs', 'AutomationController@apiLogs', 'api.automations.logs');
        });

        // Superadmin APIs - configuracoes globais (sem tenant)
        $router->group('superadmin', function ($router) {
            $router->get('/system-status', 'SuperAdminSettingsController@apiSystemStatus', 'api.superadmin.system-status');
            $router->get('/evolution-config', 'SuperAdminSettingsController@apiGetEvolutionConfig', 'api.superadmin.evolution-config');
            $router->put('/evolution-config', 'SuperAdminSettingsController@apiUpdateEvolutionConfig', 'api.superadmin.evolution-config.update');
        }, array_merge($superadminMiddleware, $csrfMiddleware));

        // Superadmin APIs - gerenciamento de tenants (com tenant para contexto de operacao)
        $router->group('superadmin', function ($router) {
            $router->get('/tenants', 'SuperAdminTenantController@apiList', 'api.superadmin.tenants');
            $router->post('/tenants', 'SuperAdminTenantController@apiCreate', 'api.superadmin.tenants.create');
            $router->post('/tenants/{id}/status', 'SuperAdminTenantController@apiSetStatus', 'api.superadmin.tenant.status');
            $router->post('/tenants/{id}/impersonate', 'SuperAdminTenantController@impersonate', 'api.superadmin.impersonate');
            $router->post('/stop-impersonate', 'SuperAdminTenantController@stopImpersonate', 'api.superadmin.stop');
        }, array_merge($superadminTenantMiddleware, $csrfMiddleware));
    }, $apiMiddleware);

    $router->group('leads', function ($router) {
        $router->get('/import', 'ImportController@showImport', 'leads.import');
    }, $authTenant);

    // Settings dos tenants - apenas admin do tenant (NAO inclui migrations)
    $router->group('settings', function ($router) {
        $router->get('/users', 'UserController@index', 'settings.users');
        $router->get('/pipelines', 'PipelineController@index', 'settings.pipelines');
        $router->get('/templates', 'TemplateController@index', 'settings.templates');
        $router->get('/whatsapp', 'WhatsAppInstanceController@page', 'settings.whatsapp');
        $router->get('/tenant', 'TenantSettingsController@page', 'settings.tenant');
        $router->get('/automations', 'AutomationController@page', 'settings.automations');
        $router->get('/automations/builder/{id}', 'AutomationController@builderPage', 'settings.automations.builder');
    }, $adminMiddleware);

    // Superadmin - Migrations e controle do sistema (acesso global, sem necessidade de tenant proprio)
    $router->group('superadmin', function ($router) {
        $router->get('/migrations', 'MigrationController@index', 'superadmin.migrations');
    }, $superadminMiddleware);

    $superadminApiMiddleware = array_merge($superadminMiddleware, $csrfMiddleware);

    $router->group('api/migrations', function ($router) {
        $router->get('/status', 'MigrationController@apiStatus', 'api.migrations.status');
        $router->post('/run', 'MigrationController@apiRun', 'api.migrations.run');
        $router->post('/rollback', 'MigrationController@apiRollback', 'api.migrations.rollback');
        $router->post('/seed', 'MigrationController@apiSeed', 'api.migrations.seed');
        $router->post('/reset', 'MigrationController@apiReset', 'api.migrations.reset');
    }, $superadminApiMiddleware);
};
