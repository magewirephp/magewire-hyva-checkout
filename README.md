# Magewire Compatibility with Hyvä Checkout

[![Mago](https://github.com/magewirephp/magewire-hyva-checkout/actions/workflows/mago.yml/badge.svg?branch=main)](https://github.com/magewirephp/magewire-hyva-checkout/actions/workflows/mago.yml)

> Keeps [Hyvä Checkout](https://hyva.io) components built for Magewire v1 (Livewire v2) working on Magewire v3.

This is the Hyvä Checkout-specific compatibility layer for [Magewire](https://github.com/magewirephp/magewire) v3. It builds on top of [`magewirephp/magewire-hyva-theme`](https://github.com/magewirephp/magewire-hyva-theme) and carries the backwards-compatibility shims that let existing Hyvä Checkout components run on Magewire v3 unchanged.

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
