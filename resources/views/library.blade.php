<x-filament-panels::page>
    @php($res = $this->results())
    @php($cats = \Jerox\EggLibrary\Catalog::categories())

    <style>
        .el-bar{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.5rem}
        .el-grid{display:grid;gap:.75rem;grid-template-columns:repeat(auto-fill,minmax(16rem,1fr))}
        .el-card{border:1px solid rgba(127,127,127,.25);border-radius:.6rem;padding:.75rem;display:flex;flex-direction:column;gap:.4rem;overflow:hidden}
        .el-img{margin:-.75rem -.75rem .25rem;aspect-ratio:460/215;background:rgba(127,127,127,.12);display:flex;align-items:center;justify-content:center;overflow:hidden}
        .el-img img{width:100%;height:100%;object-fit:cover;display:block}
        .el-img.el-none{background:linear-gradient(135deg,rgba(127,127,127,.18),rgba(127,127,127,.05))}
        .el-img.el-none svg{width:3.5rem;height:3.5rem;opacity:.45}
        .el-name{font-weight:700}
        .el-desc{font-size:.85rem;opacity:.75;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
        .el-muted{opacity:.7;font-size:.875rem}
        .el-row{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem;margin-top:auto}
    </style>

    <div>
        <x-filament::input.wrapper prefix-icon="tabler-search">
            <x-filament::input type="search" wire:model.live.debounce.300ms="search" :placeholder="trans('egg-library::strings.search_placeholder')" autofocus />
        </x-filament::input.wrapper>

        <div class="el-bar">
            @foreach ($cats as $c)
                <x-filament::button size="xs" :color="$category === $c ? 'primary' : 'gray'" wire:click="setCategory({{ \Illuminate\Support\Js::from($c) }})">{{ $c }}</x-filament::button>
            @endforeach
        </div>
    </div>

    <p class="el-muted">
        {{ trans_choice('egg-library::strings.found', $res['total'], ['count' => $res['total']]) }}
        @if ($category) {{ trans('egg-library::strings.in_category', ['category' => $category]) }} @endif
    </p>

    <div class="el-grid">
        @forelse ($res['eggs'] as $e)
            <div class="el-card" wire:key="egg-{{ md5($e['url']) }}">
                @if ($e['image'])
                    <div class="el-img"><img src="{{ $e['image']['src'] }}" alt="" loading="lazy"></div>
                @else
                    <div class="el-img el-none"><x-filament::icon :icon="\Jerox\EggLibrary\Catalog::categoryIcon($e['category'])" /></div>
                @endif
                <div class="el-name">{{ $e['name'] }}</div>
                <div class="el-desc">{{ $e['description'] ?: trans('egg-library::strings.no_description') }}</div>
                <div class="el-row">
                    <x-filament::badge color="gray">{{ $e['category'] }}</x-filament::badge>
                    @if ($e['installed_id'])
                        <x-filament::badge color="success" icon="tabler-check">{{ trans('egg-library::strings.installed') }}</x-filament::badge>
                    @endif
                    <x-filament::link :href="$e['url']" target="_blank" size="sm" icon="tabler-external-link">{{ trans('egg-library::strings.source') }}</x-filament::link>
                </div>
                <div class="el-row" style="margin-top:.25rem">
                    @if ($e['installed_id'])
                        @if ($this->canCreateServer())
                            <x-filament::button size="xs" icon="tabler-server-2" tag="a" :href="$this->createServerUrl()">{{ trans('egg-library::strings.create_server') }}</x-filament::button>
                        @endif
                        @if ($this->canViewEgg())
                            <x-filament::button size="xs" color="gray" icon="tabler-egg" tag="a" :href="$this->eggUrl($e['installed_id'])">{{ trans('egg-library::strings.view_egg') }}</x-filament::button>
                        @endif
                    @else
                        <x-filament::button size="xs" icon="tabler-download"
                            wire:click="install({{ \Illuminate\Support\Js::from($e['url']) }})"
                            wire:loading.attr="disabled" wire:target="install({{ \Illuminate\Support\Js::from($e['url']) }})"
                            wire:confirm="{{ trans('egg-library::strings.install_confirm', ['name' => $e['name']]) }}">{{ trans('egg-library::strings.install') }}</x-filament::button>
                    @endif
                </div>
            </div>
        @empty
            <p class="el-muted">{{ trans('egg-library::strings.nothing_found') }}</p>
        @endforelse
    </div>

    @if ($res['total'] > count($res['eggs']))
        <div><x-filament::button color="gray" wire:click="more">{{ trans('egg-library::strings.show_more', ['count' => $res['total'] - count($res['eggs'])]) }}</x-filament::button></div>
    @endif
</x-filament-panels::page>
