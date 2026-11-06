<?php

namespace Jerox\EggLibrary\Pages;

use App\Enums\EggFormat;
use App\Filament\Admin\Resources\Eggs\EggResource;
use App\Filament\Admin\Resources\Servers\ServerResource;
use App\Models\Egg;
use App\Services\Eggs\Sharing\EggImporterService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Jerox\EggLibrary\Catalog;
use Throwable;

class Library extends Page
{
    public const PER_PAGE = 48;

    protected static ?string $slug = 'egg-library';

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-books';

    protected static ?int $navigationSort = 99;

    protected string $view = 'egg-library::library';

    public string $search = '';

    public string $category = '';

    public int $limit = self::PER_PAGE;

    /** Users who may create eggs, or every logged-in user if the admin allowed it in the plugin settings. */
    public static function canAccess(): bool
    {
        $user = user();

        return $user && ($user->can('create egg') || config('egg-library.allow_user_install'));
    }

    public static function getNavigationLabel(): string
    {
        return trans('egg-library::strings.title');
    }

    public function getTitle(): string
    {
        return trans('egg-library::strings.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return Filament::getCurrentPanel()?->getId() === 'admin' ? EggResource::getNavigationGroup() : null;
    }

    public function updatedSearch(): void
    {
        $this->limit = self::PER_PAGE;
    }

    public function setCategory(string $category): void
    {
        $this->category = $this->category === $category ? '' : $category;
        $this->limit = self::PER_PAGE;
    }

    public function more(): void
    {
        $this->limit += self::PER_PAGE;
    }

    /** Installs an egg from the catalog. Only catalog URLs, existing eggs are never overwritten. */
    public function install(string $url, EggImporterService $importer): void
    {
        abort_unless(static::canAccess(), 403);

        $entry = collect(Catalog::all())->firstWhere('url', $url);
        if (!$entry) {
            Notification::make()->title(trans('egg-library::strings.unknown_egg'))->danger()->send();

            return;
        }

        $limiterKey = 'egg-library:' . user()->id;
        if (RateLimiter::tooManyAttempts($limiterKey, max(1, (int) config('egg-library.installs_per_hour', 10)))) {
            Notification::make()->title(trans('egg-library::strings.too_many'))
                ->body(trans('egg-library::strings.too_many_body', ['minutes' => ceil(RateLimiter::availableIn($limiterKey) / 60)]))
                ->warning()->send();

            return;
        }

        try {
            $download = str($url)->replaceFirst('/blob/', '/raw/')->toString();
            $format = match (strtolower(pathinfo((string) parse_url($download, PHP_URL_PATH), PATHINFO_EXTENSION))) {
                'json' => EggFormat::JSON,
                'yaml', 'yml' => EggFormat::YAML,
            };
            $content = Http::timeout(10)->connectTimeout(3)->get($download)->throw()->body();
            $parsed = $importer->parse($content, $format);

            // Pelican would overwrite an egg with the same UUID -> check first
            $existing = Egg::query()
                ->when(!empty($parsed['uuid']), fn ($q) => $q->where('uuid', $parsed['uuid']))
                ->orWhere('name', $parsed['name'] ?? $entry['name'])
                ->first();
            if ($existing || isset(Catalog::installed()[Catalog::normalize($url)])) {
                Notification::make()->title(trans('egg-library::strings.already_installed', ['name' => $entry['name']]))->warning()->send();

                return;
            }

            RateLimiter::hit($limiterKey, 3600);
            $egg = $importer->fromContent($content, $format);
            if (!$egg->update_url) {
                $egg->update(['update_url' => $url]);  // so the library recognizes it as installed
            }
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title(trans('egg-library::strings.install_failed', ['name' => $entry['name']]))
                ->body($e->getMessage())->danger()->send();

            return;
        }

        Log::info('Egg Library: egg installed', ['egg' => $egg->name, 'egg_id' => $egg->id, 'user' => user()->username, 'url' => $url]);

        Notification::make()->title(trans('egg-library::strings.install_done', ['name' => $entry['name']]))
            ->body(trans(static::canCreateServer() ? 'egg-library::strings.install_done_can_create' : 'egg-library::strings.install_done_admin'))
            ->success()->send();
    }

    public static function canCreateServer(): bool
    {
        return (bool) user()?->can('create server');
    }

    public static function canViewEgg(): bool
    {
        return (bool) user()?->can('view egg');
    }

    public function createServerUrl(): string
    {
        return ServerResource::getUrl('create', panel: 'admin');
    }

    public function eggUrl(int $id): string
    {
        return EggResource::getUrl('edit', ['record' => $id], panel: 'admin');
    }

    /** @return array{eggs:list<array<string,mixed>>, total:int} */
    public function results(): array
    {
        $installed = Catalog::installed();
        $installedNames = Egg::query()->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id])->all();
        $terms = array_filter(explode(' ', mb_strtolower(trim($this->search))));

        $eggs = array_values(array_filter(Catalog::all(), function (array $e) use ($terms) {
            if ($this->category !== '' && $e['category'] !== $this->category) {
                return false;
            }
            $hay = mb_strtolower($e['name'] . ' ' . $e['description'] . ' ' . $e['category']);
            foreach ($terms as $t) {
                if (!str_contains($hay, $t)) {
                    return false;
                }
            }

            return true;
        }));

        foreach ($eggs as &$e) {
            $e['installed_id'] = $installed[Catalog::normalize($e['url'])] ?? $installedNames[mb_strtolower($e['name'])] ?? null;
        }
        unset($e);

        $page = array_slice($eggs, 0, $this->limit);
        $icons = Egg::query()->whereIn('id', array_filter(array_column($page, 'installed_id')))->get()->keyBy('id');
        foreach ($page as &$e) {
            // Steam artwork, otherwise the icon of the installed egg, otherwise a placeholder in the view
            $installedIcon = $e['installed_id'] ? $icons->get($e['installed_id'])?->icon : null;
            $e['image'] = Catalog::image($e['url']) ?? ($installedIcon ? ['src' => $installedIcon, 'fit' => 'cover'] : null);
        }
        unset($e);

        return ['eggs' => $page, 'total' => count($eggs)];
    }
}
