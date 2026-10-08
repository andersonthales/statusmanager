<?php

/**
 * ---------------------------------------------------------------------
 * Status Manager – PluginStatusmanagerNativeOverride
 *
 * Gerencia a renomeação e ocultação dos status nativos do GLPI.
 * ---------------------------------------------------------------------
 */
class PluginStatusmanagerNativeOverride {

    /**
     * Mapa dos 6 status nativos do GLPI.
     *
     * @return array<int, string>
     */
    public static function getNativeStatuses() {
        return array(
            1 => 'Novo',
            2 => 'Atribuído',
            3 => 'Planejado',
            4 => 'Pendente',
            5 => 'Resolvido',
            6 => 'Fechado',
        );
    }

    public static function getTable() {
        return 'glpi_plugin_statusmanager_native_overrides';
    }

    /**
     * Garante as colunas de ícone e cor.
     *
     * Foram acrescentadas na 3.4.0: até então só era possível renomear e
     * ocultar um status nativo, mas não dar a ele o mesmo tratamento visual
     * dos personalizados — o que deixava, por exemplo, "Documentação
     * Deferida" (nativo 5) sem ícone ao lado dos outros status do fluxo.
     *
     * Chamado da tela de nativos; idempotente.
     */
    public static function ensureSchema() {
        global $DB;

        $tabela = static::getTable();
        if (!$DB->tableExists($tabela)) {
            return;
        }
        foreach (array(
            'icon'  => "VARCHAR(100) NOT NULL DEFAULT ''",
            'color' => "VARCHAR(20)  NOT NULL DEFAULT ''",
        ) as $coluna => $tipo) {
            if (!$DB->fieldExists($tabela, $coluna)) {
                $DB->doQueryOrDie(
                    "ALTER TABLE `{$tabela}` ADD COLUMN `{$coluna}` {$tipo}",
                    "Erro ao adicionar a coluna {$coluna} em {$tabela}"
                );
            }
        }
    }

    /**
     * Retorna todos os overrides indexados por status_key.
     *
     * @return array<int, array>
     */
    public static function getAll() {
        global $DB;
        $rows = array();
        try {
            $iterator = $DB->request(array('FROM' => static::getTable()));
            foreach ($iterator as $row) {
                $rows[(int)$row['status_key']] = $row;
            }
        } catch (Exception $e) {
            // Silencia erro
        }
        return $rows;
    }

    /**
     * Insere ou atualiza o override de um status nativo.
     *
     * @param int    $statusKey  Status nativo (1–6)
     * @param string $label      Novo label (vazio = manter original)
     * @param bool   $hidden     Ocultar do dropdown
     */
    public static function save($statusKey, $label, $hidden, $icon = null, $color = null) {
        global $DB;

        $statusKey = (int)$statusKey;
        $label     = trim($label);
        $hidden    = $hidden ? 1 : 0;
        $now       = date('Y-m-d H:i:s');

        // Verifica se já existe registro para este status_key
        $existing = array();
        try {
            $iter = $DB->request(array(
                'FROM'  => static::getTable(),
                'WHERE' => array('status_key' => $statusKey),
            ));
            foreach ($iter as $r) {
                $existing = $r;
            }
        } catch (Exception $e) {
            // Silencia erro
        }

        // Só grava ícone/cor se as colunas existirem — assim a mesma versão
        // do plugin funciona antes e depois do ensureSchema().
        $tem_visual = $DB->fieldExists(static::getTable(), 'icon');

        $dados = array(
            'label'     => $label,
            'is_hidden' => $hidden,
            'date_mod'  => $now,
        );
        if ($tem_visual && $icon !== null) {
            $icon  = trim($icon);
            $color = $color === null ? null : trim($color);

            $dados['icon'] = $icon;

            // Sem ícone não há personalização visual, e o <input type="color">
            // sempre envia um valor (#000000 por padrão). Gravar a cor nesse
            // caso registraria uma escolha que ninguém fez.
            $dados['color'] = ($icon === '' || $color === null) ? '' : $color;
        } elseif ($tem_visual && $color !== null) {
            $dados['color'] = trim($color);
        }

        if (empty($existing)) {
            $dados['status_key']    = $statusKey;
            $dados['date_creation'] = $now;
            $DB->insert(static::getTable(), $dados);
        } else {
            $DB->update(static::getTable(), $dados, array('status_key' => $statusKey));
        }
    }

    /**
     * Retorna o label customizado de um status nativo (ou o original se não houver override).
     *
     * @param int    $statusKey
     * @param string $original
     * @return string
     */
    public static function getLabel($statusKey, $original) {
        $overrides = static::getAll();
        if (isset($overrides[$statusKey]) && $overrides[$statusKey]['label'] !== '') {
            return $overrides[$statusKey]['label'];
        }
        return $original;
    }

    /**
     * Retorna true se o status nativo deve ser ocultado do dropdown.
     *
     * @param int $statusKey
     * @return bool
     */
    public static function isHidden($statusKey) {
        $overrides = static::getAll();
        return isset($overrides[$statusKey]) && (int)$overrides[$statusKey]['is_hidden'] === 1;
    }
}
