<?php

namespace Jerox\EggLibrary;

use App\Models\Egg;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Community egg catalog (same source as Pelican's built-in GitHub import).
 */
class Catalog
{
    private const CACHE_KEY = 'egg-library.index';

    /** @return list<array{name:string, description:string, category:string, url:string}> */
    public static function all(): array
    {
        $eggs = cache()->remember(self::CACHE_KEY, now()->addDay(), function () {
            try {
                $data = Http::timeout(10)->connectTimeout(3)->get(config('panel.cdn.egg_index_url'))->throw()->json();
            } catch (Throwable $e) {
                report($e);

                return null;
            }

            $out = [];
            foreach ($data['nests'] ?? [] as $nest) {
                foreach ($nest['Eggs'] ?? [] as $egg) {
                    $out[] = [
                        'name' => (string) ($egg['egg']['name'] ?? ''),
                        'description' => trim((string) ($egg['egg']['description'] ?? '')),
                        'category' => (string) $nest['nest_type'],
                        'url' => (string) $egg['download_url'],
                    ];
                }
            }

            return $out ?: null;
        });

        // Fallback: names only from Pelican's own index (no descriptions)
        if (!$eggs) {
            cache()->forget(self::CACHE_KEY);
            $eggs = [];
            foreach (cache()->get('eggs.index', []) as $category => $list) {
                foreach ($list as $url => $name) {
                    $eggs[] = ['name' => $name, 'description' => '', 'category' => $category, 'url' => $url];
                }
            }
        }

        usort($eggs, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $eggs;
    }

    /** @return list<string> */
    public static function categories(): array
    {
        $cats = array_values(array_unique(array_column(self::all(), 'category')));
        sort($cats);

        return $cats;
    }

    /** @return array<string,int> normalized download URL => ID of the installed egg */
    public static function installed(): array
    {
        $out = [];
        foreach (Egg::query()->whereNotNull('update_url')->pluck('update_url', 'id') as $id => $url) {
            $out[self::normalize($url)] = $id;
        }

        return $out;
    }

    /**
     * Artwork for a catalog egg: the automatically found Steam header image (see AutoImages), if any.
     *
     * @return array{src:string, fit:string}|null
     */
    public static function image(string $url): ?array
    {
        return AutoImages::get($url);
    }

    /** Tabler icon for cards without an image. */
    public static function categoryIcon(string $category): string
    {
        return match ($category) {
            'Chatbots' => 'tabler-robot',
            'Database' => 'tabler-database',
            'Games Standalone' => 'tabler-device-gamepad-2',
            'Games Steamcmd' => 'tabler-brand-steam',
            'Minecraft' => 'tabler-cube',
            'Monitoring' => 'tabler-chart-line',
            'Software' => 'tabler-apps',
            'Storage' => 'tabler-folders',
            'Voice' => 'tabler-microphone',
            default => 'tabler-egg',
        };
    }

    /** Makes GitHub links comparable (blob/raw, refs/heads, case). */
    public static function normalize(string $url): string
    {
        return str($url)->lower()
            ->replace('https://github.com/', 'https://raw.githubusercontent.com/')
            ->replace(['/blob/', '/raw/', '/refs/heads/'], '/')
            ->toString();
    }
}
