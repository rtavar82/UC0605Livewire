# Script 07 — Gestão de famílias (2): detalhe, endereço partilhável e filtro

No Script 05 criámos a gestão de famílias e no Script 06 o modal de confirmação. Falta uma operação básica: **ver o detalhe** de uma família.

Neste passo vamos:

1. acrescentar um **filtro por estado** (todas, ativas, removidas);
2. mostrar o **detalhe** de uma família num segundo **drawer**, aberto ao clicar no nome;
3. dar ao detalhe um **endereço partilhável** (`/admin/families?family=araceae`) com o atributo `#[Url]` do Livewire;
4. permitir **editar, remover, restaurar e eliminar** a partir do detalhe;
5. reservar o espaço da **imagem** com um marcador, até implementarmos o carregamento de imagens.

> 🎓 **Nota do professor — Enquadramento (≈ 3 min)**
> Aula de **consolidação**: quase tudo é reutilização de conceitos do 05 (propriedades, computadas, drawers, `Flux::modal`) com **um conceito novo** — `#[Url]` (estado no endereço) — mais `mount()`. Todas as alterações são **num único ficheiro**, o que facilita acompanhar.
> Mensagem: *«uma página tem estado; parte desse estado pode viver no URL para ser recarregado e partilhado»*. Começar por perguntar: «se eu enviar este ecrã a um colega, o que ele vê?» → hoje, nada do detalhe.


No final teremos:

```text
/admin/families?family=rosaceae
┌───────────────────────────────────────────┬──────────────────────────────┐
│ Famílias botânicas        [+ Nova família] │ ┌──────────────────────────┐ │
│ [🔍 Pesquisar…]  [Todas ▾]                 │ │   Imagem ainda não       │ │
│                                            │ │   disponível             │ │
│ Araceae      …   Ativa                     │ └──────────────────────────┘ │
│ Rosaceae  ←  clicar no nome                │ Rosaceae  [Removida]         │
│ …                                          │ rosaceae                     │
│                                            │ Descrição …                  │
│                                            │ Criada em / Atualizada em /  │
│                                            │ Removida em                  │
│                                            │ [Restaurar] [Eliminar def.]  │
│                                            │                     [Fechar] │
└───────────────────────────────────────────┴──────────────────────────────┘
```

---

**Documentação oficial:**

- Livewire — `#[Url]`: https://livewire.laravel.com/docs/4.x/attribute-url
- Livewire — Propriedades computadas: https://livewire.laravel.com/docs/4.x/computed-properties
- Livewire — Lifecycle hooks (`mount`): https://livewire.laravel.com/docs/4.x/lifecycle-hooks
- Flux — Select: https://fluxui.dev/components/select
- Flux — Modal (eventos `close`): https://fluxui.dev/components/modal
- PHP — Expressão `match`: https://www.php.net/manual/en/control-structures.match.php
- Carbon — Formatação de datas: https://carbon.nesbot.com/docs/#api-formatting

---

**Índice**

<!-- TOC -->
* [Script 07 — Gestão de famílias (2): detalhe, endereço partilhável e filtro](#script-07--gestão-de-famílias-2-detalhe-endereço-partilhável-e-filtro)
  * [Antes de começar](#antes-de-começar)
  * [0. Conceitos](#0-conceitos)
  * [1. Filtro por estado](#1-filtro-por-estado)
  * [2. O estado do detalhe no endereço: `#[Url]`](#2-o-estado-do-detalhe-no-endereço-url)
  * [3. Abrir e fechar o detalhe](#3-abrir-e-fechar-o-detalhe)
  * [4. O drawer de detalhe](#4-o-drawer-de-detalhe)
  * [5. Abrir o detalhe a partir do endereço](#5-abrir-o-detalhe-a-partir-do-endereço)
  * [6. Ações a partir do detalhe](#6-ações-a-partir-do-detalhe)
  * [7. Verificação no browser](#7-verificação-no-browser)
  * [8. Verificação final e commit](#8-verificação-final-e-commit)
  * [9. Resumo](#9-resumo)
  * [10. Problemas comuns](#10-problemas-comuns)
<!-- TOC -->

---

## Antes de começar

### Como utilizar este script

Este script foi escrito para ser seguido **de forma autónoma**, depois da aula.

- Seguir os passos **pela ordem indicada**. Cada passo depende do anterior.
- No fim de cada secção existe um bloco **Verificar**. Não avançar sem confirmar que tudo funciona.
- Os blocos **Exercício** devem ser resolvidos antes de continuar.
- Se algo não funcionar, consultar a secção [10. Problemas comuns](#10-problemas-comuns).

Todas as alterações deste script são feitas num único ficheiro:

```text
resources/views/pages/admin/⚡families.blade.php
```

### Pré-requisitos

- Scripts **05** e **06** concluídos.

- Serviços Docker em execução:

  ```bash
  docker compose up -d
  ```

- Aplicação em execução num terminal próprio:

  ```bash
  composer run dev
  ```

- As famílias do seeder existem. Se alguma foi eliminada definitivamente durante os testes, repô-la (o seeder é idempotente):

> ⚠️ **Atenção (professor) — Dados de partida**
> Os alunos podem ter eliminado famílias do 05 ou 06 (ou deixado fictícias). Confirmar `Family::withTrashed()->count()` e, se necessário, `db:seed --class=FamilySeeder` (idempotente, ver 04/05). Não usar `migrate:fresh`.


  ```bash
  php artisan db:seed --class=FamilySeeder
  ```

---

## 0. Conceitos

### 0.1. Estado da página no endereço

Até agora, o estado da página (pesquisa, página da tabela, drawer aberto) vive **apenas** nas propriedades do componente. Se o administrador recarregar a página, ou enviar o endereço a um colega, esse estado perde-se.

Com o atributo `#[Url]`, o Livewire **sincroniza** uma propriedade com a *query string* do endereço:

> 📖 **Definição — Query string**
> A parte do URL depois de `?` (`?family=araceae&page=2`): pares `chave=valor`. Não faz parte do caminho da rota; serve para **parâmetros opcionais** (filtros, página). `#[Url]` liga uma propriedade do componente a um desses parâmetros, nos dois sentidos.


```text
propriedade $showing = 'araceae'   ⇄   /admin/families?family=araceae
```

- Quando a propriedade muda, o endereço no browser muda (sem recarregar a página).
- Quando a página é aberta com `?family=araceae`, a propriedade começa com esse valor.

É aqui que o **slug** (Script 05) ganha a primeira utilização real: `?family=araceae` é mais legível do que `?family=3`.

> A paginação do Livewire já usa este mecanismo: repare no `?page=2` no endereço quando muda de página.

> 💬 **Pergunta à turma**
> «Que estado da página **faz** sentido pôr no URL?» Esperado: o que alguém quer **partilhar ou recarregar** (item aberto, filtros, página). Que estado **não**? Formulários em edição, dados sensíveis, estado transitório (drawer de edição aberto). Por isso `$search` e `$status` ficam fora do URL aqui.

> ⏱️ **Ritmo — Demonstração**
> Mostrar o `?page=2` já existente na paginação antes de escrever código: os alunos veem que o Livewire já faz isto e percebem a utilidade.


### 0.2. Dois drawers na mesma página

A página passa a ter **dois** modais `flyout`, cada um com o seu nome:

| Drawer | Nome | Abre com | Estado |
|--------|------|----------|--------|
| Formulário (Script 05) | `family-form` | **Nova família**, **Editar** | `$editingId`, `$name`, `$slug`, `$description` |
| Detalhe (este script) | `family-show` | clicar no nome | `$showing` (slug, no endereço) |

Nunca devem estar os dois abertos ao mesmo tempo: **Editar**, a partir do detalhe, fecha o detalhe e abre o formulário.

> ⚠️ **Atenção (professor) — Dois modais, um conjunto de nomes**
> Os `name`s têm de ser distintos (`family-form`, `family-show`) e usados **iguais** em `Flux::modal('...')` e `<flux:modal name="...">`. Já é o erro mais comum desde 03.4. Quando os dois drawers se sobrepõem, o sintoma é visual e fácil de diagnosticar.


### 0.3. Guardar o slug, não o modelo

A propriedade `$showing` guarda apenas o **slug** (um texto). O modelo `Family` é obtido a partir dele por uma **propriedade computada**, `shownFamily`, calculada de novo em cada pedido. Assim:

> 🎓 **Nota do professor — Porque não guardar o modelo?**
> Alternativa: `public ?Family $family`. Livewire consegue serializar modelos, mas o URL ficaria com o objeto e os dados poderiam ficar desatualizados. Guardar só o **identificador** (slug) e reconstruir a partir da BD é o padrão mais seguro e atualizado. Liga ao que se disse em 05: propriedades públicas = leves e simples; dados pesados = computadas.


- o endereço fica legível;
- os dados mostrados estão sempre atualizados (por exemplo, depois de **Restaurar**).

---

## 1. Filtro por estado

### 1.1. A propriedade e a consulta

Acrescentar a propriedade, depois de `$search`:

```php
    public string $status = 'all';
```

E alterar o início do método `families()`, para escolher a consulta conforme o estado:

```php
    /**
     * The families filtered by status and by the search term.
     *
     * @return LengthAwarePaginator<int, Family>
     */
    #[Computed]
    public function families(): LengthAwarePaginator
    {
        $query = match ($this->status) {
            'active' => Family::query(),
            'trashed' => Family::onlyTrashed(),
            default => Family::withTrashed(),
        };

        return $query
            ->when($this->search !== '', function ($query) {
                // … a pesquisa fica igual …
            })
            ->orderBy('name')
            ->paginate(15);
    }
```

Notar:

| Estado | Consulta | Resultado |
|--------|----------|-----------|
| `active` | `Family::query()` | só as ativas (o `SoftDeletes` já exclui as removidas) |
| `trashed` | `Family::onlyTrashed()` | só as removidas |
| outro valor | `Family::withTrashed()` | todas |

- `match` é a versão moderna do `switch` em PHP: devolve um valor e compara com `===`.

> 📖 **Definição — `match` (revisão)**
> Já usado em 03.2 (`x-button`). Devolve um valor, compara com `===` (comparação estrita) e lança erro se nenhum caso coincidir e não houver `default`. Aqui o `default` cobre o `'all'` e qualquer valor inesperado.

> ⚠️ **Atenção (professor) — Propriedade pública do filtro**
> `$status` é pública: pode ser alterada no browser para qualquer texto. O `default` do `match` evita que valores estranhos causem erro. Princípio recorrente: **não confiar em valores vindos do browser**.

- O `default` também protege contra valores inesperados: como `$status` é uma propriedade pública, alguém pode enviar outro valor a partir do browser. Nesse caso, mostramos simplesmente todas.

> Lembra-se do agrupamento das condições da pesquisa no Script 05 (passo 5.1)? Com `Family::query()`, o `SoftDeletes` acrescenta `deleted_at IS NULL` à consulta. Graças ao agrupamento, o SQL fica `WHERE (name LIKE ? OR description LIKE ?) AND deleted_at IS NULL`, e o filtro funciona corretamente.

> 🎓 **Nota do professor — Pagamento da dívida do exercício 5.1**
> Quem agrupou o `orWhere` no 05 está agora a beneficiar; quem não agrupou verá o filtro «Ativas» devolver removidas quando houver pesquisa (o `OR` anula o `deleted_at IS NULL`). Verificar com a combinação *pesquisa + Removidas* da secção 1.4. Boa demonstração do **porquê** de um exercício anterior.


### 1.2. O campo

Na vista, o cabeçalho da tabela passa a ter o campo de pesquisa **e** o filtro lado a lado. Substituir o `<div>` que contém a pesquisa por:

```blade
        <div class="flex flex-wrap items-center gap-3 border-b border-stone-200 p-4">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Pesquisar por nome ou descrição…"
                class="max-w-sm"
            />

            <flux:select wire:model.live="status" class="max-w-44">
                <flux:select.option value="all">Todas</flux:select.option>
                <flux:select.option value="active">Ativas</flux:select.option>
                <flux:select.option value="trashed">Removidas</flux:select.option>
            </flux:select>
        </div>
```

`wire:model.live` envia o novo valor **assim que a opção muda**.

> ⚠️ **Atenção (professor) — `flux:select` gratuito**
> Aqui usamos o `flux:select` (nativo, gratuito) e não o `listbox`/`select` Pro mais elaborado. Se alguém copiar do site um `flux:select` com `variant="listbox"`, é Pro (ver nota de 03.4).


### 1.3. Voltar à primeira página

**Exercício:** se o administrador estiver na página 2 e escolher **Removidas** (que só ocupam uma página), a tabela fica vazia. Resolver o problema da **mesma forma** que foi resolvido para a pesquisa no Script 05 (método `updatedSearch()`).

> ⏱️ **Ritmo — Exercício 1.3**
> Solução: `public function updatedStatus(): void { $this->resetPage(); }`. Reforça a convenção `updated` + nome da propriedade. Se faltarem páginas para reproduzir o problema, criar famílias fictícias com a factory (e apagá-las depois).


### 1.4. Verificar

- [ ] **Ativas** → só aparecem famílias com o badge **Ativa**.
- [ ] Remover uma família e escolher **Removidas** → aparece só essa, a vermelho.
- [ ] Com **Removidas** escolhido, pesquisar pelo nome dessa família → continua a aparecer; pesquisar outro nome → "Nenhuma família encontrada.".
- [ ] **Todas** → aparecem todas.

---

## 2. O estado do detalhe no endereço: `#[Url]`

### 2.1. A propriedade

Acrescentar o `use`:

```php
use Livewire\Attributes\Url;
```

E a propriedade, depois de `$status`:

```php
    #[Url(as: 'family', except: '')]
    public string $showing = '';
```

| Parte | Significado |
|-------|-------------|
| `#[Url]` | sincroniza a propriedade com a *query string* |
| `as: 'family'` | no endereço, o parâmetro chama-se `family` (e não `showing`) |
| `except: ''` | quando o valor é `''`, o parâmetro **não** aparece no endereço |

> 🎓 **Nota do professor — Porque `except`?**
> Sem `except: ''`, o URL ficaria `/admin/families?family=` mesmo sem família aberta. O `except` diz ao Livewire «o valor por omissão não precisa de aparecer». Mostrar o antes/depois no DevTools ou na barra de endereços.


### 2.2. A família mostrada

Acrescentar uma propriedade computada, depois de `families()`:

```php
    /**
     * The family shown in the show drawer, including a soft deleted one.
     */
    #[Computed]
    public function shownFamily(): ?Family
    {
        return Family::withTrashed()->firstWhere('slug', $this->showing);
    }
```

- O tipo de retorno `?Family` significa "uma `Family` **ou** `null`". Devolve `null` quando nenhuma família tem aquele slug (por exemplo, quando `$showing` é `''`).

> 📖 **Definição — Tipo anulável (`?Family`)**
> O `?` antes do tipo permite `null`. Aqui é essencial: sem família aberta o resultado é `null`. Por isso na vista temos o `@if ($shownFamily = $this->shownFamily)`. Bom momento para reforçar a **tipagem explícita** (convenção do projeto).

- `withTrashed()` permite ver o detalhe de uma família **removida**.
- Na vista, fica disponível como `$this->shownFamily`.

---

## 3. Abrir e fechar o detalhe

Acrescentar os dois métodos seguintes, depois de `updatedSearch()`:

```php
    /**
     * Open the show drawer with the family's details.
     */
    public function show(int $id): void
    {
        $this->showing = Family::withTrashed()->findOrFail($id)->slug;

        Flux::modal('family-show')->show();
    }

    /**
     * Close the show drawer and remove the family from the URL.
     */
    public function closeShow(): void
    {
        $this->showing = '';

        Flux::modal('family-show')->close();
    }
```

- `show()` recebe o `id` (como os outros botões da tabela), mas guarda o **slug**, que é o que aparece no endereço.
- `closeShow()` limpa `$showing` e, por causa de `except: ''`, o parâmetro `?family=…` desaparece do endereço.

**Exercício:** no `show()`, porque é que usamos `withTrashed()->findOrFail()` e não só `findOrFail()`? O que aconteceria ao clicar no nome de uma família removida?

> ⚠️ **Atenção (professor) — Resposta**
> Sem `withTrashed()` o `findOrFail` lançaria 404 ao clicar numa família removida (que também é listada). Compare com `edit()`, onde **não** usamos `withTrashed()` de propósito (não se edita removida). Cada método define a sua regra: a política de «o que é permitido» está em cada ação.


---

## 4. O drawer de detalhe

### 4.1. O nome passa a ser um botão

Na tabela, o nome da família está num `<p>`. Substituí-lo por um `<button>` que chama `show()`, mantendo as classes:

```blade
                                        <button type="button" wire:click="show({{ $family->id }})" @class([
                                            'text-left font-medium hover:underline',
                                            'text-stone-900' => ! $family->trashed(),
                                            'text-red-700 line-through' => $family->trashed(),
                                        ])>{{ $family->name }}</button>
```

> Usamos um `<button>` e não um `<a>` porque a ação é tratada pelo Livewire (`wire:click`) e não é uma navegação para outra página. Um `<button>` também pode ser ativado com o teclado (**Tab** + **Enter**).

> 💬 **Pergunta à turma**
> «`<a>` ou `<button>`?» Esperado: `<a>` navega (muda de URL); `<button>` executa uma ação. Aqui o URL muda por `#[Url]`, mas a ação é do Livewire. Mesma regra de 03.2 (`x-button`). Ligação a acessibilidade: semântica correta = utilizadores de teclado e leitores de ecrã.


### 4.2. O drawer

Acrescentar o drawer de detalhe **antes** do drawer do formulário (`<flux:modal name="family-form" …>`):

```blade
    <flux:modal name="family-show" flyout class="md:w-lg" @close="closeShow">
        @if ($shownFamily = $this->shownFamily)
            <div class="space-y-6">
                {{-- Imagem da família: o carregamento de imagens será implementado numa tarefa futura --}}
                <div class="flex aspect-video flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-stone-300 bg-stone-50 text-stone-400">
                    <flux:icon.photo class="size-10" />
                    <span class="text-sm">Imagem ainda não disponível</span>
                </div>

                <div>
                    <div class="flex items-center gap-3">
                        <flux:heading size="xl">{{ $shownFamily->name }}</flux:heading>

                        {{-- Exercício: badge "Ativa" ou "Removida" --}}
                    </div>
                    <flux:text class="mt-1">{{ $shownFamily->slug }}</flux:text>
                </div>

                <div>
                    <flux:heading>Descrição</flux:heading>
                    <flux:text class="mt-2 whitespace-pre-line">{{ $shownFamily->description ?? 'Sem descrição.' }}</flux:text>
                </div>

                <dl class="grid grid-cols-2 gap-4 rounded-xl bg-stone-50 p-4 text-sm">
                    <div>
                        <dt class="text-stone-500">Criada em</dt>
                        <dd class="font-medium text-stone-900">{{ $shownFamily->created_at?->format('d/m/Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Atualizada em</dt>
                        <dd class="font-medium text-stone-900">{{ $shownFamily->updated_at?->format('d/m/Y H:i') }}</dd>
                    </div>
                    {{-- Exercício: "Removida em", só para famílias removidas --}}
                </dl>

                <div class="flex flex-wrap justify-end gap-2">
                    @if ($shownFamily->trashed())
                        <flux:button icon="arrow-uturn-left" wire:click="restore({{ $shownFamily->id }})">Restaurar</flux:button>
                        <flux:button variant="danger" icon="trash" wire:click="confirmForceDelete({{ $shownFamily->id }})">Eliminar definitivamente</flux:button>
                    @else
                        <flux:button icon="pencil-square" wire:click="edit({{ $shownFamily->id }})">Editar</flux:button>
                        <flux:button variant="danger" icon="trash" wire:click="delete({{ $shownFamily->id }})">Remover</flux:button>
                    @endif

                    <flux:button variant="ghost" wire:click="closeShow">Fechar</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
```

Notar:

- `@close="closeShow"`: o drawer também fecha com a tecla **Esc**, com o **X** e ao clicar fora. Em todos os casos, o Flux dispara o evento `close` e chamamos `closeShow()`, para limpar o endereço.

> 📖 **Definição — `@close` (evento do Flux)**
> `@close="closeShow"` é sintaxe **Alpine.js** (`@evento` = `x-on:evento`) aplicada a um componente Flux. Quando o modal fecha (Esc, X, clique fora), chama o método Livewire `closeShow()`. Primeira vez que Alpine aparece no código dos alunos: dizê-lo, sem aprofundar. Se faltar `@close`, o `?family=…` fica no URL ao fechar com Esc (sintoma na tabela de problemas).

> ⚠️ **Atenção (professor) — `$shownFamily` vs `$family`**
> O nome `$shownFamily` evita colisão com `$family` do `@foreach` da tabela. O `@if ($shownFamily = $this->shownFamily)` é uma atribuição dentro do `@if` (intencional). Pode parecer um erro a quem conhece PHP: explicar que atribui **e** testa.

- `@if ($shownFamily = $this->shownFamily)` faz duas coisas: guarda a família numa variável e só mostra o conteúdo se ela **existir** (não for `null`). Usamos o nome `$shownFamily` para não confundir com a variável `$family` do ciclo da tabela.
- As datas são objetos Carbon (graças aos `timestamps` e ao `SoftDeletes`), por isso podemos usar `format('d/m/Y H:i')`. O `?->` evita um erro se a data for `null`.
- `whitespace-pre-line` mantém as mudanças de linha escritas na descrição.
- Os botões do fundo são **os mesmos da linha da tabela**, e chamam **os mesmos métodos**.
- O marcador da imagem ocupa o espaço que a imagem vai ter quando implementarmos o carregamento.

**Exercício 1:** substituir o comentário do badge pelo mesmo `@if` usado na coluna **Estado** da tabela (badge verde **Ativa** ou vermelho **Removida**).

> ⏱️ **Ritmo — Exercícios 4.2**
> Ambos são «reutilizar o que já fizeste». Se houver pressa, o exercício 2 (removida em) pode ficar para casa. Solução do 2: `@if ($shownFamily->trashed()) <div><dt class="text-red-600">Removida em</dt><dd class="font-medium text-red-700">{{ $shownFamily->deleted_at?->format('d/m/Y H:i') }}</dd></div> @endif`.


**Exercício 2:** substituir o comentário "Removida em" por um terceiro bloco `<div>` com `<dt>` e `<dd>`, que mostra `deleted_at` **só** quando a família está removida. Usar `text-red-600` / `text-red-700` em vez das cores cinzentas.

### 4.3. Verificar

- [ ] Clicar no nome de uma família → abre o drawer de detalhe, à direita.
- [ ] O endereço passa a ter `?family=<slug>`, **sem recarregar** a página.
- [ ] Aparece o marcador "Imagem ainda não disponível", o nome, o slug, a descrição e as datas.
- [ ] **Fechar** → o drawer fecha e o `?family=…` desaparece do endereço.
- [ ] Abrir de novo e fechar com **Esc** → o `?family=…` também desaparece.
- [ ] Abrir o detalhe de uma família **removida** → badge **Removida**, data de remoção, e botões **Restaurar** e **Eliminar definitivamente**.

> ⏱️ **Ritmo — Checkpoint 1**
> Boa altura para pausa. Neste ponto: filtro + detalhe por clique funcionam. A secção 5 traz o conceito de ciclo de vida (`mount`) e é o remate do `#[Url]`.


---

## 5. Abrir o detalhe a partir do endereço

Neste momento, abrir diretamente `http://localhost:8000/admin/families?family=araceae` preenche `$showing`, mas o drawer **não abre**: só `show()` o abre, e ninguém clicou em nada.

O método `mount()` corre **uma vez**, quando a página é carregada (é o "construtor" do componente). Acrescentá-lo **antes** de `families()`:

> 📖 **Definição — `mount()`**
> Método de ciclo de vida chamado **uma vez**, quando o componente é criado (primeira carga). Não corre nos pedidos seguintes de Livewire. Analogia: o construtor. Aqui é o sítio certo para «reagir ao que veio no URL».

> 🎓 **Nota do professor — Porquê não basta `#[Url]`?**
> `#[Url]` preenche `$showing`, mas **abrir um modal é um efeito**; ninguém o chamou. `mount()` corre quando a página é carregada com `?family=` e executa `Flux::modal(...)->show()`. Mostra a separação entre **estado** (propriedade) e **efeito** (abrir o modal).


```php
    /**
     * Open the drawer of the family given in the URL (?family=slug).
     */
    public function mount(): void
    {
        if ($this->showing === '') {
            return;
        }

        if ($this->shownFamily === null) {
            $this->showing = '';

            return;
        }

        Flux::modal('family-show')->show();
    }
```

Os três casos:

| Endereço | O que acontece |
|----------|----------------|
| `/admin/families` | `$showing` é `''` → nada a fazer |
| `/admin/families?family=nao-existe` | não há família com esse slug → limpa `$showing` (e o endereço) e não abre nada |
| `/admin/families?family=araceae` | abre o drawer com a *Araceae* |

> **Porque não devolver 404** quando o slug não existe? A página de famílias existe e funciona. Só o parâmetro é inválido (por exemplo, uma família entretanto eliminada). Mostrar a tabela é mais útil do que um erro.

> 💬 **Pergunta à turma**
> «404 ou ignorar o parâmetro?» Debate curto. O guião opta por ignorar (a página existe; o parâmetro é que está errado). Em páginas **dedicadas a um recurso** (`/families/{slug}`), 404 seria o correto. Distinguir **página** de **parâmetro de filtro**.

> ⚠️ **Atenção (professor) — Segurança do slug no URL**
> Qualquer pessoa pode escrever `?family=…`. Isto é só leitura e protegido por `role:admin` (e middleware persistente). Se fosse público, haveria que decidir se famílias removidas podem ser vistas (aqui usamos `withTrashed`, ok para admin).


### 5.1. Verificar

- [ ] Abrir `http://localhost:8000/admin/families?family=araceae` → a página abre **com o drawer da Araceae aberto**.
- [ ] Recarregar a página (**F5**) → o drawer continua aberto.
- [ ] Copiar o endereço, abri-lo noutro separador → o mesmo drawer.
- [ ] `http://localhost:8000/admin/families?family=nao-existe` → a tabela aparece normalmente e o `?family=…` desaparece do endereço.

> ⏱️ **Ritmo — Demonstração**
> Abrir o URL num separador novo e **partilhá-lo entre alunos** (a ligação funciona se estiverem autenticados como admin). É o *aha moment* da aula: o estado do ecrã viaja no URL.


---

## 6. Ações a partir do detalhe

Os botões do detalhe já funcionam, mas deixam o drawer num estado estranho:

- **Editar** abre o formulário **por cima** do detalhe (dois drawers abertos);
- **Remover** remove a família, mas o detalhe continua aberto.

Queremos que estas ações **fechem o detalhe**. Como `closeShow()` já faz tudo o que é preciso, basta chamá-lo.

> 🎓 **Nota do professor — Reutilizar métodos**
> `closeShow()` faz duas coisas (limpa o estado e fecha o modal). Chamá-lo a partir de `edit()`, `delete()` e `forceDelete()` evita duplicar. É a mesma ideia de «uma fonte de verdade» de 04 (`dashboardRoute`). Sublinhar que métodos `public` podem ser chamados por outros métodos do próprio componente.


### 6.1. Editar

No método `edit()`, chamar `closeShow()` antes de preparar o formulário:

```php
        $family = Family::findOrFail($id);

        $this->closeShow();
        $this->resetForm();
        // …
```

### 6.2. Remover

No método `delete()`, depois de remover:

```php
        Family::findOrFail($id)->delete();

        $this->closeShow();

        Flux::toast(variant: 'warning', text: 'Família removida.');
```

### 6.3. Eliminar definitivamente

**Exercício:** fazer o mesmo no método `forceDelete()`. Depois de uma família ser eliminada, o detalhe não pode continuar aberto: a família já não existe.

### 6.4. E o Restaurar?

**Restaurar** fica como está, **sem** fechar o detalhe. Depois de restaurar, o drawer continua aberto e mostra a família já **Ativa**, com os botões **Editar** e **Remover**. O administrador vê imediatamente o resultado.

> 💬 **Pergunta à turma**
> «Porque é que o detalhe se atualiza sozinho depois de Restaurar?» Esperado: `shownFamily` é computada, logo recalculada em cada pedido, a partir da BD. Se fosse uma propriedade pública com o modelo guardado, ficaria desatualizada. Justificação final da decisão de 0.3.


Isto acontece porque `shownFamily` é uma propriedade computada: é calculada de novo depois do `restore()`, com os dados atualizados.

> Chamar `closeShow()` quando o detalhe **não** está aberto (por exemplo, ao clicar em **Remover** na tabela) não tem qualquer efeito visível. Por isso podemos chamá-lo sempre, sem verificar primeiro.

### 6.5. Verificar

- [ ] Detalhe de uma família ativa → **Editar** → o detalhe fecha e abre o formulário preenchido.
- [ ] Detalhe de uma família ativa → **Remover** → o detalhe fecha, aparece o toast e a linha fica vermelha.
- [ ] Detalhe de uma família removida → **Restaurar** → o detalhe continua aberto, agora com o badge **Ativa**.
- [ ] Detalhe de uma família removida → **Eliminar definitivamente** → abre o modal de confirmação **por cima** do detalhe:
  - [ ] **Cancelar** → volta ao detalhe;
  - [ ] **Eliminar** → os dois fecham, aparece o toast e a família desaparece da tabela.

> ⚠️ **Atenção (professor) — Modais sobrepostos**
> Este é o caso mais delicado: o modal de confirmação (script 06, no layout) abre **por cima** do drawer de detalhe. Ao confirmar, o `forceDelete` chama `closeShow()` e ambos fecham. Se o detalhe ficar aberto e vazio, falta `closeShow()` em `forceDelete()` (exercício 6.3; sintoma na tabela de problemas). Demonstração conjunta recomendada.


---

## 7. Verificação no browser

Como **administrador**:

- [ ] Filtro **Todas / Ativas / Removidas** funciona, também combinado com a pesquisa.
- [ ] Clicar no nome abre o detalhe; o endereço tem `?family=<slug>`.
- [ ] O endereço do detalhe pode ser recarregado e partilhado.
- [ ] Todas as ações do detalhe funcionam e mostram o toast.
- [ ] Criar, editar, remover, restaurar e eliminar **a partir da tabela** continuam a funcionar.

Como **utilizador**:

- [ ] `http://localhost:8000/admin/families?family=araceae` → **403**.

---

## 8. Verificação final e commit

### 8.1. Formatação, análise estática e build

```bash
vendor/bin/pint --dirty
composer run types:check
npm run build
```

### 8.2. Ficheiros alterados

```bash
git status
```

Resultado esperado:

```text
modified:   resources/views/pages/admin/⚡families.blade.php
```

Fazer commit e push com uma mensagem como:

```text
Detalhe das famílias em drawer, endereço partilhável e filtro por estado
```

---

## 9. Resumo

### 9.1. A página de famílias no fim deste script

> 🎓 **Nota do professor — Fecho (≈ 5 min)**
> Pedir que preencham, de memória, a tabela «Parte → estado → está no endereço?» (9.1). Mensagem final: **cada parte do estado tem uma casa certa**: propriedade (privada ao componente), URL (partilhável), BD (persistente), computada (derivada).
> Gancho para o 08: «temos um painel de admin com CRUD completo, mas **qualquer admin pode fazer tudo**. E se quisermos regras por recurso? → **policies**.»


| Parte | Estado (propriedades) | No endereço? |
|-------|-----------------------|--------------|
| Pesquisa | `$search` | não |
| Filtro | `$status` | não |
| Paginação | (trait `WithPagination`) | sim, `?page=` |
| Drawer do formulário | `$editingId`, `$name`, `$slug`, `$description` | não |
| Drawer do detalhe | `$showing` | sim, `?family=` |

| Método | Chamado por |
|--------|-------------|
| `mount()` | carregamento da página (abre o detalhe do endereço) |
| `families()`, `shownFamily()` | a vista (`$this->families`, `$this->shownFamily`) |
| `updatedSearch()`, `updatedStatus()` | alteração da pesquisa / do filtro |
| `show()`, `closeShow()` | clicar no nome / **Fechar**, **Esc**, **X** |
| `create()`, `edit()`, `save()` | drawer do formulário |
| `delete()`, `restore()`, `confirmForceDelete()`, `forceDelete()` | tabela e detalhe |

### 9.2. Conceitos trabalhados

| Conceito | Onde |
|----------|------|
| Expressão `match` | passo 1.1 |
| `onlyTrashed()` / `withTrashed()` num filtro | passo 1.1 |
| `flux:select` com `wire:model.live` | passo 1.2 |
| Estado no endereço com `#[Url]` (`as`, `except`) | secção 2 |
| Propriedade computada que devolve `?Family` | passo 2.2 |
| Dois modais na mesma página | secções 0.2 e 6 |
| Evento `close` do modal (`@close`) | passo 4.2 |
| Formatação de datas com Carbon | passo 4.2 |
| `mount()` como ponto de entrada da página | secção 5 |
| Reutilizar métodos (`closeShow()`) em várias ações | secção 6 |

---

## 10. Problemas comuns

| Sintoma | Causa provável | Solução |
|---------|----------------|---------|
| O filtro não faz nada | Falta `.live` no `wire:model` do `flux:select` | Rever o passo 1.2 |
| Com **Removidas**, a tabela fica vazia até mudar de página | Falta `updatedStatus()` com `resetPage()` | Rever o passo 1.3 |
| Clicar no nome não faz nada | O nome continua num `<p>` sem `wire:click` | Rever o passo 4.1 |
| O drawer abre vazio | `$this->shownFamily` devolve `null`: falta `withTrashed()`, ou `show()` não guarda o slug | Rever os passos 2.2 e 3 |
| O endereço não muda ao abrir o detalhe | Falta `#[Url]` em `$showing`, ou falta o `use Livewire\Attributes\Url;` | Rever o passo 2.1 |
| Aparece `?showing=araceae` em vez de `?family=araceae` | Falta `as: 'family'` | Rever o passo 2.1 |
| Aparece `?family=` (vazio) no endereço | Falta `except: ''` | Rever o passo 2.1 |
| Fechar com **Esc** não limpa o endereço | Falta `@close="closeShow"` no `flux:modal` | Rever o passo 4.2 |
| Abrir o endereço com `?family=…` não abre o drawer | Falta o método `mount()` | Rever a secção 5 |
| `Attempt to read property "name" on null` | O conteúdo do drawer está fora do `@if` | Rever o passo 4.2 |
| **Editar** no detalhe abre o formulário por cima | Falta `$this->closeShow()` em `edit()` | Rever o passo 6.1 |
| Depois de **Eliminar definitivamente**, o detalhe fica aberto e vazio | Falta `$this->closeShow()` em `forceDelete()` | Rever o passo 6.3 |
| Alterações no PHP não têm efeito | Cache das vistas | `php artisan optimize:clear` |
