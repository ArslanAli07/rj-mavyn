# Mavyn contracts

Framework-independent integration boundaries, requiring PHP 8.3+. The initial public API is `Mavyn\Contracts\SettingsStore::get(string $key, mixed $default = null): mixed`. Implementations return the stored value or the supplied default; they must not return persistence models.

Core services depend on this interface. The host binds an Eloquent-backed adapter in `AppServiceProvider`. A future plugin can consume the same services without importing `App` classes. The Plugins namespace now supplies Plugin/PluginContext/PluginLifecycle, readonly ProductDisplay/payment/shipping DTOs, and payment/fulfillment provider contracts. Core implements the framework-independent runtime algorithms; the host supplies discovery and persistence adapters. See ../../docs/PLUGIN_RUNTIME.md for the supported API.

Changes to public signatures follow semantic versioning. The development host assigns version `0.1.0` through its Composer path repository. Release consumers should use tagged packages from their Composer repository; no public release has been published.
