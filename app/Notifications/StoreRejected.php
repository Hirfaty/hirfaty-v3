<?php

namespace App\Notifications;

use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StoreRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Store $store, public string $reason) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'fr';

        $copy = [
            'fr' => [
                'subject' => 'Votre atelier nécessite des corrections — Hirfaty',
                'greeting' => "Bonjour {$notifiable->first_name},",
                'line1' => "Notre équipe a examiné votre atelier « {$this->store->store_name} » et ne peut pas le valider en l'état.",
                'reason' => 'Motif : '.$this->reason,
                'line2' => 'Corrigez les points ci-dessus depuis vos paramètres, puis renvoyez votre atelier en validation. Nous le relirons rapidement.',
                'action' => 'Corriger mon atelier',
            ],
            'ar' => [
                'subject' => 'ورشتك تحتاج إلى تعديلات — حرفتي',
                'greeting' => "مرحبًا {$notifiable->first_name}،",
                'line1' => "راجع فريقنا ورشتك «{$this->store->store_name}» ولا يمكن الموافقة عليها بوضعها الحالي.",
                'reason' => 'السبب: '.$this->reason,
                'line2' => 'صحّح النقاط أعلاه من الإعدادات، ثم أعد إرسال ورشتك للمراجعة. سنراجعها في أقرب وقت.',
                'action' => 'تصحيح بيانات ورشتي',
            ],
            'en' => [
                'subject' => 'Your workshop needs changes — Hirfaty',
                'greeting' => "Hello {$notifiable->first_name},",
                'line1' => "Our team reviewed your workshop “{$this->store->store_name}” and cannot approve it as it stands.",
                'reason' => 'Reason: '.$this->reason,
                'line2' => 'Fix the points above from your settings, then resubmit your workshop for review. We will take another look shortly.',
                'action' => 'Fix my workshop',
            ],
        ];

        $t = $copy[$locale] ?? $copy['fr'];

        return (new MailMessage)
            ->subject($t['subject'])
            ->greeting($t['greeting'])
            ->line($t['line1'])
            ->line($t['reason'])
            ->line($t['line2'])
            ->action($t['action'], url('/seller/settings'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'store_id' => $this->store->id,
            'store_name' => $this->store->store_name,
            'reason' => $this->reason,
        ];
    }
}
