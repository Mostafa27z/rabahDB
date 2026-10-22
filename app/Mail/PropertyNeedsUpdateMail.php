<?php

namespace App\Mail;

use App\Models\Property;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PropertyNeedsUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public Property $property;
    public int $daysSinceUpdate;

    /**
     * Create a new message instance.
     */
    public function __construct(Property $property, int $daysSinceUpdate = 60)
    {
        $this->property = $property;
        $this->daysSinceUpdate = $daysSinceUpdate;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $ref = $this->property->reference ?: 'Shj-' . $this->property->id;
        $subject = "[تنبيه تحديث] مر أكثر من {$this->daysSinceUpdate} يوماً على العقار: {$this->property->title} (#{$ref})";

        return $this->mailer('notice')
            ->from(config('mail.mailers.notice.username', 'NOTICE@tamleekuae.com'), 'منظومة عقارات تمليك - Tamleek Alerts')
            ->subject($subject)
            ->view('emails.property_needs_update');
    }
}
