<?php

declare(strict_types=1);

namespace Bektasozcetin\RandevuSlot;

use Carbon\CarbonImmutable;

/** Tek bir randevu başlangıcı ve o andaki doluluk durumu. */
final readonly class Slot
{
    public function __construct(
        public CarbonImmutable $baslangic,
        public CarbonImmutable $bitis,
        public int $dolu,
        public int $kapasite,
        public bool $musait,
        public ?string $sebep = null,
    ) {}

    public function saat(): string
    {
        return $this->baslangic->format('H:i');
    }

    public function kalan(): int
    {
        return max(0, $this->kapasite - $this->dolu);
    }

    /** @return array{saat: string, baslangic: string, bitis: string, dolu: int, kapasite: int, kalan: int, musait: bool, sebep: ?string} */
    public function toArray(): array
    {
        return [
            'saat' => $this->saat(),
            'baslangic' => $this->baslangic->toIso8601String(),
            'bitis' => $this->bitis->toIso8601String(),
            'dolu' => $this->dolu,
            'kapasite' => $this->kapasite,
            'kalan' => $this->kalan(),
            'musait' => $this->musait,
            'sebep' => $this->sebep,
        ];
    }
}
