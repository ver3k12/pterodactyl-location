@extends('layouts.admin')

@section('title')
    Pterodactyl Location Settings
@endsection

@section('content-header')
    <h1>Pterodactyl Location<small>Configure the DNS resolver used for hostname allocations.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.extensions') }}">Extensions</a></li>
        <li class="active">Pterodactyl Location</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-xs-12">
            <form action="{{ route('admin.extensions.pterodactyllocation.index') }}" method="POST">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">API Settings</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="control-label" for="region-ip-api">IP Geolocation API</label>
                                <select class="form-control" id="region-ip-api" name="region-api:ip">
                                    <option value="ipinfo.spacehost.ovh" selected>ipinfo.spacehost.ovh (API V2)</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label" for="region-dns-api">DNS Resolver</label>
                                <select class="form-control" id="region-dns-api" name="region-api:dns">
                                    <option value="DNS-Google" @if($dns_api === 'DNS-Google') selected @endif>DNS Google</option>
                                    <option value="DNS-Cloudflare" @if($dns_api === 'DNS-Cloudflare') selected @endif>DNS Cloudflare</option>
                                    <option value="DNS-OpenDNS" @if($dns_api === 'DNS-OpenDNS') selected @endif>OpenDNS</option>
                                    <option value="DNS-Quad9" @if($dns_api === 'DNS-Quad9') selected @endif>Quad9</option>
                                </select>
                            </div>
                        </div>
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