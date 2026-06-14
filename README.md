# Magewire Compatibility with Hyvä Checkout

[![Mago](https://github.com/magewirephp/magewire-hyva-checkout/actions/workflows/mago.yml/badge.svg?branch=main)](https://github.com/magewirephp/magewire-hyva-checkout/actions/workflows/mago.yml)

> Keeps [Hyvä Checkout](https://hyva.io) components built for Magewire v1 (Livewire v2) working on Magewire v3.

This is the Hyvä Checkout-specific compatibility layer for [Magewire](https://github.com/magewirephp/magewire) v3. It builds on top of [`magewirephp/magewire-hyva-theme`](https://github.com/magewirephp/magewire-hyva) and carries the backwards-compatibility shims that let existing Hyvä Checkout components run on Magewire v3 unchanged.

## What it does

Hyvä Checkout was built on Magewire v1 (Livewire v2). Magewire v3 (Livewire v3) changed the `wire:` directive and `entangle` semantics:

| Magewire v1 (Livewire v2) | Magewire v3 (Livewire v3)      |
|---------------------------|--------------------------------|
| `wire:model` (instant)    | `wire:model.live`              |
| `wire:model.defer`        | `wire:model` (now the default) |
| `wire:model.lazy`         | `wire:model.blur`              |
| `$wire.entangle()` (live) | `$wire.entangle()` (deferred)  |

The `SupportHyvaCheckoutBackwardsCompatibility` Magewire feature flags components that need the old behavior by pushing a `bc.enabled` memo into the snapshot. The flag is resolved, in priority order, from:

1. The `#[HandleBackwardsCompatibility]` attribute on the component class
2. A previously hydrated value from the component's data store
3. Whether the component lives inside the `hyva-checkout-main` layout container

Frontend JS then reads the flag to migrate `wire:model` directives and make `entangle` default to live — so existing Hyvä Checkout components run on Magewire v3 unchanged.

## Requirements

- `magewirephp/magewire` `>=3.2`
- `magewirephp/magewire-hyva-theme`
- `Hyva_Theme`
- `Hyva_Checkout`

The module declares a `sequence` after `Magewirephp_Magewire`, `Magewirephp_MagewireHyvaTheme`, `Hyva_Theme`, and `Hyva_Checkout`.

## Installation

```bash
composer require magewirephp/magewire-hyva-checkout
bin/magento module:enable Magewirephp_MagewireHyvaCheckout
bin/magento setup:upgrade
```

## Documentation

See the [Hyvä docs](https://docs.hyva.io/) and the [Magewire docs](https://github.com/magewirephp/magewire).

## Security Vulnerabilities

Please do not report security issues publicly. Disclose them privately to the Magewire maintainers.

## License

Open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).