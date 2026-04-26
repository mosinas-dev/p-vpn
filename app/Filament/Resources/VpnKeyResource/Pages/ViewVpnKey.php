<?php

namespace App\Filament\Resources\VpnKeyResource\Pages;

use App\Filament\Resources\VpnKeyResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewVpnKey extends ViewRecord
{
    protected static string $resource = VpnKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
