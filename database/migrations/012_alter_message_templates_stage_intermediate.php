<?php

return [
    'up' => "ALTER TABLE message_templates 
             MODIFY COLUMN stage_type ENUM('initial','intermediate','hot','warm','cold','won','lost','any') DEFAULT 'any'",
    'down' => "ALTER TABLE message_templates 
             MODIFY COLUMN stage_type ENUM('initial','hot','warm','cold','won','lost','any') DEFAULT 'any'",
    'description' => 'Adiciona intermediate ao ENUM de templates (alinha com pipeline_stages)',
];
