<?php

declare(strict_types=1);

namespace Bektasozcetin\RandevuSlot;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Kapalı-açık [başlangıç, bitiş) zaman aralığı.
 *
 * Dolu randevular ve molalar bu tiple ifade edilir. Bitiş anı aralığa dahil
 * değildir: 10:00–10:30 ile 10:30–11:00 çakışmaz.
 */
final readonly class Aralik
{
    public CarbonImmutable $baslangic;

    public CarbonImmutable $bitis;

    /**
     * @param  int  $adet  Bu aralığın kaç kapasite işgal ettiği (grup randevusu için >1).
     */
    public function __construct(
        CarbonInterface $baslangic,
        CarbonInterface $bitis,
        public int $adet = 1,
    ) {
        $this->baslangic = CarbonImmutable::instance($baslangic);
        $this->bitis = CarbonImmutable::instance($bitis);

        if ($this->bitis->lessThanOrEqualTo($this->baslangic)) {
            throw new InvalidArgumentException('Aralık bitişi başlangıcından sonra olmalı.');
        }

        if ($adet < 1) {
            throw new InvalidArgumentException('Aralık adedi en az 1 olmalı.');
        }
    }

    public static function olustur(CarbonInterface $baslangic, CarbonInterface $bitis, int $adet = 1): self
    {
        return new self($baslangic, $bitis, $adet);
    }

    /** Başlangıç ve süreden aralık üretir. */
    public static function sureyle(CarbonInterface $baslangic, int $dakika, int $adet = 1): self
    {
        return new self($baslangic, CarbonImmutable::instance($baslangic)->addMinutes($dakika), $adet);
    }

    public function cakisiyorMu(self $diger): bool
    {
        return $this->baslangic->lessThan($diger->bitis)
            && $this->bitis->greaterThan($diger->baslangic);
    }

    public function dakika(): int
    {
        return (int) $this->baslangic->diffInMinutes($this->bitis);
    }

    public function iceriyorMu(CarbonInterface $an): bool
    {
        return $an->greaterThanOrEqualTo($this->baslangic) && $an->lessThan($this->bitis);
    }
}
