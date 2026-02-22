@extends('layout')

@section('title', 'Dashboard')

@section('content')
<div class="card">
    <h1 class="card-header">Welcome to Foodpanda Dashboard, {{ $user->name }}! 🎉</h1>
    
    <div class="dashboard-grid">
        <div class="info-card">
            <h3>👤 Your Profile</h3>
            <p><strong>Name:</strong> {{ $user->name }}</p>
            <p><strong>Email:</strong> {{ $user->email }}</p>
            <p><strong>Member Since:</strong> {{ $user->created_at->format('M d, Y') }}</p>
        </div>

        <div class="info-card">
            <h3>🔐 Single Sign-On Status</h3>
            <p>✅ You are logged in to <strong>Foodpanda System</strong></p>
            <p>✅ You are automatically logged in to <strong>E-Commerce System</strong></p>
            <p style="margin-top: 1rem; font-size: 0.9rem;">
                With our SSO system, you can seamlessly access both platforms without logging in twice!
            </p>
            @if($ecommerceUrl && $ssoToken)
                <a href="{{ $ecommerceUrl }}/sso-auto-login?token={{ urlencode($ssoToken) }}" 
                   class="sso-link" 
                   target="_blank">
                    🛍️ Access E-Commerce Dashboard →
                </a>
            @endif
        </div>
    </div>

    <div style="margin-top: 2rem; padding: 1.5rem; background-color: #f3f4f6; border-radius: 10px;">
        <h3 style="color: #333; margin-bottom: 1rem;">How SSO Works:</h3>
        <ol style="color: #555; line-height: 1.8;">
            <li>When you log in to Foodpanda, a secure SSO token is generated</li>
            <li>This token is automatically shared with the E-Commerce system</li>
            <li>You can access E-Commerce without entering credentials again</li>
            <li>When you logout from one system, you're logged out from both</li>
        </ol>
    </div>
</div>
@endsection
