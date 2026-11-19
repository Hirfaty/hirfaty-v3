<?php

use App\Models\Media;
use App\Services\Cathedis\CathedisShipmentService;
use App\Services\DhlExpress\DhlExpressClient;
use App\Services\DhlExpress\DhlExpressException;
use App\Services\DhlExpress\DhlShipmentService;
use App\Services\MediaHealthService;
use App\Services\PaymentService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Spatie\Health\Commands\DispatchQueueCheckJobsCommand;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\Commands\ScheduleCheckHeartbeatCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Health monitoring ───────────────────────────────────────────────
// Runs the checks registered in AppServiceProvider and stores the results,
// which the admin dashboard reads. Requires `php artisan schedule:work`
// (or a cron entry) to be running.
Schedule::command(RunHealthChecksCommand::class)->everyMinute();

// ScheduleCheck only passes while this heartbeat keeps getting written, so a
// dead scheduler surfaces as a failing check instead of silently stale results.
Schedule::command(ScheduleCheckHeartbeatCommand::class)->everyMinute();

// QueueCheck works the same way: this pushes a job the queue worker must pick
// up, proving the worker is actually consuming jobs.
Schedule::command(DispatchQueueCheckJobsCommand::class)->everyMinute();

/*
 * ── Exchange rates ──────────────────────────────────────────────────
 *
 * Warms the rate cache so no buyer ever waits on the rates API.
 * ExchangeRateService fetches lazily if this hasn't run, and falls back to
 * config/currency.php if the fetch fails, so a missed run degrades the
 * freshness of a displayed price and nothing more.
 *
 * Early morning, before Moroccan traffic picks up. Daily is plenty: these
 * figures are indicative, and prices settle in MAD regardless.
 */
Schedule::command('currency:refresh-rates')->dailyAt('04:30');

/*
 * ── Cathedis cities ─────────────────────────────────────────────────
 *
 * Cathedis adds localities and sectors to its list every few weeks; this
 * picks them up and links any locality of our card still waiting for one
 * (CathedisCitySyncService). Weekly is plenty, and a failed run only leaves
 * last week's list in place.
 */
Schedule::command('cathedis:sync-cities')->weeklyOn(1, '04:45');

/*
 * ── Cathedis parcels ────────────────────────────────────────────────
 *
 * Reads every parcel still on its way and carries its status onto the
 * orders: "shipped" once Cathedis has collected it, "delivered" once it is
 * delivered (the buyer is emailed both times), tracking notes in between, and
 * an alert in the admin bell when it is refused or cancelled at the door —
 * see CathedisShipmentService::refreshOpen. Cathedis offers no webhook.
 */
Artisan::command('cathedis:refresh-deliveries', function (CathedisShipmentService $shipments) {
    $result = $shipments->refreshOpen();

    $this->info("{$result['checked']} Cathedis parcel(s) checked, {$result['changed']} moved, {$result['failed']} failed.");
})->purpose("Read Cathedis parcels' statuses and carry them onto the orders");

Schedule::command('cathedis:refresh-deliveries')->everyThirtyMinutes()->withoutOverlapping();

/*
 * ── DHL Express shipments ──────────────────────────────────────────
 *
 * Reads the checkpoints of every shipment still on its way, 200 per call,
 * and carries them onto the orders: "shipped" at DHL's first checkpoint,
 * "delivered" at OK (the buyer is emailed both times), tracking notes in
 * between, and an alert in the admin bell for a bad address, a refusal,
 * unpaid duties or a return — see DhlShipmentService::refreshOpen.
 */
Artisan::command('dhl:refresh-shipments', function (DhlShipmentService $shipments) {
    $result = $shipments->refreshOpen();

    $this->info("{$result['checked']} DHL shipment(s) checked, {$result['changed']} moved, {$result['failed']} failed.");
})->purpose('Read DHL Express checkpoints and carry them onto the orders');

Schedule::command('dhl:refresh-shipments')->everyThirtyMinutes()->withoutOverlapping();

/*
 * Checks the DHL Express settings without shipping anything: the login (an
 * address check, Paris 75001) and the account (a price for a 1 kg box from
 * the desk to Paris). Run it after changing DHL_EXPRESS_* in .env.
 */
Artisan::command('dhl:check', function (DhlExpressClient $dhl) {
    $this->line('Environnement : '.$dhl->baseUrl().($dhl->isTest() ? ' (TEST)' : ' (PRODUCTION)'));

    if (! $dhl->isConfigured()) {
        $this->error('DHL_EXPRESS_USERNAME, DHL_EXPRESS_PASSWORD et DHL_EXPRESS_ACCOUNT_NUMBER doivent être renseignés.');

        return 1;
    }

    try {
        $towns = collect($dhl->validateAddress('FR', '75001'))->pluck('cityName')->filter()->unique()->implode(', ');
        $this->info('✓ Identifiants acceptés — 75001 (FR) : '.($towns ?: 'aucune ville'));

        $rates = $dhl->rates([
            'originCountryCode' => config('services.dhl_express.shipper.country', 'MA'),
            'originCityName' => config('services.dhl_express.shipper.city', 'Casablanca'),
            'originPostalCode' => config('services.dhl_express.shipper.postal_code'),
            'destinationCountryCode' => 'FR',
            'destinationCityName' => 'Paris',
            'destinationPostalCode' => '75001',
        ]);

        foreach ($rates as $product) {
            $price = collect($product['totalPrice'] ?? [])->firstWhere('currencyType', 'BILLC') ?? ($product['totalPrice'][0] ?? []);
            $this->info(sprintf('✓ Compte accepté — %s (%s) : %s %s', $product['productName'] ?? '?', $product['productCode'] ?? '?', $price['price'] ?? '?', $price['priceCurrency'] ?? ''));
        }
    } catch (DhlExpressException $e) {
        $this->error('✗ DHL : '.$e->getMessage().($e->details ? ' — '.implode(' · ', $e->details) : ''));

        return 1;
    }

    return 0;
})->purpose('Check the DHL Express login and account without shipping anything');

/*
 * ── Unpaid card purchases ───────────────────────────────────────────
 *
 * A card checkout holds its stock while the buyer is on Payzone's page. One
 * nobody pays for within PAYZONE_EXPIRE_AFTER_MINUTES is cancelled and its
 * stock handed back — see PaymentService::expireStale.
 */
Artisan::command('payments:expire-stale', function (PaymentService $payments) {
    $this->info($payments->expireStale().' unpaid card purchase(s) released.');
})->purpose('Release card purchases nobody paid for in time');

Schedule::command('payments:expire-stale')->everyFiveMinutes()->withoutOverlapping();

/*
 * ── Broken images ───────────────────────────────────────────────────
 *
 * Downloads every stored image and flags the ones that no longer read, for
 * the admin products list's "Images cassées" filter — see MediaHealthService.
 * Lost files each stall until the read timeout (batches of 20 run in
 * parallel), so this runs at night and in the background, where it cannot
 * hold up the every-minute health checks above. Admins can also start it from
 * the products list ("Vérifier maintenant"), which queues it instead.
 */
Artisan::command('media:check', function (MediaHealthService $health) {
    $result = $health->scan(function (Media $media, bool $healthy) {
        if (! $healthy) {
            $this->line("<fg=red>broken</>  #{$media->id} {$media->path}");
        }
    });

    $this->info("{$result['checked']} image(s) checked, {$result['broken']} broken.");
})->purpose('Flag product images whose stored file can no longer be read');

Schedule::command('media:check')->dailyAt('03:30')->withoutOverlapping()->runInBackground();
