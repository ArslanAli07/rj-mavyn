<?php

namespace Mavyn\Contracts\Themes;

/** Canonical presentation names; adding a file does not add a route. Version 0.1. */
final class TemplateInventory
{
    public const VERSION = '0.1.0';

    public const NAMES = [
        'account.addresses',
        'account.dashboard',
        'account.order-detail',
        'account.orders',
        'account.password',
        'account.profile',
        'account.wishlist',
        'auth.confirm-password',
        'auth.forgot-password',
        'auth.login',
        'auth.register',
        'auth.reset-password',
        'auth.verify-email',
        'components.breadcrumb',
        'components.pagination',
        'components.product-card',
        'components.product-card-add',
        'layouts.app',
        'layouts.auth',
        'layouts.guest',
        'pages.about',
        'pages.cart',
        'pages.category',
        'pages.checkout',
        'pages.contact',
        'pages.faq',
        'pages.gallery',
        'pages.home',
        'pages.order-success',
        'pages.payment-instructions',
        'pages.privacy',
        'pages.product',
        'pages.returns',
        'pages.search',
        'pages.shipping',
        'pages.shop',
        'pages.terms',
        'pages.track-order',
        'pages.wishlist',
    ];
}
