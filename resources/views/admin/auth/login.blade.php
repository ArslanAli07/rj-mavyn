<x-auth-layout title="Admin Login">

    <div class="space-y-4">
        <div class="text-center">
            <h2 class="text-xl font-heading font-bold text-espresso-800">Admin Portal</h2>
            <p class="text-xs text-espresso-500 mt-1">Sign in to manage your store.</p>
        </div>

        <form method="POST" action="{{ route('admin.login.post') }}" class="space-y-4">
            @csrf

            {{-- Email Address --}}
            <div>
                <label for="email" class="label-luxury">Admin Email</label>
                <input id="email" class="input-luxury text-sm" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                @error('email')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password --}}
            <div>
                <label for="password" class="label-luxury">Password</label>
                <input id="password" class="input-luxury text-sm" type="password" name="password" required autocomplete="current-password">
                @error('password')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Remember Me --}}
            <div class="flex items-center justify-between pt-1">
                <label class="inline-flex items-center text-xs font-semibold text-espresso-750 cursor-pointer select-none">
                    <input id="remember_me" type="checkbox" class="rounded border-cream-300 text-primary-500 focus:ring-primary-300" name="remember">
                    <span class="ml-2 text-xs font-medium text-espresso-600">Remember session</span>
                </label>
            </div>

            <button type="submit" class="btn-gold w-full py-3 text-sm font-semibold tracking-wide shadow-md">
                Sign In to Admin
            </button>
        </form>
    </div>

</x-auth-layout>
