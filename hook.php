<?php
/**
 * Status Manager – hook.php
 * Funções de instalação e desinstalação do plugin (GLPI 10/11 compatible).
 */

function plugin_statusmanager_install() {
    global $DB;

    $charset   = DBConnection::getDefaultCharset();
    $collation = DBConnection::getDefaultCollation();
    // GLPI 10.0+ e 11 recomendam chaves primárias unsigned e datas em
    // TIMESTAMP; fora disso o db:check acusa a tabela do plugin.
    $sign      = DBConnection::getDefaultPrimaryKeySignOption();

    if (!$DB->tableExists('glpi_plugin_statusmanager_statuses')) {
        $DB->doQueryOrDie("
            CREATE TABLE `glpi_plugin_statusmanager_statuses` (
                `id`            INT {$sign}  NOT NULL AUTO_INCREMENT,
                `name`          VARCHAR(255) NOT NULL DEFAULT '',
                `color`         VARCHAR(20)  NOT NULL DEFAULT '#0070c0',
                `icon`          VARCHAR(100) NOT NULL DEFAULT 'ti-circle',
                `behavior`      VARCHAR(20)  NOT NULL DEFAULT 'pending',
                `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
                `rank`          INT(11)      NOT NULL DEFAULT 99,
                `status_key`    INT(11)      NOT NULL DEFAULT 0,
                `date_mod`      TIMESTAMP    NULL DEFAULT NULL,
                `date_creation` TIMESTAMP    NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_status_key` (`status_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}
        ", 'Erro ao criar tabela statusmanager_statuses');
    }

    if (!$DB->tableExists('glpi_plugin_statusmanager_native_overrides')) {
        $DB->doQueryOrDie("
            CREATE TABLE `glpi_plugin_statusmanager_native_overrides` (
                `id`            INT {$sign}  NOT NULL AUTO_INCREMENT,
                `status_key`    INT(11)      NOT NULL DEFAULT 0,
                `label`         VARCHAR(100) NOT NULL DEFAULT '',
                `icon`          VARCHAR(100) NOT NULL DEFAULT '',
                `color`         VARCHAR(20)  NOT NULL DEFAULT '',
                `is_hidden`     TINYINT(1)   NOT NULL DEFAULT 0,
                `date_mod`      TIMESTAMP    NULL DEFAULT NULL,
                `date_creation` TIMESTAMP    NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_native_key` (`status_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}
        ", 'Erro ao criar tabela statusmanager_native_overrides');
    }

    // Instalações anteriores à 3.5.0 criaram as tabelas com INT signed e
    // DATETIME. Converte ao reinstalar/atualizar, só o que ainda está antigo.
    foreach (array('glpi_plugin_statusmanager_statuses', 'glpi_plugin_statusmanager_native_overrides') as $table) {
        $changes = array();
        foreach ($DB->doQuery("SHOW COLUMNS FROM `{$table}`") as $col) {
            $type = strtolower($col['Type']);
            if ($col['Field'] === 'id' && $sign !== '' && strpos($type, 'unsigned') === false) {
                $changes[] = "MODIFY `id` INT {$sign} NOT NULL AUTO_INCREMENT";
            } elseif (in_array($col['Field'], array('date_mod', 'date_creation'), true) && $type === 'datetime') {
                $changes[] = "MODIFY `{$col['Field']}` TIMESTAMP NULL DEFAULT NULL";
            }
        }
        if (!empty($changes)) {
            $DB->doQueryOrDie("ALTER TABLE `{$table}` " . implode(', ', $changes), 'Erro ao atualizar ' . $table);
        }
    }

    return true;
}

/**
 * Aviso no painel central para quem administra a configuração.
 *
 * Sem os patches, os status do plugin continuam gravados nos chamados mas
 * somem de dropdowns, filtros, dashboards e da contagem de SLA — e nada
 * avisa. O caso mais comum é atualizar o GLPI, que substitui o src/.
 */
function plugin_statusmanager_display_central() {
    if (!Session::haveRight('config', UPDATE)
        || !class_exists('PluginStatusmanagerPatcher')
        || !PluginStatusmanagerRegistry::hasConfiguration()
        || PluginStatusmanagerPatcher::isApplied()) {
        return;
    }
    $url = Plugin::getWebDir('statusmanager') . '/front/config.php';
    echo '<div class="alert alert-warning m-2">'
        . '<strong>Status Manager:</strong> os patches no core do GLPI não estão completos '
        . '(provavelmente o GLPI foi atualizado). Os status personalizados não aparecem '
        . 'corretamente nem pausam o SLA até reaplicar. '
        . '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">Abrir o Status Manager</a> '
        . 'ou rodar <code>php bin/console plugins:statusmanager:patch apply</code>.'
        . '</div>';
}

function plugin_statusmanager_uninstall() {
    global $DB;

    if (class_exists('PluginStatusmanagerPatcher')) {
        PluginStatusmanagerPatcher::revertAll();
    }

    foreach ([
        'glpi_plugin_statusmanager_statuses',
        'glpi_plugin_statusmanager_native_overrides',
    ] as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQueryOrDie("DROP TABLE `{$table}`", 'Erro ao remover tabela ' . $table);
        }
    }

    return true;
}
