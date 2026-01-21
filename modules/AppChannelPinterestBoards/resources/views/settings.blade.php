@extends('layouts.app')

@section('sub_header')
    <x-sub-header 
        title="{{ __('Pinterest API') }}" 
        description="{{ __('Essential Guide to Configure Pinterest API Easily') }}"
    >
    </x-sub-header>
@endsection

@section('content')
<div class="container max-w-800 pb-5">
    <form class="actionForm" action="{{ url_admin("settings/save") }}">
        <div class="card shadow-none border-gray-300 mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-4">
                            <label class="form-label">{{ __('Status') }}</label>
                            <div class="d-flex gap-8 flex-column flex-lg-row flex-md-column">
                                <div class="form-check me-3">
                                    <input class="form-check-input" type="radio" name="pinterest_status" value="1" id="pinterest_status_1" {{ get_option("pinterest_status", 0)==1?"checked":"" }}>
                                    <label class="form-check-label mt-1" for="pinterest_status_1">
                                        {{ __('Enable') }}
                                    </label>
                                </div>
                                <div class="form-check me-3">
                                    <input class="form-check-input" type="radio" name="pinterest_status" value="0" id="pinterest_status_0"{{ get_option("pinterest_status", 0)==0?"checked":"" }}>
                                    <label class="form-check-label mt-1" for="pinterest_status_0">
                                        {{ __('Disable') }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="mb-4">
                            <label class="form-label">{{ __('Mode') }}</label>
                            <div class="d-flex gap-8 flex-column flex-lg-row flex-md-column">
                                <div class="form-check me-3">
                                    <input class="form-check-input" type="radio" name="pinterest_mode" value="1" id="pinterest_mode_1" {{ get_option("pinterest_mode", 1)==1?"checked":"" }}>
                                    <label class="form-check-label mt-1" for="pinterest_mode_1">
                                        {{ __('Live') }}
                                    </label>
                                </div>
                                <div class="form-check me-3">
                                    <input class="form-check-input" type="radio" name="pinterest_mode" value="0" id="pinterest_mode_0"{{ get_option("pinterest_mode", 0)==0?"checked":"" }}>
                                    <label class="form-check-label mt-1" for="pinterest_mode_0">
                                        {{ __('Sandbox') }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="mb-4">
                            <label for="name" class="form-label">{{ __('App ID') }}</label>
                            <input class="form-control" name="pinterest_client_id" id="pinterest_client_id" type="text" value="{{ get_option("pinterest_client_id", "") }}">
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="mb-4">
                            <label for="name" class="form-label">{{ __('App Secret') }}</label>
                            <input class="form-control" name="pinterest_client_secret" id="pinterest_client_secret" type="text" value="{{ get_option("pinterest_client_secret", "") }}">
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="mb-4">
                            <label for="name" class="form-label">{{ __('Scopes') }}</label>
                            <input class="form-control" name="pinterest_scopes" id="pinterest_scopes" type="text" value="{{ get_option("pinterest_scopes", "user_accounts:read,pins:read,pins:read_secret,pins:write,pins:write_secret,boards:read,boards:read_secret,boards:write") }}">
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="alert alert-primary fs-14">
                            <div>
                                <span class="fw-6">{{ __("Callback URL: ") }} </span>
                                <a href="{{ url_app("pinterest/board") }}" target="_blank">{{ url_app("pinterest/board") }}</a>
                            </div>

                            <div>
                                <span class="fw-6">{{ __("Create Pinterest app: ") }}</span> 
                                <a href="https://developers.pinterest.com/apps/connect/" target="_blank">https://developers.pinterest.com/apps/connect/</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-4">
            <button type="submit" class="btn btn-dark b-r-10 w-100">
                {{ __('Save changes') }}
            </button>
        </div>

    </form>

</div>

@endsection
