<?php

/**
 * ---------------------------------------------------------------------
 * Status Manager – Plugin para GLPI 10 e 11
 * Gerencia status personalizados de chamados em tempo de execução,
 * sem necessidade de editar arquivos do core manualmente.
 *
 * @author    Anderson Thales <https://github.com/andersonthales>
 * @license   GPLv3+
 * @link      https://github.com/andersonthales/statusmanager
 * ---------------------------------------------------------------------
 */

define('PLUGIN_STATUSMANAGER_VERSION',  '3.4.2');
define('PLUGIN_STATUSMANAGER_MIN_GLPI', '10.0.0');
define('PLUGIN_STATUSMANAGER_MAX_GLPI', '11.1.99');
define('PLUGIN_STATUSMANAGER_KEY_OFFSET', 200); // status_keys dos custom começam aqui

function plugin_version_statusmanager() {
    return [
        'name'         => 'Status Manager',
        'version'      => PLUGIN_STATUSMANAGER_VERSION,
        'author'       => 'Anderson Thales',
        'license'      => 'GPLv3+',
        'homepage'     => 'https://github.com/andersonthales/statusmanager',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_STATUSMANAGER_MIN_GLPI,
                'max' => PLUGIN_STATUSMANAGER_MAX_GLPI,
            ],
        ],
    ];
}

function plugin_statusmanager_check_prerequisites() {
    if (version_compare(GLPI_VERSION, PLUGIN_STATUSMANAGER_MIN_GLPI, 'lt')) {
        echo 'Requer GLPI >= ' . PLUGIN_STATUSMANAGER_MIN_GLPI;
        return false;
    }
    return true;
}

function plugin_statusmanager_check_config() {
    return true;
}

function plugin_init_statusmanager() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['statusmanager'] = true;

    // Os arquivos estáticos moram em public/. O que muda entre as versões é
    // como o GLPI monta a URL a partir do caminho registrado aqui:
    //
    //   GLPI 11 -> o roteador já procura recursos de plugin dentro de public/,
    //              então o caminho registrado NÃO deve incluir esse prefixo.
    //   GLPI 10 -> serve o arquivo direto do diretório do plugin, então o
    //              caminho precisa incluir public/.
    //
    // Registrar errado gera 404 silencioso: o <script> vai para a página, o
    // navegador não encontra o arquivo e nada no PHP acusa o problema.
    $asset_prefix = version_compare(GLPI_VERSION, '11.0.0', '>=') ? '' : 'public/';

    $PLUGIN_HOOKS['add_css']['statusmanager']        = $asset_prefix . 'css/statusmanager.css';
    $PLUGIN_HOOKS['add_javascript']['statusmanager'] = $asset_prefix . 'js/statusmanager.js';
    $PLUGIN_HOOKS['post_init']['statusmanager']      = 'plugin_statusmanager_post_init';
    $PLUGIN_HOOKS['config_page']['statusmanager']    = 'front/config.php';
}

function plugin_statusmanager_post_init() {
    global $DB;
    if (!isset($DB) || !$DB->tableExists('glpi_plugin_statusmanager_statuses')) {
        return;
    }
    PluginStatusmanagerRegistry::load();
}
