<x-app-layout title="Forgot Password — Happy Tea">

    <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div class="text-center mb-8">
            <h1 class="text-3xl sm:text-4xl font-heading font-bold text-espresso-800">Forgot Password</h1>
            <div class="gold-line w-16 mx-auto mt-2 mb-4"></div>
            <p class="text-sm text-espresso-500 font-light leading-relaxed">
                Enter your email and we’ll send you a secure link to reset your password.
            </p>
        </div>

        <div class="bg-white border border-cream-150 rounded-3xl p-6 sm:p-8 shadow-sm">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                @csrf
                <x-honeypot />

                <div>
                    <label for="email" class="label-luxury">Email Address *</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="input-luxury text-sm">
                    @error('email')
                        <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn-gold w-full py-3.5 text-sm font-semibold tracking-wider">
                    Email Reset Link
                </button>
            </form>

            <div class="mt-6 text-center border-t border-cream-100 pt-5">
                <p class="text-xs text-espresso-500">
                    Remembered your password?
                    <a class="font-bold text-primary-600 hover:text-primary-700 hover:underline" href="{{ route('login') }}">
                        Sign In
                    </a>
                </p>
            </div>
        </div>
    </div>

</x-app-layout>
