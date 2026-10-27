<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        VerifyEmail::toMailUsing(function ($notifiable, $url) {
            // Ensure HTTPS when in production or on rabah.eg domain
            if (app()->isProduction() || str_contains($url, 'rabah.eg')) {
                $url = preg_replace('/^http:\/\//i', 'https://', $url);
            }

            return (new MailMessage)
                ->subject('تأكيد البريد الإلكتروني - منصة رباح للتسويق العقاري')
                ->view('emails.verify_email', [
                    'user' => $notifiable,
                    'url' => $url,
                ]);
        });
    }
}
