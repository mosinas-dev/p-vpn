<?php

namespace App\Filament\Resources\VpnKeyResource\Pages;

use App\Filament\Resources\VpnKeyResource;
use Filament\Resources\Pages\ListRecords;

class ListVpnKeys extends ListRecords
{
    protected static string $resource = VpnKeyResource::class;
    protected function getHeaderActions(): array { return []; }
}
