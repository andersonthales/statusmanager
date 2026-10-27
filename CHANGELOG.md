# Changelog

Histórico reconstruído a partir das versões encontradas nos servidores.

## [3.5.0] - 2026-10-27
### Alterado
- Patches com âncoras mínimas: cada trecho procura uma única linha do core (por regex, preservando a indentação) em vez de um bloco inteiro. Os patches agora entram no GLPI 10.0.28, 11.0.11 e 12.0.0; antes, no 12 a barra de progresso do SLA ficava de fora
- Gravação tudo ou nada por arquivo: se algum trecho não acha a âncora, ou se a âncora aparece mais de uma vez, o arquivo não é tocado e o relatório diz qual trecho falhou
- Gravação atômica: arquivo temporário, validação de sintaxe (`token_get_all` com `TOKEN_PARSE`), `rename` e `opcache_invalidate`. Um patch com erro não chega a ficar no disco
- "Core patcheado" só aparece quando todos os arquivos têm patch. Antes só o `Ticket.php` era conferido
- Lista branca dos templates (GLPI 11) sincronizada em toda gravação de status, incluindo a API, e não só pelo formulário

### Adicionado
- Comando `php bin/console plugins:statusmanager:patch status|apply|revert [--dry-run]`. Rodando como root, o `src/` pode ficar só leitura para o servidor web
- Aviso no painel central, para quem tem *Configuração → Atualizar*, quando há status cadastrados e os patches sumiram (por exemplo, depois de atualizar o GLPI)
- Tabela com a situação de cada arquivo na configuração. Os botões somem quando o servidor web não pode gravar, e a tela indica o comando
- Assistente **Cadastrar a partir dos chamados**: lista os status gravados em `glpi_tickets` sem cadastro (bases migradas de GLPI com status no core, como 101–112) e os cadastra com a mesma chave
- Validação no modelo, valendo para formulário, assistente e API: chave entre 100 e 9999 e única, chave imutável depois de criada, cor `#rgb`/`#rrggbb`, ícone só com classes CSS e comportamento conhecido

### Corrigido
- GLPI 10: o ícone dos status personalizados nunca aparecia, porque a âncora de `getStatusClass` era a do GLPI 11. Mesmo assim, o relatório marcava o arquivo como patcheado
- O formulário dizia "criado com sucesso" mesmo quando a gravação falhava
- Ícone padrão de um status novo no GLPI 11 era Font Awesome (`fas fa-circle`), que não desenha. Agora usa o primeiro da lista Tabler

## [3.4.3] - 2026-10-08
### Corrigido
- Aplicar e reverter patches agora exigem POST com token CSRF. Antes eram links GET: uma página externa aberta por um administrador logado podia alterar o core
- Backup renovado a cada aplicação do patch. Antes, um `.bak` de uma versão anterior do GLPI era mantido e, ao reverter ou desinstalar, restaurado por cima do GLPI atualizado
- Reverter não restaura o backup sobre um arquivo que já não tem patch

## [3.4.2] - 2026-08-27
- Versão em produção no GLPI 11.0.8.

## [3.4.0]
### Adicionado
- Ícone e cor para os status nativos (colunas `icon` e `color` em `glpi_plugin_statusmanager_native_overrides`, criadas automaticamente)

## [3.3.0]
### Alterado
- CSS e JavaScript movidos para `public/`, exigência do roteador do GLPI 11. No GLPI 10, os caminhos registrados incluem `public/`

## [3.x]
### Adicionado
- Sincronização dos status do plugin com `glpi_tickettemplates.allowed_statuses` (GLPI 11)
- Lista de ícones Tabler para o GLPI 11
### Corrigido
- Ícone de status personalizado não aparecia no GLPI 11 (`getStatusClass` devolve `null` para status desconhecido)
- Sobrescrita do `templateItilStatus` resistente à ordem de carregamento do JavaScript
- A tela de patches não declara mais sucesso quando algum patch não foi aplicado

## [3.0.0] - 2026-05-06
### Adicionado
- Suporte ao GLPI 11: caminhos novos de `Dashboard/Provider.php` e da barra de progresso (`Search/Provider/SQLProvider.php`)

## [2.0.0] - 2026-03-11
- Primeira versão como **statusmanager** (antes *customstatus*), para GLPI 10
- Status personalizados com comportamento, pausa de SLA, status nativos renomeáveis e ocultáveis
- Patches no core com backup e reversão automáticos
- Script de migração `upgrade.php` a partir do customstatus
