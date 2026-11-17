<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Middleware\Activity\ServerSubject;
use Pterodactyl\Http\Middleware\Api\Client\Server\AuthenticateServerAccess;
use Pterodactyl\Http\Middleware\Api\Client\Server\ResourceBelongsToServer;
use Pterodactyl\BlueprintFramework\Extensions\pterodactyllocation\ServerRegionController;

Route::get('/servers/{server}/region', ServerRegionController::class)
    ->middleware([
        ServerSubject::class,
        AuthenticateServerAccess::class,
        ResourceBelongsToServer::class,
        'throttle:30,1',
    ]);