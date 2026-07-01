<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\ShortLinkClick;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Таблица журнала кликов по короткой ссылке (для модального окна в Filament).
 */
class ShortLinkClicksTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public int $shortLinkId;

    public function mount(int $shortLinkId): void
    {
        $this->shortLinkId = $shortLinkId;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ShortLinkClick::query()
                    ->where('short_link_id', $this->shortLinkId)
                    ->whereHas('shortLink', fn ($query) => $query->where('user_id', auth()->id()))
            )
            ->columns([
                TextColumn::make('ip_address')
                    ->label('IP-адрес')
                    ->searchable(),
                TextColumn::make('visited_at')
                    ->label('Время перехода')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('visited_at', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Кликов пока нет.')
            ->emptyStateDescription('');
    }

    public function render(): View
    {
        return view('livewire.short-link-clicks-table');
    }
}
