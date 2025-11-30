<?php

function plugin_uniapp_install()
{
    require_once 'install.php';
    return true;
}

function plugin_uniapp_uninstall()
{
    require_once 'uninstall.php';
    return true;
}

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