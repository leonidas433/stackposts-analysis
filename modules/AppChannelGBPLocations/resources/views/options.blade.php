

<div class="mb-3">
    <div class="card shadow-none b-r-6">
        <div class="card-header px-3">
            <div class="fs-12 fw-6 text-gray-700">
                {{ __("Google business profile") }}
            </div>
        </div>
        <div class="card-body px-3">

        	<div class="mb-3">
				<label class="form-label">{{ __("Call To Action") }}</label>
				<select class="form-select" name="options[gbp_action]">
			        <option value="">{{ __('No Action') }}</option>
			        <option value="LEARN_MORE">{{ __('Learn more') }}</option>
			        <option value="BOOK">{{ __('Book') }}</option>
			        <option value="ORDER">{{ __('Order online') }}</option>
			        <option value="SHOP">{{ __('Shop') }}</option>
			        <option value="SIGN_UP">{{ __('Sign up') }}</option>
			    </select>
			</div>
			<div class="mb-3">
				<label class="form-label">{{ __("Action Link") }}</label>
				<input type="text" class="form-control" name="options[gbp_link]" placeholder="{{ __('Enter your link') }}">
			</div>
        </div>
    </div>
</div>