<?php
define('GLPI_ROOT', dirname(__DIR__, 3));
require_once GLPI_ROOT . '/inc/includes.php';

// garante plugin carregado
Plugin::load('uniapp');

require_once __DIR__ . '/../inc/PluginUniappConfig.class.php';

Session::checkLoginUser();
Session::checkRight('config', UPDATE);

global $CFG_GLPI;

// URL canônica desta página
$SELFURL = $CFG_GLPI['root_doc'] . Plugin::getWebDir('uniapp', true) . '/front/config.php';

$defaultConfig = [
    'fcm_project_id'     => '',
    'fcm_client_email'   => '',
    'fcm_private_key'    => '',
    'fcm_super_admin_user' => '',
    'fcm_super_admin_password' => '',
    'enable_attachments' => '0',
    'public_colors_rps'  => '300',
    'public_colors_version' => '0',
    'public_colors_updated_at' => '',
    'public_logos_version' => '0',
    'public_logos_updated_at' => '',
    'app_max_tickets'    => '500',
    'app_max_tickets_old'=> '10',
    'app_max_files'      => '5',
    'app_max_file_size_mb'=> '2',
    'app_grid_space'     => '5',
    'app_text_scale'     => '1',
    'app_icon_scale'     => '1',
    'app_max_image_height'=> '400',
    'app_max_image_width'=> '300',
    'app_logo_png'       => '',
    'app_splash_png'     => '',

    // cores compartilhadas com o aplicativo
    'color_header'       => '#1A3557',
    'color_buttons'      => '#C7802F',
    'color_background'   => '#F5F5F5',
    'color_text'         => '#000000',
    'color_primary'      => '#1A3557',
    'color_primary_light'=> '#0D1A29',
    'color_primary_on'   => '#FFFFFF',
    'color_secondary'    => '#C7802F',
    'color_secondary_on' => '#FFFFFF',
    'color_background_shadow' => '#000000',
    'color_content'      => '#F5F5F5',
    'color_content_on'   => '#000000',
    'color_content_on_light' => '#808080',
    'color_highlight'    => '#C7802F',
    'color_highlight_on' => '#FFFFFF',
    'color_highlight_on_light' => '#FFFFFF',
    'color_alert'        => '#FFD700',
    'color_alert_on'     => '#000000',
    'color_success'      => '#008000',
    'color_warning'      => '#FF8C00',
    'color_critical'     => '#FF0000',
    'color_critical_on'  => '#FFFFFF',
    'color_completed'    => '#000000',

    // cores específicas para login
    'color_login_primary'            => '#1A3557',
    'color_login_primary_on'         => '#FFFFFF',
    'color_login_secondary'          => '#C7802F',
    'color_login_secondary_on'       => '#FFFFFF',
    'color_login_background_shadow'  => '#000000',
    'color_login_input_background'   => '#FFFFFF',
    'color_login_input_on'           => '#FFFFFF',
    'color_login_input_icon'         => '#1A3557',
    'color_login_highlight'          => '#C7802F',
    'color_login_highlight_on'       => '#FFFFFF',
    'color_login_highlight_on_light' => '#FFFFFF',
    'color_login_critical'           => '#FF7F7F',

    // cores para splash
    'color_splash_primary'           => '#1A3557',
    'color_splash_primary_on'        => '#FFFFFF',

    'ticket_title'       => '',
    'ticket_message'     => '',
    'ticket_user_types'  => '',
    'followup_title'     => '',
    'followup_message'   => '',
    'followup_user_types'=> '',
    'solution_title'     => '',
    'solution_message'   => '',
    'solution_user_types'=> '',
    'validation_title'   => '',
    'validation_message' => '',
    'validation_user_types' => '',
    'write_log'          => '0',
    'log_file'           => ''
];

$errors = [];
$configValues = array_merge($defaultConfig, PluginUniappConfig::getAll());

$notificationSections = [
    'ticket'    => 'Chamado',
    'followup'  => 'Acompanhamento',
    'solution'  => 'Solução',
    'validation'=> 'Aprovação'
];

$logoFields = [
    'app_logo_png'   => 'Logo (PNG)',
    'app_splash_png' => 'Splash (PNG)',
];

$logoFieldKeys = array_keys($logoFields);

$logoPreviewData = [];
foreach ($logoFields as $field => $_label) {
    $logoPreviewData[$field] = PluginUniappConfig::getLogoBase64($field);
}

$colorFields = array_keys(PluginUniappConfig::getDefaultColors());

// Processa POST (PRG: Post/Redirect/Get)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {

    $payload = [];
    foreach ($defaultConfig as $field => $defaultValue) {
        if (in_array($field, $logoFieldKeys, true)) {
            continue;
        }
        if ($field === 'enable_attachments' || $field === 'write_log') {
            $payload[$field] = isset($_POST[$field]) ? '1' : '0';
            continue;
        }
        if ($field === 'public_colors_rps') {
            $rateLimit = max(1, (int)($_POST[$field] ?? $defaultValue));
            $payload[$field] = (string)$rateLimit;
            continue;
        }
        $value = $_POST[$field] ?? '';
        if ($field !== 'fcm_private_key') {
            $value = trim((string)$value);
        }
        $payload[$field] = $value;
    }

    $palette = [];
    $colorsDirty = false;
    foreach ($colorFields as $colorField) {
        $newColor = $payload[$colorField] ?? '';
        $palette[$colorField] = $newColor;
        if (!$colorsDirty && ($newColor !== ($configValues[$colorField] ?? ''))) {
            $colorsDirty = true;
        }
    }

    if ($colorsDirty) {
        $currentVersion = max(0, (int)($configValues['public_colors_version'] ?? 0));
        $payload['public_colors_version'] = (string)($currentVersion + 1);
        $payload['public_colors_updated_at'] = gmdate('c');
    }

    $logosDirty = false;
    foreach ($logoFieldKeys as $logoField) {
        if (empty($_FILES[$logoField]) || $_FILES[$logoField]['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $upload = $_FILES[$logoField];
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            $errors[] = sprintf(__('%s: falha no upload (código %d).', 'uniapp'), $logoFields[$logoField], $upload['error']);
            continue;
        }

        $tmpName = (string)$upload['tmp_name'];
        if (!is_uploaded_file($tmpName)) {
            continue;
        }

        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $tmpName);
                finfo_close($finfo);
            }
        }
        if ($mime === '' && function_exists('mime_content_type')) {
            $mime = mime_content_type($tmpName);
        }
        $mime = strtolower((string)$mime);
        if (strpos($mime, 'image/png') !== 0) {
            $errors[] = sprintf(__('%s: envie apenas PNG.', 'uniapp'), $logoFields[$logoField]);
            continue;
        }

        $storageName = PluginUniappConfig::getLogoStorageFilename($logoField);
        if ($storageName === null) {
            continue;
        }

        if (!PluginUniappConfig::ensureLogosDirectory()) {
            $errors[] = sprintf(__('%s: falha ao preparar o diretório de logos.', 'uniapp'), $logoFields[$logoField]);
            continue;
        }

        $targetPath = PluginUniappConfig::getLogosDirectory() . '/' . $storageName;
        if (!move_uploaded_file($tmpName, $targetPath)) {
            $errors[] = sprintf(__('%s: falha ao mover o arquivo enviado.', 'uniapp'), $logoFields[$logoField]);
            continue;
        }
        @chmod($targetPath, 0644);

        $payload[$logoField] = $storageName;
        $logosDirty = true;
    }

    if ($logosDirty) {
        $currentVersion = max(0, (int)($configValues['public_logos_version'] ?? 0));
        $payload['public_logos_version'] = (string)($currentVersion + 1);
        $payload['public_logos_updated_at'] = gmdate('c');
    }

    if (empty($errors)) {
        $errors = PluginUniappConfig::save($payload);
    }

    if (empty($errors)) {
        Session::addMessageAfterRedirect(__('Configuração salva com sucesso', 'uniapp'), true, INFO);
        Html::redirect($SELFURL);
        exit;
    } else {
        // agrega erros para exibir abaixo sem perder o preenchimento
        Session::addMessageAfterRedirect(
            __('Falha ao salvar configuração', 'uniapp') . ': ' . implode(' | ', array_map('htmlspecialchars', $errors)),
            true,
            ERROR
        );
        // mantém valores preenchidos na mesma requisição
        $configValues = array_merge($configValues, $payload);
    }
}

// header canônico
Html::header(__('Configuração UniApp', 'uniapp'), $SELFURL, 'plugins', 'uniapp');
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        --glpi-blue: #005a8d;
        --glpi-bg: #f5f7f9;
        --glpi-border: #dcdcdc;
        --glpi-green: #486d1b;
        --glpi-text: #4a4a4a;
        --glpi-white: #ffffff;
    }
    body { font-family: 'Roboto','Segoe UI',Tahoma,sans-serif; background-color: var(--glpi-bg); color: var(--glpi-text); }
    .uniapp-container { max-width: 1000px; margin: 0 auto; background: var(--glpi-white); border: 1px solid var(--glpi-border);
        border-top: 3px solid var(--glpi-blue); border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,.05); padding-bottom: 30px; }
    .uniapp-header { padding: 15px 20px; border-bottom: 1px solid var(--glpi-border); display: flex; align-items: center; gap: 10px; }
    .uniapp-header h2 { margin: 0; font-size: 18px; color: var(--glpi-blue); font-weight: 500; }
    .uniapp-form { padding: 20px; }
    .form-group { display: flex; margin-bottom: 20px; align-items: center; border-bottom: 1px dotted #eee; padding-bottom: 15px; }
    .form-group:last-child { border-bottom: none; }
    .label-col { width: 25%; font-weight: 600; text-align: right; padding-right: 20px; color: #333; }
    .input-col { width: 75%; display: flex; align-items: center; gap: 10px; }
    input[type="text"], textarea { width: 100%; max-width: 520px; padding: 8px 10px; border: 1px solid #ccc; border-radius: 3px; font-size: 13px;
        transition: border .2s; font-family: inherit; }
    textarea { resize: vertical; min-height: 120px; }
    input[type="text"]:focus, textarea:focus { border-color: var(--glpi-blue); outline: none; }
    .switch { position: relative; display: inline-block; width: 40px; height: 22px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
    .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: #fff; transition: .4s; border-radius: 50%; }
    input:checked + .slider { background-color: var(--glpi-green); }
    input:checked + .slider:before { transform: translateX(18px); }
    .config-tabs { display: flex; gap: 10px; flex-wrap: wrap; border-bottom: 1px solid #dcdcdc; margin: 0 20px 25px; }
    .tab-button {
        border: 1px solid #dcdcdc;
        border-bottom: none;
        background: #fff;
        padding: 8px 18px;
        cursor: pointer;
        font-weight: 600;
        border-radius: 4px 4px 0 0;
        color: #4a4a4a;
        transition: background .2s, border-color .2s;
    }
    .tab-button.active {
        color: var(--glpi-blue);
        border-color: var(--glpi-blue);
        background: #f1f5ff;
    }
    .tab-pane { display: none; }
    .tab-pane.active { display: block; }
    .notification-subtabs { display: flex; gap: 10px; margin: 0 20px 15px; }
    .subtab-button {
        border: 1px solid #dcdcdc;
        border-bottom: none;
        background: #fff;
        padding: 6px 16px;
        cursor: pointer;
        font-weight: 600;
        border-radius: 4px 4px 0 0;
        color: #4a4a4a;
        transition: background .2s, border-color .2s;
    }
    .subtab-button.active {
        color: var(--glpi-blue);
        border-color: var(--glpi-blue);
        background: #f1f5ff;
    }
    .subtab-pane { display: none; }
    .subtab-pane.active { display: block; }
    .colors-group { margin-bottom: 25px; }
    .colors-group .section-heading { margin: 0 0 10px; font-size: 15px; }
    .colors-section { background-color: #f9f9f9; border: 1px solid #eee; padding: 15px; border-radius: 4px; width: 100%; max-width: 900px; }
    .color-row { display: flex; align-items: center; margin-bottom: 10px; justify-content: space-between; }
    .color-row label { width: 40%; font-weight: normal; }
    .color-picker-wrapper { display: flex; align-items: center; width: 60%; gap: 10px; }
    .color-input-visual { width: 40px; height: 34px; padding: 0; border: none; background: none; cursor: pointer; }
    .color-input-hex { width: 100px !important; text-transform: uppercase; }
    .form-actions { margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--glpi-border); text-align: center; }
    .btn-save { background-color: var(--glpi-green); color: #fff; border: none; padding: 10px 25px; font-size: 14px; border-radius: 3px; cursor: pointer;
        display: inline-flex; align-items: center; gap: 8px; transition: background .2s; }
    .btn-save:hover { background-color: #3a5816; }
    .section-heading { margin: 25px 20px 5px; font-size: 16px; font-weight: 600; color: var(--glpi-blue); }
    .section-description { margin: 0 20px 15px; font-size: 13px; color: #666; }
    .help-text { font-size: 11px; color: #888; margin-top: 4px; display: block; }
    .logos-preview { margin: 20px 0 0; }
    .logos-preview-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 18px; margin-top: 15px; }
    .logos-preview-card { border: 1px solid #dcdcdc; border-radius: 6px; padding: 12px; background: #fff; min-height: 150px;
        display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
    .logos-preview-label { font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #333; }
    .logos-preview-card img { max-width: 100%; max-height: 120px; object-fit: contain; border: 1px solid #ececec;
        border-radius: 4px; padding: 6px; background: #fff; }
    .logos-preview-placeholder { width: 100%; min-height: 120px; display: flex; align-items: center; justify-content: center;
        border: 1px dashed #ccc; border-radius: 4px; background: #fafafa; color: #777; font-size: 13px; padding: 6px; }
    @media (max-width: 768px) {
        .form-group { flex-direction: column; align-items: flex-start; }
        .label-col { width: 100%; text-align: left; margin-bottom: 5px; }
        .input-col { width: 100%; }
        .color-row { flex-direction: column; align-items: flex-start; }
        .color-row label { width: 100%; margin-bottom: 6px; }
        .color-picker-wrapper { width: 100%; }
    }
    .logo-preview { margin-bottom: 10px; }
    .logo-preview img {
        max-width: 120px;
        max-height: 120px;
        border: 1px solid #e0e0e0;
        border-radius: 3px;
        object-fit: contain;
        background: #fff;
        padding: 4px;
    }
    .uniapp-message { margin: 20px; padding: 10px; border-radius: 3px; font-size: 13px; }
    .uniapp-message.success { background-color: #dff0d8; border: 1px solid #b2dba1; color: #2f6627; }
    .uniapp-message.error   { background-color: #fbeaea; border: 1px solid #f0a9af; color: #8d2424; }
</style>

<div class="uniapp-container">
    <div class="uniapp-header">
        <i class="fa-solid fa-puzzle-piece fa-lg" style="color: var(--glpi-blue);"></i>
        <h2><?php echo __('Configuração do Plugin App', 'uniapp'); ?></h2>
    </div>

    <?php
    // Mensagens de redirect (se houver) já são mostradas pelo GLPI; abaixo é fallback local
    if (!empty($errors)) {
        echo '<div class="uniapp-message error">'.implode('<br>', array_map('htmlspecialchars', $errors)).'</div>';
    }

    $colorGroups = [
        'Tema principal' => [
            'color_header' => 'Cor do cabeçalho (legado)',
            'color_buttons' => 'Cor dos botões (legado)',
            'color_primary' => 'Cor primária',
            'color_primary_light' => 'Primária escura',
            'color_primary_on' => 'Texto sobre primária',
            'color_secondary' => 'Cor secundária',
            'color_secondary_on' => 'Texto sobre secundária'
        ],
        'Plano de fundo e conteúdo' => [
            'color_background' => 'Cor de fundo geral',
            'color_background_shadow' => 'Sombra de fundo',
            'color_content' => 'Fundo do conteúdo',
            'color_content_on' => 'Texto sobre conteúdo',
            'color_content_on_light' => 'Texto leve sobre conteúdo',
            'color_text' => 'Texto principal',
            'color_highlight' => 'Destaques e links',
            'color_highlight_on' => 'Texto em destaque',
            'color_highlight_on_light' => 'Texto leve em destaque'
        ],
        'Estados e sinais' => [
            'color_alert' => 'Alertas',
            'color_alert_on' => 'Texto do alerta',
            'color_success' => 'Sucesso',
            'color_warning' => 'Atenção',
            'color_critical' => 'Crítico',
            'color_critical_on' => 'Texto do crítico',
            'color_completed' => 'Situação concluída'
        ],
        'Tela de login' => [
            'color_login_primary' => 'Fundo principal do login',
            'color_login_primary_on' => 'Texto sobre o fundo principal',
            'color_login_secondary' => 'Botões do login',
            'color_login_secondary_on' => 'Texto dos botões do login',
            'color_login_background_shadow' => 'Sombra de fundo do login',
            'color_login_input_background' => 'Fundo dos campos',
            'color_login_input_on' => 'Texto dos campos',
            'color_login_input_icon' => 'Ícones dos campos',
            'color_login_highlight' => 'Destaques na tela de login',
            'color_login_highlight_on' => 'Texto sobre destaques do login',
            'color_login_highlight_on_light' => 'Texto leve sobre destaque do login',
            'color_login_critical' => 'Estado crítico na tela de login'
        ],
        'Splash' => [
            'color_splash_primary' => 'Fundo da splash',
            'color_splash_primary_on' => 'Texto da splash'
        ]
    ];

    $flatColorFields = [];
    foreach ($colorGroups as $groupFields) {
        foreach ($groupFields as $field => $_label) {
            $flatColorFields[] = $field;
        }
    }
    ?>

    <form method="post" action="<?php echo htmlspecialchars($SELFURL); ?>" class="uniapp-form" enctype="multipart/form-data">
        <input type="hidden" name="_glpi_csrf_token" value="<?php echo Session::getNewCSRFToken(); ?>">
        <input type="hidden" name="PluginUniappConfig" value="1">

        <div class="config-tabs">
            <button type="button" class="tab-button active" data-tab="tab-cores">Cores</button>
            <button type="button" class="tab-button" data-tab="tab-notificacoes">Notificações</button>
            <button type="button" class="tab-button" data-tab="tab-parametros">Parâmetros gerais</button>
            <button type="button" class="tab-button" data-tab="tab-logos">Logos</button>
        </div>

        <div class="tab-pane active" id="tab-cores">
            <div class="section-description">
                Escolha as cores que o aplicativo deve expor nos diferentes contextos antes de salvar.
            </div>
            <div class="form-group">
                <div class="label-col"><label for="public_colors_rps">Limite de consultas por segundo</label></div>
                <div class="input-col">
                    <input type="number" id="public_colors_rps" name="public_colors_rps" min="1"
                           value="<?php echo htmlspecialchars($configValues['public_colors_rps'] ?? '300'); ?>">
                    <span class="help-text">Defina quantas chamadas por segundo a API pública de cores deve permitir antes de começar a recusar.</span>
                </div>
            </div>
            <?php foreach ($colorGroups as $groupLabel => $fields): ?>
                <div class="colors-group">
                    <div class="section-heading"><?php echo $groupLabel; ?></div>
                    <div class="colors-section">
                        <?php foreach ($fields as $field => $labelText): ?>
                            <div class="color-row">
                                <label for="<?php echo $field; ?>"><?php echo $labelText; ?></label>
                                <div class="color-picker-wrapper">
                                    <input type="color" id="picker_<?php echo $field; ?>" class="color-input-visual"
                                           value="<?php echo htmlspecialchars($configValues[$field] ?? '#ffffff'); ?>">
                                    <input type="text" id="<?php echo $field; ?>" name="<?php echo $field; ?>"
                                           class="color-input-hex"
                                           value="<?php echo htmlspecialchars($configValues[$field] ?? '#ffffff'); ?>" maxlength="7">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="tab-pane" id="tab-notificacoes">
            <div class="section-description">
                Gerencie os dados de envio do Firebase e altere os textos exibidos ao usuário final.
            </div>
            <div class="notification-subtabs">
                <button type="button" class="subtab-button active" data-subtab="notifications-servidor">Servidor</button>
                <button type="button" class="subtab-button" data-subtab="notifications-mensagens">Mensagens</button>
            </div>
            <div class="subtab-pane active" id="notifications-servidor">
                <div class="section-heading">Servidor FCM</div>
                <div class="section-description">
                    Informe o projeto e a conta de serviço usados para enviar notificações via Firebase.
                </div>
                <div class="form-group">
                    <div class="label-col"><label for="fcm_project_id">Projeto FCM</label></div>
                    <div class="input-col">
                        <input type="text" id="fcm_project_id" name="fcm_project_id"
                               value="<?php echo htmlspecialchars($configValues['fcm_project_id'] ?? ''); ?>"
                               placeholder="Informe o ID do projeto Firebase">
                        <span class="help-text">ID registrado no Firebase para envio de notificações via FCM.</span>
                    </div>
                </div>

                <div class="form-group">
                    <div class="label-col"><label for="fcm_client_email">Client Email</label></div>
                    <div class="input-col">
                        <input type="text" id="fcm_client_email" name="fcm_client_email"
                               value="<?php echo htmlspecialchars($configValues['fcm_client_email'] ?? ''); ?>"
                               placeholder="E-mail do service account no Firebase">
                        <span class="help-text">E-mail vinculado ao service account do FCM.</span>
                    </div>
                </div>

                <div class="form-group">
                    <div class="label-col"><label for="fcm_private_key">Private Key</label></div>
                    <div class="input-col">
                        <textarea id="fcm_private_key" name="fcm_private_key"
                                  placeholder="Cole a chave privada JSON do Firebase"><?php
                            echo htmlspecialchars($configValues['fcm_private_key'] ?? '');
                        ?></textarea>
                        <span class="help-text">Chave privada usada para autenticar com o Firebase Admin.</span>
                    </div>
                </div>

                <div class="section-heading">Usuário Super-Admin</div>
                <div class="section-description">
                    Usuário utilizado para realizar as alterações de FCM token dos usuários no banco.
                </div>
                <div class="form-group">
                    <div class="label-col"><label for="fcm_super_admin_user">Usuário</label></div>
                    <div class="input-col">
                        <input type="password" id="fcm_super_admin_user" name="fcm_super_admin_user"
                               value="<?php echo htmlspecialchars($configValues['fcm_super_admin_user'] ?? ''); ?>"
                               autocomplete="username">
                        <span class="help-text">Login do super-admin que aplica as atualizações de token.</span>
                    </div>
                </div>

                <div class="form-group">
                    <div class="label-col"><label for="fcm_super_admin_password">Senha</label></div>
                    <div class="input-col">
                        <input type="password" id="fcm_super_admin_password" name="fcm_super_admin_password"
                               value="<?php echo htmlspecialchars($configValues['fcm_super_admin_password'] ?? ''); ?>"
                               autocomplete="current-password">
                        <span class="help-text">Senha do super-admin usada nas operações sensíveis.</span>
                    </div>
                </div>
            </div>

            <div class="subtab-pane" id="notifications-mensagens">
                <div class="section-heading">Mensagens de notificação</div>
                <div class="section-description">
                    Defina os títulos, as mensagens e os tipos de usuário que devem receber cada notificação.
                </div>

                <?php foreach ($notificationSections as $key => $label): ?>
                    <div class="form-group">
                        <div class="label-col">
                            <label for="<?php echo $key; ?>_title"><?php echo $label; ?> — Título</label>
                        </div>
                        <div class="input-col">
                            <input type="text" id="<?php echo $key; ?>_title" name="<?php echo $key; ?>_title"
                                   value="<?php echo htmlspecialchars($configValues[$key . '_title'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="label-col">
                            <label for="<?php echo $key; ?>_message"><?php echo $label; ?> — Mensagem</label>
                        </div>
                        <div class="input-col">
                            <textarea id="<?php echo $key; ?>_message" name="<?php echo $key; ?>_message"
                                      placeholder="Mensagem exibida no aplicativo"><?php echo htmlspecialchars($configValues[$key . '_message'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="label-col">
                            <label for="<?php echo $key; ?>_user_types"><?php echo $label; ?> — Tipos de usuário</label>
                        </div>
                        <div class="input-col">
                            <input type="text" id="<?php echo $key; ?>_user_types" name="<?php echo $key; ?>_user_types"
                                   value="<?php echo htmlspecialchars($configValues[$key . '_user_types'] ?? ''); ?>"
                                   placeholder="Ex: 1,3,412">
                            <span class="help-text">Informe os tipos de usuário (IDs separados por vírgula) conforme a tabela de direitos do GLPI.</span>
                        </div>
                    </div>
            <?php endforeach; ?>
            </div>
        </div>

        <div class="tab-pane" id="tab-parametros">
            <div class="section-heading">Configurações do app</div>
            <div class="section-description">
                Ajuste o comportamento do aplicativo móvel sem precisar recompilar o binário; todos os valores abaixo são expostos em <code>front/parametros-gerais.php</code>.
            </div>

            <div class="form-group">
                <div class="label-col"><label for="enable_attachments">Incluir anexos</label></div>
                <div class="input-col">
                    <label class="switch">
                        <input type="checkbox" id="enable_attachments" name="enable_attachments"
                            <?php echo ($configValues['enable_attachments'] ?? '0') === '1' ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                    <span style="color:#666;">Permite o app anexar arquivos nos tickets.</span>
                </div>
            </div>

            <div class="form-group">
                <div class="label-col"><label for="app_max_tickets">Máximo de tickets recentes</label></div>
                <div class="input-col">
                    <input type="number" id="app_max_tickets" name="app_max_tickets" min="1"
                           value="<?php echo htmlspecialchars($configValues['app_max_tickets'] ?? '500'); ?>">
                    <span class="help-text">Quantidade máxima de tickets abertos exibidos.</span>
                </div>
            </div>

            <div class="form-group">
                <div class="label-col"><label for="app_max_tickets_old">Máximo de tickets antigos</label></div>
                <div class="input-col">
                    <input type="number" id="app_max_tickets_old" name="app_max_tickets_old" min="0"
                           value="<?php echo htmlspecialchars($configValues['app_max_tickets_old'] ?? '10'); ?>">
                    <span class="help-text">Quantidade máxima de tickets em estado antigo.</span>
                </div>
            </div>

            <div class="form-group">
                <div class="label-col"><label for="app_max_files">Máximo de anexos</label></div>
                <div class="input-col">
                    <input type="number" id="app_max_files" name="app_max_files" min="1"
                           value="<?php echo htmlspecialchars($configValues['app_max_files'] ?? '5'); ?>">
                    <span class="help-text">Número máximo de arquivos permitidos por envio.</span>
                </div>
            </div>

            <div class="form-group">
                <div class="label-col"><label for="app_max_file_size_mb">Tamanho máximo de arquivo (MB)</label></div>
                <div class="input-col">
                    <input type="number" id="app_max_file_size_mb" name="app_max_file_size_mb" min="1"
                           value="<?php echo htmlspecialchars($configValues['app_max_file_size_mb'] ?? '2'); ?>">
                    <span class="help-text">Tamanho máximo em megabytes por anexo.</span>
                </div>
            </div>

            <div class="form-group">
                <div class="label-col"><label for="app_grid_space">Espaçamento da grade</label></div>
                <div class="input-col">
                    <input type="number" id="app_grid_space" name="app_grid_space" min="0"
                           value="<?php echo htmlspecialchars($configValues['app_grid_space'] ?? '5'); ?>">
                    <span class="help-text">Espaço (px) usado para espaçar elementos em listas no app.</span>
                </div>
            </div>

            <div class="form-group">
                <div class="label-col"><label for="app_text_scale">Escala de texto</label></div>
                <div class="input-col">
                    <input type="number" id="app_text_scale" name="app_text_scale" min="0.1" step="0.1"
                           value="<?php echo htmlspecialchars($configValues['app_text_scale'] ?? '1'); ?>">
                    <span class="help-text">Multiplicador aplicado aos textos (1.0 = 100%).</span>
                </div>
            </div>

            <div class="form-group">
                <div class="label-col"><label for="app_icon_scale">Escala de ícones</label></div>
                <div class="input-col">
                    <input type="number" id="app_icon_scale" name="app_icon_scale" min="0.1" step="0.1"
                           value="<?php echo htmlspecialchars($configValues['app_icon_scale'] ?? '1'); ?>">
                    <span class="help-text">Multiplicador aplicado aos ícones (1.0 = 100%).</span>
                </div>
            </div>

            <div class="form-group">
                <div class="label-col"><label for="app_max_image_height">Altura máxima de imagem</label></div>
                <div class="input-col">
                    <input type="number" id="app_max_image_height" name="app_max_image_height" min="0"
                           value="<?php echo htmlspecialchars($configValues['app_max_image_height'] ?? '400'); ?>">
                    <span class="help-text">Altura máxima desejada (px) para miniaturas.</span>
                </div>
            </div>

            <div class="form-group">
                <div class="label-col"><label for="app_max_image_width">Largura máxima de imagem</label></div>
                <div class="input-col">
                    <input type="number" id="app_max_image_width" name="app_max_image_width" min="0"
                           value="<?php echo htmlspecialchars($configValues['app_max_image_width'] ?? '300'); ?>">
                    <span class="help-text">Largura máxima desejada (px) para miniaturas.</span>
                </div>
            </div>

            <div class="section-heading">Logs e auditoria</div>
            <div class="section-description">
                Ative o registro em arquivo caso queira acompanhar as operações do plugin.
            </div>

            <div class="form-group">
                <div class="label-col"><label for="write_log">Gerar log</label></div>
                <div class="input-col">
                    <label class="switch">
                        <input type="checkbox" id="write_log" name="write_log"
                            <?php echo ($configValues['write_log'] ?? '0') === '1' ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                    <span class="help-text">Habilita o arquivo definido abaixo. Deixe desmarcado para desativar.</span>
                </div>
            </div>

            <div class="form-group">
                <div class="label-col"><label for="log_file">Caminho do arquivo de log</label></div>
                <div class="input-col">
                    <input type="text" id="log_file" name="log_file"
                           value="<?php echo htmlspecialchars($configValues['log_file'] ?? ''); ?>"
                           placeholder="/caminho/para/uniapp.log">
                    <span class="help-text">Informe um caminho absoluto válido. Deixe em branco para usar o log interno do GLPI.</span>
                </div>
            </div>
        </div>

        <div class="tab-pane" id="tab-logos">
        <div class="section-heading">Logos</div>
        <div class="section-description">
            Envie PNGs para hospedar o logo e o splash usados pelo aplicativo.
        </div>
            <?php foreach ($logoFields as $field => $label): ?>
                <div class="form-group">
                    <div class="label-col">
                        <label for="<?php echo $field; ?>"><?php echo $label; ?></label>
                    </div>
                    <div class="input-col">
                        <?php $previewData = $logoPreviewData[$field] ?? ''; ?>
                        <?php if ($previewData !== ''): ?>
                            <div class="logo-preview">
                                <img src="data:image/png;base64,<?php echo htmlspecialchars($previewData); ?>"
                                     alt="<?php echo htmlspecialchars($label); ?>">
                            </div>
                        <?php endif; ?>
                        <input type="file" id="<?php echo $field; ?>" name="<?php echo $field; ?>" accept="image/png">
                        <span class="help-text">Envie um PNG válido; ele será hospedado para uso no app.</span>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="logos-preview">
                <div class="section-heading">Pré-visualização</div>
                <div class="logos-preview-grid">
                    <?php foreach ($logoFields as $field => $label): ?>
                        <?php $previewData = $logoPreviewData[$field] ?? ''; ?>
                        <div class="logos-preview-card">
                            <div class="logos-preview-label"><?php echo htmlspecialchars($label); ?></div>
                            <img data-preview-img="<?php echo $field; ?>"
                                 src="<?php echo $previewData ? 'data:image/png;base64,' . htmlspecialchars($previewData) : ''; ?>"
                                 alt="<?php echo htmlspecialchars($label); ?>"
                                 style="display: <?php echo $previewData ? 'block' : 'none'; ?>;">
                            <div class="logos-preview-placeholder"
                                 data-preview-placeholder="<?php echo $field; ?>"
                                 style="display: <?php echo $previewData ? 'none' : 'flex'; ?>;">
                                Nenhuma imagem configurada
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-save" name="save_config">
                <i class="fa-solid fa-floppy-disk"></i> Salvar configurações
            </button>
        </div>
    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tabs = document.querySelectorAll('.tab-button');
    const panes = document.querySelectorAll('.tab-pane');

    tabs.forEach((button) => {
        button.addEventListener('click', () => {
            const targetId = button.dataset.tab;
            tabs.forEach(btn => btn.classList.toggle('active', btn === button));
            panes.forEach(pane => pane.classList.toggle('active', pane.id === targetId));
        });
    });

    const notificationTab = document.getElementById('tab-notificacoes');
    if (notificationTab) {
        const subButtons = notificationTab.querySelectorAll('.subtab-button');
        const subPanes = notificationTab.querySelectorAll('.subtab-pane');
        subButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const targetId = button.dataset.subtab;
                subButtons.forEach(btn => btn.classList.toggle('active', btn === button));
                subPanes.forEach(pane => pane.classList.toggle('active', pane.id === targetId));
            });
        });
    }

    const colorFields = <?php echo json_encode($flatColorFields, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    colorFields.forEach((field) => {
        const picker = document.getElementById(`picker_${field}`);
        const textInput = document.getElementById(field);
        if (!picker || !textInput) {
            return;
        }

        picker.addEventListener('input', (event) => {
            textInput.value = event.target.value.toUpperCase();
        });

        textInput.addEventListener('input', (event) => {
            let value = event.target.value;
            if (!value.startsWith('#') && (value.length === 3 || value.length === 6)) {
                value = '#' + value;
            }
            if (/^#[0-9A-F]{6}$/i.test(value)) {
                picker.value = value.toUpperCase();
            }
        });
    });

    const logoPreviewFields = <?php echo json_encode(array_keys($logoFields), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    logoPreviewFields.forEach((field) => {
        const fileInput = document.getElementById(field);
        const previewImage = document.querySelector(`[data-preview-img="${field}"]`);
        const placeholder = document.querySelector(`[data-preview-placeholder="${field}"]`);
        if (!fileInput || !previewImage || !placeholder) {
            return;
        }

        fileInput.addEventListener('change', () => {
            const file = fileInput.files && fileInput.files[0];
            if (!file) {
                const hasImage = previewImage.getAttribute('src');
                previewImage.style.display = hasImage ? 'block' : 'none';
                placeholder.style.display = hasImage ? 'none' : 'flex';
                return;
            }

            const reader = new FileReader();
            reader.addEventListener('load', (event) => {
                const result = event.target ? event.target.result : '';
                previewImage.src = result;
                previewImage.style.display = 'block';
                placeholder.style.display = 'none';
            });
            reader.readAsDataURL(file);
        });
    });
});
</script>

<?php
Html::footer();
