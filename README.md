# Status Manager — Plugin para GLPI 10 e 11

Cria **status personalizados de chamado** pelo painel do GLPI e permite **renomear, recolorir ou ocultar** os status nativos, sem editar o código do GLPI à mão.

| | |
|---|---|
| **Versão** | 3.5.0 |
| **GLPI** | 10.0.x e 11.0.x (em produção no 11.0.8) |
| **PHP** | 7.4+ no GLPI 10 · 8.2+ no GLPI 11 |
| **Licença** | GPLv3+ |

> ⚠️ **Este plugin altera arquivos do core do GLPI.** O GLPI não oferece hooks para acrescentar status de chamado, então o plugin aplica *patches* em quatro arquivos de `src/`, com backup antes de cada alteração. Leia [Patches no core](#patches-no-core) antes de instalar.

---

## Funcionalidades

- **Status personalizados ilimitados**, com nome, cor, ícone e ordem.
- **Comportamento** de cada status, que define como o GLPI o trata em filtros, dashboards e SLA:

  | Comportamento | O GLPI trata como |
  |---|---|
  | Em atendimento | *Atribuído / Planejado* (em processamento) |
  | Pendente | *Pendente*: **pausa o SLA** e esconde a barra de progresso |
  | Solucionado | *Solucionado* |
  | Fechado / Cancelado | *Fechado* |

- **Status nativos** (Novo, Atribuído, Planejado, Pendente, Solucionado, Fechado): renomear, definir ícone e cor, ou ocultar do seletor.
- Ícones **Font Awesome** no GLPI 10 e **Tabler Icons** no GLPI 11, com a lista certa para cada versão.
- Dashboards contam os status personalizados.
- **No GLPI 11**, os status são incluídos automaticamente na lista de status permitidos dos *templates de chamado*. Sem isso, o formulário os esconderia sem aviso.

## Instalação

1. Copie o plugin para `plugins/statusmanager`:
   ```bash
   cd /var/www/html/glpi/plugins
   git clone https://github.com/andersonthales/statusmanager.git
   chown -R www-data:www-data statusmanager
   ```
2. Em **Configurar → Plugins**, clique em **Instalar** e depois em **Ativar** no **Status Manager**.
3. Abra a configuração do plugin e clique em **Aplicar patches no core**.
4. Confira a mensagem: se algum patch **não** for aplicado, o plugin lista qual arquivo e qual trecho falhou.

Pela tela, o usuário do servidor web precisa de **permissão de escrita** nos arquivos de `src/` listados abaixo e nos diretórios deles. O recomendado é deixar o `src/` só leitura para o servidor web e aplicar pelo console, como root:

```bash
cd /var/www/html/glpi
php bin/console plugins:statusmanager:patch status            # situação de cada arquivo
php bin/console plugins:statusmanager:patch apply --dry-run   # confere sem gravar
php bin/console plugins:statusmanager:patch apply
php bin/console plugins:statusmanager:patch revert
```

O código de saída é 0 quando tudo ficou como pedido, o que permite usar o comando em scripts de atualização. O dono e as permissões originais dos arquivos são preservados.

## Uso

Em **Configurar → Plugins → Status Manager** (requer *Configuração → Atualizar*):

- **Novo status**: nome, cor, ícone, comportamento, ordem e *chave interna*.
- **Gerenciar status nativos**: renomear, definir ícone e cor, ou ocultar.
- **Aplicar / Reverter patches**, com a situação de cada arquivo.
- **Cadastrar a partir dos chamados**: aparece quando há chamados com status que nem o GLPI nem o plugin conhecem, como numa base migrada de um GLPI com status criados no código. Cada status é cadastrado com a mesma chave, então os chamados não mudam.

### Chave interna

É o número gravado em `glpi_tickets.status`. Os novos status começam em **200**, longe da faixa usada pelo GLPI (1 a 14). A faixa **100 a 199** existe só para reaproveitar números já gravados em chamados de bases que tinham patches manuais. A chave precisa estar entre 100 e 9999 e **não pode ser alterada depois de criada**, porque os chamados existentes guardam esse número. O plugin recusa a alteração.

## Patches no core

| Alteração | GLPI 10 | GLPI 11 e 12 |
|---|---|---|
| Lista de status (`getAllStatusArray`) e grupos *fechado*, *solucionado* e *em processamento* | `src/Ticket.php` | `src/Ticket.php` |
| Contagem nos dashboards (`nbTicketsGeneric`) | `src/Dashboard/Provider.php` | `src/Glpi/Dashboard/Provider.php` |
| Ícones (`getStatusClass`) e pausa/retomada do SLA | `src/CommonITILObject.php` | `src/CommonITILObject.php` |
| Barra de progresso do SLA em status pendente | `src/Search.php` | `src/Glpi/Search/Provider/SQLProvider.php` |

Os patches foram testados no GLPI 10.0.28, 11.0.11 e 12.0.0. O plugin em si declara compatibilidade até o 11.1.x.

- Cada trecho procura **uma única linha** do core e insere o código antes ou depois dela. Se a linha não existir, ou existir mais de uma vez, o arquivo inteiro fica intacto e o relatório diz qual trecho falhou.
- O resultado passa por validação de sintaxe e é gravado de forma atômica (temporário + `rename`): um arquivo nunca fica pela metade.
- Antes do patch, cada arquivo é copiado para `<arquivo>.statusmanager.bak`.
- **Reverter patches** (ou desinstalar o plugin) restaura os backups.
- Os trechos inseridos são marcados com `[StatusManager]`, o que permite conferir com `grep -rn "\[StatusManager\]" src/`.

### Ao atualizar o GLPI

A atualização substitui os arquivos de `src/` e **apaga os patches**: os status personalizados somem do seletor e dos dashboards (os chamados mantêm o número gravado). O plugin mostra um aviso no painel central para os administradores enquanto isso. Depois de atualizar, rode `php bin/console plugins:statusmanager:patch apply` ou clique em **Aplicar patches no core**.

Os backups `.statusmanager.bak` que sobraram da versão anterior do GLPI são substituídos automaticamente ao reaplicar, e nunca são restaurados sobre um arquivo sem patch.

> Em versões **anteriores à 3.4.3**, apague esses backups antigos antes de reaplicar ou reverter, porque restaurá-los quebraria a instalação nova:
> ```bash
> find /var/www/html/glpi/src -name '*.statusmanager.bak' -delete
> ```

## Recuperação de emergência

Se o GLPI parar de abrir depois de um patch, restaure os backups manualmente:

```bash
cd /var/www/html/glpi
for f in $(find src -name '*.statusmanager.bak'); do cp "$f" "${f%.statusmanager.bak}"; done
```

## Migração do plugin antigo "customstatus"

Quem usava o antecessor **customstatus** pode migrar os dados acessando, como administrador, `https://<seu-glpi>/plugins/statusmanager/upgrade.php`. O script copia as tabelas `glpi_plugin_customstatus_*` para `glpi_plugin_statusmanager_*` quando as novas ainda não existem.

## Banco de dados

| Tabela | Conteúdo |
|---|---|
| `glpi_plugin_statusmanager_statuses` | Status personalizados |
| `glpi_plugin_statusmanager_native_overrides` | Nome, ícone, cor e ocultação dos status nativos |

A desinstalação **reverte os patches** e remove as duas tabelas. Chamados que estavam em um status personalizado continuam com o número gravado em `glpi_tickets.status`, que deixa de ter nome. Mude o status desses chamados antes de desinstalar.

## Estrutura

```
statusmanager/
├── setup.php                     # Registro do plugin e hooks
├── hook.php                      # Instalação / desinstalação
├── upgrade.php                   # Migração do antigo "customstatus"
├── inc/
│   ├── registry.class.php        # Cache dos status; usado pelo código injetado no core
│   ├── status.class.php          # Status personalizados (CRUD)
│   ├── nativeoverride.class.php  # Ajustes nos status nativos
│   ├── patcher.class.php         # Aplica e reverte os patches
│   └── patchcommand.class.php    # bin/console plugins:statusmanager:patch
├── front/
│   ├── config.php                # Página principal
│   ├── status.form.php           # Criar / editar status
│   ├── native.php                # Status nativos
│   ├── patch.php                 # Aplicar / reverter patches
│   ├── import.php                # Cadastrar status encontrados nos chamados
│   └── dynamic.css.php           # CSS com a cor de cada status
├── ajax/dynamic_styles.php       # Status em JSON para o JavaScript
└── public/                       # Arquivos estáticos (exigência do GLPI 11)
    ├── css/statusmanager.css
    └── js/statusmanager.js       # Ícones e cores no seletor de status (Select2)
```

## Changelog

Veja [CHANGELOG.md](CHANGELOG.md).

## Licença

[GPLv3 ou posterior](LICENSE). Copyright © 2026 Anderson Thales.

## Autor

**Anderson Thales** — [@andersonthales](https://github.com/andersonthales)
