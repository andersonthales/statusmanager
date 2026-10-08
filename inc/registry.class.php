<?php

/**
 * ---------------------------------------------------------------------
 * Status Manager – PluginStatusmanagerRegistry
 *
 * Cache em memória dos status personalizados e overrides dos nativos.
 * Compatível com PHP 7.4+ / GLPI 10.
 * ---------------------------------------------------------------------
 */
class PluginStatusmanagerRegistry {

    /** @var array<int, array> Status personalizados ativos, indexados por status_key */
    private static $statuses = array();

    /** @var array<int, array> Overrides dos status nativos, indexados por status_key */
    private static $nativeOverrides = array();

    /** @var bool Indica se o cache já foi populado */
    private static $loaded = false;

    // -------------------------------------------------------------------------
    // Carregamento / Invalidação de cache
    // -------------------------------------------------------------------------

    /**
     * Carrega os dados do banco de dados para o cache em memória (lazy load).
     */
    public static function load() {
        if (self::$loaded) {
            return;
        }
        global $DB;

        // Status personalizados ativos
        try {
            $iter = $DB->request(array(
                'FROM'  => 'glpi_plugin_statusmanager_statuses',
                'WHERE' => array('is_active' => 1),
                'ORDER' => 'rank ASC',
            ));
            foreach ($iter as $row) {
                self::$statuses[(int)$row['status_key']] = $row;
            }
        } catch (Exception $e) {
            // Silencia erro; tabela pode ainda não existir em ambiente de testes
        }

        // Overrides dos status nativos
        try {
            if ($DB->tableExists('glpi_plugin_statusmanager_native_overrides')) {
                $iter = $DB->request(array('FROM' => 'glpi_plugin_statusmanager_native_overrides'));
                foreach ($iter as $row) {
                    self::$nativeOverrides[(int)$row['status_key']] = $row;
                }
            }
        } catch (Exception $e) {
            // Silencia erro
        }

        self::$loaded = true;
    }

    /**
     * Invalida o cache e recarrega do banco.
     * Chamado após salvar/remover status ou alterar overrides.
     */
    public static function reload() {
        self::$loaded        = false;
        self::$statuses      = array();
        self::$nativeOverrides = array();
        self::load();
    }

    // -------------------------------------------------------------------------
    // Consultas
    // -------------------------------------------------------------------------

    /**
     * Retorna todos os status personalizados ativos, indexados por status_key.
     *
     * @return array<int, array>
     */
    public static function getAll() {
        self::load();
        return self::$statuses;
    }

    /**
     * Retorna os dados de um status pelo status_key, ou null se não existir.
     *
     * @param int $key
     * @return array|null
     */
    public static function getRow($key) {
        self::load();
        $key = (int)$key;
        return isset(self::$statuses[$key]) ? self::$statuses[$key] : null;
    }

    /**
     * Retorna array de status_keys para um dado behavior.
     *
     * @param string $behavior  'assigned' | 'pending' | 'solved' | 'closed'
     * @return int[]
     */
    public static function getKeysByBehavior($behavior) {
        self::load();
        $keys = array();
        foreach (self::$statuses as $key => $row) {
            if ($row['behavior'] === $behavior) {
                $keys[] = (int)$key;
            }
        }
        return $keys;
    }

    /**
     * Retorna o próximo status_key disponível (>= PLUGIN_STATUSMANAGER_KEY_OFFSET).
     *
     * @return int
     */
    public static function getNextStatusKey() {
        self::load();
        $offset = defined('PLUGIN_STATUSMANAGER_KEY_OFFSET') ? PLUGIN_STATUSMANAGER_KEY_OFFSET : 200;
        if (empty(self::$statuses)) {
            return $offset;
        }
        return max($offset, max(array_keys(self::$statuses)) + 1);
    }

    /**
     * Retorna os status_keys nativos que devem ser ocultados do dropdown.
     *
     * @return int[]
     */
    public static function getHiddenNativeKeys() {
        self::load();
        $hidden = array();
        foreach (self::$nativeOverrides as $key => $ov) {
            if ((int)$ov['is_hidden'] === 1) {
                $hidden[] = (int)$key;
            }
        }
        return $hidden;
    }

    /**
     * Retorna o ícone customizado de um status nativo, ou null.
     *
     * Permite dar a um nativo rebatizado (ex.: 5 -> "Documentação Deferida")
     * o mesmo tratamento visual dos personalizados.
     *
     * @param int $key
     * @return string|null
     */
    public static function getNativeIcon($key) {
        self::load();
        $key = (int)$key;
        if (isset(self::$nativeOverrides[$key]['icon'])
            && self::$nativeOverrides[$key]['icon'] !== '') {
            return self::$nativeOverrides[$key]['icon'];
        }
        return null;
    }

    /**
     * Retorna a cor customizada de um status nativo, ou null.
     *
     * @param int $key
     * @return string|null
     */
    public static function getNativeColor($key) {
        self::load();
        $key = (int)$key;
        if (isset(self::$nativeOverrides[$key]['color'])
            && self::$nativeOverrides[$key]['color'] !== '') {
            return self::$nativeOverrides[$key]['color'];
        }
        return null;
    }

    /**
     * Retorna o label customizado de um status nativo, ou null se não houver override.
     *
     * @param int $key  status_key nativo (1–6)
     * @return string|null
     */
    public static function getNativeLabel($key) {
        self::load();
        $key = (int)$key;
        if (
            isset(self::$nativeOverrides[$key])
            && self::$nativeOverrides[$key]['label'] !== ''
        ) {
            return self::$nativeOverrides[$key]['label'];
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Geração de CSS/JS dinâmico
    // -------------------------------------------------------------------------

    /**
     * Gera CSS dinâmico para colorir os ícones dos status personalizados.
     *
     * @return string
     */
    public static function generateCSS() {
        self::load();
        $css = '';
        foreach (self::$statuses as $key => $row) {
            $color = preg_replace('/[^#a-fA-F0-9]/', '', $row['color']);
            $css  .= ".cs-status-{$key} { color: {$color} !important; }\n";
        }
        // Nativos com cor customizada entram no mesmo esquema
        foreach (self::$nativeOverrides as $key => $row) {
            if (empty($row['color'])) {
                continue;
            }
            $color = preg_replace('/[^#a-fA-F0-9]/', '', $row['color']);
            $css  .= ".cs-status-{$key} { color: {$color} !important; }\n";
        }
        return $css;
    }

    /**
     * Sincroniza a lista branca de status dos templates de chamado.
     *
     * O GLPI 11 introduziu glpi_tickettemplates.allowed_statuses (default
     * "[1,10,2,3,4,5,6]"): o dropdown do formulário filtra por essa lista
     * DEPOIS de montar getAllStatusArray(). Sem incluir as chaves do plugin
     * ali, os status personalizados existem no core mas nunca aparecem no
     * formulário — e sem nenhum erro, o que torna o sintoma difícil de rastrear.
     *
     * No GLPI 10 a coluna não existe e o método simplesmente não faz nada.
     *
     * @return int Quantidade de templates atualizados
     */
    public static function syncTemplateAllowedStatuses() {
        global $DB;

        if (!$DB->tableExists('glpi_tickettemplates')
            || !$DB->fieldExists('glpi_tickettemplates', 'allowed_statuses')) {
            return 0; // GLPI 10: nada a fazer
        }

        self::load();
        $pluginKeys = array_map('intval', array_keys(self::$statuses));
        $updated    = 0;

        foreach ($DB->request(array(
            'SELECT' => array('id', 'allowed_statuses'),
            'FROM'   => 'glpi_tickettemplates',
        )) as $row) {
            $current = json_decode((string)$row['allowed_statuses'], true);
            if (!is_array($current)) {
                $current = array();
            }
            $current = array_map('intval', $current);

            // Mantém os nativos (faixa 1–14 do GLPI) e descarta chaves de
            // status do plugin que já não existem mais.
            $kept = array();
            foreach ($current as $k) {
                if ($k <= 14 || in_array($k, $pluginKeys, true)) {
                    $kept[] = $k;
                }
            }

            $merged = array_values(array_unique(array_merge($kept, $pluginKeys)));
            $novo   = json_encode($merged);

            if ($novo !== (string)$row['allowed_statuses']) {
                $DB->update('glpi_tickettemplates', array('allowed_statuses' => $novo), array('id' => $row['id']));
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Retorna array serializável para uso no frontend (JS).
     *
     * @return array<int, array{name: string, icon: string, color: string}>
     */
    public static function toArray() {
        self::load();
        $result = array();
        foreach (self::$statuses as $key => $row) {
            $result[$key] = array(
                'name'  => $row['name'],
                'icon'  => $row['icon'],
                'color' => $row['color'],
            );
        }
        // Nativos com ícone customizado, para o dropdown desenhar igual
        foreach (self::$nativeOverrides as $key => $row) {
            if (empty($row['icon'])) {
                continue;
            }
            $result[$key] = array(
                'name'  => isset($row['label']) ? $row['label'] : '',
                'icon'  => $row['icon'],
                'color' => isset($row['color']) ? $row['color'] : '',
            );
        }
        return $result;
    }
}
