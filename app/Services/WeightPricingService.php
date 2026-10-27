<?php

namespace App\Services;

use App\Models\DeliveryService;
use App\Models\DeliveryWeightBand;
use Illuminate\Support\Collection;

/**
 * What a weight-priced carrier — DHL Express — charges to ship one basket to
 * one zone. Only DeliveryFeeService calls it: it decides the zone, this works
 * out the money.
 *
 *   grid price for the billed weight
 *   + a handling surcharge per bulky or heavy piece
 *   + the fuel surcharge, a % of those two
 *   + the service's fixed fee per shipment
 *
 * The fuel surcharge is not applied to the fixed fee: DHL levies it on
 * transport and surcharges, and the fixed fee is a local service charge.
 */
class WeightPricingService
{
    /** A piece longer than this, in cm, needs additional handling… */
    public const HANDLING_LONGEST_SIDE_CM = 100;

    /** …as does one whose second-longest side is over this… */
    public const HANDLING_SECOND_SIDE_CM = 80;

    /** …or one that actually weighs more than this, in kg. */
    public const HANDLING_WEIGHT_KG = 25;

    /** Over this, actual or volumetric, a piece is overweight instead. */
    public const OVERWEIGHT_KG = 70;

    /**
     * One quote per zone that has a grid, zone ⇒ price — what checkout needs
     * to price every country on the card for this basket at once.
     *
     * @param  array<int, array{weight: float|null, chargeable: float|null, dimensions: array{length: float, width: float, height: float}|null}>  $pieces  ProductShippingService::pieces()
     * @return array<int, float>
     */
    public function quotesByZone(DeliveryService $service, array $pieces): array
    {
        return DeliveryWeightBand::query()
            ->where('delivery_service_id', $service->id)
            ->get()
            ->groupBy('zone')
            ->map(fn (Collection $bands) => $this->quote($service, $bands, $pieces))
            ->all();
    }

    /**
     * The quote for one zone, or null when that zone has no grid on file —
     * a destination that cannot be priced.
     *
     * @param  array<int, array{weight: float|null, chargeable: float|null, dimensions: array{length: float, width: float, height: float}|null}>  $pieces
     */
    public function quoteForZone(DeliveryService $service, int $zone, array $pieces): ?float
    {
        $bands = DeliveryWeightBand::query()
            ->where('delivery_service_id', $service->id)
            ->where('zone', $zone)
            ->get();

        return $bands->isEmpty() ? null : $this->quote($service, $bands, $pieces);
    }

    /**
     * kg the carrier bills: each piece's chargeable weight, summed, to the
     * gram. A piece with no weight on file counts as the service's default
     * weight, or its volumetric weight when its dimensions say it is bigger.
     *
     * @param  array<int, array{weight: float|null, chargeable: float|null, dimensions: array{length: float, width: float, height: float}|null}>  $pieces
     */
    public function billedWeight(DeliveryService $service, array $pieces): float
    {
        return round(array_sum(array_map(
            fn (array $piece) => $this->pieceWeight($service, $piece),
            $pieces,
        )), 3);
    }

    /**
     * The grid price for a weight in one zone.
     *
     * Up to the last flat band, the first band the weight fits under. Past
     * it, each stepped band adds its price per started step over its own
     * stretch — 10.2 kg on DHL's card is the 10 kg price plus one 0.5 kg step.
     *
     * @param  Collection<int, DeliveryWeightBand>  $bands  One zone's.
     */
    public function gridPrice(Collection $bands, float $kg): float
    {
        $bands = $bands->sortBy(fn (DeliveryWeightBand $band) => (float) $band->up_to)->values();
        $flat = $bands->filter(fn (DeliveryWeightBand $band) => $band->step === null)->values();

        $bracket = $flat->first(fn (DeliveryWeightBand $band) => $kg <= (float) $band->up_to);

        if ($bracket !== null) {
            return (float) $bracket->price;
        }

        $price = (float) ($flat->last()?->price ?? 0);
        $from = (float) ($flat->last()?->up_to ?? 0);

        foreach ($bands->filter(fn (DeliveryWeightBand $band) => $band->step !== null) as $band) {
            $upTo = (float) $band->up_to;

            if ($kg <= $from) {
                break;
            }

            if ($upTo <= $from) {
                continue;
            }

            // Rounded before ceil(): (20.2 - 20) / 0.5 is 0.40000000000000568.
            $steps = ceil(round((min($kg, $upTo) - $from) / (float) $band->step, 6));
            $price += $steps * (float) $band->price;
            $from = $upTo;
        }

        return $price;
    }

    /**
     * Additional handling per piece, or the overweight fee instead for a
     * piece over 70 kg. Applied per unit, the way the carrier counts pieces.
     *
     * @param  array<int, array{weight: float|null, chargeable: float|null, dimensions: array{length: float, width: float, height: float}|null}>  $pieces
     */
    public function pieceSurcharges(DeliveryService $service, array $pieces): float
    {
        $total = 0.0;

        foreach ($pieces as $piece) {
            if ($this->pieceWeight($service, $piece) > self::OVERWEIGHT_KG) {
                $total += (float) $service->overweight_fee;

                continue;
            }

            if ($this->needsHandling($piece)) {
                $total += (float) $service->handling_fee;
            }
        }

        return $total;
    }

    /**
     * @param  Collection<int, DeliveryWeightBand>  $bands  One zone's.
     * @param  array<int, array{weight: float|null, chargeable: float|null, dimensions: array{length: float, width: float, height: float}|null}>  $pieces
     */
    private function quote(DeliveryService $service, Collection $bands, array $pieces): float
    {
        $transport = $this->gridPrice($bands, $this->billedWeight($service, $pieces))
            + $this->pieceSurcharges($service, $pieces);

        $fuel = $transport * (float) $service->fuel_surcharge_percent / 100;

        return round($transport + $fuel + (float) $service->shipment_fee, 2);
    }

    /**
     * @param  array{weight: float|null, chargeable: float|null, dimensions: array{length: float, width: float, height: float}|null}  $piece
     */
    private function pieceWeight(DeliveryService $service, array $piece): float
    {
        if ($piece['chargeable'] !== null) {
            return $piece['chargeable'];
        }

        $volumetric = $piece['dimensions'] === null
            ? 0.0
            : $piece['dimensions']['length'] * $piece['dimensions']['width'] * $piece['dimensions']['height']
                / ProductShippingService::VOLUMETRIC_DIVISOR;

        return max((float) ($service->default_piece_weight ?? 0), $volumetric);
    }

    /**
     * @param  array{weight: float|null, chargeable: float|null, dimensions: array{length: float, width: float, height: float}|null}  $piece
     */
    private function needsHandling(array $piece): bool
    {
        if ($piece['weight'] !== null && $piece['weight'] > self::HANDLING_WEIGHT_KG) {
            return true;
        }

        if ($piece['dimensions'] === null) {
            return false;
        }

        $sides = array_values($piece['dimensions']);
        rsort($sides);

        return $sides[0] > self::HANDLING_LONGEST_SIDE_CM
            || $sides[1] > self::HANDLING_SECOND_SIDE_CM;
    }
}
