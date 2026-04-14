<?php

return [
    'up' => "ALTER TABLE message_templates 
             ADD COLUMN pipeline_id INT NULL AFTER stage_type,
             ADD COLUMN stage_id INT NULL AFTER pipeline_id,
             ADD COLUMN position INT NOT NULL DEFAULT 1 AFTER stage_id,
             ADD INDEX idx_pipeline (pipeline_id),
             ADD INDEX idx_stage (stage_id),
             ADD INDEX idx_pipeline_stage (pipeline_id, stage_id),
             ADD INDEX idx_position (pipeline_id, stage_id, position),
             ADD CONSTRAINT fk_template_pipeline 
                 FOREIGN KEY (pipeline_id) REFERENCES pipelines(id) ON DELETE CASCADE,
             ADD CONSTRAINT fk_template_stage 
                 FOREIGN KEY (stage_id) REFERENCES pipeline_stages(id) ON DELETE CASCADE",
    
    'down' => "ALTER TABLE message_templates 
               DROP FOREIGN KEY fk_template_pipeline,
               DROP FOREIGN KEY fk_template_stage,
               DROP COLUMN pipeline_id,
               DROP COLUMN stage_id,
               DROP COLUMN position",
    
    'description' => 'Adiciona vinculo de templates a pipeline e etapa especificas, mais campo de ordenacao (cadencia)'
];
