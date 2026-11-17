<?php

namespace Pterodactyl\Http\Controllers\Admin\Extensions\pterodactyllocation;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\View\View;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Admin\BlueprintAdminLibrary as BlueprintExtensionLibrary;

class pterodactyllocationExtensionController extends Controller
{
    public function __construct(
        private ViewFactory $view,
        private BlueprintExtensionLibrary $blueprint,
        private SettingsRepositoryInterface $settings
    ) {
    }

    public function index(): View
    {
        return $this->view->make('admin.extensions.pterodactyllocation.index', [
            'root' => '/admin/extensions/pterodactyllocation',
            'blueprint' => $this->blueprint,
            'dns_api' => $this->settings->get('settings::region-api:dns', 'DNS-Cloudflare'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $values = $request->validate([
            'region-api:ip' => ['required', 'string', 'in:ipinfo.spacehost.ovh'],
            'region-api:dns' => ['required', 'string', 'in:DNS-Google,DNS-Cloudflare,DNS-OpenDNS,DNS-Quad9'],
        ]);

        foreach ($values as $key => $value) {
            $this->settings->set('settings::' . $key, $value);
        }

        return redirect()->route('admin.extensions.pterodactyllocation.index');
    }
}