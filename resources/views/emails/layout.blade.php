@php
    $emailBrand = $brand ?? (isset($order) ? ($order->brand_snapshot ?? \App\Services\StoreIdentity::snapshot()) : \App\Services\StoreIdentity::snapshot());
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>@yield('title', $emailBrand['name'])</title>
    <style>
        /* Brand: matches storefront theme */
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #FFF9F2;
            margin: 0;
            padding: 0;
            color: #2E1B12;
        }
        .container {
            max-width: 600px;
            margin: 30px auto;
            background: #ffffff;
            border: 1px solid #F0DCC8;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(74, 46, 34, 0.06);
        }
        .header {
            background-color: #4A2E22;
            padding: 30px;
            text-align: center;
            border-bottom: 3px solid #F88027;
        }
        .header h1 {
            color: #ffffff;
            font-size: 24px;
            margin: 0;
            letter-spacing: 1px;
        }
        .header p {
            color: #FFE3C2;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin: 5px 0 0 0;
        }
        .body {
            padding: 30px;
        }
        .welcome {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #4A2E22;
        }
        .intro {
            font-size: 14px;
            color: #8C7568;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        .button {
            display: inline-block;
            background-color: #F88027;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: bold;
            margin-top: 15px;
            text-align: center;
        }
        .footer {
            background-color: #FFF1E0;
            padding: 20px;
            text-align: center;
            font-size: 11px;
            color: #8C7568;
            border-top: 1px solid #F0DCC8;
        }
        .social-links {
            margin-top: 15px;
        }
        .social-link {
            display: inline-block;
            margin: 0 8px;
            color: #F88027;
            text-decoration: none;
            font-weight: bold;
        }

        /* Order Confirmation & Update specifics */
        .summary-box {
            background-color: #FFF9F2;
            border: 1px solid #F0DCC8;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 25px;
            font-size: 13px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .summary-row:last-child {
            margin-bottom: 0;
        }
        .summary-label {
            color: #8C7568;
        }
        .summary-value {
            font-weight: bold;
            color: #2E1B12;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            font-size: 13px;
        }
        .table th {
            text-align: left;
            padding: 10px;
            border-bottom: 2px solid #F0DCC8;
            color: #4A2E22;
            font-weight: bold;
        }
        .table td {
            padding: 12px 10px;
            border-bottom: 1px solid #FFF1E0;
            color: #2E1B12;
        }
        .total-box {
            border-top: 2px solid #F0DCC8;
            padding-top: 15px;
            margin-top: 15px;
            text-align: right;
            font-size: 15px;
            font-weight: bold;
            color: #4A2E22;
        }
        .total-value {
            color: #F88027;
            font-size: 18px;
        }

        /* Welcome Email specifics */
        .features-box {
            background-color: #FFF9F2;
            border: 1px solid #F0DCC8;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .feature-item {
            margin-bottom: 12px;
            font-size: 13px;
            line-height: 1.5;
            color: #8C7568;
        }
        .feature-item:last-child {
            margin-bottom: 0;
        }
        .feature-title {
            font-weight: bold;
            color: #4A2E22;
        }

        /* Forgot Password specifics */
        .warning-box {
            background-color: #FFF1E0;
            border: 1px solid #F0DCC8;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 25px;
            font-size: 13px;
            color: #8C7568;
            line-height: 1.5;
        }
        .break-link {
            font-size: 11px;
            color: #8C7568;
            word-break: break-all;
            margin-top: 20px;
            text-align: center;
        }

        /* Order Status Update specifics */
        .status-badge {
            display: inline-block;
            padding: 6px 15px;
            font-weight: bold;
            font-size: 14px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 25px;
        }
        .status-pending { background-color: #FFC857; color: #4A2E22; }
        .status-confirmed { background-color: #FFE3C2; color: #DD6E14; }
        .status-preparing { background-color: #FFF1E0; color: #F88027; }
        .status-out_for_delivery { background-color: #FFE3C2; color: #4A2E22; }
        .status-delivered { background-color: #8FBF9F; color: #2E1B12; }
        .status-cancelled { background-color: #fee2e2; color: #991b1b; }

        .progress-container {
            margin: 30px 0;
            text-align: center;
        }
        .steps {
            display: table;
            width: 100%;
            table-layout: fixed;
            margin: 0 auto;
            padding: 0;
        }
        .step {
            display: table-cell;
            text-align: center;
            position: relative;
            font-size: 11px;
            color: #8C7568;
        }
        .step-icon {
            width: 24px;
            height: 24px;
            line-height: 24px;
            border-radius: 50%;
            background-color: #FFF1E0;
            color: #8C7568;
            margin: 0 auto 8px auto;
            font-weight: bold;
            font-size: 12px;
            position: relative;
            z-index: 2;
        }
        .step.active {
            color: #F88027;
            font-weight: bold;
        }
        .step.active .step-icon {
            background-color: #F88027;
            color: #ffffff;
        }
        .step::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 4px;
            background-color: #F0DCC8;
            top: 10px;
            left: 50%;
            z-index: 1;
        }
        .step:last-child::after {
            display: none;
        }
        .step.completed::after {
            background-color: #F88027;
        }
        .admin-note {
            background-color: #FFF9F2;
            border-left: 4px solid #F88027;
            padding: 15px;
            margin-bottom: 25px;
            font-size: 13px;
            font-style: italic;
            color: #4A2E22;
            border-radius: 0 12px 12px 0;
        }
    </style>
</head>
<body>

    <div class="container">
        {{-- Header branding --}}
        <div class="header">
            <table align="center" border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto; text-align: left;">
                <tr>
                    <td style="padding-right: 12px; vertical-align: middle;">
                        @if($storeLogoUrl = app(\App\Services\Media\ContentMediaService::class)->branding('site_logo')['url'] ?? null)
                            <img src="{{ $storeLogoUrl }}" alt="{{ setting('site_name', 'Store') }} Logo" width="45" height="45" style="height: 45px; width: 45px; border-radius: 50%; display: block;">
                        @endif
                    </td>
                    <td style="vertical-align: middle; line-height: 1.2;">
                        <span style="color: #ffffff; font-size: 24px; font-weight: bold; letter-spacing: 1px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; display: block;">{{ $emailBrand['name'] }}</span>
                        <span style="color: #FFE3C2; font-size: 11px; text-transform: uppercase; letter-spacing: 2px; margin-top: 4px; display: block; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; padding-left: 3px;">{{ $emailBrand['tagline'] ?? '' }}</span>
                    </td>
                </tr>
            </table>
        </div>

        {{-- Email Body --}}
        <div class="body">
            @yield('content')
        </div>

        {{-- Footer --}}
        <div class="footer">
            <p>© {{ date('Y') }} {{ $emailBrand['name'] }}. All rights reserved.</p>
            @php
                $emailAddress = trim((string) ($emailBrand['address'] ?? ''));
                $emailFacebook = trim((string) setting('facebook_url'));
                $emailInstagram = trim((string) setting('instagram_url'));
                $emailTiktok = trim((string) setting('tiktok_url'));
                $emailWhatsapp = preg_replace('/[^0-9]/', '', (string) setting('site_whatsapp'));
            @endphp
            @if($emailAddress)
                <p>{{ $emailAddress }}</p>
            @endif
            @if($emailFacebook || $emailInstagram || $emailTiktok || $emailWhatsapp)
            <div class="social-links">
                @if($emailFacebook)
                    <a href="{{ $emailFacebook }}" class="social-link">Facebook</a>
                @endif
                @if($emailInstagram)
                    <a href="{{ $emailInstagram }}" class="social-link">Instagram</a>
                @endif
                @if($emailTiktok)
                    <a href="{{ $emailTiktok }}" class="social-link">TikTok</a>
                @endif
                @if($emailWhatsapp)
                    <a href="https://wa.me/{{ $emailWhatsapp }}" class="social-link">WhatsApp</a>
                @endif
            </div>
            @endif
        </div>
    </div>

</body>
</html>
