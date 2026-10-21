<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

$active = fn (User $user) => ! $user->eliminado && (! $user->demo_room || (config('trackeat.demo_enabled') && $user->demo_expires_at?->isFuture()));
Broadcast::channel('trackeat.personal', fn (User $user) => $active($user) && in_array($user->rol, ['administrador', 'cocinero', 'mesero', 'cajero'], true));
Broadcast::channel('trackeat.usuario.{id}', fn (User $user, string $id) => $active($user) && $user->id === (int) $id);
Broadcast::channel('trackeat.demo.{room}', fn (User $user, string $room) => $active($user) && $user->demo_room === $room);
