<?php

require_once __DIR__ . '/install.php';
require_once __DIR__ . '/uninstall.php';

function plugin_init_uniapp()
{
    global $PLUGIN_HOOKS;

    Plugin::registerClass(PluginUniappEvent::class);

    $PLUGIN_HOOKS['csrf_compliant']['uniapp'] = true;
    $PLUGIN_HOOKS['item_add']['uniapp'] = [
        Ticket::class => 'plugin_uniapp_item_add',
        ITILFollowup::class => 'plugin_uniapp_followup_add',
        ITILSolution::class => 'plugin_uniapp_solution_add',
        TicketValidation::class => 'plugin_uniapp_validation_add'
    ];
}

function plugin_version_uniapp()
{
    return [
        'name' => 'UniApp',
        'version' => '1.11',
        'author' => 'Unitá Soluções Digitais',
        'license' => '',
        'homepage' => 'https://unitasolucoes.com.br/',
        'minGlpiVersion' => '11.0'
    ];
}

function plugin_uniapp_check_prerequisites()
{
    return true;
}

function plugin_uniapp_check_config()
{
    return true;
}
