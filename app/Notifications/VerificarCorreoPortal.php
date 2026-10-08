<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class VerificarCorreoPortal extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'portal.correo.verificar',
            now()->addMinutes(60),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->email)]
        );

        return (new MailMessage)
            ->subject('Confirma tu correo · MRJUEGOZ')
            ->greeting('Hola, '.$notifiable->name.' 👋')
            ->line('Tu cuenta del portal de MRJUEGOZ ya está creada.')
            ->line('Confirma que este correo es tuyo para consultar tus juegos, datos de instalación y garantía.')
            ->action('Verificar mi correo', $url)
            ->line('Este enlace vence en 60 minutos. Puedes solicitar otro desde el portal.')
            ->line('Abre el enlace e inicia sesión con la cuenta que acabas de activar si se te solicita.')
            ->line('Si no creaste esta cuenta, no confirmes el correo.');
    }
}
