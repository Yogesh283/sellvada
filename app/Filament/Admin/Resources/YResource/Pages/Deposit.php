<?php

namespace App\Filament\Admin\Resources\YResource\Pages;

use App\Filament\Admin\Resources\YResource;
use Filament\Resources\Pages\Page;

class Deposit extends Page
{
    protected static string $resource = YResource::class;

    protected static string $view = 'filament.admin.resources.y-resource.pages.deposit';
}
