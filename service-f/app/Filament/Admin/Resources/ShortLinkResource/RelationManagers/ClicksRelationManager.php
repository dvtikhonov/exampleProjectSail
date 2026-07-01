<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ShortLinkResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * История переходов по короткой ссылке (только просмотр).
 */
class ClicksRelationManager extends RelationManager
{
    protected static string $relationship = 'clicks';

    protected static ?string $title = 'Клики';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ip_address')
            ->columns([
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP-адрес')
                    ->searchable(),
                Tables\Columns\TextColumn::make('visited_at')
                    ->label('Время перехода')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('visited_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
