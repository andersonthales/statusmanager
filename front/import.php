<?php

/**
 * Status Manager – Cadastro de status a partir dos chamados
 *
 * Lista os status gravados em glpi_tickets que nem o GLPI nem o plugin
 * conhecem — caso típico de uma base migrada de um GLPI com status criados
 * editando o core — e cadastra os escolhidos com a MESMA chave, para que
 * os chamados existentes voltem a ter nome, ícone e comportamento.
 */

include('../../../inc/includes.php');
Session::checkRight('config', UPDATE);

$base = Plugin::getWebDir('statusmanager');

// ── Cadastrar selecionados ────────────────────────────────────────────────────
if (isset($_POST['import'])) {
    $keys      = isset($_POST['keys']) && is_array($_POST['keys']) ? $_POST['keys'] : array();
    $names     = isset($_POST['name']) && is_array($_POST['name']) ? $_POST['name'] : array();
    $behaviors = isset($_POST['behavior']) && is_array($_POST['behavior']) ? $_POST['behavior'] : array();
    $colors    = isset($_POST['color']) && is_array($_POST['color']) ? $_POST['color'] : array();
    $icon      = key(PluginStatusmanagerStatus::getIconOptions());

    $created = 0;
    foreach ($keys as $key) {
        $key = (int)$key;
        $s   = new PluginStatusmanagerStatus();
        // Validação (faixa, chave repetida, nome, cor) fica no modelo;
        // o que for recusado deixa a própria mensagem de erro.
        $ok = $s->add(array(
            'name'          => isset($names[$key]) ? $names[$key] : '',
            'status_key'    => $key,
            'behavior'      => isset($behaviors[$key]) ? $behaviors[$key] : 'pending',
            'color'         => isset($colors[$key]) ? $colors[$key] : '#0070c0',
            'icon'          => $icon,
            'rank'          => 99,
            'is_active'     => 1,
            'date_creation' => date('Y-m-d H:i:s'),
            'date_mod'      => date('Y-m-d H:i:s'),
        ));
        if ($ok) {
            $created++;
        }
    }

    if ($created > 0) {
        Session::addMessageAfterRedirect($created . ' status cadastrado(s) com a chave já usada nos chamados.', false, INFO);
    }
    Html::redirect($base . '/front/import.php');
    exit;
}

// ── Listagem ──────────────────────────────────────────────────────────────────
Html::header('Status Manager', '', 'config', 'plugins');
Html::displayMessageAfterRedirect();

$unknown   = PluginStatusmanagerRegistry::getUnknownTicketStatuses();
$behaviors = PluginStatusmanagerStatus::getBehaviorOptions();
?>

<div class="container-fluid mt-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-0"><i class="fas fa-file-import me-2 text-primary"></i>Status encontrados nos chamados</h2>
    <a href="<?php echo $base; ?>/front/config.php" class="btn btn-secondary">Voltar</a>
  </div>

  <?php if (empty($unknown)): ?>
    <div class="alert alert-success">
      <i class="fas fa-check-circle me-2"></i>Todos os status gravados nos chamados já estão cadastrados.
    </div>
  <?php else: ?>
  <div class="alert alert-info">
    Cada status abaixo está gravado em chamados, mas não tem cadastro. Informe o nome que ele tinha
    na instalação de origem e o comportamento — <em>Pendente</em> pausa o SLA, <em>Solucionado</em> e
    <em>Fechado</em> contam como encerrados. A chave não muda: os chamados existentes passam a usar
    o cadastro novo direto, sem nenhuma alteração neles.
  </div>

  <form method="post" action="<?php echo $base; ?>/front/import.php">
    <input type="hidden" name="_glpi_csrf_token" value="<?php echo Session::getNewCSRFToken(); ?>">
    <div class="card">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:40px"></th>
            <th style="width:90px">Chave</th>
            <th style="width:100px">Chamados</th>
            <th style="width:170px">Última alteração</th>
            <th>Nome</th>
            <th style="width:240px">Comportamento</th>
            <th style="width:80px">Cor</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($unknown as $key => $info):
            $key = (int)$key;
            $importable = $key >= PluginStatusmanagerStatus::KEY_MIN && $key <= PluginStatusmanagerStatus::KEY_MAX;
        ?>
          <tr>
            <td>
              <?php if ($importable): ?>
                <input class="form-check-input" type="checkbox" name="keys[]" value="<?php echo $key; ?>" checked>
              <?php endif; ?>
            </td>
            <td><code><?php echo $key; ?></code></td>
            <td><?php echo (int)$info['count']; ?></td>
            <td><?php echo htmlspecialchars((string)$info['last'], ENT_QUOTES, 'UTF-8'); ?></td>
            <?php if ($importable): ?>
              <td><input type="text" name="name[<?php echo $key; ?>]" class="form-control" maxlength="255"
                         value="<?php echo 'Status ' . $key; ?>"></td>
              <td>
                <select name="behavior[<?php echo $key; ?>]" class="form-select">
                  <?php foreach ($behaviors as $val => $lbl): ?>
                    <option value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $val === 'pending' ? ' selected' : ''; ?>>
                      <?php echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="color" name="color[<?php echo $key; ?>]" class="form-control form-control-color" value="#0070c0"></td>
            <?php else: ?>
              <td colspan="3" class="text-muted">
                Fora da faixa aceita (<?php echo PluginStatusmanagerStatus::KEY_MIN; ?>–<?php echo PluginStatusmanagerStatus::KEY_MAX; ?>):
                esses chamados precisam ter o status corrigido no banco.
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="mt-3">
      <button type="submit" name="import" value="1" class="btn btn-primary">
        <i class="fas fa-save me-1"></i>Cadastrar selecionados
      </button>
    </div>
  </form>
  <?php endif; ?>
</div>

<?php Html::footer(); ?>
