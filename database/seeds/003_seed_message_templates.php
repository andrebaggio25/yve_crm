<?php

return [
    'run' => function(PDO $db) {
        $stmt = $db->query("SELECT COUNT(*) FROM message_templates");
        
        if ($stmt->fetchColumn() > 0) {
            return ['message' => 'Templates ja existem', 'skipped' => true];
        }
        
        $templates = [
            [
                'name' => 'Primeiro Contato',
                'slug' => 'primeiro-contato',
                'channel' => 'whatsapp',
                'stage_type' => 'initial',
                'content' => "Ola {nome}! Tudo bem? Aqui e da equipe comercial. Vi seu interesse em {produto} e gostaria de entender melhor como posso ajudar. Podemos conversar?"
            ],
            [
                'name' => 'Follow-up HOT',
                'slug' => 'follow-up-hot',
                'channel' => 'whatsapp',
                'stage_type' => 'hot',
                'content' => "Oi {nome}! Vi que voce respondeu rapidamente - isso mostra interesse real! Posso te ligar agora ou preferir outro horario?"
            ],
            [
                'name' => 'Follow-up WARM',
                'slug' => 'follow-up-warm',
                'channel' => 'whatsapp',
                'stage_type' => 'warm',
                'content' => "Ola {nome}! So para lembrar sobre nossa conversa de {produto}. Alguma duvida que eu possa esclarecer?"
            ],
            [
                'name' => 'Ultima Chance COLD',
                'slug' => 'ultima-chance-cold',
                'channel' => 'whatsapp',
                'stage_type' => 'cold',
                'content' => "Oi {nome}! Essa e minha ultima mensagem sobre {produto}. Se ainda tiver interesse, estou por aqui. Se nao, boa sorte!"
            ],
            [
                'name' => 'Parabens Venda',
                'slug' => 'parabens-venda',
                'channel' => 'whatsapp',
                'stage_type' => 'won',
                'content' => "Parabens {nome}! Sua compra foi confirmada. Em breve entraremos em contato com os proximos passos. Obrigado pela confianca!"
            ],
            [
                'name' => 'Win-back',
                'slug' => 'win-back',
                'channel' => 'whatsapp',
                'stage_type' => 'lost',
                'content' => "Ola {nome}! Vi que ainda nao fechou. Tenho uma condicao especial so para hoje. Gostaria de conhecer?"
            ],
        ];
        
        $stmt = $db->prepare("INSERT INTO message_templates 
            (tenant_id, name, slug, channel, stage_type, content, variables) 
            VALUES (1, :name, :slug, :channel, :stage_type, :content, :variables)");
        
        foreach ($templates as $template) {
            $stmt->execute([
                ':name' => $template['name'],
                ':slug' => $template['slug'],
                ':channel' => $template['channel'],
                ':stage_type' => $template['stage_type'],
                ':content' => $template['content'],
                ':variables' => json_encode(['nome', 'produto', 'vendedor'])
            ]);
        }
        
        return [
            'message' => count($templates) . ' templates de mensagem criados com sucesso'
        ];
    }
];
