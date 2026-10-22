<?php

namespace App\Mail;

use App\Models\Property;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PropertyEventNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public Property $property;
    public string $eventType;       // 'created', 'price_changed', 'under_negotiation', 'reserved', 'sold'
    public string $eventTitle;
    public ?float $oldPrice;
    public ?float $newPrice;
    public ?string $performedByName;

    /**
     * Create a new message instance.
     */
    public function __construct(
        Property $property,
        string $eventType,
        string $eventTitle,
        ?float $oldPrice = null,
        ?float $newPrice = null,
        ?string $performedByName = null
    ) {
        $this->property = $property;
        $this->eventType = $eventType;
        $this->eventTitle = $eventTitle;
        $this->oldPrice = $oldPrice;
        $this->newPrice = $newPrice;
        $this->performedByName = $performedByName;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $ref = $this->property->reference ?: 'Shj-' . $this->property->id;
        $subject = "[$this->eventTitle] عقار: {$this->property->title} (#{$ref})";

                $fromAddress = config('mail.mailers.notice.username') ?: config('mail.from.address', 'notice@tamleekuae.com');

        return $this->from($fromAddress, 'منظومة عقارات تمليك - Tamleek Alerts')
            ->subject($subject)
            ->view('emails.property_event_notification');
    }
}
