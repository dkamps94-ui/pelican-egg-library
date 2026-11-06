# Egg Library

A Pelican Panel plugin that turns the community egg catalog into a searchable library with artwork.
Find a game, click **Install**, done.

## Features

- Browse all community eggs (the same catalog Pelican's built-in GitHub import uses) with descriptions
- Search by name, description or category, filter by category
- One-click install; existing eggs are never overwritten (checked by UUID, name and source URL)
- Steam artwork for game eggs, found automatically in the background and stored on your panel
  (no images are loaded from Steam in your users' browsers)
- Shows which eggs are already installed, with shortcuts to create a server or open the egg
- Available in the admin area and, if you allow it, for regular users (profile menu, top right)
- Per-user install rate limit
- English and German

## Installation

1. Download the ZIP.
2. In your panel go to **Admin → Plugins → Import** and select the ZIP.
3. Click **Install**.

The library appears in the admin navigation next to **Eggs**.

## Settings

Open **Admin → Plugins → Egg Library → Settings**:

| Setting | Default | Description |
|---|---|---|
| Allow all users to install eggs | off | Off: only users with the *create egg* permission can open the library. On: every logged-in user can browse it and install eggs. |
| Installs per user and hour | 10 | Rate limit for installs. |
| Find artwork automatically | on | Looks up Steam header images for game eggs. |

## Artwork

The panel scheduler runs `egg-library:find-images` every hour and looks up up to 25 eggs per run on the
Steam store (near-exact name matches only). After the first few hours every game has artwork; afterwards
only new catalog eggs are looked up. Eggs without a match are retried after 7 days and show a category icon.

Run it manually (as the web server user, e.g. `www-data`):

```bash
php artisan egg-library:find-images --limit=100
```

Images are stored in `storage/app/public/egg-library/`. They are fetched by your own panel from Steam;
this plugin does not ship any images. Game artwork belongs to the respective rights holders.

## Requirements

- Pelican Panel with plugin support (tested with v1.0.0-rc.2)
- A running panel scheduler (standard in the official Docker image)
- Outgoing HTTPS to `raw.githubusercontent.com` and `store.steampowered.com`

## License

MIT, see [LICENSE](LICENSE).

This plugin is not affiliated with Pelican, Valve or Steam.
