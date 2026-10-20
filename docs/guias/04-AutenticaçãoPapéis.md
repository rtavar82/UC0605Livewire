# Guia 04 — Autenticação, papéis e redirecionamento

No Guia 03.5 criámos os templates da aplicação. Os painéis de utilizador e de administração eram **públicos** e mostravam dados fictícios.

Neste passo vamos:

1. instalar o pacote **Spatie Laravel Permission**;
2. criar os papéis (_roles_) `admin` e `user`;
3. criar utilizadores de desenvolvimento, um para cada papel;
4. atribuir automaticamente o papel `user` a quem se regista;
5. proteger as áreas da aplicação com **middleware**;
6. enviar cada utilizador para o **seu painel** depois de iniciar sessão;
7. mostrar o nome e as iniciais do utilizador autenticado nos painéis e ativar o botão **Terminar sessão**;
8. aplicar o design aprovado às **páginas de autenticação**.

No final teremos:

```text
Visitante (guest)          → sem sessão iniciada
   │  /                     → página inicial (pública)
   │  /login, /register     → páginas de autenticação
   │
   ├── Iniciar sessão como user
   │      → /dashboard                  (auth + verified + role:user)
   │      → /admin/...   ✗ 403
   │
   └── Iniciar sessão como admin
          → /admin/dashboard            (auth + verified + role:admin)
          → /dashboard   ✗ 403
```

---

**Documentação oficial:**

- Spatie Laravel Permission: https://spatie.be/docs/laravel-permission/v8/introduction
- Spatie — Instalação: https://spatie.be/docs/laravel-permission/v8/installation-laravel
- Spatie — Middleware: https://spatie.be/docs/laravel-permission/v8/basic-usage/middleware
- Laravel — Autenticação: https://laravel.com/docs/13.x/authentication
- Laravel — Middleware: https://laravel.com/docs/13.x/middleware
- Laravel — Grupos de rotas: https://laravel.com/docs/13.x/routing#route-groups
- Laravel — Seeders: https://laravel.com/docs/13.x/seeding
- Laravel Fortify: https://laravel.com/docs/13.x/fortify

---

**Índice**

<!-- TOC -->
* [Guia 04 — Autenticação, papéis e redirecionamento](#guia-04--autenticação-papéis-e-redirecionamento)
  * [Antes de começar](#antes-de-começar)
  * [0. Conceitos](#0-conceitos)
  * [1. Instalar o Spatie Laravel Permission](#1-instalar-o-spatie-laravel-permission)
  * [2. Preparar o modelo `User`](#2-preparar-o-modelo-user)
  * [3. Criar os papéis e os utilizadores de desenvolvimento](#3-criar-os-papéis-e-os-utilizadores-de-desenvolvimento)
  * [4. Atribuir o papel `user` no registo](#4-atribuir-o-papel-user-no-registo)
  * [5. Registar o middleware `role`](#5-registar-o-middleware-role)
  * [6. Proteger as rotas](#6-proteger-as-rotas)
  * [7. Redirecionar para o painel certo](#7-redirecionar-para-o-painel-certo)
  * [8. Mostrar o utilizador autenticado e terminar sessão](#8-mostrar-o-utilizador-autenticado-e-terminar-sessão)
  * [9. Layout das páginas de autenticação](#9-layout-das-páginas-de-autenticação)
  * [10. Início de sessão rápido (apenas desenvolvimento)](#10-início-de-sessão-rápido-apenas-desenvolvimento)
  * [11. Verificação no browser](#11-verificação-no-browser)
  * [12. Verificação final e commit](#12-verificação-final-e-commit)
  * [13. Resumo](#13-resumo)
  * [14. Problemas comuns](#14-problemas-comuns)
<!-- TOC -->

---

## Antes de começar

### Como utilizar este guia

Este guia foi escrito para ser seguido **de forma autónoma**, depois da aula.

- Seguir os passos **pela ordem indicada**. Cada passo depende do anterior.
- No fim de cada secção existe um bloco **Verificar**. Não avançar sem confirmar que tudo funciona.
- Os blocos **Exercício** devem ser resolvidos antes de continuar.
- Sempre que existe um comando Artisan para criar um ficheiro, **usá-lo**. Não criar ficheiros manualmente.
- Se algo não funcionar, consultar a secção [14. Problemas comuns](#14-problemas-comuns).

### Pré-requisitos

- Guia **03** concluído.

- Serviços Docker em execução:

  ```bash
  docker compose up -d
  ```

- Aplicação em execução num terminal próprio:

  ```bash
  composer run dev
  ```

- Protótipos disponíveis em `docs/resources/layout/` (vamos usar `login.html` e `register.html`).

Todos os comandos `php artisan …` e `composer …` são executados noutro terminal, **na raiz do projeto**.

---

## 0. Conceitos

### 0.1. Autenticação vs. autorização

| Conceito         | Pergunta                        | No FloraHub                         |
|------------------|---------------------------------|-------------------------------------|
| **Autenticação** | *Quem é este utilizador?*       | Fortify (login, registo, 2FA…)      |
| **Autorização**  | *O que pode este utilizador fazer?* | Papéis `admin` e `user` (Spatie) |

A autenticação **já existe** desde o Guia 01 (Starter Kit + Fortify). Neste guia **não** vamos reescrevê-la: vamos acrescentar a autorização por papéis e ajustar o que acontece **depois** do login.

> Os testes automáticos não fazem parte deste guia. Se executar `php artisan test`, alguns testes do Starter Kit (ex.: `DashboardTest`, `RegistrationTest`) vão falhar, porque ainda não conhecem os papéis. É o comportamento esperado.

### 0.2. Os papéis da aplicação

A aplicação tem exatamente **dois papéis**:

| Papel   | Descrição                                 | Área             |
|---------|-------------------------------------------|------------------|
| `admin` | Administrador da aplicação                | `/admin/...`     |
| `user`  | Utilizador normal                         | `/dashboard`, …  |

> **Visitante (guest) não é um papel.** Um visitante é simplesmente alguém que **não iniciou sessão**. O Laravel já distingue esta situação com os middlewares `auth` (exige sessão) e `guest` (exige que **não** exista sessão). Nunca se cria um papel `guest`.

### 0.3. Só papéis, sem permissões

O Spatie permite trabalhar com **papéis** (_roles_) e **permissões** (_permissions_). Neste projeto, por agora, usamos **apenas papéis**. Não vamos criar permissões.

### 0.4. Convenção de URLs

- Toda a área de administração começa por **`/admin/`** e as rotas têm nomes que começam por **`admin.`**.
- A área do utilizador **não** tem prefixo (ex.: `/dashboard`).

---

## 1. Instalar o Spatie Laravel Permission

### 1.1. Instalar o pacote

```bash
composer require spatie/laravel-permission
```

Confirmar a versão instalada:

```bash
composer show spatie/laravel-permission
```

Este guia foi escrito para a versão **8.x**.

### 1.2. Publicar a configuração e a migration

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

Resultado esperado (a data no nome da migration será diferente):

```text
Copying file [.../config/permission.php] to [config/permission.php]  DONE
Copying file [.../create_permission_tables.php.stub] to [database/migrations/2026_09_30_154148_create_permission_tables.php]  DONE
```

Foram criados:

```text
config/permission.php
database/migrations/xxxx_xx_xx_xxxxxx_create_permission_tables.php
```

**Exercício:** abrir a migration criada e identificar as **cinco tabelas** que vai criar. Para que serve cada uma?

| Tabela                   | Função                                                  |
|--------------------------|---------------------------------------------------------|
| `roles`                  | lista de papéis (`admin`, `user`)                       |
| `permissions`            | lista de permissões (não usada neste projeto)           |
| `model_has_roles`        | liga utilizadores a papéis                              |
| `model_has_permissions`  | liga utilizadores diretamente a permissões (não usada)  |
| `role_has_permissions`   | liga papéis a permissões (não usada)                    |

### 1.3. Executar a migration

Limpar a configuração em cache e executar a migration:

```bash
php artisan config:clear
php artisan migrate
```

Resultado esperado:

```text
xxxx_xx_xx_xxxxxx_create_permission_tables ....... DONE
```

> `php artisan migrate` executa **apenas as migrations novas**. Os dados existentes (utilizadores, etc.) **não** são apagados. **Nunca** usar `migrate:fresh` para "resolver" problemas: esse comando apaga todas as tabelas.

### 1.4. Verificar

```bash
php artisan migrate:status
```

A migration `create_permission_tables` deverá aparecer como `Ran`.

---

## 2. Preparar o modelo `User`

### 2.1. Adicionar o trait `HasRoles`

Abrir `app/Models/User.php`.

Acrescentar o `use` do trait, junto dos restantes:

```php
use Spatie\Permission\Traits\HasRoles;
```

e acrescentá-lo à lista de traits da classe:

```php
use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;
```

O trait `HasRoles` acrescenta ao modelo métodos como:

| Método                    | Exemplo                            |
|---------------------------|------------------------------------|
| `assignRole()`            | `$user->assignRole('user')`        |
| `syncRoles()`             | `$user->syncRoles('admin')` (substitui todos os papéis) |
| `hasRole()`               | `$user->hasRole('admin')` → `true`/`false` |
| `roles`                   | `$user->roles` (relação Eloquent)  |

### 2.2. Saber qual é o painel de cada utilizador

A decisão "para que painel vai este utilizador?" vai ser usada em **dois sítios** (depois do login e quando um utilizador autenticado tenta abrir `/login`). Para não repetir a mesma verificação, colocamo-la **num único método** do modelo.

No fim da classe `User`, depois do método `initials()`, acrescentar:

```php
    /**
     * Get the name of the dashboard route for the user's role.
     */
    public function dashboardRoute(): string
    {
        return $this->hasRole('admin') ? 'admin.dashboard' : 'dashboard';
    }
```

O método devolve o **nome** da rota, não o URL. Assim, se um URL mudar, basta alterar `routes/web.php`.

### 2.3. Verificar

```bash
php artisan tinker
```

```php
$u = App\Models\User::first();
$u->roles;            // coleção vazia: ainda não há papéis
$u->dashboardRoute(); // "dashboard"
exit
```

---

## 3. Criar os papéis e os utilizadores de desenvolvimento

### 3.1. Seeder dos papéis

Criar o seeder:

```bash
php artisan make:seeder RoleSeeder
```

Foi criado `database/seeders/RoleSeeder.php`. Substituir o conteúdo por:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Create the application roles.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('admin');
        Role::findOrCreate('user');
    }
}
```

Notar:

- `findOrCreate()` só cria o papel se ainda **não existir**. Podemos executar o seeder várias vezes sem criar duplicados.
- O Spatie guarda os papéis e permissões em **cache**. `forgetCachedPermissions()` limpa essa cache antes de criar os papéis, como recomenda a documentação.
- **Não** existe papel `guest`.

### 3.2. Seeder dos utilizadores de desenvolvimento

Para testar as duas áreas precisamos de um utilizador de cada papel. Criar o seeder:

```bash
php artisan make:seeder DevelopmentUserSeeder
```

Substituir o conteúdo de `database/seeders/DevelopmentUserSeeder.php` por:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DevelopmentUserSeeder extends Seeder
{
    /**
     * Create one user for each role, for local development only.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@florahub.test'],
            ['name' => 'Rui Mendes', 'password' => 'password'],
        );
        $admin->markEmailAsVerified(); //Para marcar o email como verificado
        $admin->syncRoles('admin');

        // Exercício: criar o utilizador user@florahub.test
    }
}
```

Analisar:

| Instrução | O que faz |
|-----------|-----------|
| `firstOrCreate([...], [...])` | procura pelo email; se não existir, cria com o nome e a palavra-passe indicados |
| `'password' => 'password'` | o modelo `User` tem o cast `'password' => 'hashed'`, por isso a palavra-passe é guardada **encriptada** |
| `markEmailAsVerified()` | marca o email como verificado (as rotas usam o middleware `verified`) |
| `syncRoles('admin')` | garante que o utilizador tem **apenas** o papel `admin` |

**Exercício:** substituir o comentário pelo código que cria o utilizador:

```text
Email:          user@florahub.test
Nome:           Sofia Almeida
Palavra-passe:  password
Papel:          user
```

(seguir exatamente o modelo do administrador.)

> Estes utilizadores e palavras-passe são **apenas para desenvolvimento**. Nunca devem existir em produção.

### 3.3. Atualizar o `DatabaseSeeder`

Abrir `database/seeders/DatabaseSeeder.php`. Neste momento cria um utilizador `test@example.com`. Substituir o método `run()` por:

```php
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        if (app()->isLocal()) {
            $this->call(DevelopmentUserSeeder::class);
        }
    }
```

Remover também o `use App\Models\User;` que deixou de ser usado.

- `RoleSeeder` é executado **sempre**: a aplicação precisa dos papéis em qualquer ambiente.
- `DevelopmentUserSeeder` só é executado quando `APP_ENV=local`: os utilizadores de desenvolvimento **não** chegam à produção.

### 3.4. Executar os seeders

```bash
php artisan db:seed
```

Resultado esperado:

```text
Database\Seeders\RoleSeeder ............. DONE
Database\Seeders\DevelopmentUserSeeder ... DONE
```

> `db:seed` **não apaga** dados. Como os seeders usam `findOrCreate` e `firstOrCreate`, pode ser executado mais do que uma vez.

### 3.5. Verificar

Ver os papéis criados:

```bash
php artisan permission:show
```

Ver os utilizadores e respetivos papéis:

```bash
php artisan tinker
```

```php
App\Models\User::with('roles')->get()->map(fn ($u) => $u->email.' => '.$u->roles->pluck('name')->join(','));
exit
```

Resultado esperado (entre outros utilizadores que já existam):

```text
"admin@florahub.test => admin"
"user@florahub.test => user"
```

### 3.6. Utilizadores criados antes deste guia

Os utilizadores que já existiam (por exemplo, `test@example.com` ou a conta criada no Guia 02) **não têm papel**. Depois do passo 6, ao iniciarem sessão, receberão o erro **403**.

Para lhes atribuir o papel `user`, usar o comando do Spatie:

```bash
php artisan permission:assign-role user 1
```

(o último valor é o `id` do utilizador; consultar os ids com `php artisan tinker` → `App\Models\User::pluck('email', 'id');`)

---

## 4. Atribuir o papel `user` no registo

Quem cria conta através da página de registo deve ficar com o papel `user`.

O registo é tratado pelo Fortify, mas a criação do utilizador está na **ação** do projeto:

```text
app/Actions/Fortify/CreateNewUser.php
```

### 4.1. Alterar a ação

Acrescentar o `use` da facade `DB`:

```php
use Illuminate\Support\Facades\DB;
```

Substituir o `return User::create([...]);` por:

```php
        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $user->assignRole('user');

            return $user;
        });
```

**Porquê uma transação?** Criar o utilizador e atribuir-lhe o papel são **duas escritas** na base de dados. Se a segunda falhar (por exemplo, porque o papel `user` não existe), a transação desfaz também a primeira. Nunca fica um utilizador **sem papel**.

> A validação (`Validator::make(...)`) mantém-se exatamente igual. Não estamos a alterar a autenticação, apenas a completar a criação do utilizador.

### 4.2. Verificar

O registo será verificado no browser no passo 11.

---

## 5. Registar o middleware `role`

O Spatie fornece o middleware `RoleMiddleware`, que verifica se o utilizador autenticado tem um determinado papel. Para o usarmos nas rotas como `role:admin`, é necessário registar o **alias** `role`.

Abrir `bootstrap/app.php`.

Acrescentar, junto dos restantes `use`:

```php
use Spatie\Permission\Middleware\RoleMiddleware;
```

Substituir:

```php
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
```

por:

```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);
    })
```

(no passo 7.4 vamos acrescentar mais uma linha a este bloco.)

Quando o utilizador **não** tem o papel exigido, o middleware responde com **403 — Forbidden**.

---

## 6. Proteger as rotas

### 6.1. Nova organização de `routes/web.php`

Abrir `routes/web.php`. Neste momento tem:

- a rota `home`;
- as rotas **temporárias** do Guia 03.5 (`user/dashboard` e `admin/dashboard`, públicas);
- a rota `dashboard` do Starter Kit (que mostra a vista `dashboard.blade.php`).

Substituir **todo** o conteúdo por:

```php
<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::middleware(['auth', 'verified', 'role:user'])->group(function () {
    Route::view('dashboard', 'user.dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::view('dashboard', 'admin.dashboard')->name('dashboard');
    });

require __DIR__.'/settings.php';
```

Analisar:

| Parte | Significado |
|-------|-------------|
| `auth` | exige sessão iniciada; um visitante é enviado para `/login` |
| `verified` | exige email verificado |
| `role:user` / `role:admin` | exige o papel indicado; caso contrário, **403** |
| `->prefix('admin')` | todos os URLs do grupo começam por `/admin/` |
| `->name('admin.')` | todos os nomes do grupo começam por `admin.` |

Dentro do grupo de administração, a rota escrita como `'dashboard'` fica, na realidade, com:

```text
URL:   /admin/dashboard
Nome:  admin.dashboard
```

Qualquer rota que se acrescente no futuro a este grupo (ex.: `species`) fica automaticamente protegida e com o prefixo: `/admin/species`, `admin.species`.

> A rota do utilizador passa a ser a própria rota `dashboard`, que é o destino por omissão do Fortify (`'home' => '/dashboard'` em `config/fortify.php`). Por isso o **registo** e a **verificação de email** continuam a funcionar sem alterações.

### 6.2. Remover a vista antiga do Starter Kit

A vista `resources/views/dashboard.blade.php` (o painel "placeholder" do Starter Kit) deixou de ser usada: a rota `dashboard` mostra agora `user/dashboard.blade.php`.

```bash
git rm resources/views/dashboard.blade.php
```

### 6.3. Atualizar o link do layout do utilizador

A rota `user.dashboard` deixou de existir. Abrir `resources/views/layouts/user.blade.php` e, no link **Painel**, substituir as **três** ocorrências de `'user.dashboard'` por `'dashboard'`:

```blade
<a href="{{ route('dashboard') }}" @class([
    'flex items-center gap-3 rounded-lg border-l-2 px-3 py-2 text-sm font-medium',
    'border-brand-600 bg-brand-50 text-brand-800' => request()->routeIs('dashboard'),
    'border-transparent text-stone-600 hover:bg-stone-50' => ! request()->routeIs('dashboard'),
])>
```

Confirmar que não ficou nenhuma referência:

**macOS / Linux**

```bash
grep -rn "user.dashboard" resources routes
```

**Windows PowerShell**

```powershell
Get-ChildItem resources, routes -Recurse -File | Select-String -Pattern "user.dashboard"
```

Não deverá haver resultados.

### 6.4. Verificar

```bash
php artisan route:list --name=dashboard -v
```

A opção `-v` mostra também os middlewares, com o nome completo da classe de cada alias (`auth`, `verified`, `role`). Deverão aparecer:

```text
GET|HEAD  admin/dashboard .. admin.dashboard
 ⇂ web
 ⇂ Illuminate\Auth\Middleware\Authenticate                 ← auth
 ⇂ Illuminate\Auth\Middleware\EnsureEmailIsVerified        ← verified
 ⇂ Spatie\Permission\Middleware\RoleMiddleware:admin       ← role:admin
GET|HEAD  dashboard .. dashboard
 ⇂ web
 ⇂ Illuminate\Auth\Middleware\Authenticate
 ⇂ Illuminate\Auth\Middleware\EnsureEmailIsVerified
 ⇂ Spatie\Permission\Middleware\RoleMiddleware:user
```

No browser, **sem sessão iniciada** (usar uma janela privada):

- [ ] `http://localhost:8000/dashboard` → é enviado para `/login`;
- [ ] `http://localhost:8000/admin/dashboard` → é enviado para `/login`.

---

## 7. Redirecionar para o painel certo

### 7.1. O problema

Depois de iniciar sessão, o Fortify envia **sempre** o utilizador para o valor de `home` em `config/fortify.php`:

```php
'home' => '/dashboard',
```

Para um administrador, isso resultaria num **403**, porque `/dashboard` exige o papel `user`.

### 7.2. Como o Fortify responde a um login

O Fortify não tem o redirecionamento "escrito" no controlador. Depois do login, pede ao **service container** um objeto que implemente o contrato:

```text
Laravel\Fortify\Contracts\LoginResponse
```

e usa-o para criar a resposta. Por omissão, esse objeto é a classe `Laravel\Fortify\Http\Responses\LoginResponse`.

Se registarmos **a nossa própria classe** para esse contrato, o Fortify passa a usá-la. Não alteramos o Fortify, apenas **substituímos uma peça**.

Existe um segundo contrato, `TwoFactorLoginResponse`, usado quando o utilizador tem **autenticação de dois fatores** ativa e conclui o código. Vamos usar a mesma classe para os dois casos.

### 7.3. Criar a resposta

Criar a classe com o Artisan:

```bash
php artisan make:class Http/Responses/LoginResponse
```

Foi criado `app/Http/Responses/LoginResponse.php`. Substituir o conteúdo por:

```php
<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

class LoginResponse implements LoginResponseContract, TwoFactorLoginResponseContract
{
    /**
     * Redirect the authenticated user to the dashboard of their role.
     *
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return redirect()->intended(route($user->dashboardRoute()));
    }
}
```

Analisar:

- `implements LoginResponseContract, TwoFactorLoginResponseContract`: a classe cumpre os **dois contratos**;
- `$user->dashboardRoute()`: o método criado no passo 2.2 decide o painel;
- `redirect()->intended(...)`: se o utilizador tinha tentado abrir uma página protegida antes do login, volta para essa página. Caso contrário, vai para o seu painel.

> Exemplo de `intended`: um visitante abre `/settings/profile`, é enviado para o login e, depois de iniciar sessão, regressa a `/settings/profile` em vez de ir para o painel.

### 7.4. Registar a resposta no Fortify

Abrir `app/Providers/FortifyServiceProvider.php`.

Acrescentar os `use`:

```php
use App\Http\Responses\LoginResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
```

Substituir o método `register()`:

```php
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(TwoFactorLoginResponseContract::class, LoginResponse::class);
    }
```

Leitura: *"sempre que alguém pedir um `LoginResponseContract` (ou um `TwoFactorLoginResponseContract`), entregar um `App\Http\Responses\LoginResponse`"*.

### 7.5. Utilizador autenticado a abrir `/login`

As páginas `/login` e `/register` usam o middleware `guest`. Se um utilizador **já autenticado** as abrir, o Laravel redireciona-o, por omissão, para `/dashboard`. Para um administrador, isso daria **403**.

Abrir novamente `bootstrap/app.php` e acrescentar ao bloco `withMiddleware`, **depois** do `alias`:

```php
        $middleware->redirectUsersTo(fn (Request $request) => route($request->user()->dashboardRoute()));
```

O bloco completo fica:

```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);

        $middleware->redirectUsersTo(fn (Request $request) => route($request->user()->dashboardRoute()));
    })
```

Repare que a decisão continua a estar **num único sítio**: o método `dashboardRoute()` do modelo `User`.

### 7.6. Verificar

A verificação completa é feita no passo 11. Para já, confirmar que a aplicação continua a arrancar:

```bash
php artisan route:list --name=login
```

---

## 8. Mostrar o utilizador autenticado e terminar sessão

Os painéis já só são acessíveis com sessão iniciada, por isso podemos substituir o nome e as iniciais fictícios pelos do utilizador autenticado.

Neste passo alteramos **o nome, as iniciais e o botão "Terminar sessão"**. Os restantes conteúdos dos painéis continuam estáticos.

O modelo `User` do Starter Kit já tem o método `initials()`:

```php
auth()->user()->name        // "Sofia Almeida"
auth()->user()->initials()  // "SA"
```

### 8.1. Layout do utilizador

Em `resources/views/layouts/user.blade.php`, substituir:

| Antes            | Depois                                  | Ocorrências |
|------------------|-----------------------------------------|-------------|
| `SA`             | `{{ auth()->user()->initials() }}`      | 2 (barra lateral e menu do perfil) |
| `Sofia Almeida`  | `{{ auth()->user()->name }}`            | 1 (barra lateral) |

### 8.2. Layout da administração

**Exercício:** fazer o mesmo em `resources/views/layouts/admin.blade.php` para `RM` e `Rui Mendes`.

### 8.3. Mensagem de boas-vindas

Em `resources/views/user/dashboard.blade.php`, substituir:

```blade
<h2 class="text-2xl font-semibold tracking-tight text-stone-900">Bem-vinda de volta, Sofia</h2>
```

por:

```blade
<h2 class="text-2xl font-semibold tracking-tight text-stone-900">Bem-vindo de volta, {{ auth()->user()->name }}</h2>
```

### 8.4. Terminar sessão

Nos layouts do Guia 03.5, "Terminar sessão" é um link `<a href="#">`, que não faz nada.

O Fortify já disponibiliza a rota `logout`:

```bash
php artisan route:list --name=logout
```

```text
POST  logout .. logout › Laravel\Fortify › AuthenticatedSessionController@destroy
```

Repare que é uma rota **POST**. Terminar sessão altera o estado do servidor, por isso:

- **não** pode ser um link (`<a href>` faz sempre um pedido GET);
- tem de ser um **formulário POST** com `@csrf`, para impedir que outro site termine a sessão do utilizador sem este saber (proteção CSRF).

Depois de terminar a sessão, o Fortify envia o utilizador para a página inicial (`/`).

#### 8.4.1. Layout do utilizador

Em `resources/views/layouts/user.blade.php`, no menu do perfil (slot `actions`), substituir:

```blade
                <a href="#" class="block rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50">Terminar sessão</a>
```

por:

```blade
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full rounded-lg px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50">Terminar sessão</button>
                </form>
```

As classes mantêm-se praticamente iguais. Acrescentámos `w-full` e `text-left`, porque, ao contrário de um link `block`, um `<button>` não ocupa a largura toda nem alinha o texto à esquerda por omissão.

#### 8.4.2. Layout da administração

Em `resources/views/layouts/admin.blade.php` existem **dois** "Terminar sessão":

1. **Menu do perfil** (slot `actions`): fazer a mesma substituição do passo 8.4.1.

2. **Rodapé da barra lateral** (slot `sidebarFooter`). Substituir o link:

   ```blade
   <a href="#" class="mt-2 flex items-center gap-3 rounded-lg px-2 py-2 text-sm text-stone-400 hover:bg-white/5 hover:text-white">
       <svg …>…</svg>
       Terminar sessão
   </a>
   ```

   por:

   ```blade
   <form method="POST" action="{{ route('logout') }}">
       @csrf
       <button type="submit" class="mt-2 flex w-full items-center gap-3 rounded-lg px-2 py-2 text-sm text-stone-400 hover:bg-white/5 hover:text-white">
           <svg …>…</svg>
           Terminar sessão
       </button>
   </form>
   ```

   (manter o `<svg>` original do ícone dentro do botão.)

Confirmar que já não existe nenhum "Terminar sessão" com `href="#"`:

**macOS / Linux**

```bash
grep -n "Terminar sessão" resources/views/layouts/user.blade.php resources/views/layouts/admin.blade.php
```

**Windows PowerShell**

```powershell
Select-String -Path resources\views\layouts\user.blade.php, resources\views\layouts\admin.blade.php -Pattern "Terminar sessão"
```

As três ocorrências devem estar dentro de um `<button>`.

### 8.5. Verificar

A verificação é feita no passo 11, depois de iniciar sessão com cada utilizador.

---

## 9. Layout das páginas de autenticação

### 9.1. Como estão organizadas as páginas de autenticação

Abrir os ficheiros e observar a cadeia:

```text
pages/auth/login.blade.php          →  <x-layouts::auth>
        ↓
layouts/auth.blade.php              →  <x-layouts::auth.simple>   (variante atual)
        ↓
layouts/auth/simple.blade.php       →  HTML completo da página
```

O Starter Kit fornece **três variantes** em `resources/views/layouts/auth/`:

```text
card.blade.php
simple.blade.php
split.blade.php     → dois painéis lado a lado
```

Todas as páginas de autenticação (`login`, `register`, `forgot-password`, `reset-password`, `confirm-password`, `verify-email`, `two-factor-challenge`) usam `<x-layouts::auth>`. Ou seja, **basta alterar um ficheiro para mudar o layout de todas**.

O protótipo `login.html` tem precisamente dois painéis (decorativo + formulário), o que corresponde à variante **split**.

### 9.2. Mudar para a variante `split`

Substituir o conteúdo de `resources/views/layouts/auth.blade.php` por:

```blade
<x-layouts::auth.split :title="$title ?? null">
    {{ $slot }}
</x-layouts::auth.split>
```

### 9.3. Adaptar `split` ao protótipo

Substituir **todo** o conteúdo de `resources/views/layouts/auth/split.blade.php` por:

```blade
<!DOCTYPE html>
<html lang="pt">
    <head>
        @include('partials.head')

        {{-- O design de autenticação é apenas claro: remove o modo escuro sem alterar a preferência guardada --}}
        <script>
            window.Flux.applyAppearance = () => document.documentElement.classList.remove('dark');
            window.Flux.applyAppearance();
        </script>
    </head>
    <body class="bg-stone-50 font-sans text-stone-800 antialiased">

        <div class="grid min-h-screen lg:grid-cols-2">

            <!-- Painel decorativo -->
            {{-- (a) colar aqui o painel decorativo de login.html --}}

            <!-- Painel do formulário -->
            <div class="flex flex-col justify-center px-6 py-12 sm:px-12 lg:px-16">
                <div class="mx-auto w-full max-w-sm">
                    {{-- (b) colar aqui o logótipo móvel de login.html --}}

                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
```

Completar a partir de `docs/resources/layout/login.html`:

**(a)** copiar o bloco `<!-- Decorative panel -->` (o `<div class="relative hidden flex-col … bg-brand-800 …">` completo, linhas 29–45).

**(b)** copiar o link com o logótipo que aparece só em ecrãs pequenos (`<a href="home.html" class="mb-10 flex items-center gap-2 lg:hidden">`, linhas 50–55).

Aplicar as adaptações habituais:

| No protótipo                          | Na aplicação                          |
|---------------------------------------|---------------------------------------|
| `href="home.html"`                    | `href="{{ route('home') }}"`          |
| SVG da folha                          | `<x-app-logo-icon class="h-5 w-5" />` |
| `© 2026`                              | `© {{ date('Y') }}`                   |

Notar o que **não** se copia do protótipo:

- o `<h1>`, a descrição e o `<form>`: pertencem a cada **página**, não ao layout;
- o bloco "Início de sessão rápido": é uma funcionalidade que **não** faz parte deste guia.

**Porquê o `<script>` no `<head>`?** As páginas de autenticação usam componentes **Flux** (`<flux:input>`, `<flux:button>`), que mudam de cor em modo escuro. O design aprovado é apenas claro. Este script retira a classe `dark` só nestas páginas, **sem alterar** a preferência de aparência que o utilizador escolheu nas definições.

Manter `@persist('toast')` e `@fluxScripts`: são necessários aos componentes Flux das páginas.

### 9.4. Cor de destaque dos componentes Flux

Os botões e links Flux (`<flux:button variant="primary">`, `<flux:link>`, `<flux:checkbox>`) usam a **cor de destaque** (_accent_) definida em `resources/css/app.css`. Por omissão, o Starter Kit usa cinzento escuro.

Abrir `resources/css/app.css` e, dentro de `@theme { ... }`, substituir as três variáveis `--color-accent…` por:

```css
    --color-accent: var(--color-brand-600);
    --color-accent-content: var(--color-brand-700);
    --color-accent-foreground: var(--color-white);
```

No bloco `@layer theme { .dark { ... } }`, logo abaixo, substituir as três variáveis por:

```css
        --color-accent: var(--color-brand-500);
        --color-accent-content: var(--color-brand-300);
        --color-accent-foreground: var(--color-brand-950);
```

Assim, o botão "Iniciar sessão" e os links ficam com o verde da marca, tal como no protótipo. As cores `brand-*` foram criadas no Guia 03.1 (secção 2).

### 9.5. Cabeçalho das páginas

Todas as páginas usam o componente `<x-auth-header>` para o título e a descrição. Em vez de alterar sete páginas, alteramos **um componente**.

Substituir o conteúdo de `resources/views/components/auth-header.blade.php` por (estilos do `<h1>` e do `<p>` de `login.html`):

```blade
@props([
    'title',
    'description',
])

<div class="flex w-full flex-col">
    <h1 class="text-2xl font-semibold tracking-tight text-stone-900">{{ $title }}</h1>
    <p class="mt-1.5 text-sm text-stone-500">{{ $description }}</p>
</div>
```

### 9.6. Página de login

Os campos, os nomes (`name="email"`, `name="password"`, `name="remember"`), o `action` e o `@csrf` são o que o **Fortify** espera. **Não se alteram.** Apenas traduzimos os textos e aplicamos os textos do protótipo.

Em `resources/views/pages/auth/login.blade.php`:

| Antes | Depois |
|-------|--------|
| `<x-layouts::auth :title="__('Log in')">` | `<x-layouts::auth title="Iniciar sessão">` |
| `:title="__('Log in to your account')"` | `title="Bem-vindo de volta"` |
| `:description="__('Enter your email and password below to log in')"` | `description="Inicie sessão para gerir a sua coleção de plantas."` |
| `:label="__('Email address')"` | `label="Email"` |
| `placeholder="email@example.com"` | `placeholder="voce@exemplo.com"` |
| `:label="__('Password')"` | `label="Palavra-passe"` |
| `:placeholder="__('Password')"` | `placeholder="••••••••"` |
| `{{ __('Forgot your password?') }}` | `Esqueceu-se da palavra-passe?` |
| `:label="__('Remember me')"` | `label="Lembrar-me"` |
| `{{ __('Log in') }}` (botão) | `Iniciar sessão` |

> Repare que `:title="__('…')"` passa a `title="…"`: sem `:` porque o valor já não é uma expressão PHP, é texto simples.

Por fim, substituir o bloco final (`Don't have an account?`) pelo do protótipo:

```blade
        <p class="text-center text-sm text-stone-500">
            Ainda não tem conta?
            <a href="{{ route('register') }}" class="font-medium text-brand-700 hover:text-brand-800">Criar uma</a>
        </p>
```

### 9.7. Página de registo

Em `resources/views/pages/auth/register.blade.php`, usando `register.html` como referência:

| Antes | Depois |
|-------|--------|
| `:title="__('Register')"` (layout) | `title="Criar conta"` |
| `:title="__('Create an account')"` | `title="Crie a sua conta"` |
| `:description="__('Enter your details below to create your account')"` | `description="Comece hoje a acompanhar a sua coleção de plantas."` |
| `:label="__('Name')"` | `label="Nome"` |
| `:placeholder="__('Full name')"` | `placeholder="Nome completo"` |
| `:label="__('Email address')"` | `label="Email"` |
| `placeholder="email@example.com"` | `placeholder="voce@exemplo.com"` |
| `:label="__('Password')"` | `label="Palavra-passe"` |
| `:label="__('Confirm password')"` | `label="Confirmar palavra-passe"` |
| `:placeholder="__('Password')"` / `:placeholder="__('Confirm password')"` | `placeholder="••••••••"` |
| `{{ __('Create account') }}` | `Criar conta` |

Substituir o bloco final (`Already have an account?`) por:

```blade
        <p class="text-center text-sm text-stone-500">
            Já tem conta?
            <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:text-brand-800">Iniciar sessão</a>
        </p>
```

### 9.8. Restantes páginas (exercício)

**Exercício:** traduzir as restantes páginas de `resources/views/pages/auth/`, seguindo o mesmo método (trocar `:atributo="__('…')"` por `atributo="…"` e `{{ __('…') }}` pelo texto).

| Ficheiro | Textos principais |
|----------|-------------------|
| `forgot-password` | título "Recuperar palavra-passe"; descrição "Indique o seu email para receber uma ligação de reposição da palavra-passe."; botão "Enviar ligação de reposição"; rodapé "Ou regressar ao **início de sessão**" |
| `reset-password` | título "Repor palavra-passe"; descrição "Indique a sua nova palavra-passe."; botão "Repor palavra-passe" |
| `confirm-password` | título "Confirmar palavra-passe"; descrição "Esta é uma área segura da aplicação. Confirme a sua palavra-passe antes de continuar."; botão "Confirmar" |
| `verify-email` | título "Verificar email"; descrição "Confirme o seu endereço de email clicando na ligação que acabámos de lhe enviar."; botões "Reenviar email de verificação" e "Terminar sessão" |
| `two-factor-challenge` | "Código de autenticação" / "Introduza o código indicado pela sua aplicação de autenticação."; "Código de recuperação" / "Confirme o acesso à sua conta introduzindo um dos seus códigos de recuperação."; botão "Continuar"; "ou pode entrar com um código de recuperação / de autenticação" |

Em `verify-email`, substituir o `<flux:text class="text-center">…</flux:text>` inicial por um `<x-auth-header>` (título + descrição), para ficar igual às restantes páginas. No rodapé de `forgot-password`, usar um `<p>` com link, como em 9.6.

> **Nunca** alterar nestas páginas: `name="…"`, `action="{{ route(…) }}"`, `@csrf`, `type="hidden"`, `data-test="…"` e o código Alpine (`x-data`, `x-show`…) do `two-factor-challenge`. São a ligação ao Fortify.

### 9.9. Verificar

Terminar a sessão (ou usar uma janela privada) e abrir:

- [ ] `http://localhost:8000/login`: dois painéis em ecrã largo; só o formulário, com logótipo, em ecrã estreito;
- [ ] `http://localhost:8000/register`;
- [ ] `http://localhost:8000/forgot-password`;
- [ ] todos os textos em português;
- [ ] com um email/palavra-passe errados, a mensagem de erro aparece por baixo do campo.

> As mensagens de **validação** (ex.: *These credentials do not match our records.*) vêm do Laravel e continuam em inglês. A tradução das mensagens do framework será tratada noutro passo.

---

## 10. Início de sessão rápido (apenas desenvolvimento)

Durante o desenvolvimento, e nas aulas, é útil entrar com um utilizador de cada papel **com um clique**, sem escrever email e palavra-passe.

O protótipo `login.html` já prevê este bloco ("Início de sessão rápido").

Regras que **temos** de cumprir:

1. só funciona no ambiente **local** (`APP_ENV=local`);
2. usa **apenas** os utilizadores de desenvolvimento criados pelo `DevelopmentUserSeeder`;
3. **não** substitui nem enfraquece o login normal: o formulário continua a funcionar exatamente igual.

### 10.1. Criar o controlador

```bash
php artisan make:controller Dev/QuickLoginController --invokable
```

Foi criado `app/Http/Controllers/Dev/QuickLoginController.php`.

- `Dev/` cria o controlador numa subpasta (e no namespace `App\Http\Controllers\Dev`), separando o código de desenvolvimento do resto da aplicação;
- `--invokable` cria um controlador com **uma única ação**, o método `__invoke()`.

Substituir o conteúdo por:

```php
<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuickLoginController extends Controller
{
    /**
     * Development users available for quick login, by role.
     *
     * @var array<string, string>
     */
    private const USERS = [
        'admin' => 'admin@florahub.test',
        'user' => 'user@florahub.test',
    ];

    /**
     * Log in as a predefined development user (local environment only).
     */
    public function __invoke(Request $request, string $role): RedirectResponse
    {
        abort_unless(app()->isLocal(), 404);

        $user = User::where('email', self::USERS[$role])->firstOrFail();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route($user->dashboardRoute());
    }
}
```

Analisar linha a linha:

| Instrução | Função |
|-----------|--------|
| `USERS` | a lista **fechada** de utilizadores permitidos. Não é possível entrar com outro utilizador |
| `abort_unless(app()->isLocal(), 404)` | fora do ambiente local responde **404**, como se a página não existisse |
| `firstOrFail()` | se o seeder não tiver sido executado, responde 404 |
| `Auth::login($user)` | inicia a sessão com o utilizador indicado |
| `session()->regenerate()` | gera um novo identificador de sessão (proteção contra _session fixation_), tal como o login normal |
| `$user->dashboardRoute()` | reutiliza o método do passo 2.2: cada utilizador vai para o seu painel |

### 10.2. Criar a rota

Em `routes/web.php`, acrescentar o `use` no topo:

```php
use App\Http\Controllers\Dev\QuickLoginController;
```

e, **depois** da rota `home`:

```php
// Início de sessão rápido: apenas em desenvolvimento (APP_ENV=local)
Route::post('dev/login/{role}', QuickLoginController::class)
    ->middleware('guest')
    ->whereIn('role', ['admin', 'user'])
    ->name('dev.login');
```

| Parte | Função |
|-------|--------|
| `Route::post` | é um pedido **POST**: exige o token CSRF (`@csrf`) e não pode ser disparado por um simples link |
| `{role}` | parâmetro da rota, recebido em `__invoke(..., string $role)` |
| `->whereIn('role', ['admin', 'user'])` | só aceita estes dois valores; qualquer outro dá 404 |
| `->middleware('guest')` | só para quem **ainda não** iniciou sessão |

Verificar:

```bash
php artisan route:list --name=dev
```

```text
POST  dev/login/{role} .. dev.login › Dev\QuickLoginController
```

### 10.3. Mostrar os botões na página de login

Em `resources/views/pages/auth/login.blade.php`, **depois** do parágrafo "Ainda não tem conta?" e antes do `</div>` final, acrescentar:

```blade
        @env('local')
            <div class="rounded-2xl border border-dashed border-amber-300 bg-amber-50 p-5">
                <span class="inline-flex items-center rounded-full bg-amber-200 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-amber-900">Apenas em desenvolvimento</span>
                <h2 class="mt-3 text-sm font-semibold text-amber-900">Início de sessão rápido</h2>
                <p class="mt-1 text-xs text-amber-800">Entra com os utilizadores criados pelo <code>DevelopmentUserSeeder</code>.</p>
                <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                    <form method="POST" action="{{ route('dev.login', 'user') }}" class="flex-1">
                        @csrf
                        <button type="submit" class="w-full rounded-lg border border-amber-300 bg-white px-4 py-2.5 text-sm font-semibold text-amber-900 hover:bg-amber-100">
                            Entrar como Utilizador
                        </button>
                    </form>
                    <form method="POST" action="{{ route('dev.login', 'admin') }}" class="flex-1">
                        @csrf
                        <button type="submit" class="w-full rounded-lg border border-amber-300 bg-white px-4 py-2.5 text-sm font-semibold text-amber-900 hover:bg-amber-100">
                            Entrar como Administrador
                        </button>
                    </form>
                </div>
            </div>
        @endenv
```

- `@env('local') … @endenv`: o bloco só é incluído na página quando `APP_ENV=local`;
- cada botão é um pequeno formulário **POST** com `@csrf`;
- `route('dev.login', 'user')` gera `/dev/login/user`.

Comparar com o bloco do protótipo `login.html` (linhas 89–104): o aspeto é o mesmo, mas os `<button type="button">` passaram a estar dentro de formulários reais.

### 10.4. Três camadas de proteção

| Camada | Onde | Efeito fora de `local` |
|--------|------|------------------------|
| `@env('local')` | vista `login` | os botões não aparecem |
| `abort_unless(app()->isLocal(), 404)` | controlador | o pedido é recusado (404) |
| lista `USERS` + `whereIn` | controlador + rota | só os dois utilizadores de desenvolvimento |

Esconder o botão **não chega**: qualquer pessoa pode enviar um POST manualmente. Por isso a verificação no controlador é obrigatória.

### 10.5. Verificar

Confirmar no `.env`:

```env
APP_ENV=local
```

Terminar a sessão e abrir `http://localhost:8000/login`:

- [ ] aparece o bloco amarelo "Início de sessão rápido";
- [ ] **Entrar como Utilizador** → `/dashboard`, com o nome **Sofia Almeida**;
- [ ] (terminar a sessão) **Entrar como Administrador** → `/admin/dashboard`, com o nome **Rui Mendes**.

**Exercício:** alterar temporariamente `APP_ENV=production` no `.env`, executar `php artisan config:clear` e recarregar `/login`. O bloco desaparece? Repor `APP_ENV=local` e executar novamente `php artisan config:clear`.

---

## 11. Verificação no browser

> Para mudar de utilizador, usar o botão **Terminar sessão** do painel (passo 8.4).
>
> Em vez de escrever email e palavra-passe, pode usar os botões do **Início de sessão rápido** (passo 10).

### 11.1. Administrador

1. Abrir `http://localhost:8000/login`.
2. Iniciar sessão com `admin@florahub.test` / `password`.
3. Confirmar:
   - [ ] foi enviado para `/admin/dashboard`;
   - [ ] a barra lateral mostra **Rui Mendes** e as iniciais **RM**;
   - [ ] `http://localhost:8000/dashboard` → **403**;
   - [ ] `http://localhost:8000/login` → volta a `/admin/dashboard`.
   - [ ] **Terminar sessão** (rodapé da barra lateral) → volta à página inicial `/`;
   - [ ] depois de terminar a sessão, `http://localhost:8000/admin/dashboard` → é enviado para `/login`.

### 11.2. Utilizador

1. Terminar a sessão e iniciar sessão com `user@florahub.test` / `password`.
2. Confirmar:
   - [ ] foi enviado para `/dashboard`;
   - [ ] aparece "Bem-vindo de volta, Sofia Almeida" e as iniciais **SA**;
   - [ ] `http://localhost:8000/admin/dashboard` → **403**;
   - [ ] menu do perfil (iniciais, canto superior direito) → **Terminar sessão** → volta à página inicial `/`.

### 11.3. Novo registo

1. Terminar a sessão e abrir `http://localhost:8000/register`.
2. Criar uma conta nova (ex.: `aluno2@florahub.test`).
3. Verificar o email no Mailtrap (Guia 02) e clicar na ligação.
4. Confirmar:
   - [ ] foi enviado para `/dashboard`;
   - [ ] o novo utilizador tem o papel `user`:

     ```bash
     php artisan tinker
     ```

     ```php
     App\Models\User::where('email', 'aluno2@florahub.test')->first()->getRoleNames();
     ```

---

## 12. Verificação final e commit

### 12.1. Formatação, análise estática e build

```bash
vendor/bin/pint --dirty
composer run types:check
npm run build
```

> Se `types:check` falhar por falta de memória, executar `vendor/bin/phpstan analyse --memory-limit=1G`.

### 12.2. Ficheiros alterados

```bash
git status
```

Deverão aparecer:

```text
modified:   composer.json
modified:   composer.lock
modified:   app/Models/User.php
modified:   app/Actions/Fortify/CreateNewUser.php
modified:   app/Providers/FortifyServiceProvider.php
modified:   bootstrap/app.php
modified:   routes/web.php
modified:   database/seeders/DatabaseSeeder.php
modified:   resources/views/layouts/auth.blade.php
modified:   resources/views/layouts/auth/split.blade.php
modified:   resources/views/layouts/user.blade.php
modified:   resources/views/layouts/admin.blade.php
modified:   resources/views/user/dashboard.blade.php
modified:   resources/views/components/auth-header.blade.php
modified:   resources/css/app.css
modified:   resources/views/pages/auth/*.blade.php        (7 ficheiros)
deleted:    resources/views/dashboard.blade.php

new:        config/permission.php
new:        database/migrations/xxxx_create_permission_tables.php
new:        database/seeders/RoleSeeder.php
new:        database/seeders/DevelopmentUserSeeder.php
new:        app/Http/Responses/LoginResponse.php
new:        app/Http/Controllers/Dev/QuickLoginController.php
```

Fazer commit e push com uma mensagem como:

```text
Papéis admin/user com Spatie, proteção de rotas e redirecionamento após login
```

---

## 13. Resumo

### 13.1. Fluxo final

```text
POST /login  (Fortify)
    │
    ├── credenciais erradas → volta ao formulário com erro
    │
    ├── 2FA ativo → /two-factor-challenge → POST código
    │                                          │
    └──────────────────────────────────────────┤
                                               ▼
                         App\Http\Responses\LoginResponse
                                               │
                              $user->dashboardRoute()
                                  │                 │
                          hasRole('admin')      restantes
                                  │                 │
                                  ▼                 ▼
                         /admin/dashboard       /dashboard
                   [auth, verified, role:admin] [auth, verified, role:user]
```

### 13.2. Onde está cada responsabilidade

| Responsabilidade | Ficheiro |
|------------------|----------|
| Papéis existentes | `database/seeders/RoleSeeder.php` |
| Utilizadores de desenvolvimento | `database/seeders/DevelopmentUserSeeder.php` |
| Papel atribuído no registo | `app/Actions/Fortify/CreateNewUser.php` |
| Decisão "qual o painel deste utilizador" | `User::dashboardRoute()` |
| Redirecionamento após login / 2FA | `app/Http/Responses/LoginResponse.php` + `FortifyServiceProvider` |
| Redirecionamento de autenticado em `/login` | `bootstrap/app.php` (`redirectUsersTo`) |
| Proteção das áreas | `routes/web.php` (`auth`, `verified`, `role:…`) |
| Alias `role` | `bootstrap/app.php` |
| Layout de autenticação | `layouts/auth.blade.php` → `layouts/auth/split.blade.php` |
| Início de sessão rápido (só `local`) | `Dev/QuickLoginController` + rota `dev.login` + `@env('local')` |

Repare que **não existe nenhuma verificação de papel nas vistas Blade**. A autorização está nas rotas (middleware) e a decisão de redirecionamento num único método.

### 13.3. Conceitos trabalhados

| Conceito | Onde |
|----------|------|
| Autenticação vs. autorização | secção 0 |
| Pacote Composer + publicação de configuração/migrations | `composer require`, `vendor:publish` |
| Papéis com Spatie (`HasRoles`, `assignRole`, `hasRole`) | modelo `User`, seeders |
| Seeders idempotentes (`findOrCreate`, `firstOrCreate`) | `RoleSeeder`, `DevelopmentUserSeeder` |
| Código só para desenvolvimento | `app()->isLocal()` |
| Transações | `CreateNewUser` |
| Alias de middleware | `bootstrap/app.php` |
| Grupos de rotas: `middleware`, `prefix`, `name` | `routes/web.php` |
| Contratos e service container | `LoginResponse` + `singleton()` |
| `redirect()->intended()` | `LoginResponse` |
| Utilizador autenticado nas vistas | `auth()->user()` |
| Terminar sessão com formulário POST + `@csrf` | rota `logout` do Fortify nos layouts |
| Variantes de layout do Starter Kit | `auth.simple` → `auth.split` |
| Controlador invocável (`--invokable`) | `QuickLoginController` |
| Código só para desenvolvimento | `@env('local')`, `abort_unless(app()->isLocal(), 404)` |
| `Auth::login()` + regenerar sessão | `QuickLoginController` |

### 13.4. Comandos utilizados

```bash
# Pacote
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan config:clear
php artisan migrate
php artisan migrate:status

# Seeders
php artisan make:seeder RoleSeeder
php artisan make:seeder DevelopmentUserSeeder
php artisan db:seed
php artisan permission:show
php artisan permission:assign-role user <id>

# Classes e vistas
php artisan make:class Http/Responses/LoginResponse
git rm resources/views/dashboard.blade.php

# Rotas
php artisan route:list --name=dashboard -v

# Início de sessão rápido
php artisan make:controller Dev/QuickLoginController --invokable
php artisan route:list --name=dev

# Qualidade e build
vendor/bin/pint --dirty
composer run types:check
npm run build
```

---

## 14. Problemas comuns

| Sintoma | Causa provável | Solução |
|---------|----------------|---------|
| `Base table or view not found: 'roles'` | Migration do Spatie não executada | `php artisan migrate` |
| `There is no role named 'user' for guard 'web'` (no browser) | Papéis não criados | `php artisan db:seed --class=RoleSeeder` |
| `Call to undefined method App\Models\User::hasRole()` | Falta o trait `HasRoles` | Rever o passo 2.1 |
| `Target class [role] does not exist.` | Alias `role` não registado | Rever o passo 5 (`bootstrap/app.php`) |
| **403** logo após iniciar sessão | O utilizador não tem papel (conta criada antes deste guia) | `php artisan permission:assign-role user <id>` (passo 3.6) |
| O admin é enviado para `/dashboard` e recebe 403 | `LoginResponse` não registada | Rever o passo 7.4 (`register()` do `FortifyServiceProvider`) |
| O admin é enviado para `/dashboard` só quando tem 2FA ativo | Falta o registo de `TwoFactorLoginResponseContract` | Rever o passo 7.4 |
| Admin autenticado a abrir `/login` recebe 403 | Falta `redirectUsersTo` | Rever o passo 7.5 |
| Depois do login regressa a uma página diferente do painel | Comportamento de `intended()`: volta à página que tinha tentado abrir antes | Normal. Abrir `/login` diretamente para testar o redirecionamento |
| `Route [user.dashboard] not defined.` | O layout do utilizador ainda usa o nome antigo | Rever o passo 6.3 |
| O papel foi alterado mas o comportamento não muda | Cache de permissões do Spatie | `php artisan permission:cache-reset` |
| Os campos da página de login aparecem escuros / ilegíveis | Falta o `<script>` do passo 9.3 | Rever o `<head>` de `layouts/auth/split.blade.php` |
| As páginas de autenticação mantêm o layout antigo | `layouts/auth.blade.php` ainda usa `auth.simple` | Rever o passo 9.2 |
| `db:seed` cria o `test@example.com` e não os utilizadores de desenvolvimento | `DatabaseSeeder` não foi alterado, ou `APP_ENV` não é `local` | Rever o passo 3.3 e confirmar `APP_ENV=local` no `.env` |
| Configuração/rotas antigas continuam ativas | Cache | `php artisan optimize:clear` |
| O bloco "Início de sessão rápido" não aparece | `APP_ENV` não é `local`, ou configuração em cache | Confirmar `APP_ENV=local` e executar `php artisan config:clear` |
| Botão do início de sessão rápido dá **419** | Falta `@csrf` no formulário | Rever o passo 10.3 |
| Botão do início de sessão rápido dá **404** | Utilizadores de desenvolvimento não criados | `php artisan db:seed` (passo 3.4) |
| Botão do início de sessão rápido volta para `/login` | Já existe uma sessão iniciada (middleware `guest`) | Terminar a sessão primeiro |
| "Terminar sessão" não faz nada | Ainda é um link `<a href="#">` | Rever o passo 8.4 |
| "Terminar sessão" dá **419 — Page Expired** | Falta `@csrf` no formulário | Rever o passo 8.4 |
| "Terminar sessão" dá **405 — Method Not Allowed** | O pedido foi feito com GET (link) em vez de POST | Usar `<form method="POST">` (passo 8.4) |
| Os botões Flux continuam cinzentos | Cor de destaque não alterada | Rever o passo 9.4 |
