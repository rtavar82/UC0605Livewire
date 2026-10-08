<x-layouts::dev title="Componentes">
    <div class="space-y-16">

        <h1 class="text-3xl font-bold tracking-tight text-stone-900">Laboratório de Componentes</h1>

        {{-- As secções seguintes são acrescentadas aqui --}}
        <section class="space-y-4">
            <h2 class="text-xl font-semibold text-stone-900">2. x-badge</h2>

            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center rounded-full bg-brand-100 px-2.5 py-0.5 text-xs font-medium text-brand-800">Ativa</span>
                <span class="inline-flex items-center rounded-full bg-brand-100 px-2.5 py-0.5 text-xs font-medium text-brand-800">Pública</span>
                <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">Removida</span>


                <div class="flex flex-wrap gap-2">
                    <x-badge>Ativa</x-badge>
                    <x-badge>Pública</x-badge>
                    <x-badge color="red">Removida</x-badge>
                    <x-badge color="amber">Pendente</x-badge>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold text-stone-900">4. x-card</h2>

            <div class="grid gap-6 md:grid-cols-3">
                <x-card>
                    Só com o conteúdo por omissão.
                </x-card>

                <x-card>
                    <x-slot:header>Rosaceae</x-slot:header>
                    Família das rosas, macieiras e morangueiros.
                </x-card>

                <x-card>
                    <x-slot:header>Rosaceae <x-badge>Ativa</x-badge></x-slot:header>
                    Cartão completo.
                    <x-slot:footer>12 espécies registadas</x-slot:footer>
                </x-card>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold text-stone-900">5. x-button</h2>

            <div class="flex flex-wrap items-center gap-3">
                <x-button>Guardar</x-button>
                <x-button variant="secondary">Cancelar</x-button>
                <x-button variant="danger">Eliminar</x-button>
                <x-button href="{{ route('dev.tailwind') }}">Ir para Tailwind (link)</x-button>
                <x-button type="submit" class="w-48 justify-center">type="submit"</x-button>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold text-stone-900">6. x-ui.alert</h2>

            <x-ui.alert>Mensagem informativa (tipo por omissão).</x-ui.alert>
            <x-ui.alert type="success" title="Guardado">A família foi criada com sucesso.</x-ui.alert>
            <x-ui.alert type="error" title="Erro">Não foi possível guardar. <x-badge color="red">422</x-badge></x-ui.alert>
        </section>






    </div>
</x-layouts::dev>



