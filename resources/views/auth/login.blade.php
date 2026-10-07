<x-app-layout title="Sign In — Happy Tea">

    <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div class="text-center mb-8">
            <h1 class="text-3xl sm:text-4xl font-heading font-bold text-espresso-800">Welcome Back</h1>
            <div class="gold-line w-16 mx-auto mt-2 mb-4"></div>
            <p class="text-sm text-espresso-500 font-light leading-relaxed">
                Sign in to place an order, track deliveries, and manage your account.
            </p>
        </div>

        <div class="bg-white border border-cream-150 rounded-3xl p-6 sm:p-8 shadow-sm">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <x-honeypot />

                <div>
                    <label for="email" class="label-luxury">Email Address *</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                           class="input-luxury text-sm">
                    @error('email')
                        <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label for="password" class="label-luxury mb-0">Password *</label>
                        @if (Route::has('password.request'))
                            <a class="text-xs text-primary-600 hover:text-primary-700 hover:underline font-semibold" href="{{ route('password.request') }}">
                                Forgot password?
                            </a>
                        @endif
                    </div>
                    <input id="password" type="password" name="password" required autocomplete="current-password"
                           class="input-luxury text-sm">
                    @error('password')
                        <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="remember_me" class="inline-flex items-center gap-2 cursor-pointer select-none text-xs font-semibold text-espresso-750">
                        <input id="remember_me" type="checkbox" name="remember"
                               class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                        <span>Remember me</span>
                    </label>
                </div>

                <button type="submit" class="btn-gold w-full py-3.5 text-sm font-semibold tracking-wider">
                    Sign In
                </button>
            </form>

            <div class="mt-6 text-center border-t border-cream-100 pt-5">
                <p class="text-xs text-espresso-500">
                    New to Happy Tea?
                    <a class="font-bold text-primary-600 hover:text-primary-700 hover:underline" href="{{ route('register') }}">
                        Create an account
                    </a>
                </p>
            </div>
        </div>
    </div>

</x-app-layout>
