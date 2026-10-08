<?php

/**
 * Status Manager – Script de upgrade
 *
 * Executa migrações necessárias em instalações existentes do antigo
 * plugin "customstatus" para o novo "statusmanager".
 *
 * Uso: acesse diretamente via browser após substituir os arquivos do plugin.
 *   https://seu-glpi/plugins/statusmanager/upgrade.php
 */

include('../../inc/includes.php');
Session::checkRight('config', UPDATE);

$DB = DB::getInstance();
$charset   = DBConnection::getDefaultCharset();
$collation = DBConnection::getDefaultCollation();
$errors    = array();
$messages  = array();

// ── 1. Migrar dados do plugin antigo (customstatus → statusmanager) ───────────
$migrations = array(
    'glpi_plugin_customstatus_statuses'         => 'glpi_plugin_statusmanager_statuses',
    'glpi_plugin_customstatus_native_overrides' => 'glpi_plugin_statusmanager_native_overrides',
);

foreach ($migrations as $oldTable => $newTable) {
    if ($DB->tableExists($oldTable) && !$DB->tableExists($newTable)) {
        $DB->queryOrDie(
            "CREATE TABLE `{$newTable}` LIKE `{$oldTable}`",
            "Erro ao criar {$newTable}"
        );
        $DB->queryOrDie(
            "INSERT INTO `{$newTable}` SELECT * FROM `{$oldTable}`",
            "Erro ao migrar dados de {$oldTable}"
        );
        $messages[] = "Dados migrados de <code>{$oldTable}</code> para <code>{$newTable}</code>.";
    }
}

// ── 2. Criar tabelas se não existirem ─────────────────────────────────────────
if (!$DB->tableExists('glpi_plugin_statusmanager_statuses')) {
    $DB->queryOrDie("
        CREATE TABLE `glpi_plugin_statusmanager_statuses` (
            `id`            INT(11)      NOT NULL AUTO_INCREMENT,
            `name`          VARCHAR(255) NOT NULL DEFAULT '',
            `color`         VARCHAR(20)  NOT NULL DEFAULT '#0070c0',
            `icon`          VARCHAR(100) NOT NULL DEFAULT 'fas fa-circle',
            `behavior`      VARCHAR(20)  NOT NULL DEFAULT 'pending',
            `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
            `rank`          INT(11)      NOT NULL DEFAULT 99,
            `status_key`    INT(11)      NOT NULL DEFAULT 0,
            `date_mod`      DATETIME     DEFAULT NULL,
            `date_creation` DATETIME     DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_status_key` (`status_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};
    ", 'Erro ao criar tabela statusmanager_statuses');
    $messages[] = 'Tabela <code>glpi_plugin_statusmanager_statuses</code> criada.';
}

if (!$DB->tableExists('glpi_plugin_statusmanager_native_overrides')) {
    $DB->queryOrDie("
        CREATE TABLE `glpi_plugin_statusmanager_native_overrides` (
            `id`            INT(11)      NOT NULL AUTO_INCREMENT,
            `status_key`    INT(11)      NOT NULL DEFAULT 0,
            `label`         VARCHAR(100) NOT NULL DEFAULT '',
            `is_hidden`     TINYINT(1)   NOT NULL DEFAULT 0,
            `date_mod`      DATETIME     DEFAULT NULL,
            `date_creation` DATETIME     DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_native_key` (`status_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation};
    ", 'Erro ao criar tabela statusmanager_native_overrides');
    $messages[] = 'Tabela <code>glpi_plugin_statusmanager_native_overrides</code> criada.';
}

// ── Exibe resultado ───────────────────────────────────────────────────────────
Html::header('Status Manager – Upgrade', '', 'config', 'plugins');
echo '<div class="container mt-4" style="max-width:700px;">';
echo '<h2><i class="fas fa-tools me-2"></i>Status Manager – Upgrade</h2>';

if (!empty($errors)) {
    echo '<div class="alert alert-danger"><ul class="mb-0">';
    foreach ($errors as $e) { echo '<li>' . $e . '</li>'; }
    echo '</ul></div>';
}

if (!empty($messages)) {
    echo '<div class="alert alert-success"><ul class="mb-0">';
    foreach ($messages as $m) { echo '<li>' . $m . '</li>'; }
    echo '</ul></div>';
} else {
    echo '<div class="alert alert-info">Nenhuma migração necessária. Tudo já está atualizado.</div>';
}

echo '<a href="' . Plugin::getWebDir('statusmanager') . '/front/config.php" class="btn btn-primary mt-2">'
    . '<i class="fas fa-arrow-left me-1"></i>Voltar ao plugin</a>';
echo '</div>';
Html::footer();
