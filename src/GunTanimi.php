<?php

declare(strict_types=1);

namespace Bektasozcetin\RandevuSlot;

use InvalidArgumentException;

/**
 * Bir hafta gününün çalışma düzeni.
 *
 * @param  string  $acilis  "09:00"
 * @param  string  $kapanis  "18:00"
 * @param  int  $slotDakika  Slot başlangıçları arasındaki adım
 * @param  int  $kapasite  Aynı anda kaç randevu alınabileceği
 * @param  array<int, array{0: string, 1: string}>  $molalar  [["12:30", "13:30"], …]
 */
final readonly class GunTanimi
{
    public function __construct(
        public string $acilis,
        public string $kapanis,
        public int $slotDakika = 30,
        public int $kapasite = 1,
        public array $molalar = [],
    ) {
        foreach ([$acilis, $kapanis] as $saat) {
            if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $saat)) {
                throw new InvalidArgumentException("Saat \"HH:MM\" biçiminde olmalı, \"{$saat}\" verildi.");
            }
        }

        if ($acilis >= $kapanis) {
            throw new InvalidArgumentException("Kapanış açılıştan sonra olmalı ({$acilis}–{$kapanis}).");
        }

        if ($slotDakika < 1) {
            throw new InvalidArgumentException('Slot adımı en az 1 dakika olmalı.');
        }

        if ($kapasite < 1) {
            throw new InvalidArgumentException('Kapasite en az 1 olmalı.');
        }
    }
}
