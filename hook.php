<?php

// Garante que as classes chamadas pelos hooks existam
require_once __DIR__ . '/inc/PluginUniappEvent.class.php';
require_once __DIR__ . '/inc/PluginUniappConfig.class.php';

/**
 * Executado na instalação do plugin.
 * Mantemos a lógica no arquivo dedicado, apenas encaminhando a chamada.
 */
function plugin_uniapp_install()
{
    require_once __DIR__ . '/install.php';
    // Se o install.php já executar os passos e não retornar boolean,
    // mantemos true para o GLPI considerar a instalação bem-sucedida.
    return true;
}

/**
 * Executado na desinstalação do plugin.
 */
function plugin_uniapp_uninstall()
{
    require_once __DIR__ . '/uninstall.php';
    return true;
}

/**
 * Hooks de criação (encaminham para a classe de eventos).
 */
function plugin_uniapp_item_add(Ticket $ticket)
{
    return PluginUniappEvent::item_add_ticket($ticket);
}

function plugin_uniapp_followup_add(ITILFollowup $followup)
{
    return PluginUniappEvent::item_add_followup($followup);
}

function plugin_uniapp_solution_add(ITILSolution $solution)
{
    return PluginUniappEvent::item_add_solution($solution);
}

function plugin_uniapp_validation_add(TicketValidation $validation)
{
    return PluginUniappEvent::item_add_validation($validation);
}
