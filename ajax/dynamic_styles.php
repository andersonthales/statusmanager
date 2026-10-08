<?php

/**
 * Status Manager – Endpoint AJAX: retorna status do plugin em JSON
 * Usado pelo JS para sobrescrever templateItilStatus no dropdown Select2.
 */

include('../../../inc/includes.php');
header('Content-Type: application/json; charset=UTF-8');

PluginStatusmanagerRegistry::load();

echo json_encode(PluginStatusmanagerRegistry::toArray());
