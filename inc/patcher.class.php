<?php

/**
 * ---------------------------------------------------------------------
 * Status Manager – PluginStatusmanagerPatcher
 *
 * Aplica e reverte patches cirúrgicos nos arquivos do core do GLPI,
 * criando backup automático antes de cada modificação.
 *
 * Arquivos patcheados (o caminho varia entre GLPI 10 e 11/12):
 *   1. src/Ticket.php                          – getAllStatusArray, getClosedStatusArray,
 *                                                getSolvedStatusArray, getProcessStatusArray
 *   2. Dashboard/Provider.php                  – nbTicketsGeneric (case default)
 *        GLPI 10:    src/Dashboard/Provider.php
 *        GLPI 11/12: src/Glpi/Dashboard/Provider.php
 *   3. src/CommonITILObject.php                – getStatusClass (ícones), pausa e retomada do SLA
 *   4. Barra de progresso do SLA               – oculta em status pending
 *        GLPI 10:    src/Search.php
 *        GLPI 11/12: src/Glpi/Search/Provider/SQLProvider.php
 *
 * Cada alvo é uma lista de trechos. Cada trecho tem uma âncora mínima — uma
 * linha inteira do core, casada por regex — e o código do plugin entra antes
 * ou depois dela, com a mesma indentação. A âncora precisa aparecer UMA vez
 * (dentro do método, quando há método): zero ou mais de uma conta como
 * ausente. O arquivo só é gravado se TODOS os trechos dele entraram.
 * ---------------------------------------------------------------------
 */
class PluginStatusmanagerPatcher {

    const MARKER = '[StatusManager]';

    /**
     * Caminhos candidatos por alvo, em ordem de tentativa.
     * O primeiro que existir no disco é o usado — assim o mesmo plugin
     * atende GLPI 10, 11 e 12 sem ramificar por versão.
     */
    private static $targets = array(
        'ticket'   => array('/src/Ticket.php'),
        'provider' => array('/src/Glpi/Dashboard/Provider.php', '/src/Dashboard/Provider.php'),
        'itil'     => array('/src/CommonITILObject.php'),
        'search'   => array('/src/Glpi/Search/Provider/SQLProvider.php', '/src/Search.php'),
    );

    /** Relatório da última execução de applyAll()/revertAll(): alvo => status. */
    private static $report = array();

    /** Trechos que não entraram na última execução: alvo => [trecho, ...]. */
    private static $missing = array();

    // -------------------------------------------------------------------------
    // API pública
    // -------------------------------------------------------------------------

    /**
     * Aplica todos os patches.
     *
     * Status possíveis por alvo:
     *   patched        – gravado agora
     *   already        – o arquivo já tem a marca do plugin
     *   would_patch    – (dry-run) todos os trechos entrariam
     *   not_found      – arquivo não existe nesta versão do GLPI
     *   anchor_missing – algum trecho não achou a âncora; arquivo NÃO foi tocado
     *   not_writable   – sem permissão de escrita no arquivo ou no diretório
     *   syntax_error   – o resultado não passou na validação de sintaxe; arquivo NÃO foi tocado
     *   write_failed   – falha ao gravar o backup ou o arquivo
     *
     * @param bool $dryRun Só simula: nada é gravado
     * @return array Relatório alvo => status
     */
    public static function applyAll($dryRun = false) {
        self::$report  = array();
        self::$missing = array();
        foreach (array_keys(self::$targets) as $target) {
            self::$report[$target] = self::patchTarget($target, $dryRun);
        }
        return self::$report;
    }

    /**
     * Relatório da última chamada a applyAll() ou revertAll().
     */
    public static function getReport() {
        return self::$report;
    }

    /**
     * Trechos que não entraram na última chamada a applyAll().
     *
     * @return array alvo => lista de nomes de trecho
     */
    public static function getMissing() {
        return self::$missing;
    }

    /**
     * Reverte todos os patches a partir dos backups.
     *
     * Status possíveis por alvo: reverted, original (sem patch; só o backup
     * velho foi descartado), no_backup (tem patch mas não tem backup),
     * not_found, write_failed.
     *
     * @return array Relatório alvo => status
     */
    public static function revertAll() {
        self::$report = array();
        foreach (array_keys(self::$targets) as $target) {
            $file = self::resolveFile($target);
            if ($file === null) {
                self::$report[$target] = 'not_found';
                continue;
            }
            $bak     = $file . '.statusmanager.bak';
            $patched = strpos(file_get_contents($file), self::MARKER) !== false;
            if (!file_exists($bak)) {
                self::$report[$target] = $patched ? 'no_backup' : 'original';
                continue;
            }
            // Só restaura se o arquivo atual ainda tem o patch. Sem a marca,
            // o arquivo já é original — tipicamente porque o GLPI foi
            // atualizado — e o backup é de uma versão ANTERIOR do GLPI:
            // copiá-lo por cima quebraria a instalação.
            if ($patched) {
                $error = self::writeAtomic($file, file_get_contents($bak), false);
                if ($error !== null) {
                    self::$report[$target] = $error;
                    continue;
                }
                self::$report[$target] = 'reverted';
            } else {
                self::$report[$target] = 'original';
            }
            @unlink($bak);
        }
        return self::$report;
    }

    /**
     * Situação de cada alvo no disco, sem alterar nada.
     *
     * @return array alvo => ['file' => caminho relativo|null, 'state' => patched|original|not_found,
     *                        'backup' => bool, 'writable' => bool]
     */
    public static function status() {
        $result = array();
        foreach (array_keys(self::$targets) as $target) {
            $file = self::resolveFile($target);
            if ($file === null) {
                $result[$target] = array('file' => null, 'state' => 'not_found', 'backup' => false, 'writable' => false);
                continue;
            }
            $result[$target] = array(
                'file'     => substr($file, strlen(self::glpiRoot()) + 1),
                'state'    => strpos(file_get_contents($file), self::MARKER) !== false ? 'patched' : 'original',
                'backup'   => file_exists($file . '.statusmanager.bak'),
                'writable' => self::isWritable($file),
            );
        }
        return $result;
    }

    /**
     * Verifica se TODOS os alvos existentes estão patcheados.
     *
     * Checar só Ticket.php escondia o caso mais comum de quebra: uma
     * atualização do GLPI troca parte do src/ e deixa os demais arquivos
     * sem patch, com o painel ainda dizendo "Core patcheado".
     *
     * @return bool
     */
    public static function isApplied() {
        $found = 0;
        foreach (self::status() as $info) {
            if ($info['state'] === 'not_found') {
                continue;
            }
            if ($info['state'] !== 'patched') {
                return false;
            }
            $found++;
        }
        return $found > 0;
    }

    /**
     * Indica se o processo atual consegue gravar todos os alvos.
     * Quando o src/ é só leitura para o servidor web, os patches
     * são aplicados pelo console (bin/console plugins:statusmanager:patch).
     *
     * @return bool
     */
    public static function canWrite() {
        foreach (self::status() as $info) {
            if ($info['state'] !== 'not_found' && !$info['writable']) {
                return false;
            }
        }
        return true;
    }

    /**
     * Nome amigável de cada alvo, para mensagens.
     */
    public static function getLabels() {
        return array(
            'ticket'   => 'Ticket.php',
            'provider' => 'Dashboard/Provider.php',
            'itil'     => 'CommonITILObject.php',
            'search'   => 'barra de progresso do SLA',
        );
    }

    // -------------------------------------------------------------------------
    // Utilitários privados
    // -------------------------------------------------------------------------

    private static function glpiRoot() {
        return rtrim(GLPI_ROOT, '/');
    }

    /**
     * Resolve o caminho real de um alvo, testando os candidatos na ordem.
     *
     * @param string $target Chave em self::$targets
     * @return string|null   Caminho absoluto, ou null se nenhum candidato existir
     */
    private static function resolveFile($target) {
        if (!isset(self::$targets[$target])) {
            return null;
        }
        foreach (self::$targets[$target] as $rel) {
            $file = self::glpiRoot() . $rel;
            if (file_exists($file)) {
                return $file;
            }
        }
        return null;
    }

    /**
     * A gravação é por arquivo temporário + rename no mesmo diretório,
     * então o diretório também precisa aceitar escrita.
     */
    private static function isWritable($file) {
        return is_writable($file) && is_writable(dirname($file));
    }

    /**
     * Aplica os trechos de um alvo e grava o arquivo, tudo ou nada.
     *
     * @return string Status do alvo (ver applyAll)
     */
    private static function patchTarget($target, $dryRun) {
        $file = self::resolveFile($target);
        if ($file === null) {
            return 'not_found';
        }
        $content = file_get_contents($file);
        if (strpos($content, self::MARKER) !== false) {
            return 'already';
        }

        $new     = $content;
        $missing = array();
        foreach (self::hunks($target) as $name => $hunk) {
            $result = self::applyHunk($new, $hunk);
            if ($result === null) {
                $missing[] = $name;
            } else {
                $new = $result;
            }
        }
        if (!empty($missing)) {
            self::$missing[$target] = $missing;
            return 'anchor_missing';
        }
        if (!self::isValidSyntax($new)) {
            return 'syntax_error';
        }
        if ($dryRun) {
            return 'would_patch';
        }
        if (!self::isWritable($file)) {
            return 'not_writable';
        }

        // O backup é sempre renovado: um .bak que já exista neste ponto
        // (arquivo sem a marca, ou seja, original) sobrou de uma versão
        // anterior do GLPI — a atualização troca src/ mas não apaga os
        // .bak — e não pode ser mantido.
        if (!@copy($file, $file . '.statusmanager.bak')) {
            return 'write_failed';
        }
        $error = self::writeAtomic($file, $new, true);
        return $error === null ? 'patched' : $error;
    }

    /**
     * Grava $content em $file sem nunca deixar o arquivo pela metade:
     * escreve num temporário ao lado, valida e só então troca por rename()
     * (atômico no mesmo sistema de arquivos). Preserva permissões e, quando
     * roda como root (console), o dono original.
     *
     * @return string|null null em caso de sucesso, ou o status de erro
     */
    private static function writeAtomic($file, $content, $validate) {
        if ($validate && !self::isValidSyntax($content)) {
            return 'syntax_error';
        }
        $tmp = $file . '.statusmanager.tmp';
        if (@file_put_contents($tmp, $content) !== strlen($content)) {
            @unlink($tmp);
            return 'write_failed';
        }
        @chmod($tmp, fileperms($file) & 0777);
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            @chown($tmp, fileowner($file));
            @chgrp($tmp, filegroup($file));
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return 'write_failed';
        }
        // Sem isto o PHP pode continuar servindo a versão antiga do arquivo
        // em cache até o próximo revalidate (ou para sempre, se
        // opcache.validate_timestamps=0).
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
        return null;
    }

    /**
     * Valida a sintaxe sem depender de exec()/php -l (costuma estar
     * desabilitado no servidor web): TOKEN_PARSE monta a árvore sintática
     * e lança ParseError se o código não compila.
     */
    private static function isValidSyntax($code) {
        try {
            token_get_all($code, TOKEN_PARSE);
            return true;
        } catch (ParseError $e) {
            return false;
        }
    }

    /**
     * Limites [início, fim] do corpo de um método, ou null se não existir.
     */
    private static function methodBounds($content, $methodSignature) {
        $methodPos = strpos($content, $methodSignature);
        if ($methodPos === false) {
            return null;
        }
        $start = strpos($content, '{', $methodPos);
        if ($start === false) {
            return null;
        }
        $depth = 0;
        $len   = strlen($content);
        for ($i = $start; $i < $len; $i++) {
            if ($content[$i] === '{') {
                $depth++;
            } elseif ($content[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return array($methodPos, $i);
                }
            }
        }
        return null;
    }

    /**
     * Aplica um trecho. Retorna o conteúdo novo, ou null se a âncora não
     * aparecer exatamente uma vez no escopo (ou se o escopo tiver algo que
     * torna o trecho inseguro, via 'unless').
     *
     * Formato do trecho:
     *   method  – assinatura do método que delimita a busca (opcional)
     *   anchor  – regex de UMA linha inteira; o grupo 1 captura a indentação
     *   where   – 'before' ou 'after' da linha da âncora
     *   code    – linhas a inserir, com indentação relativa à da âncora
     *   unless  – regex que, se casar no escopo, aborta o trecho (opcional)
     */
    private static function applyHunk($content, array $hunk) {
        $from = 0;
        $to   = strlen($content) - 1;
        if (!empty($hunk['method'])) {
            $bounds = self::methodBounds($content, $hunk['method']);
            if ($bounds === null) {
                return null;
            }
            list($from, $to) = $bounds;
        }
        $scope = substr($content, $from, $to - $from + 1);

        if (!empty($hunk['unless']) && preg_match($hunk['unless'], $scope)) {
            return null;
        }
        if (preg_match_all($hunk['anchor'], $scope, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }
        $indent = $m[1][0][0];
        $pos    = $m[0][0][1];

        // Tira só a última quebra: uma linha em branco no fim do trecho é
        // intencional (separa o bloco do código seguinte).
        $code = $hunk['code'];
        if (substr($code, -1) === "\n") {
            $code = substr($code, 0, -1);
        }
        $lines = '';
        foreach (explode("\n", $code) as $line) {
            $lines .= ($line === '' ? '' : $indent . $line) . "\n";
        }

        if ($hunk['where'] === 'before') {
            $at = $pos;
        } else {
            $eol = strpos($scope, "\n", $pos);
            if ($eol === false) {
                return null;
            }
            $at = $eol + 1;
        }

        return substr($content, 0, $from + $at) . $lines . substr($content, $from + $at);
    }

    // -------------------------------------------------------------------------
    // Trechos por alvo
    // -------------------------------------------------------------------------

    private static function hunks($target) {
        switch ($target) {
            case 'ticket':
                return self::hunksTicket();
            case 'provider':
                return self::hunksProvider();
            case 'itil':
                return self::hunksCommonITIL();
            case 'search':
                return self::hunksSearch();
        }
        return array();
    }

    // -------------------------------------------------------------------------
    // Patch 1 – Ticket.php
    // Injeta status do plugin em getAllStatusArray, getClosedStatusArray,
    // getSolvedStatusArray e getProcessStatusArray.
    // -------------------------------------------------------------------------
    private static function hunksTicket() {
        $hunks = array();

        $hunks['getAllStatusArray'] = array(
            'method' => 'public static function getAllStatusArray(',
            'anchor' => '/^([ \t]*)if \(\$withmetaforsearch\) \{$/m',
            'where'  => 'before',
            'code'   => <<<'PHP'
// [StatusManager] injeta status do plugin em runtime
if (class_exists('PluginStatusmanagerRegistry')) {
    foreach (PluginStatusmanagerRegistry::getAll() as $csKey => $csRow) {
        $tab[(int)$csKey] = $csRow['name'];
    }
    foreach (array_keys($tab) as $nKey) {
        $nlabel = PluginStatusmanagerRegistry::getNativeLabel($nKey);
        if ($nlabel !== null) {
            $tab[$nKey] = $nlabel;
        }
    }
    foreach (PluginStatusmanagerRegistry::getHiddenNativeKeys() as $hKey) {
        unset($tab[$hKey]);
    }
}

PHP
        );

        // As três listas por comportamento seguem o mesmo molde: o código
        // entra antes do return original, que continua lá como fallback
        // quando o plugin está desativado.
        $groups = array(
            'getClosedStatusArray'  => array('self::CLOSED', 'closed'),
            'getSolvedStatusArray'  => array('self::SOLVED', 'solved'),
            'getProcessStatusArray' => array('self::ASSIGNED, self::PLANNED', 'assigned'),
        );
        foreach ($groups as $method => $group) {
            list($natives, $behavior) = $group;
            $hunks[$method] = array(
                'method' => 'public static function ' . $method . '(',
                'anchor' => '/^([ \t]*)return \[' . preg_quote($natives, '/') . '\];$/m',
                'where'  => 'before',
                'code'   => "// [StatusManager] status do plugin com comportamento '{$behavior}'\n"
                    . "if (class_exists('PluginStatusmanagerRegistry')) {\n"
                    . "    return array_values(array_unique(array_merge([{$natives}], PluginStatusmanagerRegistry::getKeysByBehavior('{$behavior}'))));\n"
                    . "}\n",
            );
        }

        return $hunks;
    }

    // -------------------------------------------------------------------------
    // Patch 2 – Dashboard/Provider.php
    // Adiciona case default em nbTicketsGeneric para status numéricos dinâmicos.
    // -------------------------------------------------------------------------
    private static function hunksProvider() {
        return array(
            'nbTicketsGeneric' => array(
                'method' => 'public static function nbTicketsGeneric(',
                // A chave que fecha o switch, logo antes da montagem dos filtros:
                //   GLPI 10.0.x antigo -> $search_criteria['criteria'] = self::getSearchFiltersCriteria
                //   GLPI 10.0.28/11/12 -> $filter_criteria = self::getSearchFiltersCriteria
                'anchor' => '/^([ \t]*)\}\n(?=\s*(?:\$filter_criteria|\$search_criteria\[\'criteria\'\]) = self::getSearchFiltersCriteria)/m',
                'where'  => 'before',
                // Um segundo `default:` no mesmo switch é erro de compilação
                // que a validação de sintaxe não pega. Se o core passar a ter
                // um, o trecho não entra.
                'unless' => '/^\s*default\s*:/m',
                'code'   => <<<'PHP'

    // [StatusManager] status numéricos dinâmicos do plugin
    default:
        if (class_exists('PluginStatusmanagerRegistry') && is_numeric($case)) {
            $csKey = (int)$case;
            $csRow = PluginStatusmanagerRegistry::getRow($csKey);
            if ($csRow) {
                $status = $csKey;
                $params['icon']  = htmlspecialchars($csRow['icon']);
                $params['label'] = htmlspecialchars($csRow['name']);
                $search_criteria = [['field' => 12, 'searchtype' => 'equals', 'value' => $status]];
                $query_criteria  = array_merge_recursive($query_criteria, ['WHERE' => ["$table.status" => $status]]);
            }
        }
        break;
PHP
            ),
        );
    }

    // -------------------------------------------------------------------------
    // Patch 3 – CommonITILObject.php
    // a) getStatusClass: ícone do plugin para status do plugin e nativos com ícone próprio
    // b) Pausa SLA: inclui status pending do plugin na condição de begin_waiting_date
    // c) Retomada SLA: inclui status pending do plugin na saída da espera
    // -------------------------------------------------------------------------
    private static function hunksCommonITIL() {
        return array(
            // A âncora é o return final, que muda de forma entre versões:
            //   GLPI 10:    return $class == null ? '' : 'itilstatus ' . ($solid ? 'fas fa-' ...
            //   GLPI 11/12: return $class === null ? '' : 'itilstatus ti ti-' . $class ...
            // Casar a linha inteira do 11 deixava o GLPI 10 sem ícone.
            'getStatusClass' => array(
                'method' => 'public static function getStatusClass(',
                'anchor' => '/^([ \t]*)return \$class ===? null\b.*$/m',
                'where'  => 'before',
                'code'   => <<<'PHP'
// [StatusManager] ícone dos status do plugin e dos nativos com ícone próprio
if (class_exists('PluginStatusmanagerRegistry')) {
    $csRow = PluginStatusmanagerRegistry::getRow((int)$status);
    if ($csRow && ($class === null || $class === '')) {
        return 'itilstatus ' . PluginStatusmanagerRegistry::iconClass($csRow['icon']) . ' cs-status-' . (int)$status;
    }
    $nvIcon = PluginStatusmanagerRegistry::getNativeIcon((int)$status);
    if ($nvIcon !== null) {
        return 'itilstatus ' . PluginStatusmanagerRegistry::iconClass($nvIcon) . ' cs-status-' . (int)$status;
    }
}

PHP
            ),
            // "Set begin waiting date if needed" (10/11) / "handle levels" (12)
            'pausaSLA' => array(
                'anchor' => '/^([ \t]*)&& \(\(\$this->fields\[\'status\'\] == self::WAITING\)$/m',
                'where'  => 'after',
                'code'   => "  // [StatusManager] pausa SLA para behavior=pending do plugin\n"
                    . "  || (class_exists('PluginStatusmanagerRegistry') && in_array(\$this->fields['status'], PluginStatusmanagerRegistry::getKeysByBehavior('pending')))\n",
            ),
            // "Manage come back to waiting state"
            'retomadaSLA' => array(
                'anchor' => '/^([ \t]*)\$this->oldvalues\[\'status\'\] == self::WAITING$/m',
                'where'  => 'after',
                'code'   => "// [StatusManager] reset ao sair de pending do plugin\n"
                    . "|| (class_exists('PluginStatusmanagerRegistry') && in_array(\$this->oldvalues['status'], PluginStatusmanagerRegistry::getKeysByBehavior('pending')))\n",
            ),
        );
    }

    // -------------------------------------------------------------------------
    // Patch 4 – Search.php / SQLProvider.php
    // Oculta barra de progresso do SLA quando o status é pending do plugin.
    // A âncora é só a linha da comparação com WAITING: o bloco em volta
    // mudou no GLPI 12 (saiu internal_time_to_resolve) e casar o bloco
    // inteiro deixava o patch de fora.
    // -------------------------------------------------------------------------
    private static function hunksSearch() {
        return array(
            'barraSLA' => array(
                'anchor' => '/^([ \t]*)\$data\[\$ID\]\[0\]\[\'status\'\] == CommonITILObject::WAITING$/m',
                'where'  => 'after',
                'code'   => "// [StatusManager] no progress bar para status pending do plugin\n"
                    . "|| (class_exists('PluginStatusmanagerRegistry') && in_array(\$data[\$ID][0]['status'], PluginStatusmanagerRegistry::getKeysByBehavior('pending')))\n",
            ),
        );
    }
}
