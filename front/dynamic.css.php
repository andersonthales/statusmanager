<?php

/**
 * Status Manager – CSS dinâmico dos status personalizados
 */

include('../../../inc/includes.php');
header('Content-Type: text/css; charset=UTF-8');

PluginStatusmanagerRegistry::load();

// CSS base do plugin.
// Desde a 3.3.0 os estáticos moram em public/ (exigência do roteador do
// GLPI 11). O caminho antigo fica como fallback para instalações que ainda
// não foram reorganizadas.
$css_base = null;
foreach ([__DIR__ . '/../public/css/statusmanager.css', __DIR__ . '/../css/statusmanager.css'] as $candidato) {
    if (is_readable($candidato)) {
        $css_base = $candidato;
        break;
    }
}
if ($css_base !== null) {
    echo file_get_contents($css_base);
    echo "\n";
}

// CSS dinâmico por status
echo PluginStatusmanagerRegistry::generateCSS();

// Regras adicionais para ícones inline
foreach (PluginStatusmanagerRegistry::getAll() as $key => $row) {
    $color = preg_replace('/[^#a-fA-F0-9]/', '', $row['color']);
    echo "i.cs-status-{$key} { color: {$color} !important; }\n";
    echo ".itilstatus.cs-status-{$key} { color: {$color} !important; }\n";
}
