<?php

namespace App\Notifications;

use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StoreApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Store $store) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Falls back to French, which is the marketplace's default locale.
        $locale = $notifiable->locale ?? 'fr';

        $copy = [
            'fr' => [
                'subject' => 'Votre atelier est validé — Hirfaty',
                'greeting' => "Bonjour {$notifiable->first_name} !",
                'line1' => "Bonne nouvelle : votre atelier « {$this->store->store_name} » a été validé par notre équipe.",
                'line2' => 'Vous pouvez dès maintenant ajouter vos produits. Chaque fiche est relue avant sa mise en ligne, comptez un à deux jours ouvrés.',
                'action' => 'Ajouter un produit',
            ],
            'ar' => [
                'subject' => 'تمت الموافقة على ورشتك — حرفتي',
                'greeting' => "مرحبًا {$notifiable->first_name}!",
                'line1' => "خبر سار: تمت الموافقة على ورشتك «{$this->store->store_name}» من طرف فريقنا.",
                'line2' => 'يمكنك الآن إضافة منتجاتك. تخضع كل بطاقة منتج للمراجعة قبل نشرها، وتستغرق من يوم إلى يومَي عمل.',
                'action' => 'إضافة منتج',
            ],
            'en' => [
                'subject' => 'Your workshop is approved — Hirfaty',
                'greeting' => "Hello {$notifiable->first_name}!",
                'line1' => "Good news: your workshop “{$this->store->store_name}” has been approved by our team.",
                'line2' => 'You can start adding products now. Every listing is reviewed before it goes live — allow one to two working days.',
                'action' => 'Add a product',
            ],
        ];

        $t = $copy[$locale] ?? $copy['fr'];

        return (new MailMessage)
            ->subject($t['subject'])
            ->greeting($t['greeting'])
            ->line($t['line1'])
            ->line($t['line2'])
            ->action($t['action'], url('/seller/products/create'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'store_id' => $this->store->id,
            'store_name' => $this->store->store_name,
        ];
    }
}
