<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\Subscription;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Подписки';
    protected static ?string $modelLabel = 'Подписка';

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
                Tables\Columns\BadgeColumn::make('status')->colors([
                    'gray' => Subscription::STATUS_PENDING,
                    'success' => Subscription::STATUS_ACTIVE,
                    'warning' => Subscription::STATUS_EXPIRED,
                    'danger' => Subscription::STATUS_CANCELLED,
                ]),
                Tables\Columns\TextColumn::make('months')->sortable()->label('Мес.'),
                Tables\Columns\TextColumn::make('price_kopecks')
                    ->label('Цена')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 0) . ' ₽'),
                Tables\Columns\TextColumn::make('starts_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('ends_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'pending',
                    'active' => 'active',
                    'expired' => 'expired',
                    'cancelled' => 'cancelled',
                ]),
            ])
            ->actions([Tables\Actions\ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'view' => Pages\ViewSubscription::route('/{record}'),
        ];
    }
}
