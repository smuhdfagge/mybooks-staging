<?php

namespace App\Http\Controllers\Api;

use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Phones registered for push notifications (mobile plan A2). Register after
 * signing in; the device goes when its API token is revoked (sign out) or
 * with DELETE. A token registered again moves to whoever signed in last.
 */
class DeviceController extends BaseApiController
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['required', Rule::in(UserDevice::PLATFORMS)],
            'app_version' => ['nullable', 'string', 'max:20'],
        ]);

        // A real API token has an id; a session (as in tests) has none.
        $tokenId = data_get($request->user()->currentAccessToken(), 'id');
        $tokenId = is_numeric($tokenId) ? (int) $tokenId : null;
        $device = UserDevice::updateOrCreate(
            ['token_hash' => UserDevice::hashToken($data['token'])],
            [
                'user_id' => $request->user()->id,
                'personal_access_token_id' => $tokenId,
                'token' => $data['token'],
                'platform' => $data['platform'],
                'app_version' => $data['app_version'] ?? null,
            ],
        );

        return $this->success([
            'id' => $device->id,
            'platform' => $device->platform,
            'app_version' => $device->app_version,
            'registered_at' => $device->created_at?->toISOString(),
        ], 'Device registered for notifications.', $device->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        UserDevice::where('user_id', $request->user()->id)
            ->where('token_hash', UserDevice::hashToken($token))
            ->delete();

        return $this->success(null, 'Device removed.');
    }
}
