<?php

declare(strict_types=1);

namespace Bektasozcetin\RandevuSlot;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Takvim + dolu aralıklardan müsait randevu saatlerini üretir.
 *
 * Veritabanına hiç dokunmaz: dolu randevularını kendin çekip Aralik
 * nesnelerine çevirirsin. Böylece Eloquent, Mongo ya da uzak bir API —
 * kaynağın ne olduğu fark etmez.
 */
final readonly class Musaitlik
{
    /**
     * @param  int  $enErkenDakika  Şu andan itibaren en erken kaç dakika sonrasına randevu verilebilir
     * @param  int  $enGecGun  Bugünden itibaren en fazla kaç gün ileriye randevu verilebilir (0 = sınırsız)
     */
    public function __construct(
        private Takvim $takvim,
        private int $enErkenDakika = 0,
        private int $enGecGun = 0,
    ) {}

    /**
     * Bir günün tüm slotları (dolu olanlar dahil; `musait` alanına bak).
     *
     * @param  array<int, Aralik>|Collection<int, Aralik>  $dolular
     * @return Collection<int, Slot>
     */
    public function gun(CarbonInterface $gun, int $sureDakika, array|Collection $dolular = []): Collection
    {
        $tanim = $this->takvim->gunTanimi($gun);

        if (! $tanim) {
            return new Collection;
        }

        $gun = CarbonImmutable::instance($gun)->startOfDay();
        $dolular = $dolular instanceof Collection ? $dolular : new Collection($dolular);

        $acilis = $gun->setTimeFromTimeString($tanim->acilis);
        $kapanis = $gun->setTimeFromTimeString($tanim->kapanis);
        $sure = max(1, $sureDakika);
        $molalar = $this->molaAraliklari($gun, $tanim);

        $slotlar = new Collection;

        for ($bas = $acilis; $bas->addMinutes($sure)->lessThanOrEqualTo($kapanis); $bas = $bas->addMinutes($tanim->slotDakika)) {
            $aralik = new Aralik($bas, $bas->addMinutes($sure));

            $dolu = $dolular
                ->filter(fn (Aralik $r) => $r->cakisiyorMu($aralik))
                ->sum(fn (Aralik $r) => $r->adet);

            $sebep = $this->musaitSebebi($aralik, (int) $dolu, $tanim->kapasite, $molalar);

            $slotlar->push(new Slot(
                baslangic: $bas,
                bitis: $aralik->bitis,
                dolu: (int) $dolu,
                kapasite: $tanim->kapasite,
                musait: $sebep === null,
                sebep: $sebep,
            ));
        }

        return $slotlar;
    }

    /**
     * Yalnızca müsait slotlar.
     *
     * @param  array<int, Aralik>|Collection<int, Aralik>  $dolular
     * @return Collection<int, Slot>
     */
    public function musaitSlotlar(CarbonInterface $gun, int $sureDakika, array|Collection $dolular = []): Collection
    {
        return $this->gun($gun, $sureDakika, $dolular)
            ->filter(fn (Slot $s) => $s->musait)
            ->values();
    }

    /**
     * Verilen başlangıç saatine randevu alınabilir mi?
     *
     * @param  array<int, Aralik>|Collection<int, Aralik>  $dolular
     */
    public function musaitMi(CarbonInterface $baslangic, int $sureDakika, array|Collection $dolular = []): bool
    {
        $hedef = CarbonImmutable::instance($baslangic)->format('H:i');

        return $this->gun($baslangic, $sureDakika, $dolular)
            ->first(fn (Slot $s) => $s->saat() === $hedef)?->musait ?? false;
    }

    /**
     * Aralıktaki her gün için "en az bir müsait slot var mı".
     *
     * @param  array<int, Aralik>|Collection<int, Aralik>  $dolular
     * @return array<string, bool> "Y-m-d" => müsait
     */
    public function gunHaritasi(CarbonInterface $ilkGun, int $gunSayisi, int $sureDakika, array|Collection $dolular = []): array
    {
        $ilk = CarbonImmutable::instance($ilkGun)->startOfDay();
        $sonuc = [];

        for ($i = 0; $i < max(1, $gunSayisi); $i++) {
            $gun = $ilk->addDays($i);
            $sonuc[$gun->toDateString()] = $this->musaitSlotlar($gun, $sureDakika, $dolular)->isNotEmpty();
        }

        return $sonuc;
    }

    /**
     * İleriye doğru tarayıp ilk müsait slotu bulur; yoksa null.
     *
     * @param  array<int, Aralik>|Collection<int, Aralik>  $dolular
     * @param  int  $taranacakGun  Kaç gün ileriye bakılacağı
     */
    public function ilkMusait(CarbonInterface $ilkGun, int $sureDakika, array|Collection $dolular = [], int $taranacakGun = 30): ?Slot
    {
        $ilk = CarbonImmutable::instance($ilkGun)->startOfDay();

        for ($i = 0; $i < max(1, $taranacakGun); $i++) {
            $slot = $this->musaitSlotlar($ilk->addDays($i), $sureDakika, $dolular)->first();

            if ($slot) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * Slotun neden müsait olmadığını söyler; müsaitse null.
     *
     * @param  array<int, Aralik>  $molalar
     */
    private function musaitSebebi(Aralik $slot, int $dolu, int $kapasite, array $molalar): ?string
    {
        if ($slot->baslangic->lessThan($this->enErkenAn())) {
            return 'gecmis';
        }

        if ($this->enGecGun > 0 && $slot->baslangic->greaterThan($this->enGecAn())) {
            return 'cok_ileri';
        }

        foreach ($molalar as $mola) {
            if ($slot->cakisiyorMu($mola)) {
                return 'mola';
            }
        }

        return $dolu >= $kapasite ? 'dolu' : null;
    }

    private function enErkenAn(): CarbonImmutable
    {
        return CarbonImmutable::now()->addMinutes($this->enErkenDakika);
    }

    private function enGecAn(): CarbonImmutable
    {
        return CarbonImmutable::now()->addDays($this->enGecGun)->endOfDay();
    }

    /** @return array<int, Aralik> */
    private function molaAraliklari(CarbonImmutable $gun, GunTanimi $tanim): array
    {
        return array_map(
            fn (array $mola) => new Aralik(
                $gun->setTimeFromTimeString($mola[0]),
                $gun->setTimeFromTimeString($mola[1]),
            ),
            $tanim->molalar,
        );
    }
}
