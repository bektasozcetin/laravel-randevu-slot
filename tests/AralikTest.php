<?php

declare(strict_types=1);

use Bektasozcetin\RandevuSlot\Aralik;
use Carbon\CarbonImmutable;

it('cakismayi uc noktalarda dogru hesaplar', function () {
    $a = Aralik::olustur(CarbonImmutable::parse('2026-11-10 10:00'), CarbonImmutable::parse('2026-11-10 10:30'));
    $bitisik = Aralik::olustur(CarbonImmutable::parse('2026-11-10 10:30'), CarbonImmutable::parse('2026-11-10 11:00'));
    $ortusen = Aralik::olustur(CarbonImmutable::parse('2026-11-10 10:15'), CarbonImmutable::parse('2026-11-10 10:45'));

    expect($a->cakisiyorMu($bitisik))->toBeFalse()   // [10:00,10:30) ile [10:30,11:00) cakismaz
        ->and($a->cakisiyorMu($ortusen))->toBeTrue()
        ->and($ortusen->cakisiyorMu($a))->toBeTrue();
});

it('sure ve icerme hesaplar', function () {
    $a = Aralik::sureyle(CarbonImmutable::parse('2026-11-10 09:00'), 45);

    expect($a->dakika())->toBe(45)
        ->and($a->bitis->format('H:i'))->toBe('09:45')
        ->and($a->iceriyorMu(CarbonImmutable::parse('2026-11-10 09:44')))->toBeTrue()
        ->and($a->iceriyorMu(CarbonImmutable::parse('2026-11-10 09:45')))->toBeFalse();
});

it('gecersiz araligi reddeder', function () {
    Aralik::olustur(CarbonImmutable::parse('2026-11-10 11:00'), CarbonImmutable::parse('2026-11-10 10:00'));
})->throws(InvalidArgumentException::class);

it('sifir adedi reddeder', function () {
    Aralik::sureyle(CarbonImmutable::parse('2026-11-10 10:00'), 30, 0);
})->throws(InvalidArgumentException::class);
