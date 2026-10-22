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
            // Ensure HTTPS when in production or on tamleekuae.com domain
            if (app()->isProduction() || str_contains($url, 'tamleekuae.com')) {
                $url = preg_replace('/^http:\/\//i', 'https://', $url);
            }

            return (new MailMessage)
                ->subject('تأكيد البريد الإلكتروني - منصة تمليك العقارية')
                ->view('emails.verify_email', [
                    'user' => $notifiable,
                    'url' => $url,
                ]);
        });
    }
}
