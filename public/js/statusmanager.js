/**
 * Status Manager – JS principal
 *
 * 1. Carrega o CSS dinâmico dos status personalizados
 * 2. Sobrescreve templateItilStatus (Select2) para exibir ícones/cores dos
 *    status do plugin no dropdown de chamados
 *
 * O GLPI define templateItilStatus em common.js com um switch/case hardcoded
 * (ids 1–14). Qualquer status fora dessa faixa cai no default e renderiza sem
 * ícone — por isso a sobrescrita.
 */
(function () {
    'use strict';

    var base = (window.CFG_GLPI && CFG_GLPI.root_doc) ? CFG_GLPI.root_doc : '';

    // ── 1. CSS dinâmico ───────────────────────────────────────────────────────
    var link  = document.createElement('link');
    link.rel  = 'stylesheet';
    link.type = 'text/css';
    link.href = base + '/plugins/statusmanager/front/dynamic.css.php?v=' + Date.now();
    document.head.appendChild(link);

    // ── 2. Sobrescreve templateItilStatus ─────────────────────────────────────
    // A sobrescrita é instalada IMEDIATAMENTE e o dado chega depois, pelo AJAX.
    // Fazer o contrário (esperar o AJAX para só então sobrescrever) cria uma
    // corrida: se o Select2 inicializar antes da resposta, ele guarda a
    // referência da função original e a sobrescrita nunca surte efeito.
    var customStatuses = {};
    var originalFn     = window.templateItilStatus;

    /**
     * Instala a sobrescrita de forma imune à ordem de carregamento.
     *
     * Se este arquivo for avaliado ANTES do bundle common.js do GLPI, uma
     * atribuição simples seria destruída: o common.js faz
     * `templateItilStatus = function(){...}` e apaga a nossa. Com um
     * accessor, qualquer atribuição posterior do GLPI é capturada como
     * função original (o nosso fallback) e o wrapper continua na frente.
     */
    function instalar(wrapper) {
        try {
            Object.defineProperty(window, 'templateItilStatus', {
                configurable: true,
                get: function () { return wrapper; },
                set: function (fn) { originalFn = fn; }
            });
        } catch (e) {
            // Navegador/ambiente sem suporte: cai no modo simples
            window.templateItilStatus = wrapper;
        }
    }

    /**
     * Normaliza a classe do ícone.
     *
     * O Tabler exige a classe base `ti` junto do modificador `ti-*`: sozinho,
     * `ti-circle-x` não define font-family e o glifo não aparece. O Font
     * Awesome (usado no GLPI 10) já traz o par `fas fa-*`, então é preservado
     * como veio.
     */
    function normalizeIcon(icon) {
        icon = (icon || '').trim() || 'ti-circle';

        var hasTiModifier = /(^|\s)ti-/.test(icon);
        var hasTiBase     = /(^|\s)ti(\s|$)/.test(icon);

        if (hasTiModifier && !hasTiBase) {
            icon = 'ti ' + icon;
        }
        return icon;
    }

    function renderStatus(option) {
        if (!option || !option.id) {
            return option ? option.text : '';
        }

        var statusId = parseInt(option.id, 10);
        var cs       = customStatuses[statusId];

        if (cs) {
            var icon  = normalizeIcon(cs.icon);
            var color = cs.color || '#999999';
            return $(
                '<span>'
                + '<i class="itilstatus ' + icon + ' cs-status-' + statusId + '"'
                + ' style="color:' + color + ';margin-right:4px;"></i>'
                + option.text
                + '</span>'
            );
        }

        // Fallback: comportamento original para status nativos
        return (typeof originalFn === 'function')
            ? originalFn(option)
            : option.text;
    }

    instalar(renderStatus);

    var xhr = new XMLHttpRequest();
    xhr.open('GET', base + '/plugins/statusmanager/ajax/dynamic_styles.php', true);
    xhr.onload = function () {
        if (xhr.status !== 200) {
            console.warn('[StatusManager] dynamic_styles.php respondeu ' + xhr.status
                + ' — os status do plugin vão aparecer sem ícone.');
            return;
        }
        try {
            customStatuses = JSON.parse(xhr.responseText) || {};
        } catch (e) {
            console.warn('[StatusManager] resposta de dynamic_styles.php não é JSON válido.');
        }
    };
    xhr.onerror = function () {
        console.warn('[StatusManager] falha ao buscar dynamic_styles.php.');
    };
    xhr.send();

}());
