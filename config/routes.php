<?php

use App\Core\Router;
use App\Core\Session;
use App\Core\Response;
use App\Core\Request;

return function (Router $router) {
    
    $authMiddleware = [\App\Middleware\AuthMiddleware::class];
    $adminMiddleware = [\App\Middleware\AuthMiddleware::class, \App\Middleware\RoleMiddleware::class . ':admin'];
    $csrfMiddleware = [\App\Middleware\CsrfMiddleware::class];
    
    $router->get('/', function(Request $request, Response $response) {
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

    $router->group('', function ($router) {
        $router->post('/logout', 'AuthController@logout', 'logout');
    }, array_merge($authMiddleware, $csrfMiddleware));
    
    $router->group('dashboard', function($router) {
        $router->get('/', 'DashboardController@index', 'dashboard');
    }, $authMiddleware);
    
    $router->group('kanban', function($router) {
        $router->get('/', 'KanbanController@index', 'kanban');
        $router->get('/{pipeline_id}', 'KanbanController@show', 'kanban.show');
    }, $authMiddleware);
    
    $apiMiddleware = array_merge($authMiddleware, $csrfMiddleware);

    $router->group('api', function($router) {
        
        $router->group('pipelines', function($router) {
            $router->get('/', 'PipelineController@apiList', 'api.pipelines.list');
            $router->get('/{id}', 'PipelineController@apiShow', 'api.pipelines.show');
            $router->post('/', 'PipelineController@apiCreate', 'api.pipelines.create');
            $router->put('/{id}', 'PipelineController@apiUpdate', 'api.pipelines.update');
            $router->delete('/{id}', 'PipelineController@apiDelete', 'api.pipelines.delete');
            $router->get('/{id}/kanban', 'KanbanController@apiGetKanban', 'api.kanban.data');
            $router->put('/{id}/stages', 'PipelineController@apiUpdateStages', 'api.pipelines.stages');
        });
        
        $router->group('leads', function($router) {
            $router->get('/', 'LeadController@apiList', 'api.leads.list');
            $router->post('/', 'LeadController@apiCreate', 'api.leads.create');
            $router->post('/import/parse', 'ImportController@apiParse', 'api.leads.import.parse');
            $router->post('/import/commit', 'ImportController@apiCommit', 'api.leads.import.commit');
            $router->get('/{id}', 'LeadController@apiShow', 'api.leads.show');
            $router->put('/{id}', 'LeadController@apiUpdate', 'api.leads.update');
            $router->delete('/{id}', 'LeadController@apiDelete', 'api.leads.delete');
            $router->post('/{id}/move-stage', 'LeadController@apiMoveStage', 'api.leads.move');
            $router->post('/{id}/notes', 'LeadController@apiAddNote', 'api.leads.note');
            $router->post('/{id}/followup', 'LeadController@apiLogFollowup', 'api.leads.followup');
            $router->get('/{id}/events', 'LeadController@apiEvents', 'api.leads.events');
            $router->get('/{id}/templates', 'LeadController@apiGetTemplatesForLead', 'api.leads.templates');
            $router->post('/{id}/whatsapp-trigger', 'LeadController@apiWhatsAppTrigger', 'api.leads.whatsapp');
        });
        
        $router->group('tags', function($router) {
            $router->get('/', 'TagController@apiList', 'api.tags.list');
            $router->post('/', 'TagController@apiCreate', 'api.tags.create');
            $router->put('/{id}', 'TagController@apiUpdate', 'api.tags.update');
            $router->delete('/{id}', 'TagController@apiDelete', 'api.tags.delete');
        });
        
        $router->group('templates', function($router) {
            $router->get('/', 'TemplateController@apiList', 'api.templates.list');
            $router->post('/', 'TemplateController@apiCreate', 'api.templates.create');
            $router->put('/{id}', 'TemplateController@apiUpdate', 'api.templates.update');
            $router->delete('/{id}', 'TemplateController@apiDelete', 'api.templates.delete');
        });
        
        $router->group('users', function($router) {
            $router->get('/', 'UserController@apiList', 'api.users.list');
            $router->post('/', 'UserController@apiCreate', 'api.users.create');
            $router->get('/{id}', 'UserController@apiShow', 'api.users.show');
            $router->put('/{id}', 'UserController@apiUpdate', 'api.users.update');
            $router->delete('/{id}', 'UserController@apiDelete', 'api.users.delete');
        });
        
        $router->group('dashboard', function($router) {
            $router->get('/metrics', 'DashboardController@apiMetrics', 'api.dashboard.metrics');
        });
        
    }, $apiMiddleware);
    
    $router->group('leads', function($router) {
        $router->get('/import', 'ImportController@showImport', 'leads.import');
    }, $authMiddleware);
    
    $router->group('settings', function($router) use ($adminMiddleware) {
        $router->get('/users', 'UserController@index', 'settings.users');
        $router->get('/pipelines', 'PipelineController@index', 'settings.pipelines');
        $router->get('/templates', 'TemplateController@index', 'settings.templates');
        
        $router->group('migrations', function($router) {
            $router->get('/', 'MigrationController@index', 'settings.migrations');
        }, $adminMiddleware);
        
    }, $authMiddleware);
    
    $adminApiMiddleware = array_merge($adminMiddleware, $csrfMiddleware);

    $router->group('api/migrations', function($router) {
        $router->get('/status', 'MigrationController@apiStatus', 'api.migrations.status');
        $router->post('/run', 'MigrationController@apiRun', 'api.migrations.run');
        $router->post('/rollback', 'MigrationController@apiRollback', 'api.migrations.rollback');
        $router->post('/seed', 'MigrationController@apiSeed', 'api.migrations.seed');
        $router->post('/reset', 'MigrationController@apiReset', 'api.migrations.reset');
    }, $adminApiMiddleware);
    
};
