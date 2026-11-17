<?php

namespace Pterodactyl\Http\Controllers\Admin\Settings;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Http\Requests\Admin\Settings\PterodactylRegionSettingsFormRequest;

class PterodactylRegionController extends Controller
{
    /**
     * PterodactylRegionController constructor.
     */
    public function __construct(
        private AlertsMessageBag $alert,
        private SettingsRepositoryInterface $settings,
        private Kernel $kernel,
        private ViewFactory $view
    ) {
    }

    /**
     * Render advanced Panel settings UI.
     */
    public function index(): View
    {
        return $this->view->make('admin.settings.pterodactyl-region', [
            'version' => '3.0.0',
            'dns_api' => $this->settings->get('settings::region-api:dns', 'DNS-Cloudflare'),
        ]);
    }

    /**
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function update(PterodactylRegionSettingsFormRequest $request): RedirectResponse
    {
        foreach ($request->normalize() as $key => $value) {
            $this->settings->set('settings::' . $key, $value);
        }

        $this->kernel->call('view:clear');
        $this->alert->success('Pterodactyl Region settings have been successfully updated.')->flash();

        return redirect()->route('admin.settings.pterodactyl-region');
    }
}
