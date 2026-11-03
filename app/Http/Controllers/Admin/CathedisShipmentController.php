<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Cathedis\CathedisException;
use App\Services\Cathedis\CathedisShipmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The desk's Cathedis buttons on a purchase: create its parcel, print the
 * slip, read its status now, and ask Cathedis to come and collect. The work is
 * CathedisShipmentService's; this only turns its answers into a flash.
 */
class CathedisShipmentController extends Controller
{
    /**
     * Create the parcel from the dialog's fields — the draft the panel
     * offered, as the desk corrected it. The shape is checked here; what only
     * Cathedis's list can tell (a served city, a sector of it, a phone it
     * accepts) is checked by the service.
     */
    public function store(Request $request, Order $order, CathedisShipmentService $shipments)
    {
        $data = $request->validate([
            'recipient' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'cathedis_city_id' => ['required', 'integer'],
            'sector' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'payment_type' => ['required', Rule::in(CathedisShipmentService::PAYMENT_TYPES)],
            'subject' => ['required', 'string', 'max:255'],
            'range_weight' => ['required', Rule::in(CathedisShipmentService::RANGES)],
            'weight' => ['nullable', 'integer', 'min:1', 'max:1000', 'required_if:range_weight,'.CathedisShipmentService::RANGE_HEAVY],
            'fragile' => ['boolean'],
            'declared_value' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'allow_opening' => ['boolean'],
            'package_count' => ['required', 'integer', 'min:1', 'max:20'],
            'comment' => ['nullable', 'string', 'max:255'],
            'remember_city' => ['boolean'],
        ], [
            'cathedis_city_id.required' => 'Choisissez la ville Cathedis.',
            'weight.required_if' => 'Au-delà de 30 kg, indiquez le poids exact.',
        ]);

        try {
            $shipment = $shipments->create($order, $data);
        } catch (CathedisException $e) {
            return back()->with('error', 'Cathedis : '.$e->getMessage());
        }

        return back()->with('success', "Colis Cathedis créé : {$shipment->code}.");
    }

    /** The cities and sectors the dialog lets the desk pick from. */
    public function cities(CathedisShipmentService $shipments)
    {
        return response()->json($shipments->cityOptions());
    }

    /**
     * The slip as a PDF download: A5, or `?format=10x10` for a label printer.
     * Fetched by the page itself, so a refusal comes back as JSON it can show.
     */
    public function label(Request $request, Order $order, CathedisShipmentService $shipments)
    {
        $thermal = $request->query('format') === '10x10';

        try {
            $pdf = $shipments->label($order, $thermal);
        } catch (CathedisException $e) {
            return response()->json(['message' => 'Cathedis : '.$e->getMessage()], 422);
        }

        $name = 'bon-livraison-'.($order->group_reference ?? $order->reference).($thermal ? '-10x10' : '').'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    public function refresh(Order $order, CathedisShipmentService $shipments)
    {
        try {
            $moved = $shipments->refreshFor($order);
        } catch (CathedisException $e) {
            return back()->with('error', 'Cathedis : '.$e->getMessage());
        }

        return back()->with('success', $moved ? 'Statut Cathedis mis à jour.' : 'Aucun changement chez Cathedis.');
    }

    public function pickup(CathedisShipmentService $shipments)
    {
        try {
            $request = $shipments->requestPickup();
        } catch (CathedisException $e) {
            return back()->with('error', 'Cathedis : '.$e->getMessage());
        }

        return back()->with('success', trim(
            "Ramassage demandé pour {$request['count']} colis"
            .($request['agent'] ? " — ramasseur : {$request['agent']}" : '')
            .($request['name'] ? " ({$request['name']})" : '').'.'
        ));
    }
}
