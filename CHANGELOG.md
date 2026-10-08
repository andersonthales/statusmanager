# Changelog

Histórico reconstruído a partir das versões encontradas nos servidores.

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
