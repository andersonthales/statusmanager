<?php

/**
 * ---------------------------------------------------------------------
 * Status Manager – PluginStatusmanagerStatus
 *
 * Modelo CRUD para os status personalizados.
 * Compatível com PHP 7.4+ / GLPI 10.
 * ---------------------------------------------------------------------
 */
class PluginStatusmanagerStatus extends CommonDBTM {

    public static $rightname = 'config';

    public static function getTypeName($nb = 0) {
        return _n('Status personalizado', 'Status personalizados', $nb);
    }

    public static function getTable($classname = null) {
        return 'glpi_plugin_statusmanager_statuses';
    }

    public static function getFormURL($full = true) {
        return Plugin::getWebDir('statusmanager') . '/front/status.form.php';
    }

    public static function getSearchURL($full = true) {
        return Plugin::getWebDir('statusmanager') . '/front/config.php';
    }

    /**
     * Retorna todos os status ordenados por rank.
     *
     * @return array<int, array>
     */
    public static function getAll() {
        global $DB;
        $rows = array();
        try {
            $iterator = $DB->request(array(
                'FROM'  => static::getTable(),
                'ORDER' => 'rank ASC',
            ));
            foreach ($iterator as $row) {
                $rows[$row['id']] = $row;
            }
        } catch (Exception $e) {
            // Retorna array vazio em caso de erro
        }
        return $rows;
    }

    /**
     * Retorna o próximo status_key disponível.
     *
     * @return int
     */
    public static function getNextStatusKey() {
        return PluginStatusmanagerRegistry::getNextStatusKey();
    }

    /**
     * Opções de comportamento disponíveis.
     *
     * @return array<string, string>
     */
    public static function getBehaviorOptions() {
        return array(
            'assigned' => 'Em atendimento (processando)',
            'pending'  => 'Pendente (pausa SLA)',
            'solved'   => 'Solucionado',
            'closed'   => 'Fechado / Cancelado',
        );
    }

    /**
     * Ícones Font Awesome disponíveis para seleção.
     *
     * @return array<string, string>
     */
    /**
     * Ícones oferecidos no formulário.
     *
     * O GLPI 10 usa Font Awesome; o 11 trocou por Tabler Icons. Oferecer a
     * lista errada não dá erro — grava normalmente e o ícone simplesmente
     * não desenha, aparecendo como um quadrado colorido na listagem.
     */
    public static function getIconOptions() {
        if (version_compare(GLPI_VERSION, '11.0.0', '>=')) {
            return array(
                'ti-circle-filled'       => 'Círculo sólido',
                'ti-circle'              => 'Círculo vazio',
                'ti-player-pause-filled' => 'Pausa',
                'ti-circle-check-filled' => 'Círculo verificado',
                'ti-circle-x-filled'     => 'Círculo com X',
                'ti-copy'                => 'Duplicado',
                'ti-file-check'          => 'Documento aprovado',
                'ti-file-x'              => 'Documento recusado',
                'ti-archive'             => 'Arquivo',
                'ti-users'               => 'Grupo / Usuários',
                'ti-user-clock'          => 'Usuário com relógio',
                'ti-tools'               => 'Ferramentas',
                'ti-ban'                 => 'Cancelado / Bloqueado',
                'ti-hourglass'           => 'Ampulheta',
                'ti-clock'               => 'Relógio',
                'ti-settings'            => 'Engrenagem',
                'ti-truck-delivery'      => 'Entrega / Fornecedor',
                'ti-printer'             => 'Impressora',
                'ti-shield'              => 'Garantia / Escudo',
                'ti-flask'               => 'Laboratório',
                'ti-box'                 => 'Peça / Caixa',
                'ti-calendar'            => 'Agendado',
                'ti-alert-triangle'      => 'Alerta',
            );
        }

        return array(
            'fas fa-circle'        => 'Círculo sólido',
            'far fa-circle'        => 'Círculo vazio',
            'fas fa-pause-circle'  => 'Círculo de pausa',
            'fas fa-check-circle'  => 'Círculo verificado',
            'fas fa-times-circle'  => 'Círculo com X',
            'fas fa-check-square'  => 'Quadrado verificado',
            'fas fa-archive'       => 'Arquivo',
            'fas fa-users'         => 'Grupo / Usuários',
            'fas fa-user-clock'    => 'Usuário com relógio',
            'fas fa-tools'         => 'Ferramentas',
            'fas fa-ban'           => 'Cancelado / Bloqueado',
            'fas fa-hourglass'     => 'Ampulheta',
            'fas fa-clock'         => 'Relógio',
            'fas fa-wrench'        => 'Chave de fenda',
            'fas fa-cog'           => 'Engrenagem',
            'fas fa-truck'         => 'Entrega / Fornecedor',
            'fas fa-print'         => 'Impressora',
            'fas fa-shield-alt'    => 'Garantia / Escudo',
            'fas fa-flask'         => 'Laboratório',
            'fas fa-box'           => 'Peça / Caixa',
            'fas fa-calendar-alt'  => 'Agendado',
        );
    }

    /**
     * Exibe o formulário de criação/edição do status.
     *
     * @param int   $ID
     * @param array $options
     */
    public function showForm($ID, $options = array()) {
        $isNew = ($ID <= 0);
        if (!$isNew) {
            $this->initForm($ID, $options);
        }

        $name     = $isNew ? '' : $this->fields['name'];
        $color    = $isNew ? '#0070c0' : $this->fields['color'];
        $icon     = $isNew ? 'fas fa-circle' : $this->fields['icon'];
        $behavior = $isNew ? 'pending' : $this->fields['behavior'];
        $rank     = $isNew ? 99 : (int)$this->fields['rank'];
        $active   = $isNew ? 1 : (int)$this->fields['is_active'];
        $skey     = $isNew ? self::getNextStatusKey() : (int)$this->fields['status_key'];
        $skeyAttr = $isNew ? '' : 'readonly';

        $formUrl   = Plugin::getWebDir('statusmanager') . '/front/status.form.php';
        $cancelUrl = Plugin::getWebDir('statusmanager') . '/front/config.php';
        $title     = $isNew
            ? 'Novo status personalizado'
            : 'Editar: ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

        $colorSafe = htmlspecialchars($color, ENT_QUOTES, 'UTF-8');
        $iconSafe  = htmlspecialchars($icon,  ENT_QUOTES, 'UTF-8');

        echo '<div class="card mt-3 mb-4" style="max-width:800px;">';
        echo '<div class="card-header"><h3 class="mb-0">' . $title . '</h3></div>';
        echo '<div class="card-body">';
        echo '<form method="post" action="' . htmlspecialchars($formUrl, ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="id" value="' . (int)$ID . '">';
        echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';

        // ── Nome ──────────────────────────────────────────────────────────────
        echo '<div class="row mb-3">';
        echo '<label class="col-sm-3 col-form-label fw-bold">Nome <span class="text-danger">*</span></label>';
        echo '<div class="col-sm-9">';
        echo '<input type="text" name="name" class="form-control"'
            . ' value="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
            . ' required maxlength="255">';
        echo '</div></div>';

        // ── Cor ───────────────────────────────────────────────────────────────
        echo '<div class="row mb-3">';
        echo '<label class="col-sm-3 col-form-label fw-bold">Cor</label>';
        echo '<div class="col-sm-9 d-flex align-items-center gap-2">';
        echo '<input type="color" name="color" id="sm_cp_color" class="form-control form-control-color"'
            . ' value="' . $colorSafe . '" style="width:50px;height:38px;">';
        echo '<input type="text" name="color_text" id="sm_cp_text" class="form-control"'
            . ' style="max-width:130px;" value="' . $colorSafe . '" maxlength="20" placeholder="#rrggbb">';
        echo '<span id="sm_color_preview" style="width:32px;height:32px;border-radius:50%;'
            . 'background:' . $colorSafe . ';border:1px solid #aaa;display:inline-block;"></span>';
        echo '</div></div>';

        // ── Ícone ─────────────────────────────────────────────────────────────
        echo '<div class="row mb-3">';
        echo '<label class="col-sm-3 col-form-label fw-bold">Ícone</label>';
        echo '<div class="col-sm-9">';
        echo '<div class="d-flex gap-2 align-items-center">';
        echo '<select name="icon_select" id="sm_icon_select" class="form-select" style="max-width:280px;">';
        foreach (self::getIconOptions() as $val => $lbl) {
            $selected = ($icon === $val) ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>'
                . htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') . '</option>';
        }
        echo '</select>';
        echo '<span id="sm_icon_preview" style="font-size:1.8rem;width:40px;text-align:center;">'
            . '<i class="' . $iconSafe . '"></i></span>';
        echo '</div>';
        echo '<small class="text-muted d-block mt-2">Ou informe uma classe Font Awesome personalizada:</small>';
        echo '<input type="text" name="icon" id="sm_icon_custom" class="form-control mt-1"'
            . ' style="max-width:280px;"'
            . ' value="' . $iconSafe . '" placeholder="fas fa-circle">';
        echo '</div></div>';

        // ── Comportamento ─────────────────────────────────────────────────────
        echo '<div class="row mb-3">';
        echo '<label class="col-sm-3 col-form-label fw-bold">Comportamento</label>';
        echo '<div class="col-sm-9">';
        echo '<select name="behavior" class="form-select" style="max-width:320px;">';
        foreach (self::getBehaviorOptions() as $val => $lbl) {
            $selected = ($behavior === $val) ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>'
                . htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') . '</option>';
        }
        echo '</select>';
        echo '<small class="text-muted d-block mt-1">'
            . 'Define em qual grupo o GLPI enquadra o chamado (filtros, dashboards, SLA).'
            . '</small>';
        echo '</div></div>';

        // ── Ordem ─────────────────────────────────────────────────────────────
        echo '<div class="row mb-3">';
        echo '<label class="col-sm-3 col-form-label fw-bold">Ordem</label>';
        echo '<div class="col-sm-3">';
        echo '<input type="number" name="rank" class="form-control"'
            . ' value="' . $rank . '" min="1" max="999">';
        echo '</div></div>';

        // ── Chave interna ─────────────────────────────────────────────────────
        echo '<div class="row mb-3">';
        echo '<label class="col-sm-3 col-form-label fw-bold">Chave interna (ID)</label>';
        echo '<div class="col-sm-4">';
        // min=100: acima da faixa nativa do GLPI (1..10), mas abaixo do
        // KEY_OFFSET, para permitir adotar chaves já existentes em bases
        // que usavam patches manuais no core (ex.: 110, 111 do seletivo).
        echo '<input type="number" name="status_key" class="form-control"'
            . ' value="' . $skey . '" min="100" max="9999" ' . $skeyAttr . '>';
        echo '<small class="text-muted">'
            . 'Valor inteiro salvo em <code>glpi_tickets.status</code>. Não altere após criar.'
            . ' Novos status começam em ' . PLUGIN_STATUSMANAGER_KEY_OFFSET
            . '; use 100–199 apenas para reaproveitar chaves já gravadas em chamados existentes.'
            . '</small>';
        echo '</div></div>';

        // ── Ativo ─────────────────────────────────────────────────────────────
        echo '<div class="row mb-3">';
        echo '<label class="col-sm-3 col-form-label fw-bold">Ativo</label>';
        echo '<div class="col-sm-9">';
        echo '<div class="form-check form-switch fs-5">';
        echo '<input class="form-check-input" type="checkbox" name="is_active" value="1"'
            . ($active ? ' checked' : '') . '>';
        echo '</div></div></div>';

        // ── Botões ────────────────────────────────────────────────────────────
        echo '<div class="d-flex gap-2 pt-3 border-top">';
        echo '<button type="submit" name="save" class="btn btn-primary">'
            . '<i class="fas fa-save me-1"></i>Salvar</button>';
        echo '<a href="' . htmlspecialchars($cancelUrl, ENT_QUOTES, 'UTF-8') . '"'
            . ' class="btn btn-secondary">Cancelar</a>';
        if (!$isNew) {
            echo '<button type="submit" name="purge" class="btn btn-danger ms-auto"'
                . ' onclick="return confirm(\'Remover este status permanentemente?\')">'
                . '<i class="fas fa-trash me-1"></i>Remover</button>';
        }
        echo '</div>';
        echo '</form>';
        echo '</div></div>'; // .card-body + .card

        // ── JavaScript do formulário ──────────────────────────────────────────
        echo '<script>(function() {';
        echo 'var cp   = document.getElementById("sm_cp_color");';
        echo 'var txt  = document.getElementById("sm_cp_text");';
        echo 'var prev = document.getElementById("sm_color_preview");';
        echo 'var sel  = document.getElementById("sm_icon_select");';
        echo 'var cust = document.getElementById("sm_icon_custom");';
        echo 'var iprev= document.getElementById("sm_icon_preview");';
        echo 'function setColor(h) { cp.value = h; txt.value = h; prev.style.background = h; }';
        echo 'function setIcon(c)  { cust.value = c; iprev.innerHTML = "<i class=\"" + c + "\"></i>"; }';
        echo 'cp.addEventListener("input",  function() { setColor(this.value); });';
        echo 'txt.addEventListener("input", function() { setColor(this.value); });';
        echo 'sel.addEventListener("change",function() { setIcon(this.value); });';
        echo 'cust.addEventListener("input",function() { setIcon(this.value); });';
        echo '})();</script>';
    }

    public function getSearchOptions() {
        $tab   = array();
        $table = $this->getTable();
        $tab[] = array('id' => 'common', 'name' => self::getTypeName());
        $tab[1] = array('id'=>1,'table'=>$table,'field'=>'name',      'name'=>'Nome',          'datatype'=>'itemlink');
        $tab[2] = array('id'=>2,'table'=>$table,'field'=>'status_key','name'=>'Chave interna', 'datatype'=>'integer');
        $tab[3] = array('id'=>3,'table'=>$table,'field'=>'color',     'name'=>'Cor',           'datatype'=>'string');
        $tab[4] = array('id'=>4,'table'=>$table,'field'=>'behavior',  'name'=>'Comportamento', 'datatype'=>'string');
        $tab[5] = array('id'=>5,'table'=>$table,'field'=>'rank',      'name'=>'Ordem',         'datatype'=>'integer');
        $tab[6] = array('id'=>6,'table'=>$table,'field'=>'is_active', 'name'=>'Ativo',         'datatype'=>'bool');
        return $tab;
    }
}
