<?php

function plugin_init_uniapp()
{
    global $PLUGIN_HOOKS;

    // Registra classes (boa prática para tabs, direitos, etc.)
    Plugin::registerClass(PluginUniappEvent::class);
    Plugin::registerClass(PluginUniappConfig::class);

    // Declara compatibilidade com CSRF: o core espera validação em ações sensíveis (POST)
    $PLUGIN_HOOKS['csrf_compliant']['uniapp'] = true;

    // Página de configuração (requer direito 'config':UPDATE — checado dentro do front/config.php)
    $PLUGIN_HOOKS['config_page']['uniapp'] = 'front/config.php';

    // Hooks de criação: wrappers chamam os métodos estáticos da classe
    $PLUGIN_HOOKS['item_add']['uniapp'] = [
        Ticket::class          => 'plugin_uniapp_item_add',
        ITILFollowup::class    => 'plugin_uniapp_followup_add',
        ITILSolution::class    => 'plugin_uniapp_solution_add',
        TicketValidation::class=> 'plugin_uniapp_validation_add',
    ];
}

function plugin_version_uniapp()
{
    return [
        'name'           => 'UniApp',
        'version'        => '1.12',              // ✅ versão atualizada
        'author'         => 'Unitá Soluções Digitais',
        'license'        => '',
        'homepage'       => 'https://unitasolucoes.com.br/',
        'minGlpiVersion' => '10.0.0'             // ✅ explicita compatibilidade com GLPI 10
    ];
}

function plugin_uniapp_check_prerequisites()
{
    // Aqui você pode validar extensões PHP, versões, etc.
    return true;
}

function plugin_uniapp_check_config()
{
    // Retorne true se a configuração atual é válida
    return true;
}

/**
 * Wrappers dos hooks — encaminham para a classe PluginUniappEvent,
 * mantendo o mapeamento usado em $PLUGIN_HOOKS['item_add'].
 */
function plugin_uniapp_item_add(Ticket $item)
{
    return PluginUniappEvent::item_add_ticket($item);
}

function plugin_uniapp_followup_add(ITILFollowup $item)
{
    return PluginUniappEvent::item_add_followup($item);
}

function plugin_uniapp_solution_add(ITILSolution $item)
{
    return PluginUniappEvent::item_add_solution($item);
}

function plugin_uniapp_validation_add(TicketValidation $item)
{
    return PluginUniappEvent::item_add_validation($item);
}
