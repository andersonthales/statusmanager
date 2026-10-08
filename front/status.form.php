<?php

/**
 * Status Manager – Formulário de criação/edição de status
 */

include('../../../inc/includes.php');
Session::checkRight('config', UPDATE);

$s    = new PluginStatusmanagerStatus();
$base = Plugin::getWebDir('statusmanager');

// ── Salvar / Atualizar ────────────────────────────────────────────────────────
if (isset($_POST['save'])) {

    $name      = trim(isset($_POST['name'])       ? $_POST['name']       : '');
    $colorRaw  = trim(isset($_POST['color_text']) ? $_POST['color_text'] : (isset($_POST['color']) ? $_POST['color'] : '#0070c0'));
    $color     = preg_match('/^#[0-9a-fA-F]{3,6}$/', $colorRaw) ? $colorRaw : '#0070c0';
    $icon      = trim(isset($_POST['icon'])       ? $_POST['icon']       : 'fas fa-circle');
    $bhv       = isset($_POST['behavior'])        ? $_POST['behavior']   : 'pending';
    $behavior  = in_array($bhv, array('assigned', 'pending', 'solved', 'closed')) ? $bhv : 'pending';
    $rank      = max(1, (int)(isset($_POST['rank'])       ? $_POST['rank']       : 99));
    $active    = isset($_POST['is_active']) ? 1 : 0;
    $skey      = (int)(isset($_POST['status_key']) ? $_POST['status_key'] : PluginStatusmanagerRegistry::getNextStatusKey());
    $id        = (int)(isset($_POST['id'])         ? $_POST['id']         : 0);

    if (empty($name)) {
        Session::addMessageAfterRedirect('O nome do status é obrigatório.', false, ERROR);
        Html::redirect($base . '/front/status.form.php?id=' . $id);
        exit;
    }

    $data = array(
        'name'       => $name,
        'color'      => $color,
        'icon'       => $icon,
        'behavior'   => $behavior,
        'rank'       => $rank,
        'is_active'  => $active,
        'status_key' => $skey,
        'date_mod'   => date('Y-m-d H:i:s'),
    );

    if ($id > 0) {
        $data['id'] = $id;
        $s->update($data);
        Session::addMessageAfterRedirect(
            'Status "' . $name . '" atualizado com sucesso.',
            false, INFO
        );
    } else {
        $data['date_creation'] = date('Y-m-d H:i:s');
        $s->add($data);
        Session::addMessageAfterRedirect(
            'Status "' . $name . '" criado (chave: ' . $skey . ').',
            false, INFO
        );
    }

    // Invalida cache do registry para refletir as mudanças imediatamente
    PluginStatusmanagerRegistry::reload();
    // GLPI 11: sem isso o status existe no core mas o formulário o filtra
    PluginStatusmanagerRegistry::syncTemplateAllowedStatuses();

    Html::redirect($base . '/front/config.php');
    exit;
}

// ── Remover ───────────────────────────────────────────────────────────────────
if (isset($_POST['purge'])) {
    $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
    if ($id > 0) {
        $s->delete(array('id' => $id), true);
        PluginStatusmanagerRegistry::reload();
        PluginStatusmanagerRegistry::syncTemplateAllowedStatuses();
        Session::addMessageAfterRedirect('Status removido com sucesso.', false, INFO);
    }
    Html::redirect($base . '/front/config.php');
    exit;
}

// ── Exibir formulário ─────────────────────────────────────────────────────────
$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);

Html::header('Status Manager', '', 'config', 'plugins');
Html::displayMessageAfterRedirect();

if ($id > 0) {
    $s->getFromDB($id);
}
$s->showForm($id);

Html::footer();
