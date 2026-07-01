<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ShortLinkResource\Pages;

use App\Filament\Admin\Resources\ShortLinkResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

/** Список коротких ссылок текущего пользователя (scope в ShortLinkResource::getEloquentQuery). */
class ListShortLinks extends ListRecords
{
    protected static string $resource = ShortLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Создать'),
        ];
    }
}
