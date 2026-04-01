<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->brandName('KISE HMIS')
            ->brandLogo(new HtmlString('<div style="display:flex;align-items:center;gap:12px;"><div style="width:2.8rem;height:2.8rem;border-radius:50%;background:#ffffff;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><img src="' . asset('kise-logo.png') . '" style="height:2.2rem;width:2.2rem;object-fit:contain;"></div><span style="font-size:1.15rem;font-weight:800;color:#ffffff;letter-spacing:-.02em;line-height:1;">KISE HMIS</span></div>'))
            ->brandLogoHeight('2.8rem')
            ->favicon(asset('kise-logo.png'))
            ->colors([
                'primary' => Color::hex('#29972E'),
                'danger'  => Color::Red,
                'gray'    => Color::Zinc,
                'info'    => Color::Cyan,
                'success' => Color::hex('#29972E'),
                'warning' => Color::hex('#FFC105'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
               // Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->navigationGroups([
                'Client Management',
                'Clinical Workflow',
                'Service Delivery',
                'Billing & Payments',
                'Reports & Analytics',
                'System Settings',
            ])
            ->sidebarCollapsibleOnDesktop()
            // ── Inject theme CSS early (no flash) ─────────────────────────────
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => new HtmlString('
                    <link rel="stylesheet" href="' . asset('css/kise-theme.css') . '?v=10">
                    <script>
                        (function(){
                            var t = localStorage.getItem("kise-theme") || "forest";
                            document.documentElement.setAttribute("data-kise-theme", t);
                        })();
                    </script>
                ')
            )
            // ── Profile bar in topbar: user info + theme switcher ─────────────
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                function () {
                    $user = auth()->user();
                    if (! $user) return new HtmlString('');

                    $name     = $user->name ?? 'User';
                    $role     = method_exists($user, 'getRoleNames')
                                    ? ($user->getRoleNames()->first() ?? 'staff')
                                    : 'staff';
                    $roleLabel = ucwords(str_replace('_', ' ', $role));
                    return new HtmlString('
                    <div id="kise-profile-bar" style="display:flex;align-items:center;gap:10px;padding:5px 14px;background:rgba(0,0,0,.05);border-radius:10px;border:1px solid rgba(0,0,0,.08);margin-right:6px;">
                        <div style="line-height:1.3;min-width:0;">
                            <p style="font-size:0.82rem;font-weight:700;color:var(--kise-topbar-text,#282F3B);margin:0;white-space:nowrap;">'
                                . htmlspecialchars($name) .
                            '</p>
                            <p style="font-size:0.6rem;color:var(--kise-topbar-muted,#9ca3af);margin:0;display:flex;align-items:center;gap:4px;">
                                <span>' . htmlspecialchars($roleLabel) . '</span>
                                <span style="opacity:.4;">·</span>
                                <span id="kise-active-timer">0s</span>
                            </p>
                        </div>
                        <div style="width:1px;height:22px;background:rgba(0,0,0,.1);flex-shrink:0;"></div>
                        <div style="display:flex;gap:5px;align-items:center;">
                            <div class="kise-theme-dot" data-theme="forest"
                                 style="width:13px;height:13px;border-radius:50%;background:#29972E;cursor:pointer;flex-shrink:0;"
                                 onclick="kiseSwitchTheme(\'forest\')" title="Forest Green"></div>
                            <div class="kise-theme-dot" data-theme="dark-command"
                                 style="width:13px;height:13px;border-radius:50%;background:#282F3B;cursor:pointer;flex-shrink:0;"
                                 onclick="kiseSwitchTheme(\'dark-command\')" title="Dark Command"></div>
                            <div class="kise-theme-dot" data-theme="topbar"
                                 style="width:13px;height:13px;border-radius:50%;background:#d1d5db;cursor:pointer;flex-shrink:0;border:1px solid #aaa;"
                                 onclick="kiseSwitchTheme(\'topbar\')" title="Top Bar"></div>
                        </div>
                    </div>

                    <script>
                    (function(){
                        // Theme switcher
                        window.kiseSwitchTheme = function(theme) {
                            document.documentElement.setAttribute("data-kise-theme", theme);
                            localStorage.setItem("kise-theme", theme);
                            document.querySelectorAll(".kise-theme-dot").forEach(function(d) {
                                d.style.outline = d.dataset.theme === theme ? "2px solid #FFC105" : "none";
                                d.style.outlineOffset = "2px";
                            });
                        };
                        // Sync dots to saved theme
                        var saved = localStorage.getItem("kise-theme") || "forest";
                        document.querySelectorAll(".kise-theme-dot").forEach(function(d) {
                            d.style.outline = d.dataset.theme === saved ? "2px solid #FFC105" : "none";
                            d.style.outlineOffset = "2px";
                        });
                        // Active time counter — persists across refreshes via localStorage
                        var stored = localStorage.getItem("kise-session-start");
                        var start = stored ? parseInt(stored, 10) : Date.now();
                        if (!stored) localStorage.setItem("kise-session-start", start);
                        function fmt(ms) {
                            var s = Math.floor(ms/1000);
                            var m = Math.floor(s/60);
                            var h = Math.floor(m/60);
                            if (h) return h + "h " + (m%60) + "m";
                            if (m) return m + "m " + (s%60) + "s";
                            return s + "s";
                        }
                        function tick() {
                            var el = document.getElementById("kise-active-timer");
                            if (el) el.textContent = fmt(Date.now() - start);
                        }
                        setInterval(tick, 1000);
                        tick();
                    })();
                    </script>
                    ');
                }
            );
    }
}
