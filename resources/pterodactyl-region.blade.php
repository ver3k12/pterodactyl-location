@extends('layouts.admin')
@include('partials/admin.settings.nav', ['activeTab' => 'pterodactyl-region'])

@section('title')
    Pterodactyl Region Settings
@endsection

@section('content-header')
    <h1>Pterodactyl Region Settings<small>Configure settings for addon "Pterodactyl Region".</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Settings</li>
    </ol>
@endsection

@section('content')
    @yield('settings::nav')
    <div class="row">
        <div class="col-xs-12">
            <form action="{{ route('admin.settings.pterodactyl-region') }}" method="POST">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">API Settings</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="control-label" for="region-ip-api">IP Geolocation API</label>
                                <div>
                                    <select class="form-control" id="region-ip-api" name="region-api:ip">
                                        <option value="ipinfo.spacehost.ovh" selected>ipinfo.spacehost.ovh (API V2)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label" for="region-dns-api">DNS Resolver</label>
                                <div>
                                    @php
                                        $dns_api = old('region-api:dns', $dns_api);
                                    @endphp
                                    <select class="form-control" id="region-dns-api" name="region-api:dns">
                                        <option value="DNS-Google" @if($dns_api === 'DNS-Google') selected @endif>DNS Google</option>
                                        <option value="DNS-Cloudflare" @if($dns_api === 'DNS-Cloudflare') selected @endif>DNS Cloudflare</option>
                                        <option value="DNS-OpenDNS" @if($dns_api === 'DNS-OpenDNS') selected @endif>OpenDNS</option>
                                        <option value="DNS-Quad9" @if($dns_api === 'DNS-Quad9') selected @endif>Quad9</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="box-body">Pterodactyl Location <code>{{ $version }}</code></div>
                    </div>
                    <div class="box-footer">
                        {{ csrf_field() }}
                        <button type="submit" name="_method" value="PATCH" class="btn btn-sm btn-primary pull-right">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
