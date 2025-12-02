<?php

function plugin_init_uniapp()
{
    global $PLUGIN_HOOKS;

    // Compatível com CSRF
    $PLUGIN_HOOKS['csrf_compliant']['uniapp'] = true;

    // Página de configuração
    $PLUGIN_HOOKS['config_page']['uniapp'] = 'front/config.php';

    // Mapeamento de hooks para as funções (definidas em hook.php)
    $PLUGIN_HOOKS['item_add']['uniapp'] = [
        Ticket::class           => 'plugin_uniapp_item_add',
        ITILFollowup::class     => 'plugin_uniapp_followup_add',
        ITILSolution::class     => 'plugin_uniapp_solution_add',
        TicketValidation::class => 'plugin_uniapp_validation_add',
    ];
}

function plugin_version_uniapp()
{
    return [
        'name'           => 'UniApp',
        'version'        => '1.12',
        'author'         => 'Unitá Soluções Digitais',
        'license'        => '',
        'homepage'       => 'https://unitasolucoes.com.br/',
        'minGlpiVersion' => '10.0.0'
    ];
}

function plugin_uniapp_check_prerequisites() { return true; }
function plugin_uniapp_check_config()        { return true; }
