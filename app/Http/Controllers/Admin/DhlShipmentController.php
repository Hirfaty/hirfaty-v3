<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DhlExpress\DhlExpressException;
use App\Services\DhlExpress\DhlShipmentService;
use App\Services\DhlExpress\DhlTownService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The desk's DHL Express buttons on a purchase: check the shipment with DHL,
 * create it, download its documents, read its checkpoints now, and book or
 * cancel the courier. The work is DhlShipmentService's; this only turns its
 * answers into a flash or JSON.
 */
class DhlShipmentController extends Controller
{
    /** Create the shipment from the dialog's fields, as the desk corrected them. */
    public function store(Request $request, Order $order, DhlShipmentService $shipments)
    {
        try {
            $shipment = $shipments->create($order, $this->fields($request));
        } catch (DhlExpressException $e) {
            return back()->with('error', $this->message($e));
        }

        return back()->with('success', "Expédition DHL créée : {$shipment->tracking_number}."
            .($shipment->hasPickup() ? " Ramassage réservé ({$shipment->pickup_confirmation_number})." : ''));
    }

    /**
     * Have DHL check the dialog's fields without creating anything. JSON, so
     * the dialog stays open with DHL's answer in it.
     */
    public function check(Request $request, Order $order, DhlShipmentService $shipments): JsonResponse
    {
        try {
            $warnings = $shipments->validate($order, $this->fields($request));
        } catch (DhlExpressException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage(), 'details' => $e->details], 422);
        }

        return response()->json(['ok' => true, 'message' => 'DHL accepte cette expédition.', 'details' => $warnings]);
    }

    /** A document as a PDF download: label, invoice, waybillDoc or pod. */
    public function document(Order $order, string $type, DhlShipmentService $shipments)
    {
        abort_unless(in_array($type, DhlShipmentService::DOCUMENT_TYPES, true), 404);

        try {
            $document = $shipments->document($order, $type);
        } catch (DhlExpressException $e) {
            return response()->json(['message' => $this->message($e)], 422);
        }

        $name = "dhl-{$type}-".($order->group_reference ?? $order->reference).'.'.strtolower($document->format);

        return response($document->bytes(), 200, [
            'Content-Type' => strtolower($document->format) === 'pdf' ? 'application/pdf' : 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    public function refresh(Order $order, DhlShipmentService $shipments)
    {
        try {
            $moved = $shipments->refreshFor($order);
        } catch (DhlExpressException $e) {
            return back()->with('error', $this->message($e));
        }

        return back()->with('success', $moved ? 'Suivi DHL mis à jour.' : 'Aucun nouveau passage chez DHL.');
    }

    public function pickup(Request $request, Order $order, DhlShipmentService $shipments)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'close_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'location' => ['required', 'string', 'max:80'],
        ]);

        try {
            $shipment = $shipments->bookPickup($order, $data['date'], $data['close_time'], $data['location']);
        } catch (DhlExpressException $e) {
            return back()->with('error', $this->message($e));
        }

        return back()->with('success', "Ramassage DHL réservé : {$shipment->pickup_confirmation_number}.");
    }

    public function cancelPickup(Order $order, DhlShipmentService $shipments)
    {
        try {
            $answer = $shipments->cancelPickup($order);
        } catch (DhlExpressException $e) {
            return back()->with('error', $this->message($e));
        }

        return back()->with('success', 'Ramassage DHL annulé.'.($answer !== '' ? " ({$answer})" : ''));
    }

    /** The towns DHL files a postcode under, for the dialog's town field. */
    public function towns(Request $request, DhlTownService $towns): JsonResponse
    {
        $data = $request->validate([
            'country' => ['required', 'string', 'size:2'],
            'postal_code' => ['required', 'string', 'max:12'],
        ]);

        try {
            return response()->json(['towns' => $towns->townsFor($data['country'], $data['postal_code'])]);
        } catch (DhlExpressException $e) {
            return response()->json(['towns' => [], 'message' => $e->getMessage()]);
        }
    }

    /**
     * The dialog's fields, shape-checked. What only DHL can tell (a postcode
     * it serves, a description customs accepts) is DHL's to refuse.
     *
     * @return array<string, mixed>
     */
    private function fields(Request $request): array
    {
        return $request->validate([
            'receiver.name' => ['required', 'string', 'max:255'],
            'receiver.phone' => ['required', 'string', 'max:70'],
            'receiver.email' => ['nullable', 'email', 'max:70'],
            'receiver.address_line1' => ['required', 'string', 'max:45'],
            'receiver.address_line2' => ['nullable', 'string', 'max:45'],
            'receiver.address_line3' => ['nullable', 'string', 'max:45'],
            'receiver.city' => ['required', 'string', 'max:45'],
            'receiver.postal_code' => ['nullable', 'string', 'max:12'],
            'receiver.country' => ['required', 'string', 'size:2'],
            'packages' => ['required', 'array', 'min:1', 'max:20'],
            'packages.*.weight' => ['required', 'numeric', 'min:0.1', 'max:1000'],
            'packages.*.length' => ['required', 'numeric', 'min:1', 'max:300'],
            'packages.*.width' => ['required', 'numeric', 'min:1', 'max:300'],
            'packages.*.height' => ['required', 'numeric', 'min:1', 'max:300'],
            'items' => ['required', 'array', 'min:1', 'max:999'],
            'items.*.description' => ['required', 'string', 'max:512'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.price' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'items.*.hs_code' => ['nullable', 'string', 'max:18'],
            'items.*.origin_country' => ['required', 'string', 'size:2'],
            'items.*.weight' => ['required', 'numeric', 'min:0.001', 'max:1000'],
            'incoterm' => ['required', Rule::in(DhlShipmentService::INCOTERMS)],
            'shipping_date' => ['required', 'date_format:Y-m-d'],
            'pickup.requested' => ['boolean'],
            'pickup.close_time' => ['required_if:pickup.requested,true', 'nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'pickup.location' => ['required_if:pickup.requested,true', 'nullable', 'string', 'max:80'],
            'notify_buyer' => ['boolean'],
        ], [
            'receiver.city.required' => 'Indiquez la ville du destinataire.',
            'receiver.address_line1.required' => 'Indiquez l’adresse du destinataire.',
            'receiver.address_line1.max' => 'Ligne d’adresse 1 : 45 caractères au plus (DHL).',
            'receiver.address_line2.max' => 'Ligne d’adresse 2 : 45 caractères au plus (DHL).',
            'receiver.address_line3.max' => 'Ligne d’adresse 3 : 45 caractères au plus (DHL).',
            'receiver.country.required' => 'Choisissez le pays du destinataire.',
        ]);
    }

    /**
     * DHL's reason, with the fields it refused when it listed them — and,
     * when only the courier booking failed (998, or 202401: no billing
     * address on the account), what the desk can do about it.
     */
    private function message(DhlExpressException $e): string
    {
        $text = 'DHL : '.$e->getMessage().($e->details !== [] ? ' — '.implode(' · ', $e->details) : '');
        $pickupFailed = collect($e->details)->contains(fn (string $d) => str_starts_with($d, '998:') && stripos($d, 'pickup') !== false
            || str_starts_with($d, '202401:'));

        return $pickupFailed
            ? $text.' → Le ramassage n’a pas pu être réservé : décochez « Réserver le coursier DHL » pour créer l’expédition et déposez le colis dans un point DHL, et demandez à DHL d’ajouter l’adresse de facturation du compte.'
            : $text;
    }
}
