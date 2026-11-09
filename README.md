# laravel-randevu-slot

Randevu müsaitlik motoru: **çalışma saatleri, molalar, izin/tatil günleri, kapasite, çakışma kontrolü, müsait gün haritası ve ilk boş saat.**

Veritabanına hiç dokunmaz. Dolu randevuları kendin çekip `Aralik` nesnelerine çevirirsin — gerisini paket hesaplar. Eloquent, Mongo ya da uzak bir API, kaynağın ne olduğu fark etmez.

> A database-agnostic appointment availability engine for PHP: working hours, breaks, holidays, capacity, overlap checks and day maps.

[![Tests](https://github.com/bektasozcetin/laravel-randevu-slot/actions/workflows/tests.yml/badge.svg)](https://github.com/bektasozcetin/laravel-randevu-slot/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

## Kurulum

```bash
composer require bektasozcetin/laravel-randevu-slot
```

**Gereksinimler:** PHP 8.2+, Carbon 2.72+ veya 3.x. Laravel şart değil.

## Hızlı başlangıç

```php
use Bektasozcetin\RandevuSlot\{Aralik, Gun, Musaitlik, Takvim};
use Carbon\CarbonImmutable;

$takvim = Takvim::make()
    ->gunler(Gun::haftaIci(), '09:00', '18:00', slotDakika: 30, kapasite: 2)
    ->gun(Gun::Cumartesi, '10:00', '14:00')
    ->mola(Gun::haftaIci(), '12:30', '13:30')
    ->kapali('2026-01-01')                      // resmî tatil
    ->kapaliAralik('2026-07-01', '2026-07-15'); // yıllık izin

$musaitlik = new Musaitlik($takvim, enErkenDakika: 60, enGecGun: 60);

// Dolu randevularını kendi modelinden Aralik'a çevir
$dolular = Randevu::whereDate('baslangic', $gun)->get()
    ->map(fn ($r) => Aralik::olustur($r->baslangic, $r->bitis));

$slotlar = $musaitlik->musaitSlotlar($gun, sureDakika: 45, dolular: $dolular);
```

## Takvim kurma

```php
Takvim::make()
    ->gun(Gun::Pazartesi, '09:00', '18:00', slotDakika: 30, kapasite: 1)
    ->gunler([Gun::Sali, Gun::Carsamba], '09:00', '18:00')
    ->gunler(Gun::haftaIci(), '09:00', '18:00')   // Pazartesi–Cuma
    ->gunler(Gun::hepsi(), '09:00', '18:00')
    ->mola(Gun::Pazartesi, '12:30', '13:30')
    ->mola(Gun::haftaIci(), '15:00', '15:15')     // birden çok güne aynı mola
    ->kapali('2026-01-01', '2026-04-23')          // tek tek günler
    ->kapaliAralik('2026-07-01', '2026-07-15');   // aralık (uçlar dahil)
```

`Takvim` değiştirilemez (immutable): her çağrı yeni bir nesne döner, mevcut takvim bozulmaz.

| Alan | Anlamı |
|---|---|
| `slotDakika` | Slot **başlangıçları** arasındaki adım. 30 ise 09:00, 09:30, 10:00… |
| `kapasite` | Aynı anda kaç randevu alınabileceği |
| `molalar` | Slotun çakıştığı aralıklar kapatılır |

Randevu süresi takvimde değil, sorgu anında verilir — aynı takvimle 30 dakikalık da 90 dakikalık da hizmet satabilirsin.

## Sorgular

```php
$musaitlik->gun($gun, 45, $dolular);            // tüm slotlar (dolular dahil)
$musaitlik->musaitSlotlar($gun, 45, $dolular);  // yalnızca müsait olanlar
$musaitlik->musaitMi($baslangic, 45, $dolular); // belirli saat alınabilir mi
$musaitlik->gunHaritasi($ilkGun, 14, 45, $dolular);      // ['2026-11-09' => true, …]
$musaitlik->ilkMusait($ilkGun, 45, $dolular, 30);        // ilk boş slot ya da null
```

### Slot

```php
$slot->saat();      // "09:30"
$slot->baslangic;   // CarbonImmutable
$slot->bitis;       // CarbonImmutable
$slot->dolu;        // 1
$slot->kapasite;    // 2
$slot->kalan();     // 1
$slot->musait;      // true
$slot->sebep;       // müsait değilse neden: gecmis | cok_ileri | mola | dolu
$slot->toArray();   // API yanıtı için
```

`sebep` alanı arayüzde işe yarar: dolu bir saati "dolu", molaya denk geleni "öğle arası" diye farklı gösterebilirsin.

## Dolu aralıklar

```php
Aralik::olustur($baslangic, $bitis);          // iki Carbon'dan
Aralik::sureyle($baslangic, 45);              // başlangıç + süre
Aralik::sureyle($baslangic, 45, adet: 3);     // grup randevusu: 3 kapasite işgal eder
```

Aralıklar **kapalı-açık** `[başlangıç, bitiş)`: 10:00–10:30 ile 10:30–11:00 **çakışmaz.** Bitişik randevular birbirini bloke etmez.

## Zaman sınırları

```php
new Musaitlik($takvim, enErkenDakika: 60, enGecGun: 60);
```

- `enErkenDakika`: şu andan itibaren en erken kaç dakika sonrasına randevu verilebilir. 0 ise yalnızca geçmiş saatler kapanır.
- `enGecGun`: bugünden en fazla kaç gün ileriye. 0 = sınırsız.

## Performans

`gunHaritasi()` her gün için slot üretir. Geniş aralıklarda dolu randevuları **tek sorguda** çekip hepsini birden geçir; paket her gün için ayrıca sorgu atmaz:

```php
$dolular = Randevu::whereBetween('baslangic', [$ilk, $son])->get()
    ->map(fn ($r) => Aralik::olustur($r->baslangic, $r->bitis));

$harita = $musaitlik->gunHaritasi($ilk, 30, 45, $dolular);
```

## Test

```bash
composer install
composer test
```

## Lisans

MIT. Ayrıntı için [LICENSE](LICENSE).
