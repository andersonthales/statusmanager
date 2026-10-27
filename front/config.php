<?php

/**
 * Status Manager – Página principal de configuração
 */

include('../../../inc/includes.php');
Session::checkRight('config', UPDATE);

Html::header('Status Manager', '', 'config', 'plugins');
Html::displayMessageAfterRedirect();

$isPatchApplied = false;
$patchStatus    = array();
$canWrite       = false;
if (class_exists('PluginStatusmanagerPatcher')) {
    try {
        $patchStatus    = PluginStatusmanagerPatcher::status();
        $isPatchApplied = PluginStatusmanagerPatcher::isApplied();
        $canWrite       = PluginStatusmanagerPatcher::canWrite();
    } catch (Exception $e) {
        $isPatchApplied = false;
    }
}
$anyPatched = false;
foreach ($patchStatus as $info) {
    if ($info['state'] === 'patched') {
        $anyPatched = true;
    }
}

// Status gravados em chamados sem cadastro (ex.: base migrada de um GLPI
// com status criados editando o core)
$unknown = array();
try {
    $unknown = PluginStatusmanagerRegistry::getUnknownTicketStatuses();
} catch (Exception $e) {
    $unknown = array();
}

$statuses = array();
if (class_exists('PluginStatusmanagerStatus')) {
    try {
        $statuses = PluginStatusmanagerStatus::getAll();
    } catch (Exception $e) {
        $statuses = array();
    }
}

$behaviorMap = array(
    'assigned' => array('Em atendimento', 'bg-primary'),
    'pending'  => array('Pendente',        'bg-warning text-dark'),
    'solved'   => array('Solucionado',     'bg-success'),
    'closed'   => array('Fechado',         'bg-secondary'),
);

$base = Plugin::getWebDir('statusmanager');
?>

<div class="container-fluid mt-3">

  <!-- Cabeçalho -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="mb-0">
      <i class="fas fa-tags me-2 text-primary"></i>
      Status Manager
    </h2>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <?php if ($isPatchApplied): ?>
        <span class="badge bg-success fs-6 p-2">
          <i class="fas fa-check-circle me-1"></i>Core patcheado
        </span>
      <?php endif; ?>
      <?php if (!$isPatchApplied && $canWrite): ?>
        <form method="post" action="<?php echo $base; ?>/front/patch.php" class="d-inline"
              onsubmit="return confirm('Aplicar patches nos arquivos do core GLPI?\nUm backup automático será criado antes de cada modificação.')">
          <input type="hidden" name="_glpi_csrf_token" value="<?php echo Session::getNewCSRFToken(); ?>">
          <button type="submit" name="action" value="apply" class="btn btn-warning">
            <i class="fas fa-magic me-1"></i>Aplicar patches no core
          </button>
        </form>
      <?php endif; ?>
      <?php if ($anyPatched && $canWrite): ?>
        <form method="post" action="<?php echo $base; ?>/front/patch.php" class="d-inline"
              onsubmit="return confirm('Reverter patches? Os backups serão restaurados.')">
          <input type="hidden" name="_glpi_csrf_token" value="<?php echo Session::getNewCSRFToken(); ?>">
          <button type="submit" name="action" value="revert" class="btn btn-outline-danger btn-sm">
            <i class="fas fa-undo me-1"></i>Reverter
          </button>
        </form>
      <?php endif; ?>
      <a href="<?php echo $base; ?>/front/native.php" class="btn btn-outline-secondary">
        <i class="fas fa-sliders-h me-1"></i>Gerenciar nativos
      </a>
      <a href="<?php echo $base; ?>/front/status.form.php?id=-1" class="btn btn-success">
        <i class="fas fa-plus me-1"></i>Novo status
      </a>
    </div>
  </div>

  <!-- Aviso patches não aplicados -->
  <?php if (!$isPatchApplied): ?>
  <div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <?php if ($anyPatched): ?>
      <strong>Patches incompletos.</strong>
      Parte dos arquivos do core está sem patch — normalmente depois de atualizar o GLPI.
      Dashboards, SLA ou ícones podem estar errados até aplicar de novo.
    <?php else: ?>
      <strong>Patches ainda não aplicados.</strong>
      Os status cadastrados não aparecerão nos dropdowns até os patches serem aplicados.
    <?php endif; ?>
    <?php if (!$canWrite): ?>
      <br>O servidor web não tem permissão de escrita no <code>src/</code> do GLPI (o recomendado).
      Aplique pelo console, como root, na pasta do GLPI:
      <code>php bin/console plugins:statusmanager:patch apply</code>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Status desconhecidos nos chamados -->
  <?php if (!empty($unknown)): ?>
  <div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
      <i class="fas fa-question-circle me-2"></i>
      <strong><?php echo count($unknown); ?> status sem cadastro</strong> em
      <?php echo array_sum(array_column($unknown, 'count')); ?> chamados
      (chaves <?php echo implode(', ', array_map('intval', array_keys($unknown))); ?>).
      Esses chamados aparecem sem nome de status e ficam fora dos filtros.
    </div>
    <a href="<?php echo $base; ?>/front/import.php" class="btn btn-primary btn-sm">
      <i class="fas fa-file-import me-1"></i>Cadastrar a partir dos chamados
    </a>
  </div>
  <?php endif; ?>

  <!-- Tabela de status -->
  <div class="card">
    <div class="card-header"><strong>Status cadastrados</strong></div>
    <div class="card-body p-0">
      <?php if (empty($statuses)): ?>
        <p class="p-4 text-muted mb-0">
          Nenhum status cadastrado. Clique em "Novo status" para começar.
        </p>
      <?php else: ?>
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:60px">Ordem</th>
            <th style="width:50px">Ícone</th>
            <th>Nome</th>
            <th style="width:110px">Chave ID</th>
            <th>Comportamento</th>
            <th style="width:70px">Ativo</th>
            <th style="width:80px"></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($statuses as $s):
            $color   = htmlspecialchars($s['color'], ENT_QUOTES, 'UTF-8');
            $icon    = htmlspecialchars($s['icon'],  ENT_QUOTES, 'UTF-8');
            $name    = htmlspecialchars($s['name'],  ENT_QUOTES, 'UTF-8');
            $beh     = isset($behaviorMap[$s['behavior']]) ? $behaviorMap[$s['behavior']] : array($s['behavior'], 'bg-dark');
            $editUrl = $base . '/front/status.form.php?id=' . (int)$s['id'];
        ?>
          <tr>
            <td class="text-center"><?php echo (int)$s['rank']; ?></td>
            <td class="text-center">
              <i class="<?php echo $icon; ?>" style="color:<?php echo $color; ?>;font-size:1.4rem;"></i>
            </td>
            <td>
              <strong><?php echo $name; ?></strong>
              <br><small style="color:<?php echo $color; ?>"><?php echo $color; ?></small>
            </td>
            <td><code><?php echo (int)$s['status_key']; ?></code></td>
            <td>
              <span class="badge <?php echo $beh[1]; ?>">
                <?php echo htmlspecialchars($beh[0], ENT_QUOTES, 'UTF-8'); ?>
              </span>
            </td>
            <td>
              <?php echo $s['is_active']
                ? '<span class="badge bg-success">Sim</span>'
                : '<span class="badge bg-secondary">Não</span>'; ?>
            </td>
            <td>
              <a href="<?php echo $editUrl; ?>" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-edit"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Informações sobre os patches -->
  <div class="card mt-4">
    <div class="card-header">
      <i class="fas fa-info-circle me-1"></i>O que o patch automático modifica
    </div>
    <div class="card-body">
      <table class="table table-sm table-bordered mb-3">
        <thead><tr><th>Arquivo do core</th><th>Modificação</th></tr></thead>
        <tbody>
          <tr>
            <td><code>src/Ticket.php</code></td>
            <td>
              <code>getAllStatusArray</code>, <code>getClosedStatusArray</code>,
              <code>getSolvedStatusArray</code> e <code>getProcessStatusArray</code>
              passam a incluir os status do plugin em tempo de execução.
            </td>
          </tr>
          <tr>
            <td><code>src/Dashboard/Provider.php</code> (GLPI 10) ou <code>src/Glpi/Dashboard/Provider.php</code></td>
            <td>
              Adiciona <code>case default</code> em <code>nbTicketsGeneric</code>
              para tratar status numéricos dinâmicos nos dashboards.
            </td>
          </tr>
          <tr>
            <td><code>src/CommonITILObject.php</code></td>
            <td>
              <code>getStatusClass</code>: retorna ícone/cor do plugin para status desconhecidos.<br>
              Pausa e retoma o SLA automaticamente ao entrar/sair de status com
              <em>behavior=pending</em>.
            </td>
          </tr>
          <tr>
            <td><code>src/Search.php</code> (GLPI 10) ou <code>src/Glpi/Search/Provider/SQLProvider.php</code></td>
            <td>
              Oculta a barra de progresso do SLA nos chamados com status pending do plugin,
              igualmente ao comportamento do status nativo "Pendente".
            </td>
          </tr>
        </tbody>
      </table>
      <?php if (!empty($patchStatus)): ?>
      <h4 class="mt-2">Situação nesta instalação</h4>
      <table class="table table-sm table-bordered mb-3">
        <thead><tr><th>Arquivo</th><th>Estado</th><th>Backup</th><th>Gravável pelo servidor web</th></tr></thead>
        <tbody>
        <?php foreach ($patchStatus as $info):
            if ($info['state'] === 'not_found') { continue; }
            $badge = $info['state'] === 'patched'
                ? '<span class="badge bg-success">com patch</span>'
                : '<span class="badge bg-warning text-dark">sem patch</span>';
        ?>
          <tr>
            <td><code><?php echo htmlspecialchars($info['file'], ENT_QUOTES, 'UTF-8'); ?></code></td>
            <td><?php echo $badge; ?></td>
            <td><?php echo $info['backup'] ? 'sim' : 'não'; ?></td>
            <td><?php echo $info['writable'] ? 'sim' : 'não'; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
      <div class="alert alert-info mb-0">
        <i class="fas fa-shield-alt me-1"></i>
        <strong>Backup automático:</strong> antes de cada modificação, o arquivo original é salvo
        com extensão <code>.statusmanager.bak</code>. Ao desinstalar o plugin, todos os backups
        são restaurados automaticamente. Um arquivo só é gravado se todos os trechos dele
        encontraram o ponto esperado e o resultado passou na validação de sintaxe.
        Pelo console: <code>php bin/console plugins:statusmanager:patch status|apply|revert</code>.
      </div>
    </div>
  </div>

</div>

<?php Html::footer(); ?>
