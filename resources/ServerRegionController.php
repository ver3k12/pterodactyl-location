<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Region\RegionLookupService;

class ServerRegionController extends Controller
{
    public function __construct(private RegionLookupService $lookup, private SettingsRepositoryInterface $settings)
    {
    }

    public function __invoke(Server $server): JsonResponse
    {
        $allocation = $server->allocation;
        if (!$allocation) {
            return new JsonResponse(['status' => 'unavailable', 'country_name' => 'No allocation', 'country_code' => '', 'city' => null]);
        }

        return new JsonResponse($this->lookup->lookup(
            RegionLookupService::selectLookupAddress($allocation->ip, $allocation->ip_alias),
            $this->settings->get('settings::region-api:dns', 'DNS-Cloudflare')
        ));
    }
}