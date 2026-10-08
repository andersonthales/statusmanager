<?php

/**
 * Status Manager – Gerenciamento dos status nativos do GLPI
 */

include('../../../inc/includes.php');
Session::checkRight('config', UPDATE);

$base = Plugin::getWebDir('statusmanager');

// Colunas de ícone/cor chegaram na 3.4.0; garante-as em instalações antigas
PluginStatusmanagerNativeOverride::ensureSchema();

// ── Salvar ────────────────────────────────────────────────────────────────────
if (isset($_POST['save_natives'])) {
    $natives = PluginStatusmanagerNativeOverride::getNativeStatuses();
    foreach (array_keys($natives) as $key) {
        // Campo hidden garante que o valor 0 chega quando checkbox não está marcado
        $hidden = (isset($_POST['hidden_' . $key]) && (int)$_POST['hidden_' . $key] === 1) ? 1 : 0;
        $label  = trim(isset($_POST['label_' . $key]) ? $_POST['label_' . $key] : '');
        $icon   = trim(isset($_POST['icon_'  . $key]) ? $_POST['icon_'  . $key] : '');
        $color  = trim(isset($_POST['color_' . $key]) ? $_POST['color_' . $key] : '');
        PluginStatusmanagerNativeOverride::save($key, $label, $hidden, $icon, $color);
    }

    // Invalida cache para refletir mudanças
    PluginStatusmanagerRegistry::reload();

    Session::addMessageAfterRedirect('Status nativos atualizados com sucesso.', false, INFO);
    Html::redirect($base . '/front/native.php');
    exit;
}

// ── Exibir formulário ─────────────────────────────────────────────────────────
Html::header('Status Manager – Nativos', '', 'config', 'plugins');
Html::displayMessageAfterRedirect();

$overrides = PluginStatusmanagerNativeOverride::getAll();
$natives   = PluginStatusmanagerNativeOverride::getNativeStatuses();
$cancelUrl = $base . '/front/config.php';
$formUrl   = $base . '/front/native.php';

echo '<div class="container-fluid mt-3" style="max-width:800px;">';

// Cabeçalho
echo '<div class="d-flex align-items-center justify-content-between mb-3">';
echo '<h2 class="mb-0"><i class="fas fa-sliders-h me-2 text-primary"></i>Gerenciar status nativos do GLPI</h2>';
echo '<a href="' . htmlspecialchars($cancelUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-secondary">'
    . '<i class="fas fa-arrow-left me-1"></i>Voltar</a>';
echo '</div>';

// Informativo
echo '<div class="alert alert-info">';
echo '<i class="fas fa-info-circle me-2"></i>';
echo '<strong>Status nativos</strong> são os 6 status padrão do GLPI. '
    . 'Você pode <strong>renomear</strong>, definir <strong>ícone e cor</strong> '
    . '(para o nativo ficar visualmente igual aos personalizados) e/ou <strong>ocultar</strong> do dropdown. '
    . 'Campo em branco mantém o comportamento original do GLPI.';
echo '</div>';

echo '<form method="post" action="' . htmlspecialchars($formUrl, ENT_QUOTES, 'UTF-8') . '">';
echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';

echo '<div class="card">';
echo '<div class="card-header"><strong>Status nativos</strong></div>';
echo '<table class="table table-hover mb-0">';
echo '<thead class="table-light"><tr>'
    . '<th style="width:50px">ID</th>'
    . '<th style="width:160px">Nome original</th>'
    . '<th>Renomear para</th>'
    . '<th style="width:190px">Ícone</th>'
    . '<th style="width:90px">Cor</th>'
    . '<th style="width:110px;text-align:center">Ocultar</th>'
    . '</tr></thead>';
echo '<tbody>';

foreach ($natives as $key => $originalName) {
    $ov     = isset($overrides[$key]) ? $overrides[$key] : array();
    $label  = isset($ov['label'])     ? htmlspecialchars($ov['label'], ENT_QUOTES, 'UTF-8') : '';
    $hidden = isset($ov['is_hidden']) ? (int)$ov['is_hidden'] : 0;
    $rowCls = $hidden ? 'table-secondary' : '';

    echo '<tr class="' . $rowCls . '" id="sm_row_' . $key . '">';
    echo '<td><code>' . $key . '</code></td>';
    echo '<td id="sm_orig_' . $key . '">';
    echo $hidden ? '<s>' : '';
    echo htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8');
    echo $hidden ? '</s>' : '';
    echo '</td>';
    echo '<td>';
    echo '<input type="text" name="label_' . $key . '" id="sm_label_' . $key . '"'
        . ' class="form-control form-control-sm"'
        . ' value="' . $label . '"'
        . ' placeholder="' . htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') . '"'
        . ' maxlength="100">';
    echo '</td>';

    // ── Ícone ────────────────────────────────────────────────────────────────
    $icon = isset($ov['icon']) ? $ov['icon'] : '';
    echo '<td>';
    echo '<div class="d-flex align-items-center gap-2">';
    echo '<i id="sm_iprev_' . $key . '" class="' . ($icon !== '' ? 'ti ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') : '') . '"></i>';
    echo '<select name="icon_' . $key . '" class="form-select form-select-sm"'
        . ' onchange="smPreviewIcon(' . $key . ', this.value)">';
    echo '<option value="">(padrão do GLPI)</option>';
    foreach (PluginStatusmanagerStatus::getIconOptions() as $cls => $rotulo) {
        echo '<option value="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '"'
            . ($icon === $cls ? ' selected' : '') . '>'
            . htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</select>';
    echo '</div></td>';

    // ── Cor ──────────────────────────────────────────────────────────────────
    $color = isset($ov['color']) && $ov['color'] !== '' ? $ov['color'] : '#000000';
    echo '<td>';
    echo '<input type="color" name="color_' . $key . '" class="form-control form-control-sm form-control-color"'
        . ' value="' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . '">';
    echo '</td>';

    echo '<td style="text-align:center">';
    // Campo hidden garante que o valor 0 chegue quando checkbox não está marcado
    echo '<input type="hidden" name="hidden_' . $key . '" value="0">';
    echo '<div class="form-check form-switch d-flex justify-content-center fs-5">';
    echo '<input class="form-check-input" type="checkbox"'
        . ' name="hidden_' . $key . '"'
        . ' value="1"'
        . ' id="sm_hide_' . $key . '"'
        . ($hidden ? ' checked' : '')
        . ' onchange="smToggleRow(' . $key . ', this.checked)">';
    echo '</div></td>';
    echo '</tr>';
}

echo '</tbody></table></div>';

echo '<div class="d-flex gap-2 mt-3">';
echo '<button type="submit" name="save_natives" class="btn btn-primary">'
    . '<i class="fas fa-save me-1"></i>Salvar alterações</button>';
echo '<a href="' . htmlspecialchars($cancelUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-secondary">Cancelar</a>';
echo '</div>';
echo '</form>';

echo '<script>
function smPreviewIcon(key, cls) {
    var el = document.getElementById("sm_iprev_" + key);
    el.className = cls ? "ti " + cls : "";
}
function smToggleRow(key, hidden) {
    var row  = document.getElementById("sm_row_"  + key);
    var orig = document.getElementById("sm_orig_" + key);
    var text = orig.textContent || orig.innerText;
    if (hidden) {
        row.classList.add("table-secondary");
        orig.innerHTML = "<s>" + text + "</s>";
    } else {
        row.classList.remove("table-secondary");
        orig.innerHTML = text;
    }
}
</script>';

echo '</div>';

Html::footer();
