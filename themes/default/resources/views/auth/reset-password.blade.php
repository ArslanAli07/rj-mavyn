<x-app-layout title="Reset Password — {{ setting('site_name', config('site.name', 'Store')) }}">

    <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div class="text-center mb-8">
            <h1 class="text-3xl sm:text-4xl font-heading font-bold text-espresso-800">Reset Password</h1>
            <div class="gold-line w-16 mx-auto mt-2 mb-4"></div>
            <p class="text-sm text-espresso-500 font-light leading-relaxed">
                Create a new secure password for your account.
            </p>
        </div>

        <div class="bg-white border border-cream-150 rounded-3xl p-6 sm:p-8 shadow-sm">
            <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
                @csrf

                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <div>
                    <label for="email" class="label-luxury">Email Address *</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username"
                           class="input-luxury text-sm">
                    @error('email')
                        <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="label-luxury">New Password *</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password"
                           class="input-luxury text-sm">
                    @error('password')
                        <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="label-luxury">Confirm Password *</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                           class="input-luxury text-sm">
                    @error('password_confirmation')
                        <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn-gold w-full py-3.5 text-sm font-semibold tracking-wider mt-2">
                    Reset Password
                </button>
            </form>
        </div>
    </div>

</x-app-layout>
