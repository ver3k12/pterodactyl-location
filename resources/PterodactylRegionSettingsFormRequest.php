<?php

namespace Pterodactyl\Http\Requests\Admin\Settings;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class PterodactylRegionSettingsFormRequest extends AdminFormRequest
{
    /**
     * Return all the rules to apply to this request's data.
     */
    public function rules(): array
    {
        return [
            'region-api:ip' => 'required|string|in:ipinfo.spacehost.ovh',
            'region-api:dns' => 'required|string|in:DNS-Google,DNS-Cloudflare,DNS-OpenDNS,DNS-Quad9',
        ];
    }

    public function attributes(): array
    {
        return [
            'region-api:ip' => 'IP API Connect',
            'region-api:dns' => 'DNS API Connect',
        ];
    }
}
