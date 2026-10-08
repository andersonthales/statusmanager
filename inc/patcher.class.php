<?php

/**
 * ---------------------------------------------------------------------
 * Status Manager – PluginStatusmanagerPatcher
 *
 * Aplica e reverte patches cirúrgicos nos arquivos do core do GLPI,
 * criando backup automático antes de cada modificação.
 *
 * Arquivos patcheados (o caminho varia entre GLPI 10 e 11):
 *   1. src/Ticket.php                          – getAllStatusArray, getClosedStatusArray,
 *                                                getSolvedStatusArray, getProcessStatusArray
 *   2. Dashboard/Provider.php                  – nbTicketsGeneric (default case)
 *        GLPI 10: src/Dashboard/Provider.php
 *        GLPI 11: src/Glpi/Dashboard/Provider.php
 *   3. src/CommonITILObject.php                – getStatusClass (ícones), pausa SLA
 *   4. Barra de progresso do SLA               – oculta em status pending
 *        GLPI 10: src/Search.php
 *        GLPI 11: src/Glpi/Search/Provider/SQLProvider.php
 * ---------------------------------------------------------------------
 */
class PluginStatusmanagerPatcher {

    /**
     * Caminhos candidatos por alvo, em ordem de tentativa.
     * O primeiro que existir no disco é o usado — assim o mesmo plugin
     * atende GLPI 10 e 11 sem ramificar por versão.
     */
    private static $targets = array(
        'ticket'   => array('/src/Ticket.php'),
        'provider' => array('/src/Glpi/Dashboard/Provider.php', '/src/Dashboard/Provider.php'),
        'itil'     => array('/src/CommonITILObject.php'),
        'search'   => array('/src/Glpi/Search/Provider/SQLProvider.php', '/src/Search.php'),
    );

    /** Relatório da última execução de applyAll(). */
    private static $report = array();

    // -------------------------------------------------------------------------
    // API pública
    // -------------------------------------------------------------------------

    /**
     * Aplica todos os patches.
     *
     * @return array Relatório alvo => status ('patched', 'already', 'not_found', 'anchor_missing')
     */
    public static function applyAll() {
        self::$report = array();
        self::patchTicket();
        self::patchProvider();
        self::patchCommonITIL();
        self::patchSearch();
        return self::$report;
    }

    /**
     * Relatório da última chamada a applyAll().
     */
    public static function getReport() {
        return self::$report;
    }

    public static function revertAll() {
        foreach (array_keys(self::$targets) as $target) {
            $file = self::resolveFile($target);
            if ($file === null) {
                continue;
            }
            $bak = $file . '.statusmanager.bak';
            if (!file_exists($bak)) {
                continue;
            }
            // Só restaura se o arquivo atual ainda tem o patch. Sem a marca,
            // o arquivo já é original — tipicamente porque o GLPI foi
            // atualizado — e o backup é de uma versão ANTERIOR do GLPI:
            // copiá-lo por cima quebraria a instalação.
            if (strpos(file_get_contents($file), '[StatusManager]') !== false) {
                copy($bak, $file);
            }
            unlink($bak);
        }
    }

    /**
     * Verifica se os patches já foram aplicados (checa Ticket.php).
     *
     * @return bool
     */
    public static function isApplied() {
        $file = self::glpiRoot() . '/src/Ticket.php';
        if (!file_exists($file)) {
            return false;
        }
        return strpos(file_get_contents($file), '[StatusManager]') !== false;
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
     * Faz backup do arquivo antes de aplicar o patch.
     *
     * Só é chamado quando o arquivo NÃO tem a marca [StatusManager], ou seja,
     * está original. Por isso o backup é sempre renovado: um .bak que já
     * exista nesse ponto sobrou de uma versão anterior do GLPI (a atualização
     * troca src/ mas não apaga os .bak) e não pode ser mantido.
     */
    private static function backup($file) {
        copy($file, $file . '.statusmanager.bak');
    }

    /**
     * Substitui $search por $replace dentro de um método específico,
     * identificado pela assinatura $methodSignature.
     * Evita substituições acidentais em outros métodos homônimos.
     *
     * @param string $content         Conteúdo completo do arquivo
     * @param string $methodSignature Assinatura do método (ex: 'public static function foo(')
     * @param string $search          Trecho a substituir
     * @param string $replace         Novo trecho
     * @return string                 Conteúdo modificado
     */
    private static function replaceInMethod($content, $methodSignature, $search, $replace) {
        $methodPos = strpos($content, $methodSignature);
        if ($methodPos === false) {
            return $content;
        }

        $start = strpos($content, '{', $methodPos);
        $depth = 0;
        $end   = $start;
        $len   = strlen($content);

        for ($i = $start; $i < $len; $i++) {
            if ($content[$i] === '{') {
                $depth++;
            } elseif ($content[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }

        $before = substr($content, 0, $methodPos);
        $method = substr($content, $methodPos, $end - $methodPos + 1);
        $after  = substr($content, $end + 1);

        return $before . str_replace($search, $replace, $method) . $after;
    }

    // -------------------------------------------------------------------------
    // Patch 1 – Ticket.php
    // Injeta status do plugin em getAllStatusArray, getClosedStatusArray,
    // getSolvedStatusArray e getProcessStatusArray.
    // -------------------------------------------------------------------------
    private static function patchTicket() {
        $file = self::resolveFile('ticket');
        if ($file === null) {
            self::$report['ticket'] = 'not_found';
            return;
        }
        $content = file_get_contents($file);
        if (strpos($content, '[StatusManager]') !== false) {
            self::$report['ticket'] = 'already';
            return;
        }
        self::backup($file);
        $original = $content;

        // getAllStatusArray: injeta custom + renomeia + oculta nativos
        $content = self::replaceInMethod(
            $content,
            'public static function getAllStatusArray(',
            'if ($withmetaforsearch) {',
            '// [StatusManager] injeta status do plugin em runtime' . "\n"
            . '        if (class_exists(\'PluginStatusmanagerRegistry\')) {' . "\n"
            . '            foreach (PluginStatusmanagerRegistry::getAll() as $csKey => $csRow) {' . "\n"
            . '                $tab[(int)$csKey] = $csRow[\'name\'];' . "\n"
            . '            }' . "\n"
            . '            foreach (array_keys($tab) as $nKey) {' . "\n"
            . '                $nlabel = PluginStatusmanagerRegistry::getNativeLabel($nKey);' . "\n"
            . '                if ($nlabel !== null) { $tab[$nKey] = $nlabel; }' . "\n"
            . '            }' . "\n"
            . '            foreach (PluginStatusmanagerRegistry::getHiddenNativeKeys() as $hKey) {' . "\n"
            . '                unset($tab[$hKey]);' . "\n"
            . '            }' . "\n"
            . '        }' . "\n\n"
            . '        if ($withmetaforsearch) {'
        );

        // getClosedStatusArray
        $content = self::replaceInMethod(
            $content,
            'public static function getClosedStatusArray(',
            'return [self::CLOSED];',
            '// [StatusManager]' . "\n"
            . '        $extra = class_exists(\'PluginStatusmanagerRegistry\') ? PluginStatusmanagerRegistry::getKeysByBehavior(\'closed\') : [];' . "\n"
            . '        return array_unique(array_merge([self::CLOSED], $extra));'
        );

        // getSolvedStatusArray
        $content = self::replaceInMethod(
            $content,
            'public static function getSolvedStatusArray(',
            'return [self::SOLVED];',
            '// [StatusManager]' . "\n"
            . '        $extra = class_exists(\'PluginStatusmanagerRegistry\') ? PluginStatusmanagerRegistry::getKeysByBehavior(\'solved\') : [];' . "\n"
            . '        return array_unique(array_merge([self::SOLVED], $extra));'
        );

        // getProcessStatusArray
        $content = self::replaceInMethod(
            $content,
            'public static function getProcessStatusArray(',
            'return [self::ASSIGNED, self::PLANNED];',
            '// [StatusManager]' . "\n"
            . '        $extra = class_exists(\'PluginStatusmanagerRegistry\') ? PluginStatusmanagerRegistry::getKeysByBehavior(\'assigned\') : [];' . "\n"
            . '        return array_unique(array_merge([self::ASSIGNED, self::PLANNED], $extra));'
        );

        self::$report['ticket'] = ($content === $original) ? 'anchor_missing' : 'patched';
        file_put_contents($file, $content);
    }

    // -------------------------------------------------------------------------
    // Patch 2 – Dashboard/Provider.php
    // Adiciona case default em nbTicketsGeneric para status numéricos dinâmicos.
    // -------------------------------------------------------------------------
    private static function patchProvider() {
        $file = self::resolveFile('provider');
        if ($file === null) {
            self::$report['provider'] = 'not_found';
            return;
        }
        $content = file_get_contents($file);
        if (strpos($content, '[StatusManager]') !== false) {
            self::$report['provider'] = 'already';
            return;
        }
        self::backup($file);
        $original = $content;

        // O corpo do case default é igual nas duas versões; o que muda é a
        // linha logo após o fechamento do switch:
        //   GLPI 10 -> $search_criteria['criteria'] = self::getSearchFiltersCriteria
        //   GLPI 11 -> $filter_criteria = self::getSearchFiltersCriteria
        $tails = array(
            "        \$filter_criteria = self::getSearchFiltersCriteria",          // GLPI 11
            "        \$search_criteria['criteria'] = self::getSearchFiltersCriteria", // GLPI 10
        );

        $defaultCase = "                break;\n\n"
            . "            // [StatusManager] status numéricos dinâmicos do plugin\n"
            . "            default:\n"
            . "                if (class_exists('PluginStatusmanagerRegistry') && is_numeric(\$case)) {\n"
            . "                    \$csKey = (int)\$case;\n"
            . "                    \$csRow = PluginStatusmanagerRegistry::getRow(\$csKey);\n"
            . "                    if (\$csRow) {\n"
            . "                        \$status = \$csKey;\n"
            . "                        \$params['icon']  = htmlspecialchars(\$csRow['icon']);\n"
            . "                        \$params['label'] = htmlspecialchars(\$csRow['name']);\n"
            . "                        \$search_criteria = [['field' => 12, 'searchtype' => 'equals', 'value' => \$status]];\n"
            . "                        \$query_criteria  = array_merge_recursive(\$query_criteria, ['WHERE' => [\"\$table.status\" => \$status]]);\n"
            . "                    }\n"
            . "                }\n"
            . "                break;\n"
            . "        }\n\n";

        foreach ($tails as $tail) {
            $search  = "                break;\n        }\n\n" . $tail;
            $replace = $defaultCase . $tail;

            $candidate = self::replaceInMethod($content, 'public static function nbTicketsGeneric(', $search, $replace);
            if ($candidate !== $content) {
                $content = $candidate;
                break;
            }
        }

        self::$report['provider'] = ($content === $original) ? 'anchor_missing' : 'patched';
        file_put_contents($file, $content);
    }

    // -------------------------------------------------------------------------
    // Patch 3 – CommonITILObject.php
    // a) getStatusClass: retorna ícone do plugin para status desconhecidos
    // b) Pausa SLA: expande condição de begin_waiting_date para status pending
    // c) Reset SLA: expande condição de reset ao sair de status pending
    // -------------------------------------------------------------------------
    private static function patchCommonITIL() {
        $file = self::resolveFile('itil');
        if ($file === null) {
            self::$report['itil'] = 'not_found';
            return;
        }
        $content = file_get_contents($file);
        if (strpos($content, '[StatusManager]') !== false) {
            self::$report['itil'] = 'already';
            return;
        }
        self::backup($file);
        $original = $content;

        // (a) getStatusClass – ícone dinâmico (GLPI 11: match + ti ti-)
        $search  = "        return \$class === null ? '' : 'itilstatus ti ti-' . \$class . \" \" . static::getStatusKey(\$status);";
        // Atenção à condição: no GLPI 10 o match/switch deixava $class como
        // string vazia para status desconhecido; no 11 o `default` devolve
        // null. Testar só por '' faz este ramo virar código morto no 11 —
        // o status existe, grava, aparece na lista, e sai sem ícone.
        $replace = "        // [StatusManager] ícone dinâmico do plugin\n"
                 . "        if ((\$class === '' || \$class === null) && class_exists('PluginStatusmanagerRegistry')) {\n"
                 . "            \$csRow = PluginStatusmanagerRegistry::getRow((int)\$status);\n"
                 . "            if (\$csRow) {\n"
                 . "                return 'itilstatus ti ' . \$csRow['icon'] . ' cs-status-' . (int)\$status;\n"
                 . "            }\n"
                 . "        }\n"
                 . "        // [StatusManager] ícone customizado de status NATIVO rebatizado\n"
                 . "        if (class_exists('PluginStatusmanagerRegistry')) {\n"
                 . "            \$nvIcon = PluginStatusmanagerRegistry::getNativeIcon((int)\$status);\n"
                 . "            if (\$nvIcon !== null) {\n"
                 . "                return 'itilstatus ti ' . \$nvIcon . ' cs-status-' . (int)\$status;\n"
                 . "            }\n"
                 . "        }\n"
                 . "        return \$class === null ? '' : 'itilstatus ti ti-' . \$class . \" \" . static::getStatusKey(\$status);";
        $content = self::replaceInMethod($content, 'public static function getStatusClass(', $search, $replace);

        // (b) Pausa SLA – "Set begin waiting date if needed"
        // Tenta os dois formatos conhecidos do bloco
        $patternsSet = array(
            "((\$key = array_search('status', \$this->updates)) !== false)\n"
            . "            &&\n"
            . "            (\n"
            . "                (\$this->fields['status'] == self::WAITING)",
            "((\$key = array_search('status', \$this->updates)) !== false)\n"
            . "            && ((\$this->fields['status'] == self::WAITING)",
        );
        $injectPending = "\n"
            . "              // [StatusManager] pausa SLA para behavior=pending do plugin\n"
            . "              || (class_exists('PluginStatusmanagerRegistry')\n"
            . "                  && in_array(\$this->fields['status'], PluginStatusmanagerRegistry::getKeysByBehavior('pending')))";

        $patched = false;
        foreach ($patternsSet as $pat) {
            if (strpos($content, $pat) !== false) {
                $content = str_replace($pat, $pat . $injectPending, $content);
                $patched = true;
                break;
            }
        }
        // Fallback: substitui apenas a linha-chave
        if (!$patched) {
            $content = str_replace(
                "(\$this->fields['status'] == self::WAITING)\n              || in_array",
                "(\$this->fields['status'] == self::WAITING)\n"
                . "              // [StatusManager] pausa SLA para behavior=pending do plugin\n"
                . "              || (class_exists('PluginStatusmanagerRegistry') && in_array(\$this->fields['status'], PluginStatusmanagerRegistry::getKeysByBehavior('pending')))\n"
                . "              || in_array",
                $content
            );
        }

        // (c) Reset SLA – ao sair de status pending
        // Tenta com || no final (formato mais comum)
        $r1 = str_replace(
            "\$this->oldvalues['status'] == self::WAITING || \n",
            "\$this->oldvalues['status'] == self::WAITING || \n"
            . "            // [StatusManager] reset ao sair de pending do plugin\n"
            . "            (class_exists('PluginStatusmanagerRegistry') && in_array(\$this->oldvalues['status'], PluginStatusmanagerRegistry::getKeysByBehavior('pending'))) || \n",
            $content
        );
        if ($r1 !== $content) {
            $content = $r1;
        } else {
            // Fallback sem trailing ||
            $content = str_replace(
                "\$this->oldvalues['status'] == self::WAITING\n",
                "\$this->oldvalues['status'] == self::WAITING\n"
                . "            // [StatusManager] reset ao sair de pending do plugin\n"
                . "            || (class_exists('PluginStatusmanagerRegistry') && in_array(\$this->oldvalues['status'], PluginStatusmanagerRegistry::getKeysByBehavior('pending')))\n",
                $content
            );
        }

        self::$report['itil'] = ($content === $original) ? 'anchor_missing' : 'patched';
        file_put_contents($file, $content);
    }

    // -------------------------------------------------------------------------
    // Patch 4 – Search.php
    // Oculta barra de progresso do SLA quando o status é pending do plugin.
    // -------------------------------------------------------------------------
    private static function patchSearch() {
        $file = self::resolveFile('search');
        if ($file === null) {
            self::$report['search'] = 'not_found';
            return;
        }
        $content = file_get_contents($file);
        if (strpos($content, '[StatusManager] no progress bar') !== false) {
            self::$report['search'] = 'already';
            return;
        }
        self::backup($file);
        $original = $content;

        $search  = "                        if (\n"
                 . "                            \$data[\$ID][0]['status'] == CommonITILObject::WAITING\n"
                 . "                        ) {\n"
                 . "                            // No due date in waiting status for TTRs\n"
                 . "                            if (\n"
                 . "                                \$table . '.' . \$field == \"glpi_tickets.time_to_resolve\"\n"
                 . "                                || \$table . '.' . \$field == \"glpi_tickets.internal_time_to_resolve\"\n"
                 . "                            ) {\n"
                 . "                                return '';\n"
                 . "                            } else {\n"
                 . "                                \$color = '#AAAAAA';\n"
                 . "                            }\n"
                 . "                        }";

        $replace = "                        if (\n"
                 . "                            \$data[\$ID][0]['status'] == CommonITILObject::WAITING\n"
                 . "                            // [StatusManager] no progress bar para status pending do plugin\n"
                 . "                            || (class_exists('PluginStatusmanagerRegistry')\n"
                 . "                                && in_array(\$data[\$ID][0]['status'], PluginStatusmanagerRegistry::getKeysByBehavior('pending')))\n"
                 . "                        ) {\n"
                 . "                            // No due date in waiting status for TTRs\n"
                 . "                            if (\n"
                 . "                                \$table . '.' . \$field == \"glpi_tickets.time_to_resolve\"\n"
                 . "                                || \$table . '.' . \$field == \"glpi_tickets.internal_time_to_resolve\"\n"
                 . "                            ) {\n"
                 . "                                return '';\n"
                 . "                            } else {\n"
                 . "                                \$color = '#AAAAAA';\n"
                 . "                            }\n"
                 . "                        }";

        $content = str_replace($search, $replace, $content);

        self::$report['search'] = ($content === $original) ? 'anchor_missing' : 'patched';
        file_put_contents($file, $content);
    }
}
