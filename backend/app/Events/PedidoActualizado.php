<?php

namespace App\Events;

use App\Models\Pedido;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class PedidoActualizado implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public int $tries = 5;

    public array $backoff = [1, 2, 5, 10];

    public function __construct(public array $pedido, private int $userId, private ?string $room) {}

    public static function forOrder(Pedido $order): self
    {
        return new self([
            'id' => $order->id, 'numero_pedido' => $order->numero_pedido,
            'estado' => $order->estado, 'updated_at' => $order->updated_at->toISOString(),
        ], $order->id_usuario, $order->demo_room);
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('trackeat.personal'), new PrivateChannel('trackeat.usuario.'.$this->userId)];
        if ($this->room) {
            $channels[] = new PrivateChannel('trackeat.demo.'.$this->room);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'pedido.actualizado';
    }

    public function broadcastWith(): array
    {
        // Never broadcast names, email addresses, notes or the customer's ID.
        return ['pedido' => $this->pedido];
    }
}
