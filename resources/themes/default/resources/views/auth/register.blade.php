<x-app-layout title="Create Account — {{ setting('site_name', config('site.name', 'Store')) }}">

    <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div class="text-center mb-8">
            <h1 class="text-3xl sm:text-4xl font-heading font-bold text-espresso-800">Create Account</h1>
            <div class="gold-line w-16 mx-auto mt-2 mb-4"></div>
            <p class="text-sm text-espresso-500 font-light leading-relaxed">
                Join our store to save favorites, track orders, and check out faster.
            </p>
        </div>

        <div class="bg-white border border-cream-150 rounded-3xl p-6 sm:p-8 shadow-sm">
            <form method="POST" action="{{ route('register') }}" class="space-y-4">
                @csrf
                <x-honeypot />

                <div>
                    <label for="name" class="label-luxury">Full Name *</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                           class="input-luxury text-sm">
                    @error('name')
                        <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="label-luxury">Email Address *</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                           class="input-luxury text-sm">
                    @error('email')
                        <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="label-luxury">Password *</label>
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
                    Create Account
                </button>
            </form>

            <div class="mt-6 text-center border-t border-cream-100 pt-5">
                <p class="text-xs text-espresso-500">
                    Already registered?
                    <a class="font-bold text-primary-600 hover:text-primary-700 hover:underline" href="{{ route('login') }}">
                        Sign In
                    </a>
                </p>
            </div>
        </div>
    </div>

</x-app-layout>
