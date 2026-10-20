# Script 06 — Modal de confirmação com componentes Livewire e eventos

No Script 05 criámos a gestão de famílias. O botão **Eliminar definitivamente** apaga a família **logo**, sem pedir confirmação. É uma operação que **não pode ser desfeita**.

Neste passo vamos:

1. pedir confirmação da forma mais rápida possível, com `wire:confirm`;
2. substituir essa solução por um **modal de confirmação** com o design da aplicação;
3. criar o modal como um **componente Livewire independente e reutilizável**;
4. pôr a página e o modal a comunicar através de **eventos** (`dispatch` e `#[On]`);
5. proteger os dados do modal com `#[Locked]`.

> 🎓 **Nota do professor — Enquadramento (≈ 3 min)**
> Script **curto e conceptual** (≈ 40 min). O objetivo real não é o modal, é perceber **comunicação entre componentes por eventos** e a ideia de **componente reutilizável**. Começar pelo problema: no 05 o «Eliminar definitivamente» apagava logo; pedir a um aluno que clique e diga o que pensa.
> Narrativa em 3 passos: *solução rápida (`wire:confirm`) → solução melhor (modal) → solução reutilizável (modal genérico + eventos)*. É o mesmo padrão de «evolução» que vimos em 03.2 (repetição → componente).


No final teremos:

```text
[Eliminar definitivamente]  (página de famílias)
        │  confirmForceDelete(3)
        │  dispatch('confirm-action', …)
        ▼
┌─────────────────────────────────────────────┐
│ Eliminar definitivamente                     │   ← componente confirm-modal
│ A família «Rosaceae» será eliminada          │      (no layout de administração)
│ definitivamente. Esta operação não pode ser  │
│ desfeita.                                    │
│                        [Cancelar] [Eliminar] │
└─────────────────────────────────────────────┘
        │  [Eliminar] → confirm()
        │  dispatch('family-force-delete-confirmed', id: 3)
        ▼
forceDelete(3)  (página de famílias)  →  toast "Família eliminada definitivamente."
```

---

**Documentação oficial:**

- Livewire — `wire:confirm`: https://livewire.laravel.com/docs/4.x/wire-confirm
- Livewire — Componentes: https://livewire.laravel.com/docs/4.x/components
- Livewire — Eventos: https://livewire.laravel.com/docs/4.x/events
- Livewire — `#[On]`: https://livewire.laravel.com/docs/4.x/attribute-on
- Livewire — `#[Locked]`: https://livewire.laravel.com/docs/4.x/attribute-locked
- Flux — Modal: https://fluxui.dev/components/modal
- PHP — Argumentos com nome e operador `...`: https://www.php.net/manual/en/functions.arguments.php

---

**Índice**

<!-- TOC -->
* [Script 06 — Modal de confirmação com componentes Livewire e eventos](#script-06--modal-de-confirmação-com-componentes-livewire-e-eventos)
  * [Antes de começar](#antes-de-começar)
  * [0. Conceitos](#0-conceitos)
  * [1. A solução rápida: `wire:confirm`](#1-a-solução-rápida-wireconfirm)
  * [2. Criar o componente `confirm-modal`](#2-criar-o-componente-confirm-modal)
  * [3. Colocar o modal no layout de administração](#3-colocar-o-modal-no-layout-de-administração)
  * [4. Ligar a página de famílias ao modal](#4-ligar-a-página-de-famílias-ao-modal)
  * [5. Verificação no browser](#5-verificação-no-browser)
  * [6. Verificação final e commit](#6-verificação-final-e-commit)
  * [7. Resumo](#7-resumo)
  * [8. Problemas comuns](#8-problemas-comuns)
<!-- TOC -->

---

## Antes de começar

### Como utilizar este script

Este script foi escrito para ser seguido **de forma autónoma**, depois da aula.

- Seguir os passos **pela ordem indicada**. Cada passo depende do anterior.
- No fim de cada secção existe um bloco **Verificar**. Não avançar sem confirmar que tudo funciona.
- Os blocos **Exercício** devem ser resolvidos antes de continuar.
- Sempre que existe um comando Artisan para criar um ficheiro, **usá-lo**. Não criar ficheiros manualmente.
- Se algo não funcionar, consultar a secção [8. Problemas comuns](#8-problemas-comuns).

### Pré-requisitos

- Script **05** concluído (gestão de famílias a funcionar, incluindo **Eliminar definitivamente**).

- Serviços Docker em execução:

  ```bash
  docker compose up -d
  ```

- Aplicação em execução num terminal próprio:

  ```bash
  composer run dev
  ```

---

## 0. Conceitos

### 0.1. Duas formas de pedir confirmação

| | `wire:confirm` | Modal (componente Livewire) |
|---|---|---|
| Código | **um atributo** no botão | um componente + eventos |
| Aspeto | caixa nativa do browser (não se pode alterar) | modal Flux com o design da aplicação |
| Texto | fixo no HTML | pode ser calculado no PHP (ex.: nome da família) |
| Reutilização | repetir o atributo em cada botão | um único modal para todas as páginas |

Vamos começar pela solução mais simples (`wire:confirm`) e depois substituí-la pelo modal.

> 💬 **Pergunta à turma**
> «Quando basta `wire:confirm`?» Esperado: protótipos, ferramentas internas, quando o aspeto não importa e o texto é fixo. É uma boa lição de engenharia: **começar pelo mais simples** e só complicar se houver razão (aqui: design, texto dinâmico, reutilização).


### 0.2. Componente de página vs. componente "normal"

Até agora só criámos **páginas** Livewire (`pages::admin.families`), que têm uma rota. O modal é diferente: é um **componente** sem rota, incluído dentro de outra vista com uma *tag*:

> ⚠️ **Atenção (professor) — Já fizemos isto**
> Os alunos já criaram um componente filho em 03.3 (`species-like`) e usaram `dispatch`/`#[On]`. Este script **reaplica** esses conceitos num caso real. Convém dizê-lo: reduz a ansiedade e mostra a coerência do curso.


```blade
<livewire:confirm-modal />
```

| | Página | Componente |
|---|---|---|
| Criado com | `make:livewire pages::admin.families` | `make:livewire confirm-modal` |
| Pasta | `resources/views/pages/` | `resources/views/components/` |
| Como se mostra | uma rota (`Route::livewire`) | uma *tag* (`<livewire:confirm-modal />`) |
| Layout | usa `#[Layout]` | não tem layout: fica dentro de outra vista |

### 0.3. Eventos: como dois componentes comunicam

A página de famílias e o modal são **dois componentes independentes**: cada um tem as suas propriedades e os seus métodos. Um não pode chamar diretamente os métodos do outro. Comunicam através de **eventos**:

| Código | O que faz |
|--------|-----------|
| `$this->dispatch('nome', a: 1, b: 2)` | envia o evento `nome` com dados, com **argumentos com nome** |
| `#[On('nome')]` por cima de um método | esse método corre quando o evento `nome` é recebido; os dados chegam como argumentos (`$a`, `$b`) |

Os eventos passam **pelo browser**: o componente que envia devolve o evento na resposta, e o browser entrega-o aos componentes que o estão a ouvir. Cada receção é **um novo pedido** ao servidor.

> 📖 **Definição — Evento (Livewire)**
> Mensagem nomeada, com dados, enviada por um componente (`dispatch`) e escutada por outros (`#[On]`). **Desacopla** quem pede de quem executa: o modal não sabe nada de famílias.

> 🎓 **Nota do professor — O caminho do evento (importante)**
> Desenhar no quadro: *servidor → resposta com «dispatches» → browser → novo pedido para o componente que ouve*. Os eventos **passam pelo browser** e **cada receção é um pedido novo**. Por isso se veem 4 pedidos na secção 5. Quem pensar que o evento «salta» diretamente entre componentes PHP fica confuso com os pedidos da rede.


O modal não sabe **nada** sobre famílias. Recebe:

- o texto a mostrar;
- o **nome do evento** que deve enviar se o utilizador confirmar;
- os **parâmetros** desse evento.

Quem pede a confirmação decide o que acontece depois. Por isso o mesmo modal vai servir, mais tarde, para eliminar espécies, utilizadores, etc.

### 0.4. O modal não é segurança

O modal serve para **evitar enganos** do administrador. **Não** protege os dados: qualquer pessoa com acesso à página pode enviar um pedido Livewire diretamente, sem passar pelo modal.

A proteção continua onde já estava:

- middleware `role:admin` na rota, e persistente nas ações Livewire (Script 05, secção 3);
- `onlyTrashed()->findOrFail()` no método `forceDelete()`: só uma família **já removida** pode ser eliminada.

> **Regra a reter:** um método com `#[On]` é mais um **ponto de entrada** da aplicação, tal como uma ação `wire:click` ou uma rota. Tem de validar os dados que recebe.

> ⚠️ **Atenção (professor) — O modal não é segurança**
> Ideia a repetir: **UX ≠ segurança**. Perguntar: «se um aluno enviar `family-force-delete-confirmed` à mão com o `id` de uma família ativa, o que acontece?» Esperado: 404 (`onlyTrashed`). A confirmação é só para evitar enganos de um administrador legítimo.
> Liga ao tema de todo o bloco: ações/eventos Livewire são endpoints (scripts 03.3, 04, 05).


---

## 1. A solução rápida: `wire:confirm`

Em `resources/views/pages/admin/⚡families.blade.php`, acrescentar `wire:confirm` ao botão **Eliminar definitivamente**:

```blade
<flux:button size="sm" variant="danger" icon="trash"
    wire:click="forceDelete({{ $family->id }})"
    wire:confirm="Eliminar definitivamente a família {{ $family->name }}? Esta operação não pode ser desfeita.">
    Eliminar definitivamente
</flux:button>
```

### 1.1. Verificar

- [ ] Remover uma família e clicar em **Eliminar definitivamente** → aparece a caixa de confirmação do browser.
- [ ] **Cancelar** → a família continua na tabela.
- [ ] **OK** → a família é eliminada e aparece o toast.

**Exercício:** experimentar a variante `wire:confirm.prompt`, que obriga a escrever uma palavra para confirmar:

```blade
wire:confirm.prompt="Escreva ELIMINAR para confirmar|ELIMINAR"
```

A solução funciona com **uma única linha**. Mas a caixa tem o aspeto do browser (diferente em cada browser) e não segue o design da aplicação. Nos passos seguintes vamos substituí-la por um modal.

**Antes de continuar, retirar o atributo `wire:confirm`** do botão (o botão volta a ficar como no fim do Script 05).

> ⏱️ **Ritmo — Secção 1 (≈ 5 min)**
> Fazer rápido: demonstração com `wire:confirm` e `wire:confirm.prompt`. O `.prompt` (escrever ELIMINAR) é um bom ponto para uma pergunta de UX: «para que operações se justifica?» Depois pedir **explicitamente** que retirem o atributo; se ficar, aparece a caixa do browser **e** o modal (está na tabela de problemas).


---

## 2. Criar o componente `confirm-modal`

### 2.1. Criar o ficheiro

```bash
php artisan make:livewire confirm-modal
```

Resultado esperado:

```text
INFO  Livewire component [resources/views/components/⚡confirm-modal.blade.php] created successfully.
```

Repare na pasta: `components/`, e não `pages/`. Não é uma página, é um componente (secção 0.2).

> ⚠️ **Atenção (professor) — Nome e pasta**
> Sem `pages::`, vai para `components/`. O nome `confirm-modal` (kebab) → tag `<livewire:confirm-modal />`. Erro comum: criar com `pages::` por hábito.


### 2.2. A classe

Substituir a parte PHP do ficheiro (entre `<?php` e `?>`) por:

```php
<?php

use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public string $heading = '';
    public string $message = '';
    public string $confirmLabel = '';

    #[Locked]
    public string $confirmEvent = '';

    /** @var array<string, mixed> */
    #[Locked]
    public array $params = [];

    /**
     * Open the modal with the text and the event to dispatch when the action is confirmed.
     *
     * @param  array<string, mixed>  $params
     */
    #[On('confirm-action')]
    public function open(string $heading, string $message, string $confirmEvent, array $params = [], string $confirmLabel = 'Confirmar'): void
    {
        $this->heading = $heading;
        $this->message = $message;
        $this->confirmEvent = $confirmEvent;
        $this->params = $params;
        $this->confirmLabel = $confirmLabel;

        Flux::modal('confirm-action')->show();
    }

    /**
     * Dispatch the confirmed action's event and close the modal.
     */
    public function confirm(): void
    {
        $this->dispatch($this->confirmEvent, ...$this->params);

        Flux::modal('confirm-action')->close();

        $this->reset();
    }
};
?>
```

Notar:

| Código | Função |
|--------|--------|
| `#[On('confirm-action')]` | `open()` corre sempre que **alguém** envia o evento `confirm-action` |

> 🎓 **Nota do professor — Desenho do modal genérico**
> O modal recebe **três coisas**: texto, **nome do evento** a disparar ao confirmar, e **parâmetros**. É um padrão de *inversão de controlo*: o modal não executa a ação, devolve o controlo a quem pediu. Por isso serve para espécies, utilizadores, etc., sem mudar. Conceito avançado; usar a analogia de um **porteiro**: pergunta «tem a certeza?» e, se sim, avisa quem o chamou.

> 📖 **Definição — Argumentos com nome e operador spread (`...`)**
> `dispatch('x', id: 3)` passa argumentos **pelo nome** (PHP 8). `...$this->params` «espalha» um array associativo (`['id' => 3]`) como argumentos nomeados (`id: 3`). É o que permite ao modal reenviar parâmetros que não conhece.

| `open(string $heading, …)` | os dados do evento chegam como argumentos, **pelo nome** |
| `$confirmEvent`, `$params` | o evento a enviar e os seus dados, guardados até o utilizador confirmar |
| `#[Locked]` | estas propriedades **não podem ser alteradas a partir do browser** (secção 2.3) |
| `Flux::modal('confirm-action')->show()` | abre o modal, tal como fizemos com o drawer |
| `...$this->params` | "espalha" o array em argumentos com nome: `['id' => 3]` passa a `id: 3` |
| `$this->reset()` | volta a pôr todas as propriedades no valor inicial |

**Exercício:** porque é que o argumento se chama `$confirmEvent` e não simplesmente `$event`? (Dica: abrir a documentação ou o código do método `dispatch()` do Livewire e ver o nome do **primeiro** parâmetro. O que acontece quando se passa `...['event' => 'x']` a um método cujo primeiro parâmetro também se chama `$event`?)

> ⚠️ **Atenção (professor) — Resposta do exercício**
> `dispatch($event, ...$params)`: o primeiro parâmetro do Livewire chama-se `$event`. Se `$params` tivesse a chave `event`, o PHP daria «Named parameter $event overwrites previous argument». Daí `confirmEvent`. Pequena lição sobre **colisão de nomes** com argumentos nomeados (está na tabela de problemas).


### 2.3. Porquê `#[Locked]`?

As propriedades públicas de um componente são **enviadas para o browser** e voltam em cada pedido. Qualquer pessoa pode alterá-las nas ferramentas de desenvolvimento do browser.

Sem `#[Locked]`, alguém podia abrir o modal para uma família e, antes de confirmar, trocar `$params` pelo `id` de **outra** família. Com `#[Locked]`, o Livewire recusa o pedido e lança uma exceção.

> 💬 **Pergunta à turma**
> «O que poderia um utilizador malicioso fazer sem `#[Locked]`?» Esperado: alterar `$params`/`$confirmEvent` no pedido e fazer o modal disparar outro evento ou outro `id`. Lembra a nota de 03.3: **propriedades públicas são adulteráveis**; `#[Locked]` é a defesa para valores que só o servidor deve definir.

> 🎓 **Nota do professor — Camadas de defesa**
> `#[Locked]` protege o **modal**; mas o método que recebe o evento (`forceDelete`) **continua a validar** (`onlyTrashed()->findOrFail`). «Defesa em profundidade»: não confiar numa só camada.


> `#[Locked]` só impede alterações feitas **no browser**. O PHP do próprio componente continua a poder alterá-las (é o que faz o método `open()`). E, como vimos em 0.4, os métodos que recebem o evento final continuam a ter de validar os dados.

### 2.4. A vista

Substituir a vista (a parte depois de `?>`) por:

```blade
<div>
    <flux:modal name="confirm-action" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $heading }}</flux:heading>
                <flux:text class="mt-2">{{ $message }}</flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>

                {{-- Exercício: botão de confirmação --}}
            </div>
        </div>
    </flux:modal>
</div>
```

- Sem `flyout`, o `flux:modal` aparece **ao centro** (é um modal, não um drawer).
- `<flux:modal.close>` fecha o modal **sem pedido ao servidor**: cancelar não chama nenhum método PHP.

**Exercício:** substituir o comentário por um `<flux:button>` com `variant="danger"`, que mostra o texto de `$confirmLabel` e chama o método `confirm` quando é clicado.

> ⏱️ **Ritmo — Exercício 2.4**
> Solução: `<flux:button variant="danger" wire:click="confirm">{{ $confirmLabel }}</flux:button>`. Observação: `<flux:modal.close>` fecha **no browser**, sem pedido; o `confirm` faz pedido. Boa pergunta: «porque é que o Cancelar não precisa de método PHP?»


### 2.5. Verificar

Ainda não há nada para ver no browser: o modal não está em nenhuma página. Confirmar apenas que o componente é encontrado:

```bash
php artisan tinker
```

```php
Livewire\Livewire::exists('confirm-modal'); // true
exit
```

---

## 3. Colocar o modal no layout de administração

O modal vai ser usado por **várias** páginas de administração (famílias agora, espécies mais tarde). Em vez de o incluir em cada página, colocamo-lo **uma vez** no layout.

> 🎓 **Nota do professor — Porquê no layout?**
> Reutilização máxima: todas as páginas admin futuras têm o modal sem trabalho extra. Ligação direta ao 03.5/04: o que é comum vive no layout. Cuidado: o modal vive **fora** do `$slot`, mas **dentro** do `<x-layouts::dashboard>`; se o aluno o puser fora do componente, não é renderizado no sítio certo.


Em `resources/views/layouts/admin.blade.php`, no fim do ficheiro, acrescentar a *tag* **depois** do `{{ $slot }}`:

```blade
    {{ $slot }}

    <livewire:confirm-modal />

</x-layouts::dashboard>
```

Qualquer página que use o layout `admin` passa a ter o modal disponível. Fica escondido até receber o evento `confirm-action`.

### 3.1. Verificar

- [ ] `http://localhost:8000/admin/dashboard` e `http://localhost:8000/admin/families` abrem sem erros.
- [ ] Nas ferramentas de desenvolvimento do browser (separador **Elements**/**Inspector**), procurar `data-modal="confirm-action"`: existe **uma** vez em cada página.

> ⏱️ **Ritmo — Checkpoint 1**
> Antes de ligar tudo, confirmar que as páginas abrem sem erro. Se houver erro «Livewire component not found», é o ficheiro mal nomeado. A partir daqui a ligação é pequena (3 alterações); os alunos fazem sozinhos.


---

## 4. Ligar a página de famílias ao modal

### 4.1. Pedir a confirmação

Em `resources/views/pages/admin/⚡families.blade.php`, acrescentar o `use`:

```php
use Livewire\Attributes\On;
```

E acrescentar este método **antes** de `forceDelete()`:

```php
    /**
     * Ask for confirmation before permanently deleting a soft deleted family.
     */
    public function confirmForceDelete(int $id): void
    {
        $family = Family::onlyTrashed()->findOrFail($id);

        $this->dispatch('confirm-action',
            heading: 'Eliminar definitivamente',
            message: "A família «{$family->name}» será eliminada definitivamente. Esta operação não pode ser desfeita.",
            confirmEvent: 'family-force-delete-confirmed',
            params: ['id' => $family->id],
            confirmLabel: 'Eliminar',
        );
    }
```

Notar:

- Os nomes dos argumentos (`heading`, `message`, `confirmEvent`, …) têm de ser **iguais** aos nomes dos parâmetros de `open()` no modal.

> ⚠️ **Atenção (professor) — O «contrato» entre componentes**
> Os nomes dos argumentos do `dispatch` e dos parâmetros de `open()` são um **contrato implícito**: um erro de escrita dá `Unable to resolve dependency [... $heading]`. Não há interface a verificar. Sugerir que se mantenha a documentação desse contrato (ou, no futuro, um componente/ação central).

- A mensagem é construída no PHP e pode incluir o **nome** da família. Com `wire:confirm` o texto estava fixo no HTML.
- `onlyTrashed()->findOrFail()` já garante aqui que a família existe e está removida. Uma família ativa dá **404** e o modal nem abre.

### 4.2. Receber a confirmação

O modal, quando o utilizador confirma, envia o evento `family-force-delete-confirmed` com `id: 3`. O método `forceDelete(int $id)` já existe e já recebe um `$id`.

**Exercício:** fazer com que `forceDelete()` corra quando o evento `family-force-delete-confirmed` é recebido. Basta **uma linha**, por cima do método. O resto do método **não muda**.

> ⏱️ **Ritmo — Exercícios 4.2 e 4.3**
> Soluções: `#[On('family-force-delete-confirmed')]` por cima de `forceDelete()` e `wire:click="confirmForceDelete({{ $family->id }})"` no botão. Insistir: o **método `forceDelete` não muda** → a lógica de negócio está separada da confirmação.


### 4.3. O botão

**Exercício:** alterar o botão **Eliminar definitivamente** para chamar `confirmForceDelete` em vez de `forceDelete`. Retirar também o comentário `{{-- Próxima tarefa: … --}}` que estava por cima do botão.

### 4.4. Verificar

Remover uma família e clicar em **Eliminar definitivamente**:

- [ ] abre o modal **ao centro**, com o título **Eliminar definitivamente** e o nome da família na mensagem;
- [ ] **Cancelar** (ou a tecla **Esc**, ou clicar fora) → o modal fecha e a família continua na tabela;
- [ ] **Eliminar** → o modal fecha, a família desaparece e aparece o toast **Família eliminada definitivamente.**

---

## 5. Verificação no browser

### 5.1. Observar os eventos

Abrir as ferramentas de desenvolvimento do browser, separador **Network** (**Rede**), e filtrar por `update`.

Remover uma família, clicar em **Eliminar definitivamente** e depois em **Eliminar**. Contar os pedidos `update`:

> 🎓 **Nota do professor — Os 4 pedidos**
> É a parte mais reveladora da aula: ver os **4 pedidos** `update` e perceber que cada evento é «ida ao browser e regresso ao servidor». Trade-off a discutir: *mais pedidos* vs *reutilização + design*. Para ações pouco frequentes (eliminar) o custo é irrelevante; para eventos de alta frequência (cada tecla) seria problemático.


| Pedido | Componente | Método | Resultado |
|--------|------------|--------|-----------|
| 1 | página de famílias | `confirmForceDelete(3)` | devolve o evento `confirm-action` |
| 2 | `confirm-modal` | `open(...)` | abre o modal |
| 3 | `confirm-modal` | `confirm()` | devolve o evento `family-force-delete-confirmed` |
| 4 | página de famílias | `forceDelete(3)` | elimina e mostra o toast |

**Exercício:** clicar num dos pedidos, abrir o separador **Payload** e encontrar o nome do método chamado. No separador **Response**, encontrar o evento devolvido (`dispatches`).

> São mais pedidos do que com `wire:confirm`. Em troca, temos um modal reutilizável e com o design da aplicação.

> 💬 **Pergunta à turma — Fecho**
> «Se amanhã quisermos confirmar a remoção de uma espécie, o que alteramos?» Esperado: só a página de espécies (um `dispatch` e um `#[On]`); o modal **não muda**. É a prova de que o desenho está certo.
> Gancho para o 07: «a tabela já gere famílias; agora vamos ver uma família em detalhe e partilhar o seu URL (`/admin/families?family=rosaceae`)».


### 5.2. Lista final

Como **administrador**:

- [ ] **Eliminar definitivamente** abre o modal com o nome da família.
- [ ] **Cancelar** não elimina nada.
- [ ] **Eliminar** elimina e mostra o toast.
- [ ] Criar, editar, remover e restaurar continuam a funcionar como no Script 05.
- [ ] O modal também existe no painel de administração (vazio e escondido).

Como **utilizador**:

- [ ] `http://localhost:8000/admin/families` → **403**.

---

## 6. Verificação final e commit

### 6.1. Formatação, análise estática e build

```bash
vendor/bin/pint --dirty
composer run types:check
npm run build
```

### 6.2. Ficheiros alterados

```bash
git status
```

Resultado esperado:

```text
modified:   resources/views/layouts/admin.blade.php
modified:   resources/views/pages/admin/⚡families.blade.php

new:        resources/views/components/⚡confirm-modal.blade.php
```

Confirmar que **não** ficou nenhum `wire:confirm` no botão (passo 1):

```bash
# macOS / Linux
grep -rnE "wire:confirm[=.]" resources/views

# Windows (PowerShell)
Get-ChildItem resources\views -Recurse -File | Select-String -Pattern "wire:confirm[=.]"
```

Não deve haver resultados. (O padrão `[=.]` evita encontrar `<livewire:confirm-modal />`, que também contém o texto `wire:confirm`.)

Fazer commit e push com uma mensagem como:

```text
Modal de confirmação reutilizável para eliminar famílias definitivamente
```

---

## 7. Resumo

### 7.1. O fluxo completo

```text
families: [Eliminar definitivamente]
    │ wire:click="confirmForceDelete(3)"
    ▼
families::confirmForceDelete(3)
    │ onlyTrashed()->findOrFail(3)            ← valida
    │ dispatch('confirm-action', heading, message, confirmEvent, params, confirmLabel)
    ▼
confirm-modal::open(...)          #[On('confirm-action')]
    │ guarda confirmEvent e params (#[Locked])
    │ Flux::modal('confirm-action')->show()
    ▼
[Cancelar] → fecha no browser, sem pedido
[Eliminar] → confirm-modal::confirm()
    │ dispatch('family-force-delete-confirmed', id: 3)
    ▼
families::forceDelete(3)          #[On('family-force-delete-confirmed')]
    │ onlyTrashed()->findOrFail(3)->forceDelete()   ← valida outra vez
    ▼
Flux::toast('Família eliminada definitivamente.')
```

### 7.2. Onde está cada responsabilidade

| Responsabilidade | Ficheiro |
|------------------|----------|
| Mostrar o modal e devolver a confirmação | `resources/views/components/⚡confirm-modal.blade.php` |
| Disponibilizar o modal em todas as páginas de administração | `resources/views/layouts/admin.blade.php` |
| Decidir **o que** confirmar e o texto a mostrar | `families::confirmForceDelete()` |
| Executar a operação (e validar) | `families::forceDelete()` |

### 7.3. Para reutilizar noutra página

Numa futura página de espécies, bastará:

```php
$this->dispatch('confirm-action',
    heading: '…',
    message: '…',
    confirmEvent: 'species-force-delete-confirmed',
    params: ['id' => $species->id],
    confirmLabel: 'Eliminar',
);
```

e um método com `#[On('species-force-delete-confirmed')]`. O modal **não muda**.

### 7.4. Conceitos trabalhados

| Conceito | Onde |
|----------|------|
| `wire:confirm` e `wire:confirm.prompt` | passo 1 |
| Componente Livewire (sem rota) vs. página | secção 0.2, passo 2.1 |
| Incluir um componente numa vista (`<livewire:… />`) | passo 3 |
| Eventos: `dispatch()` com argumentos com nome | passos 2.2 e 4.1 |
| Ouvir eventos: `#[On]` | passos 2.2 e 4.2 |
| Operador `...` com arrays associativos | passo 2.2 |
| `#[Locked]` | passo 2.3 |
| Modal Flux centrado vs. flyout | passo 2.4 |
| Um modal não é um mecanismo de segurança | secção 0.4 |
| Pedidos Livewire nas ferramentas do browser | passo 5.1 |

### 7.5. Comandos utilizados

```bash
php artisan make:livewire confirm-modal
php artisan tinker

vendor/bin/pint --dirty
composer run types:check
npm run build
```

---

## 8. Problemas comuns

| Sintoma | Causa provável | Solução |
|---------|----------------|---------|
| `Unable to find component: [confirm-modal]` | O ficheiro não está em `resources/views/components/` ou não tem o `⚡` | Criar com o comando do passo 2.1 |
| O botão não abre o modal (e não há erros) | Falta `<livewire:confirm-modal />` no layout, ou o nome do evento é diferente em `dispatch()` e em `#[On]` | Rever o passo 3 e confirmar `confirm-action` nos dois lados |
| `Unable to resolve dependency [Parameter #0 [ <required> string $heading ]]` | Os nomes dos argumentos em `dispatch()` não coincidem com os parâmetros de `open()` | Rever o passo 4.1 |
| `Named parameter $event overwrites previous argument` | Um argumento chama-se `event` e colide com o primeiro parâmetro de `dispatch()` | Usar `confirmEvent` (passo 2.2) |
| O modal abre mas **Eliminar** não faz nada | Falta `#[On('family-force-delete-confirmed')]` em `forceDelete()`, ou falta o `use Livewire\Attributes\On;` | Rever o passo 4.2 |
| O modal abre sem título nem mensagem | O `dispatch()` foi feito sem os argumentos, ou `open()` não os guarda nas propriedades | Rever os passos 2.2 e 4.1 |
| `Cannot update locked property` | Algo no browser tentou alterar `confirmEvent` ou `params` (ex.: um `wire:model` nessas propriedades) | Não ligar essas propriedades a campos: são definidas apenas no PHP |
| O modal aparece como painel lateral | Ficou o atributo `flyout` no `flux:modal` | Retirar `flyout` (passo 2.4) |
| Continua a aparecer a caixa do browser | Ficou o `wire:confirm` do passo 1 | Retirar o atributo (passo 6.2) |
| **Eliminar definitivamente** dá 404 | A família não está removida | Comportamento esperado: remover primeiro |
| Alterações no PHP não têm efeito | Cache das vistas | `php artisan optimize:clear` |
