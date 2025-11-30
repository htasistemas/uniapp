<?php

class PluginUniappSettings extends CommonDBTM
{

    public function showForm($ID, $options = [])
    {

        global $CFG_GLPI;

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        if (!isset($options['display'])) {
            $options['display'] = true;
        }

        $params = $options;
        $params['display'] = false;

        $out = 'Bem vindo ao UniApp!';

        if ($options['display'] == true) {
            echo $out;
        } else {
            return $out;
        }
    }
}