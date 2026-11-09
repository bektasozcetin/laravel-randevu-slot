<?php

declare(strict_types=1);

namespace Bektasozcetin\RandevuSlot;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Haftalık çalışma düzeni + kapalı gün istisnaları (izin, tatil).
 *
 * Akıcı arayüzle kurulur ve değiştirilemez: her çağrı yeni bir Takvim döner.
 *
 * ```php
 * $takvim = Takvim::make()
 *     ->gunler(Gun::haftaIci(), '09:00', '18:00', slotDakika: 30, kapasite: 2)
 *     ->mola(Gun::haftaIci(), '12:30', '13:30')
 *     ->kapali('2026-01-01')
 *     ->kapaliAralik('2026-07-01', '2026-07-15');
 * ```
 */
final readonly class Takvim
{
    /**
     * @param  array<int, GunTanimi>  $gunler  ISO gün numarası => tanım
     * @param  array<int, string>  $kapaliGunler  "Y-m-d" listesi
     */
    private function __construct(
        private array $gunler = [],
        private array $kapaliGunler = [],
    ) {}

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $molalar
     */
    public function gun(Gun $gun, string $acilis, string $kapanis, int $slotDakika = 30, int $kapasite = 1, array $molalar = []): self
    {
        $gunler = $this->gunler;
        $gunler[$gun->value] = new GunTanimi($acilis, $kapanis, $slotDakika, $kapasite, $molalar);

        return new self($gunler, $this->kapaliGunler);
    }

    /**
     * @param  array<int, Gun>  $gunler
     * @param  array<int, array{0: string, 1: string}>  $molalar
     */
    public function gunler(array $gunler, string $acilis, string $kapanis, int $slotDakika = 30, int $kapasite = 1, array $molalar = []): self
    {
        $takvim = $this;

        foreach ($gunler as $gun) {
            $takvim = $takvim->gun($gun, $acilis, $kapanis, $slotDakika, $kapasite, $molalar);
        }

        return $takvim;
    }

    /**
     * Belirtilen günlere mola ekler. Gün tanımlı değilse atlanır.
     *
     * @param  Gun|array<int, Gun>  $gun
     */
    public function mola(Gun|array $gun, string $baslangic, string $bitis): self
    {
        $hedefler = is_array($gun) ? $gun : [$gun];
        $gunler = $this->gunler;

        foreach ($hedefler as $g) {
            $tanim = $gunler[$g->value] ?? null;

            if (! $tanim) {
                continue;
            }

            $gunler[$g->value] = new GunTanimi(
                $tanim->acilis,
                $tanim->kapanis,
                $tanim->slotDakika,
                $tanim->kapasite,
                [...$tanim->molalar, [$baslangic, $bitis]],
            );
        }

        return new self($gunler, $this->kapaliGunler);
    }

    /** Tek bir günü tamamen kapatır (resmî tatil, izin). */
    public function kapali(CarbonInterface|string ...$tarihler): self
    {
        $kapalilar = $this->kapaliGunler;

        foreach ($tarihler as $tarih) {
            $kapalilar[] = CarbonImmutable::parse($tarih)->toDateString();
        }

        return new self($this->gunler, array_values(array_unique($kapalilar)));
    }

    /** İki tarih arasındaki tüm günleri kapatır (uçlar dahil). */
    public function kapaliAralik(CarbonInterface|string $ilk, CarbonInterface|string $son): self
    {
        $gun = CarbonImmutable::parse($ilk)->startOfDay();
        $bitis = CarbonImmutable::parse($son)->startOfDay();
        $takvim = $this;

        while ($gun->lessThanOrEqualTo($bitis)) {
            $takvim = $takvim->kapali($gun);
            $gun = $gun->addDay();
        }

        return $takvim;
    }

    public function gunTanimi(CarbonInterface $gun): ?GunTanimi
    {
        if ($this->kapaliMi($gun)) {
            return null;
        }

        return $this->gunler[$gun->isoWeekday()] ?? null;
    }

    public function kapaliMi(CarbonInterface $gun): bool
    {
        return in_array($gun->toDateString(), $this->kapaliGunler, true);
    }

    public function acikMi(CarbonInterface $gun): bool
    {
        return $this->gunTanimi($gun) !== null;
    }
}
