<?php
define('GLPI_ROOT', '../../../..');
include GLPI_ROOT . '/inc/includes.php';

// Garante que o plugin UniApp esteja carregado antes de usar as classes
Plugin::load('uniapp');

Session::checkLoginUser();
Session::checkRight('config', 'w');

$message = '';
$errors = [];
$configValues = PluginUniappConfig::getAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    Html::checkToken('PluginUniappConfig');

    $payload = [
        'fcm_project_id' => $_POST['fcm_project_id'] ?? '',
        'fcm_client_email' => $_POST['fcm_client_email'] ?? '',
        'fcm_private_key' => $_POST['fcm_private_key'] ?? '',
        'enable_attachments' => isset($_POST['enable_attachments']) ? '1' : '0',
        'color_header' => $_POST['color_header'] ?? '#005a8d',
        'color_buttons' => $_POST['color_buttons'] ?? '#486d1b',
        'color_background' => $_POST['color_background'] ?? '#ffffff',
        'color_text' => $_POST['color_text'] ?? '#333333'
    ];

    $errors = PluginUniappConfig::save($payload);
    if (empty($errors)) {
        $message = 'Configuracao salva com sucesso';
        $configValues = array_merge($configValues, $payload);
    }
}

Html::header('Configuracao UniApp', $_SERVER['PHP_SELF'], 'plugins', 'uniapp');
// Monta a pagina de configuracao com o design solicitado

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

    body {
        font-family: 'Roboto', 'Segoe UI', Tahoma, sans-serif;
        background-color: var(--glpi-bg);
        color: var(--glpi-text);
    }

    .uniapp-container {
        max-width: 1000px;
        margin: 0 auto;
        background: var(--glpi-white);
        border: 1px solid var(--glpi-border);
        border-top: 3px solid var(--glpi-blue);
        border-radius: 4px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        padding-bottom: 30px;
    }

    .uniapp-header {
        padding: 15px 20px;
        border-bottom: 1px solid var(--glpi-border);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .uniapp-header h2 {
        margin: 0;
        font-size: 18px;
        color: var(--glpi-blue);
        font-weight: 500;
    }

    .uniapp-form {
        padding: 20px;
    }

    .form-group {
        display: flex;
        margin-bottom: 20px;
        align-items: center;
        border-bottom: 1px dotted #eee;
        padding-bottom: 15px;
    }

    .form-group:last-child {
        border-bottom: none;
    }

    .label-col {
        width: 25%;
        font-weight: 600;
        text-align: right;
        padding-right: 20px;
        color: #333;
    }

    .input-col {
        width: 75%;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    input[type="text"], textarea {
        width: 100%;
        max-width: 520px;
        padding: 8px 10px;
        border: 1px solid #ccc;
        border-radius: 3px;
        font-size: 13px;
        transition: border 0.2s;
        font-family: inherit;
    }

    textarea {
        resize: vertical;
        min-height: 120px;
    }

    input[type="text"]:focus,
    textarea:focus {
        border-color: var(--glpi-blue);
        outline: none;
    }

    .switch {
        position: relative;
        display: inline-block;
        width: 40px;
        height: 22px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 34px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked + .slider {
        background-color: var(--glpi-green);
    }

    input:checked + .slider:before {
        transform: translateX(18px);
    }

    .colors-section {
        background-color: #f9f9f9;
        border: 1px solid #eee;
        padding: 15px;
        border-radius: 4px;
        width: 100%;
        max-width: 600px;
    }

    .color-row {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
        justify-content: space-between;
    }

    .color-row label {
        width: 40%;
        font-weight: normal;
    }

    .color-picker-wrapper {
        display: flex;
        align-items: center;
        width: 60%;
        gap: 10px;
    }

    .color-input-visual {
        width: 40px;
        height: 34px;
        padding: 0;
        border: none;
        background: none;
        cursor: pointer;
    }

    .color-input-hex {
        width: 100px !important;
        text-transform: uppercase;
    }

    .form-actions {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid var(--glpi-border);
        text-align: center;
    }

    .btn-save {
        background-color: var(--glpi-green);
        color: white;
        border: none;
        padding: 10px 25px;
        font-size: 14px;
        border-radius: 3px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: background 0.2s;
    }

    .btn-save:hover {
        background-color: #3a5816;
    }

    .help-text {
        font-size: 11px;
        color: #888;
        margin-top: 4px;
        display: block;
    }

    @media (max-width: 768px) {
        .form-group {
            flex-direction: column;
            align-items: flex-start;
        }
        .label-col {
            width: 100%;
            text-align: left;
            margin-bottom: 5px;
        }
        .input-col {
            width: 100%;
        }
    }

    .uniapp-message {
        margin: 20px;
        padding: 10px;
        border-radius: 3px;
        font-size: 13px;
    }

    .uniapp-message.success {
        background-color: #dff0d8;
        border: 1px solid #b2dba1;
        color: #2f6627;
    }

    .uniapp-message.error {
        background-color: #fbeaea;
        border: 1px solid #f0a9af;
        color: #8d2424;
    }
</style>

<div class="uniapp-container">
    <div class="uniapp-header">
        <i class="fa-solid fa-puzzle-piece fa-lg" style="color: var(--glpi-blue);"></i>
        <h2>Configuração do Plugin App</h2>
    </div>

    <?php if ($message): ?>
        <div class="uniapp-message success"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="uniapp-message error">
            <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
        </div>
    <?php endif; ?>

    <form class="uniapp-form" method="post">
        <?php Html::sendForm('PluginUniappConfig'); ?>

        <div class="form-group">
            <div class="label-col">
                <label for="fcm_project_id">Projeto FCM</label>
            </div>
            <div class="input-col">
                <input type="text" id="fcm_project_id" name="fcm_project_id" value="<?php echo htmlspecialchars($configValues['fcm_project_id'] ?? ''); ?>"
                       placeholder="Informe o ID do projeto Firebase">
                <span class="help-text">ID registrado no Firebase para envio de notificacoes via FCM.</span>
            </div>
        </div>

        <div class="form-group">
            <div class="label-col">
                <label for="fcm_client_email">Client Email</label>
            </div>
            <div class="input-col">
                <input type="text" id="fcm_client_email" name="fcm_client_email" value="<?php echo htmlspecialchars($configValues['fcm_client_email'] ?? ''); ?>"
                       placeholder="Email do servico no Firebase">
                <span class="help-text">Email vinculado ao service account do FCM.</span>
            </div>
        </div>

        <div class="form-group">
            <div class="label-col">
                <label for="fcm_private_key">Private Key</label>
            </div>
            <div class="input-col">
                <textarea id="fcm_private_key" name="fcm_private_key"
                          placeholder="Cole a chave privada JSON do Firebase"><?php echo htmlspecialchars($configValues['fcm_private_key'] ?? ''); ?></textarea>
                <span class="help-text">Chave privada usada para autenticar com o Firebase Admin.</span>
            </div>
        </div>

        <div class="form-group">
            <div class="label-col">
                <label for="enable_attachments">Incluir anexos</label>
            </div>
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
            <div class="label-col">
                <label>Personalizacao visual</label>
            </div>
            <div class="input-col">
                <div class="colors-section">
                    <?php
                    $colors = [
                        'color_header' => 'Cor do cabecalho',
                        'color_buttons' => 'Cor dos botoes',
                        'color_background' => 'Cor de fundo do app',
                        'color_text' => 'Cor do texto principal'
                    ];
                    ?>
                    <?php foreach ($colors as $field => $labelText): ?>
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
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-save" name="save_config">
                <i class="fa-solid fa-floppy-disk"></i> Salvar configuracoes
            </button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Sincroniza o seletor visual com o campo hexadecimal
        const colorPairs = [
            {picker: 'picker_color_header', text: 'color_header'},
            {picker: 'picker_color_buttons', text: 'color_buttons'},
            {picker: 'picker_color_background', text: 'color_background'},
            {picker: 'picker_color_text', text: 'color_text'}
        ];

        colorPairs.forEach(pair => {
            const picker = document.getElementById(pair.picker);
            const text = document.getElementById(pair.text);

            if (!picker || !text) {
                return;
            }

            picker.addEventListener('input', (event) => {
                text.value = event.target.value.toUpperCase();
            });

            text.addEventListener('input', (event) => {
                let value = event.target.value;
                if (!value.startsWith('#') && (value.length === 3 || value.length === 6)) {
                    value = '#' + value;
                }

                if (/^#[0-9A-F]{6}$/i.test(value)) {
                    picker.value = value;
                }
            });
        });
    });
</script>

<?php
Html::footer();
