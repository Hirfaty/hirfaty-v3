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

        /*
         * Points at the catalogue, not at an "add a product" form: artisans no
         * longer file their own listings — the Hirfaty team contacts them and
         * publishes on their behalf (see Seller\ProductController) — so this
         * tells them to expect that call rather than sending them looking for
         * a button that is not there.
         */
        $copy = [
            'fr' => [
                'subject' => 'Votre atelier est validé — Hirfaty',
                'greeting' => "Bonjour {$notifiable->first_name} !",
                'line1' => "Bonne nouvelle : votre atelier « {$this->store->store_name} » a été validé par notre équipe.",
                'line2' => 'Nous vous contactons prochainement pour rassembler vos photos, prix et descriptions, et publier vos premières pièces à votre place.',
                'action' => 'Voir mon atelier',
            ],
            'ar' => [
                'subject' => 'تمت الموافقة على ورشتك — حرفتي',
                'greeting' => "مرحبًا {$notifiable->first_name}!",
                'line1' => "خبر سار: تمت الموافقة على ورشتك «{$this->store->store_name}» من طرف فريقنا.",
                'line2' => 'سنتواصل معك قريبًا لجمع الصور والأسعار والأوصاف، وننشر قطعك الأولى نيابة عنك.',
                'action' => 'عرض ورشتي',
            ],
            'en' => [
                'subject' => 'Your workshop is approved — Hirfaty',
                'greeting' => "Hello {$notifiable->first_name}!",
                'line1' => "Good news: your workshop “{$this->store->store_name}” has been approved by our team.",
                'line2' => 'We will be in touch shortly to collect your photos, prices and descriptions, and publish your first pieces on your behalf.',
                'action' => 'View my workshop',
            ],
        ];

        $t = $copy[$locale] ?? $copy['fr'];

        return (new MailMessage)
            ->subject($t['subject'])
            ->greeting($t['greeting'])
            ->line($t['line1'])
            ->line($t['line2'])
            ->action($t['action'], url('/seller/products'));
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
