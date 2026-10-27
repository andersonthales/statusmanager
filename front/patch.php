<?php

/**
 * Status Manager – Controlador de patches (aplicar / reverter)
 */

include('../../../inc/includes.php');
Session::checkRight('config', UPDATE);

$base = Plugin::getWebDir('statusmanager');

// Só aceita POST: o GLPI valida o token CSRF apenas em POST, e esta ação
// reescreve arquivos do core. Via GET, bastaria um link ou <img> numa página
// qualquer, aberta por um administrador logado, para aplicar ou reverter.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Html::redirect($base . '/front/config.php');
}
$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    $labels  = PluginStatusmanagerPatcher::getLabels();
    $console = 'php bin/console plugins:statusmanager:patch ' . ($action === 'revert' ? 'revert' : 'apply');

    if ($action === 'apply') {
        $report  = PluginStatusmanagerPatcher::applyAll();
        $missing = PluginStatusmanagerPatcher::getMissing();

        $problemas = array();
        foreach ($report as $alvo => $status) {
            switch ($status) {
                case 'not_found':
                    // Normal para alvos que não existem nesta versão; só é
                    // problema se nenhum outro arquivo foi encontrado.
                    break;
                case 'anchor_missing':
                    $problemas[] = $labels[$alvo] . ': trecho esperado não encontrado (o core mudou) em '
                        . implode(', ', $missing[$alvo]) . ' — arquivo não foi alterado';
                    break;
                case 'not_writable':
                    $problemas[] = $labels[$alvo] . ': sem permissão de escrita para o servidor web';
                    break;
                case 'syntax_error':
                    $problemas[] = $labels[$alvo] . ': o resultado não passou na validação de sintaxe — arquivo não foi alterado';
                    break;
                case 'write_failed':
                    $problemas[] = $labels[$alvo] . ': falha ao gravar';
                    break;
            }
        }
        if (!in_array('patched', $report, true) && !in_array('already', $report, true) && empty($problemas)) {
            $problemas[] = 'nenhum arquivo do core foi encontrado nesta versão do GLPI';
        }

        // GLPI 11: garante que os status do plugin entrem na lista branca
        // dos templates de chamado, senão o formulário os filtra em silêncio.
        PluginStatusmanagerRegistry::syncTemplateAllowedStatuses();

        if (empty($problemas)) {
            Session::addMessageAfterRedirect(
                'Patches aplicados com sucesso! Os status personalizados agora aparecem em todo o GLPI.',
                false, INFO
            );
        } else {
            // Nunca declarar sucesso quando algum patch não entrou: um patch
            // silenciosamente ignorado quebra dashboard/SLA sem aviso nenhum.
            $dica = in_array('not_writable', $report, true)
                ? ' | Com o src/ só leitura, aplique pelo console, como root: ' . $console
                : '';
            Session::addMessageAfterRedirect(
                'Alguns patches NÃO foram aplicados: ' . implode(' | ', $problemas) . $dica,
                false, ERROR
            );
        }
    } elseif ($action === 'revert') {
        $report = PluginStatusmanagerPatcher::revertAll();

        $problemas = array();
        foreach ($report as $alvo => $status) {
            if ($status === 'no_backup') {
                $problemas[] = $labels[$alvo] . ': tem patch mas não tem backup — reinstale o arquivo original do GLPI';
            } elseif ($status === 'write_failed') {
                $problemas[] = $labels[$alvo] . ': falha ao gravar (sem permissão?)';
            }
        }
        if (empty($problemas)) {
            Session::addMessageAfterRedirect(
                'Patches revertidos. Arquivos originais restaurados dos backups.',
                false, INFO
            );
        } else {
            Session::addMessageAfterRedirect(
                'A reversão não foi completa: ' . implode(' | ', $problemas) . ' | Pelo console: ' . $console,
                false, ERROR
            );
        }
    }
} catch (Throwable $e) {
    Session::addMessageAfterRedirect('Erro ao processar patches: ' . $e->getMessage(), false, ERROR);
}

Html::redirect($base . '/front/config.php');
