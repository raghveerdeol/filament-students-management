<x-filament-panels::page>
    {!! QrCode::size(100)->generate($this->getRecord()->name); !!}
</x-filament-panels::page>
