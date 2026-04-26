<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\VpnKey;
use App\Services\Keys\Exceptions\KeyAlreadyIssuedException;
use App\Services\Keys\Exceptions\NoActiveSubscriptionException;
use App\Services\Keys\Exceptions\UnknownLocationException;
use App\Services\Keys\KeyProvisioningService;
use App\Services\Panel\ServerCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class KeyController extends Controller
{
    public function __construct(
        private KeyProvisioningService $service,
        private ServerCatalog $catalog,
    ) {
    }

    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $activeSub = $user->activeSubscription();
        $key = $activeSub ? VpnKey::where('subscription_id', $activeSub->id)
            ->where('status', VpnKey::STATUS_ACTIVE)->first() : null;

        try {
            $locations = array_map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name, 'clients_count' => $s->clientsCount,
            ], $this->catalog->available());
        } catch (\Throwable $e) {
            Log::error('keys.index: cannot load servers from panel', ['error' => $e->getMessage()]);
            $locations = [];
        }

        return Inertia::render('Keys/Index', [
            'has_active_subscription' => (bool) $activeSub,
            'subscription_ends_at' => $activeSub?->ends_at,
            'key' => $key ? [
                'id' => $key->id,
                'name' => $key->name,
                'panel_server_id' => $key->panel_server_id,
                'panel_client_id' => $key->panel_client_id,
                'created_at' => $key->created_at,
            ] : null,
            'locations' => $locations,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['server_id' => 'required|integer']);
        $sub = $request->user()->activeSubscription();

        if (!$sub) {
            return back()->withErrors(['server_id' => 'Нет активной подписки']);
        }

        try {
            $this->service->issue($sub, (int) $data['server_id']);
        } catch (UnknownLocationException $e) {
            return back()->withErrors(['server_id' => 'Локация недоступна']);
        } catch (KeyAlreadyIssuedException $e) {
            return back()->withErrors(['server_id' => 'Ключ уже создан — используйте «Сменить локацию»']);
        } catch (NoActiveSubscriptionException $e) {
            return back()->withErrors(['server_id' => 'Подписка не активна']);
        } catch (\Throwable $e) {
            Log::error('keys.store: panel call failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['server_id' => 'Не удалось создать ключ. Попробуйте позже.']);
        }

        return redirect('/keys')->with('success', 'Ключ создан.');
    }

    public function changeLocation(Request $request, VpnKey $key): RedirectResponse
    {
        abort_unless($key->user_id === $request->user()->id, 403);

        $data = $request->validate(['server_id' => 'required|integer']);

        try {
            $this->service->changeLocation($key, (int) $data['server_id']);
        } catch (UnknownLocationException $e) {
            return back()->withErrors(['server_id' => 'Локация недоступна']);
        } catch (\Throwable $e) {
            Log::error('keys.changeLocation: failed', ['key_id' => $key->id, 'error' => $e->getMessage()]);
            return back()->withErrors(['server_id' => 'Не удалось сменить локацию. Старый ключ ещё работает.']);
        }

        return redirect('/keys')->with('success', 'Локация обновлена. Скачайте новый конфиг.');
    }

    public function download(Request $request, VpnKey $key): Response
    {
        abort_unless($key->user_id === $request->user()->id, 403);
        abort_unless($key->status === VpnKey::STATUS_ACTIVE && $key->config_text, 404);

        $filename = "amnezia-{$key->id}.conf";
        return response($key->config_text, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function qr(Request $request, VpnKey $key): Response
    {
        abort_unless($key->user_id === $request->user()->id, 403);
        abort_unless($key->qr_code_base64, 404);

        // qr_code_base64 — data URI вида "data:image/png;base64,...."
        $dataUri = $key->qr_code_base64;
        if (!str_starts_with($dataUri, 'data:image')) {
            return response('invalid qr format', 422);
        }
        [$meta, $b64] = explode(',', $dataUri, 2);
        preg_match('#data:(image/[a-z]+);base64#', $meta, $m);
        $mime = $m[1] ?? 'image/png';

        return response(base64_decode($b64), 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
