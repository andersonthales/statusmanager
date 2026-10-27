<?php

/**
 * ---------------------------------------------------------------------
 * Status Manager – PluginStatusmanagerPatchCommand
 *
 * Aplica, reverte e confere os patches pelo console do GLPI:
 *
 *   php bin/console plugins:statusmanager:patch status
 *   php bin/console plugins:statusmanager:patch apply [--dry-run]
 *   php bin/console plugins:statusmanager:patch revert
 *
 * Rodando como root, o src/ do GLPI pode ficar só leitura para o
 * servidor web — a tela do plugin deixa de precisar gravar no core.
 * Também serve para reaplicar logo depois de atualizar o GLPI.
 *
 * Código de saída: 0 se tudo ficou como pedido, 1 se algum alvo falhou.
 * ---------------------------------------------------------------------
 */

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PluginStatusmanagerPatchCommand extends Command {

    protected function configure(): void {
        $this->setName('plugins:statusmanager:patch');
        $this->setDescription('Aplica, reverte ou mostra os patches do Status Manager no core do GLPI');
        $this->addArgument('action', InputArgument::REQUIRED, 'status | apply | revert');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Com apply: só confere se todos os trechos entrariam, sem gravar');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $action = $input->getArgument('action');
        $labels = PluginStatusmanagerPatcher::getLabels();

        switch ($action) {
            case 'status':
                $ok = true;
                foreach (PluginStatusmanagerPatcher::status() as $target => $info) {
                    $output->writeln(sprintf(
                        '  %-28s %-10s %s%s%s',
                        $labels[$target],
                        $info['state'],
                        $info['file'] !== null ? $info['file'] : '-',
                        $info['backup'] ? '  [backup]' : '',
                        ($info['state'] !== 'not_found' && !$info['writable']) ? '  [sem escrita para este usuário]' : ''
                    ));
                    if ($info['state'] === 'original') {
                        $ok = false;
                    }
                }
                $output->writeln($ok ? 'Todos os alvos estão patcheados.' : 'Há alvos SEM patch.');
                return $ok ? 0 : 1;

            case 'apply':
                $dryRun = (bool)$input->getOption('dry-run');
                $report = PluginStatusmanagerPatcher::applyAll($dryRun);
                $missing = PluginStatusmanagerPatcher::getMissing();
                $ok = true;
                foreach ($report as $target => $status) {
                    $extra = isset($missing[$target]) ? ' (trechos: ' . implode(', ', $missing[$target]) . ')' : '';
                    $output->writeln(sprintf('  %-28s %s%s', $labels[$target], $status, $extra));
                    if (!in_array($status, array('patched', 'already', 'would_patch', 'not_found'), true)) {
                        $ok = false;
                    }
                }
                if (!$dryRun) {
                    // GLPI 11: os status do plugin precisam estar na lista
                    // branca dos templates, senão o formulário os filtra.
                    PluginStatusmanagerRegistry::syncTemplateAllowedStatuses();
                    $output->writeln('O PHP do servidor web pode levar até opcache.revalidate_freq segundos para ver os arquivos novos.');
                }
                return $ok ? 0 : 1;

            case 'revert':
                $ok = true;
                foreach (PluginStatusmanagerPatcher::revertAll() as $target => $status) {
                    $output->writeln(sprintf('  %-28s %s', $labels[$target], $status));
                    if (in_array($status, array('no_backup', 'write_failed'), true)) {
                        $ok = false;
                    }
                }
                return $ok ? 0 : 1;
        }

        $output->writeln('<error>Ação desconhecida: use status, apply ou revert.</error>');
        return 1;
    }
}
