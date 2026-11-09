<?php

declare(strict_types=1);

use Bektasozcetin\RandevuSlot\Gun;
use Bektasozcetin\RandevuSlot\Takvim;
use Carbon\CarbonImmutable;

it('yalnizca tanimli gunlerde aciktir', function () {
    $takvim = Takvim::make()->gunler(Gun::haftaIci(), '09:00', '18:00');

    expect($takvim->acikMi(CarbonImmutable::parse('2026-11-09')))->toBeTrue()   // Pazartesi
        ->and($takvim->acikMi(CarbonImmutable::parse('2026-11-14')))->toBeFalse(); // Cumartesi
});

it('tatil gununu kapatir', function () {
    $takvim = Takvim::make()->gunler(Gun::haftaIci(), '09:00', '18:00')->kapali('2026-11-10');

    expect($takvim->kapaliMi(CarbonImmutable::parse('2026-11-10')))->toBeTrue()
        ->and($takvim->acikMi(CarbonImmutable::parse('2026-11-10')))->toBeFalse()
        ->and($takvim->acikMi(CarbonImmutable::parse('2026-11-11')))->toBeTrue();
});

it('izin araligindaki tum gunleri kapatir', function () {
    $takvim = Takvim::make()->gunler(Gun::hepsi(), '09:00', '18:00')->kapaliAralik('2026-07-01', '2026-07-05');

    expect($takvim->acikMi(CarbonImmutable::parse('2026-06-30')))->toBeTrue()
        ->and($takvim->acikMi(CarbonImmutable::parse('2026-07-03')))->toBeFalse()
        ->and($takvim->acikMi(CarbonImmutable::parse('2026-07-05')))->toBeFalse()
        ->and($takvim->acikMi(CarbonImmutable::parse('2026-07-06')))->toBeTrue();
});

it('degistirilemez: her cagri yeni takvim doner', function () {
    $ilk = Takvim::make()->gun(Gun::Pazartesi, '09:00', '18:00');
    $ikinci = $ilk->kapali('2026-11-09');

    expect($ilk->acikMi(CarbonImmutable::parse('2026-11-09')))->toBeTrue()
        ->and($ikinci->acikMi(CarbonImmutable::parse('2026-11-09')))->toBeFalse();
});

it('gecersiz saat bicimini reddeder', function () {
    Takvim::make()->gun(Gun::Pazartesi, '9:00', '18:00');
})->throws(InvalidArgumentException::class);

it('kapanisi acilistan once olan gunu reddeder', function () {
    Takvim::make()->gun(Gun::Pazartesi, '18:00', '09:00');
})->throws(InvalidArgumentException::class);
