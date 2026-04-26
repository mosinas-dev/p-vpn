<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Платежи';
    protected static ?string $modelLabel = 'Платёж';

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
                Tables\Columns\TextColumn::make('cardlink_bill_id')->label('Bill ID')->copyable(),
                Tables\Columns\TextColumn::make('amount_kopecks')
                    ->label('Сумма')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' ₽')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')->colors([
                    'gray' => 'pending', 'success' => 'success',
                    'danger' => 'fail', 'warning' => 'refunded',
                ]),
                Tables\Columns\BadgeColumn::make('intent'),
                Tables\Columns\TextColumn::make('paid_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->label('Создан'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'pending', 'success' => 'success',
                    'fail' => 'fail', 'refunded' => 'refunded',
                ]),
                Tables\Filters\SelectFilter::make('intent')->options([
                    'wallet_topup' => 'wallet_topup',
                    'subscription_purchase' => 'subscription_purchase',
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->actions([Tables\Actions\ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }
}
