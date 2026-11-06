<?php

namespace Jerox\EggLibrary;

use App\Contracts\Plugins\HasPluginSettings;
use App\Traits\EnvironmentWriterTrait;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Panel;
use Jerox\EggLibrary\Pages\Library;

class EggLibraryPlugin implements HasPluginSettings, Plugin
{
    use EnvironmentWriterTrait;

    public function getId(): string
    {
        return 'egg-library';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([Library::class]);

        // In the user area also reachable from the profile menu (top right)
        if ($panel->getId() === 'app') {
            $panel->userMenuItems([
                Action::make('egg_library')
                    ->label(fn () => trans('egg-library::strings.title'))
                    ->icon('tabler-books')
                    ->url(fn () => Library::getUrl(panel: 'app'))
                    ->visible(fn () => Library::canAccess()),
            ]);
        }
    }

    public function boot(Panel $panel): void {}

    public function getSettingsForm(): array
    {
        return [
            Toggle::make('allow_user_install')
                ->label(trans('egg-library::strings.settings.allow_user_install'))
                ->helperText(trans('egg-library::strings.settings.allow_user_install_help')),
            TextInput::make('installs_per_hour')
                ->label(trans('egg-library::strings.settings.installs_per_hour'))
                ->integer()->minValue(1)->maxValue(1000)->required(),
            Toggle::make('image_search')
                ->label(trans('egg-library::strings.settings.image_search'))
                ->helperText(trans('egg-library::strings.settings.image_search_help')),
        ];
    }

    public function getSettingsFormData(): array
    {
        return [
            'allow_user_install' => (bool) config('egg-library.allow_user_install'),
            'installs_per_hour' => (int) config('egg-library.installs_per_hour', 10),
            'image_search' => (bool) config('egg-library.image_search', true),
        ];
    }

    public function saveSettings(array $data): void
    {
        $this->writeToEnvironment([
            'EGG_LIBRARY_ALLOW_USER_INSTALL' => !empty($data['allow_user_install']) ? 'true' : 'false',
            'EGG_LIBRARY_INSTALLS_PER_HOUR' => (int) $data['installs_per_hour'],
            'EGG_LIBRARY_IMAGE_SEARCH' => !empty($data['image_search']) ? 'true' : 'false',
        ]);

        Notification::make()->title(trans('egg-library::strings.settings.saved'))->success()->send();
    }
}
