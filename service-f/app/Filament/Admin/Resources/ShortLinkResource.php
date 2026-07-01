<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ShortLinkResource\Pages;
use App\Filament\Admin\Resources\ShortLinkResource\RelationManagers\ClicksRelationManager;
use App\Models\ShortLink;
use App\Services\UrlShortener\ShortUrlBuilder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

/**
 * CRUD коротких ссылок текущего пользователя в Filament-панели.
 */
class ShortLinkResource extends Resource
{
    protected static ?string $model = ShortLink::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Короткие ссылки';

    protected static ?string $modelLabel = 'короткая ссылка';

    protected static ?string $pluralModelLabel = 'короткие ссылки';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('original_url')
                    ->label('Исходный URL')
                    ->required()
                    ->url()
                    ->maxLength(2048)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('code')
                    ->label('Короткий код')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
                Forms\Components\TextInput::make('short_url')
                    ->label('Короткая ссылка')
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Forms\Components\TextInput $component, ?ShortLink $record): void {
                        if ($record !== null) {
                            $component->state(self::buildShortUrl($record));
                        }
                    })
                    ->visibleOn('edit'),
                Forms\Components\TextInput::make('clicks_count')
                    ->label('Всего кликов')
                    ->disabled()
                    ->dehydrated(false)
                    ->numeric()
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('original_url')
                    ->label('Исходный URL')
                    ->limit(50)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Код')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('short_url')
                    ->label('Короткая ссылка')
                    ->state(fn (ShortLink $record): string => self::buildShortUrl($record))
                    ->url(fn (ShortLink $record): string => self::buildShortUrl($record))
                    ->openUrlInNewTab()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('clicks_count')
                    ->label('Клики')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('viewClicks')
                    ->label('Просмотр кликов')
                    ->icon('heroicon-o-cursor-arrow-rays')
                    ->color('info')
                    ->modalHeading('Журнал кликов')
                    ->modalDescription(fn (ShortLink $record): string => sprintf(
                        'Код: %s · Всего кликов: %d',
                        $record->code,
                        $record->clicks_count,
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрыть')
                    ->modalWidth(MaxWidth::TwoExtraLarge)
                    ->modalContent(fn (ShortLink $record): View => view(
                        'filament.modals.short-link-clicks',
                        ['shortLinkId' => $record->id],
                    )),
                Tables\Actions\EditAction::make()
                    ->label('Изменить'),
                Tables\Actions\DeleteAction::make()
                    ->label('Удалить'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Удалить'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ClicksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShortLinks::route('/'),
            'create' => Pages\CreateShortLink::route('/create'),
            'edit' => Pages\EditShortLink::route('/{record}/edit'),
        ];
    }

    /**
     * Ограничивает выборку ссылками текущего авторизованного пользователя.
     *
     * @return Builder<ShortLink>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }

    /** Собирает полный публичный URL короткой ссылки через {@see ShortUrlBuilder}. */
    public static function buildShortUrl(ShortLink $record): string
    {
        return app(ShortUrlBuilder::class)->build($record);
    }
}
