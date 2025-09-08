<?php
namespace App\Providers\Filament;

use Filament\PanelProvider;
use Filament\Panel;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // यह सुनिश्चित करता है कि panel path config के अनुसार होगा (default 'admin')
        $path = config('filament.path', 'admin');

        return $panel
            ->id('admin')
            ->path($path)
            ->default();
    }
}
