@php
    /** @var int $shortLinkId */
@endphp

<div class="fi-ta-ctn divide-y divide-gray-200 dark:divide-white/10">
    @livewire(\App\Livewire\ShortLinkClicksTable::class, ['shortLinkId' => $shortLinkId], key('short-link-clicks-'.$shortLinkId))
</div>
