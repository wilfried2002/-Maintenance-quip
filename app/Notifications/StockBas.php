<?php

namespace App\Notifications;

use App\Models\Piece;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StockBas extends Notification
{
    use Queueable;

    public function __construct(public Piece $piece)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'stock_bas',
            'piece_id' => $this->piece->id,
            'title' => 'Stock bas',
            'body' => $this->piece->designation.' — '.$this->piece->stock_qte.' / '.$this->piece->stock_min.' (seuil min.)',
            'url' => route('pieces.index', absolute: false),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Stock bas — '.$this->piece->designation)
            ->line('La pièce suivante est passée sous son seuil de stock minimum :')
            ->line($this->piece->designation.' ('.$this->piece->reference.') : '.$this->piece->stock_qte.' en stock, seuil minimum '.$this->piece->stock_min.'.')
            ->action('Voir les pièces', route('pieces.index'))
            ->line('Merci de prévoir un réapprovisionnement.');
    }
}
