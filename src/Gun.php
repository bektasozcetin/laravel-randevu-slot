<?php

declare(strict_types=1);

namespace Bektasozcetin\RandevuSlot;

/** ISO-8601 hafta günü: Pazartesi 1, Pazar 7. Carbon::isoWeekday() ile aynı. */
enum Gun: int
{
    case Pazartesi = 1;
    case Sali = 2;
    case Carsamba = 3;
    case Persembe = 4;
    case Cuma = 5;
    case Cumartesi = 6;
    case Pazar = 7;

    /** @return array<int, self> Pazartesi–Cuma */
    public static function haftaIci(): array
    {
        return [self::Pazartesi, self::Sali, self::Carsamba, self::Persembe, self::Cuma];
    }

    /** @return array<int, self> Cumartesi–Pazar */
    public static function haftaSonu(): array
    {
        return [self::Cumartesi, self::Pazar];
    }

    /** @return array<int, self> */
    public static function hepsi(): array
    {
        return self::cases();
    }

    public function ad(): string
    {
        return match ($this) {
            self::Pazartesi => 'Pazartesi',
            self::Sali => 'Salı',
            self::Carsamba => 'Çarşamba',
            self::Persembe => 'Perşembe',
            self::Cuma => 'Cuma',
            self::Cumartesi => 'Cumartesi',
            self::Pazar => 'Pazar',
        };
    }
}
