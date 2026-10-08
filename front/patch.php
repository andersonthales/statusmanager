<?php

/**
 * Status Manager – Controlador de patches (aplicar / reverter)
 */

include('../../../inc/includes.php');
Session::checkRight('config', UPDATE);

$action = isset($_GET['action']) ? $_GET['action'] : '';
$base   = Plugin::getWebDir('statusmanager');

try {
    if ($action === 'apply') {
        $report = PluginStatusmanagerPatcher::applyAll();

        $labels = array(
            'ticket'   => 'Ticket.php',
            'provider' => 'Dashboard/Provider.php',
            'itil'     => 'CommonITILObject.php',
            'search'   => 'barra de progresso do SLA',
        );
        $problemas = array();
        foreach ($report as $alvo => $status) {
            if ($status === 'not_found') {
                $problemas[] = $labels[$alvo] . ': arquivo não encontrado nesta versão do GLPI';
            } elseif ($status === 'anchor_missing') {
                $problemas[] = $labels[$alvo] . ': trecho esperado não encontrado (o core mudou)';
            }
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
            Session::addMessageAfterRedirect(
                'Alguns patches NÃO foram aplicados: ' . implode(' | ', $problemas),
                false, ERROR
            );
        }
    } elseif ($action === 'revert') {
        PluginStatusmanagerPatcher::revertAll();
        Session::addMessageAfterRedirect(
            'Patches revertidos. Arquivos originais restaurados dos backups.',
            false, INFO
        );
    }
} catch (Throwable $e) {
    Session::addMessageAfterRedirect('Erro ao processar patches: ' . $e->getMessage(), false, ERROR);
}

Html::redirect($base . '/front/config.php');
