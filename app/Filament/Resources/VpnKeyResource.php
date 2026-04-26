<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VpnKeyResource\Pages;
use App\Models\VpnKey;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VpnKeyResource extends Resource
{
    protected static ?string $model = VpnKey::class;
    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationLabel = 'VPN-ключи';
    protected static ?string $modelLabel = 'Ключ';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('user.email')->searchable()->label('Email'),
                Tables\Columns\TextColumn::make('panel_server_id')->label('Сервер'),
                Tables\Columns\TextColumn::make('panel_client_id')->label('panel_client_id'),
                Tables\Columns\BadgeColumn::make('status')->colors([
                    'success' => 'active', 'gray' => 'revoked',
                ]),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('revoked_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['active' => 'active', 'revoked' => 'revoked']),
            ])
            ->actions([Tables\Actions\ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVpnKeys::route('/'),
            'view' => Pages\ViewVpnKey::route('/{record}'),
        ];
    }
}
