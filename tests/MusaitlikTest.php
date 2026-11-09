<?php

declare(strict_types=1);

use Bektasozcetin\RandevuSlot\Aralik;
use Bektasozcetin\RandevuSlot\Gun;
use Bektasozcetin\RandevuSlot\Musaitlik;
use Bektasozcetin\RandevuSlot\Slot;
use Bektasozcetin\RandevuSlot\Takvim;
use Carbon\CarbonImmutable;

// Testler sabit bir "şimdi" üzerinde çalışsın; 2026-11-09 Pazartesi.
beforeEach(fn () => CarbonImmutable::setTestNow('2026-11-09 08:00:00'));
afterEach(fn () => CarbonImmutable::setTestNow());

function takvim(array $molalar = [], int $kapasite = 1, int $slotDakika = 30): Takvim
{
    return Takvim::make()->gunler(Gun::haftaIci(), '09:00', '12:00', $slotDakika, $kapasite, $molalar);
}

it('calisma saatlerine gore slot uretir', function () {
    $slotlar = (new Musaitlik(takvim()))->gun(CarbonImmutable::parse('2026-11-09'), 30);

    expect($slotlar)->toHaveCount(6)                       // 09:00–12:00, 30 dk adım
        ->and($slotlar->first()->saat())->toBe('09:00')
        ->and($slotlar->last()->saat())->toBe('11:30');
});

it('sure kapanisi asarsa son slotu uretmez', function () {
    $slotlar = (new Musaitlik(takvim()))->gun(CarbonImmutable::parse('2026-11-09'), 60);

    expect($slotlar->last()->saat())->toBe('11:00')         // 11:30 + 60dk = 12:30 > 12:00
        ->and($slotlar)->toHaveCount(5);
});

it('kapali gunde slot uretmez', function () {
    $musaitlik = new Musaitlik(takvim());

    expect($musaitlik->gun(CarbonImmutable::parse('2026-11-14'), 30))->toBeEmpty()  // Cumartesi
        ->and($musaitlik->gun(CarbonImmutable::parse('2026-11-15'), 30))->toBeEmpty();
});

it('molaya denk gelen slotu kapatir', function () {
    $slotlar = (new Musaitlik(takvim([['10:00', '11:00']])))
        ->gun(CarbonImmutable::parse('2026-11-09'), 30)
        ->keyBy(fn (Slot $s) => $s->saat());

    expect($slotlar['09:30']->musait)->toBeTrue()
        ->and($slotlar['10:00']->musait)->toBeFalse()
        ->and($slotlar['10:00']->sebep)->toBe('mola')
        ->and($slotlar['10:30']->musait)->toBeFalse()
        ->and($slotlar['11:00']->musait)->toBeTrue();
});

it('dolu randevuyu kapasiteye gore sayar', function () {
    $dolular = [Aralik::sureyle(CarbonImmutable::parse('2026-11-09 10:00'), 30)];

    $tek = (new Musaitlik(takvim(kapasite: 1)))->gun(CarbonImmutable::parse('2026-11-09'), 30)
        ->keyBy(fn (Slot $s) => $s->saat());
    $tekDolu = (new Musaitlik(takvim(kapasite: 1)))->gun(CarbonImmutable::parse('2026-11-09'), 30, $dolular)
        ->keyBy(fn (Slot $s) => $s->saat());
    $ciftDolu = (new Musaitlik(takvim(kapasite: 2)))->gun(CarbonImmutable::parse('2026-11-09'), 30, $dolular)
        ->keyBy(fn (Slot $s) => $s->saat());

    expect($tek['10:00']->musait)->toBeTrue()
        ->and($tekDolu['10:00']->musait)->toBeFalse()
        ->and($tekDolu['10:00']->sebep)->toBe('dolu')
        ->and($ciftDolu['10:00']->musait)->toBeTrue()       // kapasite 2, 1 dolu
        ->and($ciftDolu['10:00']->kalan())->toBe(1);
});

it('grup randevusunu adet kadar sayar', function () {
    $dolular = [Aralik::sureyle(CarbonImmutable::parse('2026-11-09 10:00'), 30, adet: 2)];

    $slot = (new Musaitlik(takvim(kapasite: 2)))->gun(CarbonImmutable::parse('2026-11-09'), 30, $dolular)
        ->first(fn (Slot $s) => $s->saat() === '10:00');

    expect($slot->dolu)->toBe(2)->and($slot->musait)->toBeFalse();
});

it('gecmis saatleri kapatir', function () {
    CarbonImmutable::setTestNow('2026-11-09 10:15:00');

    $slotlar = (new Musaitlik(takvim()))->gun(CarbonImmutable::parse('2026-11-09'), 30)
        ->keyBy(fn (Slot $s) => $s->saat());

    expect($slotlar['09:30']->sebep)->toBe('gecmis')
        ->and($slotlar['10:00']->sebep)->toBe('gecmis')
        ->and($slotlar['10:30']->musait)->toBeTrue();
});

it('en erken on sureye uyar', function () {
    CarbonImmutable::setTestNow('2026-11-09 09:00:00');

    $slotlar = (new Musaitlik(takvim(), enErkenDakika: 120))->gun(CarbonImmutable::parse('2026-11-09'), 30)
        ->keyBy(fn (Slot $s) => $s->saat());

    expect($slotlar['10:30']->musait)->toBeFalse()   // 09:00 + 2 saat = 11:00
        ->and($slotlar['11:00']->musait)->toBeTrue();
});

it('en gec gun sinirina uyar', function () {
    $musaitlik = new Musaitlik(takvim(), enGecGun: 2);

    expect($musaitlik->musaitSlotlar(CarbonImmutable::parse('2026-11-10'), 30))->not->toBeEmpty()
        ->and($musaitlik->musaitSlotlar(CarbonImmutable::parse('2026-11-13'), 30))->toBeEmpty()
        ->and($musaitlik->gun(CarbonImmutable::parse('2026-11-13'), 30)->first()->sebep)->toBe('cok_ileri');
});

it('belirli bir saatin musait olup olmadigini soyler', function () {
    $musaitlik = new Musaitlik(takvim());
    $dolular = [Aralik::sureyle(CarbonImmutable::parse('2026-11-09 10:00'), 30)];

    expect($musaitlik->musaitMi(CarbonImmutable::parse('2026-11-09 10:00'), 30))->toBeTrue()
        ->and($musaitlik->musaitMi(CarbonImmutable::parse('2026-11-09 10:00'), 30, $dolular))->toBeFalse()
        ->and($musaitlik->musaitMi(CarbonImmutable::parse('2026-11-09 10:07'), 30))->toBeFalse(); // slot baslangici degil
});

it('gun haritasi cikarir', function () {
    $takvim = takvim()->kapali('2026-11-11');
    $harita = (new Musaitlik($takvim))->gunHaritasi(CarbonImmutable::parse('2026-11-09'), 7, 30);

    expect($harita)->toHaveCount(7)
        ->and($harita['2026-11-09'])->toBeTrue()
        ->and($harita['2026-11-11'])->toBeFalse()   // kapali
        ->and($harita['2026-11-14'])->toBeFalse();  // cumartesi
});

it('ilk musait slotu ileriye tarayarak bulur', function () {
    $takvim = takvim()->kapali('2026-11-09', '2026-11-10');

    $slot = (new Musaitlik($takvim))->ilkMusait(CarbonImmutable::parse('2026-11-09'), 30);

    expect($slot)->not->toBeNull()
        ->and($slot->baslangic->toDateString())->toBe('2026-11-11')
        ->and($slot->saat())->toBe('09:00');
});

it('hic musait slot yoksa null doner', function () {
    $takvim = Takvim::make()->gun(Gun::Pazartesi, '09:00', '12:00')->kapaliAralik('2026-11-09', '2026-12-31');

    expect((new Musaitlik($takvim))->ilkMusait(CarbonImmutable::parse('2026-11-09'), 30, [], 20))->toBeNull();
});

it('slotu diziye cevirir', function () {
    $slot = (new Musaitlik(takvim()))->gun(CarbonImmutable::parse('2026-11-09'), 30)->first();

    expect($slot->toArray())->toHaveKeys(['saat', 'baslangic', 'bitis', 'dolu', 'kapasite', 'kalan', 'musait', 'sebep'])
        ->and($slot->toArray()['saat'])->toBe('09:00');
});
