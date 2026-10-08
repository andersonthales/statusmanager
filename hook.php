<?php
/**
 * Status Manager – hook.php
 * Funções de instalação e desinstalação do plugin (GLPI 10/11 compatible).
 */

function plugin_statusmanager_install() {
    global $DB;

    $charset   = DBConnection::getDefaultCharset();
    $collation = DBConnection::getDefaultCollation();

    if (!$DB->tableExists('glpi_plugin_statusmanager_statuses')) {
        $DB->doQueryOrDie("
            CREATE TABLE `glpi_plugin_statusmanager_statuses` (
                `id`            INT(11)      NOT NULL AUTO_INCREMENT,
                `name`          VARCHAR(255) NOT NULL DEFAULT '',
                `color`         VARCHAR(20)  NOT NULL DEFAULT '#0070c0',
                `icon`          VARCHAR(100) NOT NULL DEFAULT 'ti-circle',
                `behavior`      VARCHAR(20)  NOT NULL DEFAULT 'pending',
                `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
                `rank`          INT(11)      NOT NULL DEFAULT 99,
                `status_key`    INT(11)      NOT NULL DEFAULT 0,
                `date_mod`      DATETIME     DEFAULT NULL,
                `date_creation` DATETIME     DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_status_key` (`status_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}
        ", 'Erro ao criar tabela statusmanager_statuses');
    }

    if (!$DB->tableExists('glpi_plugin_statusmanager_native_overrides')) {
        $DB->doQueryOrDie("
            CREATE TABLE `glpi_plugin_statusmanager_native_overrides` (
                `id`            INT(11)      NOT NULL AUTO_INCREMENT,
                `status_key`    INT(11)      NOT NULL DEFAULT 0,
                `label`         VARCHAR(100) NOT NULL DEFAULT '',
                `icon`          VARCHAR(100) NOT NULL DEFAULT '',
                `color`         VARCHAR(20)  NOT NULL DEFAULT '',
                `is_hidden`     TINYINT(1)   NOT NULL DEFAULT 0,
                `date_mod`      DATETIME     DEFAULT NULL,
                `date_creation` DATETIME     DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_native_key` (`status_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}
        ", 'Erro ao criar tabela statusmanager_native_overrides');
    }

    return true;
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
