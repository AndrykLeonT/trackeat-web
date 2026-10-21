<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\RedirectResponse;

class DemoController extends Controller
{
    public function enter(Request $request, string $room)
    {
        abort_unless(config('trackeat.demo_enabled') && Str::isUuid($room), 403, 'Demostración desactivada.');
        $current = $request->user();
        // Opening the classroom link must not replace a staff login.
        if ($current && ! $current->demo_room) {
            return new RedirectResponse('/menu/');
        }
        if (! $current || $current->demo_room !== $room || ! $current->demo_expires_at?->isFuture()) {
            $id = (string) Str::uuid();
            $user = new User;
            $user->forceFill([
                'name' => 'Invitado '.substr($id, 0, 6), 'primer_nombre' => 'Invitado',
                'email' => 'demo-'.$id.'@example.test', 'password' => Str::random(48),
                'rol' => 'cliente', 'demo_room' => $room,
                'demo_expires_at' => date('Y-m-d H:i:sP', (int) $request->query('expires')),
            ])->save();
            Auth::login($user);
            $request->session()->regenerate();
        }

        return new RedirectResponse('/menu/');
    }
}
