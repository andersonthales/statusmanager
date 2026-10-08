<?php

/**
 * Status Manager – Página principal de configuração
 */

include('../../../inc/includes.php');
Session::checkRight('config', UPDATE);

Html::header('Status Manager', '', 'config', 'plugins');
Html::displayMessageAfterRedirect();

$isPatchApplied = false;
if (class_exists('PluginStatusmanagerPatcher')) {
    try {
        $isPatchApplied = PluginStatusmanagerPatcher::isApplied();
    } catch (Exception $e) {
        $isPatchApplied = false;
    }
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
      <?php if (!$isPatchApplied): ?>
        <a href="<?php echo $base; ?>/front/patch.php?action=apply"
           class="btn btn-warning"
           onclick="return confirm('Aplicar patches nos arquivos do core GLPI?\nUm backup automático será criado antes de cada modificação.')">
          <i class="fas fa-magic me-1"></i>Aplicar patches no core
        </a>
      <?php else: ?>
        <span class="badge bg-success fs-6 p-2">
          <i class="fas fa-check-circle me-1"></i>Core patcheado
        </span>
        <a href="<?php echo $base; ?>/front/patch.php?action=revert"
           class="btn btn-outline-danger btn-sm"
           onclick="return confirm('Reverter patches? Os backups serão restaurados.')">
          <i class="fas fa-undo me-1"></i>Reverter
        </a>
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
    <strong>Patches ainda não aplicados.</strong>
    Os status cadastrados não aparecerão nos dropdowns até você clicar em
    <em>"Aplicar patches no core"</em>.
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
            <td><code>src/Dashboard/Provider.php</code></td>
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
            <td><code>src/Search.php</code></td>
            <td>
              Oculta a barra de progresso do SLA nos chamados com status pending do plugin,
              igualmente ao comportamento do status nativo "Pendente".
            </td>
          </tr>
        </tbody>
      </table>
      <div class="alert alert-info mb-0">
        <i class="fas fa-shield-alt me-1"></i>
        <strong>Backup automático:</strong> antes de cada modificação, o arquivo original é salvo
        com extensão <code>.statusmanager.bak</code>. Ao desinstalar o plugin, todos os backups
        são restaurados automaticamente.
      </div>
    </div>
  </div>

</div>

<?php Html::footer(); ?>
