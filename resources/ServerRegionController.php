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

        $resolver = $this->settings->get('settings::region-api:dns', 'DNS-Cloudflare');
        $address = RegionLookupService::selectLookupAddress($allocation->ip, $allocation->ip_alias);
        $localIp = RegionLookupService::isLocalAddress($allocation->ip);
        $result = $this->lookup->lookup($address, $resolver);
        $result['local_ip'] = $localIp;
        $result['location_source'] = $address !== $allocation->ip ? 'alias' : 'allocation';

        if ($localIp && $result['status'] === 'local' && !empty($server->node->fqdn)) {
            $nodeResult = $this->lookup->lookup($server->node->fqdn, $resolver);
            if ($nodeResult['status'] === 'success') {
                $nodeResult['local_ip'] = true;
                $nodeResult['location_source'] = 'sftp_node';

                return new JsonResponse($nodeResult);
            }
        }

        return new JsonResponse($result);
    }
}