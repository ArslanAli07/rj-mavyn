<x-auth-layout title="Admin login">

    <div class="admin-stack-4">
        <div class="text-center">
            <h2 class="text-body admin-section-title">Admin portal</h2>
            <p class="small text-body-secondary mt-1">Sign in to manage your store.</p>
        </div>

        <form method="POST" action="{{ route('admin.login.post') }}" class="admin-stack-4">
            @csrf

            {{-- Email Address --}}
            <div>
                <label for="email" class="form-label">Admin email</label>
                <input id="email" class="form-control small" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                @error('email')
                    <p class="text-danger small mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password --}}
            <div>
                <label for="password" class="form-label">Password</label>
                <input id="password" class="form-control small" type="password" name="password" required autocomplete="current-password">
                @error('password')
                    <p class="text-danger small mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Remember Me --}}
            <div class="d-flex align-items-center justify-content-between pt-1">
                <label class="d-inline-flex align-items-center small fw-semibold text-body admin-clickable user-select-none">
                    <input id="remember_me" type="checkbox" class="form-check-input" name="remember">
                    <span class="ms-2 small fw-medium text-body-secondary">Remember session</span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-3 small fw-semibold admin-letter-spacing shadow">
                Sign in to admin
            </button>
        </form>
    </div>

</x-auth-layout>
