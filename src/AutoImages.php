<?php

namespace Jerox\EggLibrary;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Looks up Steam header images for catalog eggs (near-exact name matches only).
 * Images are stored on the panel under storage/egg-library/ (public disk), the mapping lives in the cache.
 * Runs from the scheduler (egg-library:find-images), never during a page request.
 */
class AutoImages
{
    private const CACHE_KEY = 'egg-library.images';

    private const DIR = 'egg-library';

    /** Search eggs without a match again after this many days */
    private const RETRY_DAYS = 7;

    private const MIN_SCORE = 0.85;

    /** Categories without Steam games */
    private const SKIP_CATEGORIES = ['Chatbots', 'Database', 'Generic', 'Monitoring', 'Software', 'Storage', 'Voice'];

    /** Minecraft software that shares its name with an unrelated Steam game */
    private const EXCLUDE = ['Feed the Beast', 'Limbo', 'Quilt', 'Blightfall', 'Waterfall'];

    /** Words in egg names that are not part of the game title */
    private const NOISE = '/\b(dedicated|server|vanilla|steamcmd|proton|wine|linux|windows|legacy|modded|mod(s|ded)?|plus|egg|bepinex|'
        . 'oxide|umod|carbon|tshock|paper|forge|fabric|spigot|bungeecord|velocity|nukkit|pocketmine|beta|experimental)\b/u';

    private static ?array $map = null;

    /** @return array{src:string, fit:string}|null */
    public static function get(string $url): ?array
    {
        self::$map ??= cache()->get(self::CACHE_KEY, []);
        $src = self::$map[$url]['src'] ?? null;

        return $src ? ['src' => asset('storage/' . self::DIR . '/' . $src), 'fit' => 'cover'] : null;
    }

    /**
     * Looks up images for at most $limit eggs that have none yet.
     *
     * @return array{found:int, missed:int, errors:int, open:int}
     */
    public static function refresh(int $limit, bool $retry = false, ?callable $log = null): array
    {
        $map = cache()->get(self::CACHE_KEY, []);
        $retryBefore = now()->subDays(self::RETRY_DAYS)->getTimestamp();

        $todo = array_values(array_filter(Catalog::all(), function (array $e) use ($map, $retry, $retryBefore) {
            if (in_array($e['category'], self::SKIP_CATEGORIES, true) || in_array($e['name'], self::EXCLUDE, true)) {
                return false;
            }
            $prev = $map[$e['url']] ?? null;

            return !$prev || (empty($prev['src']) && ($retry || $prev['checked'] < $retryBefore));
        }));

        $stats = ['found' => 0, 'missed' => 0, 'errors' => 0, 'open' => max(0, count($todo) - $limit)];
        foreach (array_slice($todo, 0, $limit) as $i => $e) {
            if ($i > 0) {
                usleep(1_200_000);  // Steam rate-limits its store API
            }
            try {
                $hit = self::lookup($e['name']);
            } catch (Throwable $ex) {
                // not marked as checked -> the next run tries again
                $stats['errors']++;
                $log && $log("Error for {$e['name']}: {$ex->getMessage()}");

                continue;
            }

            $map[$e['url']] = ($hit ?? []) + ['egg' => $e['name'], 'checked' => time()];
            cache()->forever(self::CACHE_KEY, $map);
            $hit ? $stats['found']++ : $stats['missed']++;
            $log && $log($hit ? "{$e['name']} -> {$hit['steam']} ({$hit['appid']}, {$hit['score']})" : "{$e['name']}: no match");
        }
        self::$map = $map;

        return $stats;
    }

    /** @return array{src:string, appid:int, steam:string, score:float}|null */
    private static function lookup(string $name): ?array
    {
        $q = self::norm($name);
        if ($q === '') {
            return null;
        }

        $items = self::http()->get('https://store.steampowered.com/api/storesearch/', ['cc' => 'us', 'l' => 'english', 'term' => $q])
            ->throw()->json('items') ?? [];

        $best = null;
        foreach (array_slice($items, 0, 5) as $item) {
            if (($item['type'] ?? '') !== 'app') {
                continue;
            }
            similar_text($q, self::norm((string) $item['name']), $pct);
            if (!$best || $pct > $best[0]) {
                $best = [$pct, $item];
            }
        }
        if (!$best || $best[0] / 100 < self::MIN_SCORE) {
            return null;
        }

        $appid = (int) $best[1]['id'];
        $header = self::http()->get('https://store.steampowered.com/api/appdetails', ['appids' => $appid, 'filters' => 'basic'])
            ->json("$appid.data.header_image") ?: "https://cdn.akamai.steamstatic.com/steam/apps/$appid/header.jpg";
        $image = self::http()->timeout(20)->get($header)->throw()->body();
        if (!str_starts_with($image, "\xff\xd8")) {
            return null;  // not a JPEG
        }
        Storage::disk('public')->put(self::DIR . "/$appid.jpg", $image);

        return ['src' => "$appid.jpg", 'appid' => $appid, 'steam' => (string) $best[1]['name'], 'score' => round($best[0] / 100, 2)];
    }

    private static function norm(string $s): string
    {
        $s = preg_replace('/[™®©:\'’!.,\-–()\[\]]/u', ' ', mb_strtolower($s));
        $s = preg_replace(self::NOISE, ' ', $s);

        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    private static function http(): PendingRequest
    {
        return Http::timeout(10)->connectTimeout(3)->withUserAgent('pelican-egg-library/1.0');
    }
}
