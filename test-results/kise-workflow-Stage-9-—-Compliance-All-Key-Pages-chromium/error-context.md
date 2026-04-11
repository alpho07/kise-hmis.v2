# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: kise-workflow.spec.ts >> Stage 9 — Compliance: All Key Pages
- Location: tests/e2e/kise-workflow.spec.ts:837:1

# Error details

```
Error: Server errors detected on: Intake Assessments, Billing, Service Queue, Departments, Payments
```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - generic [ref=e2]:
    - generic [ref=e4]:
      - generic [ref=e5]:
        - img [ref=e7]
        - generic [ref=e10]: Internal Server Error
      - button "Copy as Markdown" [ref=e11] [cursor=pointer]:
        - img [ref=e12]
        - generic [ref=e15]: Copy as Markdown
    - generic [ref=e18]:
      - generic [ref=e19]:
        - heading "Illuminate\\Database\\QueryException" [level=1] [ref=e20]
        - generic [ref=e22]: vendor/laravel/framework/src/Illuminate/Database/Connection.php:824
        - paragraph [ref=e23]: "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'amount_paid' in 'field list' (Connection: mysql, SQL: select sum(`amount_paid`) as aggregate from `payments` where `payments`.`deleted_at` is null)"
      - generic [ref=e24]:
        - generic [ref=e25]:
          - generic [ref=e26]:
            - generic [ref=e27]: LARAVEL
            - generic [ref=e28]: 12.38.1
          - generic [ref=e29]:
            - generic [ref=e30]: PHP
            - generic [ref=e31]: 8.4.12
        - generic [ref=e32]:
          - img [ref=e33]
          - text: UNHANDLED
        - generic [ref=e36]: CODE 42S22
      - generic [ref=e38]:
        - generic [ref=e39]:
          - img [ref=e40]
          - text: "500"
        - generic [ref=e43]:
          - img [ref=e44]
          - text: GET
        - generic [ref=e47]: http://127.0.0.1:8000/admin/payments
        - button [ref=e48] [cursor=pointer]:
          - img [ref=e49]
    - generic [ref=e53]:
      - generic [ref=e54]:
        - generic [ref=e55]:
          - img [ref=e57]
          - heading "Exception trace" [level=3] [ref=e60]
        - generic [ref=e61]:
          - generic [ref=e63] [cursor=pointer]:
            - img [ref=e64]
            - generic [ref=e68]: 10 vendor frames
            - button [ref=e69]:
              - img [ref=e70]
          - generic [ref=e74]:
            - generic [ref=e75] [cursor=pointer]:
              - generic [ref=e78]:
                - code [ref=e82]:
                  - generic [ref=e83]: App\Filament\Resources\PaymentResource\Pages\ListPayments->getHeading()
                - generic [ref=e85]: app/Filament/Resources/PaymentResource/Pages/ListPayments.php:20
              - button [ref=e87]:
                - img [ref=e88]
            - code [ref=e96]:
              - generic [ref=e97]: "15 }"
              - generic [ref=e98]: "16"
              - generic [ref=e99]: "17 public function getHeading(): string"
              - generic [ref=e100]: "18 {"
              - generic [ref=e101]: 19 $count = $this->getTableRecords()->count();
              - generic [ref=e102]: 20 $total = $this->getTableQuery()->sum('amount_paid');
              - generic [ref=e103]: "21"
              - generic [ref=e104]: "22 return \"Payments ({$count} transactions)\";"
              - generic [ref=e105]: "23 }"
              - generic [ref=e106]: "24"
              - generic [ref=e107]: "25 public function getSubheading(): ?string"
              - generic [ref=e108]: "26 {"
              - generic [ref=e109]: 27 $total = $this->getTableQuery()->sum('amount_paid');
              - generic [ref=e110]: "28 return 'Total: KES ' . number_format($total, 2);"
              - generic [ref=e111]: "29 }"
              - generic [ref=e112]: "30}"
          - generic [ref=e114] [cursor=pointer]:
            - img [ref=e115]
            - generic [ref=e119]: 86 vendor frames
            - button [ref=e120]:
              - img [ref=e121]
          - generic [ref=e126] [cursor=pointer]:
            - generic [ref=e129]:
              - code [ref=e133]:
                - generic [ref=e134]: public/index.php
              - generic [ref=e136]: public/index.php:20
            - button [ref=e138]:
              - img [ref=e139]
          - generic [ref=e144] [cursor=pointer]:
            - img [ref=e145]
            - generic [ref=e149]: 1 vendor frame
            - button [ref=e150]:
              - img [ref=e151]
      - generic [ref=e155]:
        - generic [ref=e156]:
          - generic [ref=e157]:
            - img [ref=e159]
            - heading "Queries" [level=3] [ref=e161]
          - generic [ref=e163]: 1-6 of 6
        - generic [ref=e164]:
          - generic [ref=e165]:
            - generic [ref=e166]:
              - generic [ref=e167]:
                - img [ref=e168]
                - generic [ref=e170]: mysql
              - code [ref=e174]:
                - generic [ref=e175]: "select * from `sessions` where `id` = '9UYXe1eKN5R2dMLJ04ZphwtHPf8OhkMOLZNXJXkR' limit 1"
            - generic [ref=e176]: 2.45ms
          - generic [ref=e177]:
            - generic [ref=e178]:
              - generic [ref=e179]:
                - img [ref=e180]
                - generic [ref=e182]: mysql
              - code [ref=e186]:
                - generic [ref=e187]: "select * from `users` where `id` = 1 limit 1"
            - generic [ref=e188]: 0.78ms
          - generic [ref=e189]:
            - generic [ref=e190]:
              - generic [ref=e191]:
                - img [ref=e192]
                - generic [ref=e194]: mysql
              - code [ref=e198]:
                - generic [ref=e199]: "select * from `cache` where `key` in ('kise-hmis-cache-spatie.permission.cache')"
            - generic [ref=e200]: 1.58ms
          - generic [ref=e201]:
            - generic [ref=e202]:
              - generic [ref=e203]:
                - img [ref=e204]
                - generic [ref=e206]: mysql
              - code [ref=e210]:
                - generic [ref=e211]: "select `permissions`.*, `model_has_permissions`.`model_id` as `pivot_model_id`, `model_has_permissions`.`permission_id` as `pivot_permission_id`, `model_has_permissions`.`model_type` as `pivot_model_type` from `permissions` inner join `model_has_permissions` on `permissions`.`id` = `model_has_permissions`.`permission_id` where `model_has_permissions`.`model_id` in (1) and `model_has_permissions`.`model_type` = 'App\\Models\\User'"
            - generic [ref=e212]: 0.8ms
          - generic [ref=e213]:
            - generic [ref=e214]:
              - generic [ref=e215]:
                - img [ref=e216]
                - generic [ref=e218]: mysql
              - code [ref=e222]:
                - generic [ref=e223]: "select `roles`.*, `model_has_roles`.`model_id` as `pivot_model_id`, `model_has_roles`.`role_id` as `pivot_role_id`, `model_has_roles`.`model_type` as `pivot_model_type` from `roles` inner join `model_has_roles` on `roles`.`id` = `model_has_roles`.`role_id` where `model_has_roles`.`model_id` in (1) and `model_has_roles`.`model_type` = 'App\\Models\\User'"
            - generic [ref=e224]: 1.45ms
          - generic [ref=e225]:
            - generic [ref=e226]:
              - generic [ref=e227]:
                - img [ref=e228]
                - generic [ref=e230]: mysql
              - code [ref=e234]:
                - generic [ref=e235]: "select count(*) as aggregate from `payments` where (`status` = 'completed' and date(`received_at`) = '2026-04-11') and `payments`.`deleted_at` is null"
            - generic [ref=e236]: 1.08ms
    - generic [ref=e238]:
      - generic [ref=e239]:
        - heading "Headers" [level=2] [ref=e240]
        - generic [ref=e241]:
          - generic [ref=e242]:
            - generic [ref=e243]: host
            - generic [ref=e245]: 127.0.0.1:8000
          - generic [ref=e246]:
            - generic [ref=e247]: connection
            - generic [ref=e249]: keep-alive
          - generic [ref=e250]:
            - generic [ref=e251]: sec-ch-ua
            - generic [ref=e253]: "\"HeadlessChrome\";v=\"147\", \"Not.A/Brand\";v=\"8\", \"Chromium\";v=\"147\""
          - generic [ref=e254]:
            - generic [ref=e255]: sec-ch-ua-mobile
            - generic [ref=e257]: "?0"
          - generic [ref=e258]:
            - generic [ref=e259]: sec-ch-ua-platform
            - generic [ref=e261]: "\"Windows\""
          - generic [ref=e262]:
            - generic [ref=e263]: upgrade-insecure-requests
            - generic [ref=e265]: "1"
          - generic [ref=e266]:
            - generic [ref=e267]: user-agent
            - generic [ref=e269]: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.7727.15 Safari/537.36
          - generic [ref=e270]:
            - generic [ref=e271]: accept-language
            - generic [ref=e273]: en-US
          - generic [ref=e274]:
            - generic [ref=e275]: accept
            - generic [ref=e277]: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7
          - generic [ref=e278]:
            - generic [ref=e279]: sec-fetch-site
            - generic [ref=e281]: none
          - generic [ref=e282]:
            - generic [ref=e283]: sec-fetch-mode
            - generic [ref=e285]: navigate
          - generic [ref=e286]:
            - generic [ref=e287]: sec-fetch-user
            - generic [ref=e289]: "?1"
          - generic [ref=e290]:
            - generic [ref=e291]: sec-fetch-dest
            - generic [ref=e293]: document
          - generic [ref=e294]:
            - generic [ref=e295]: accept-encoding
            - generic [ref=e297]: gzip, deflate, br, zstd
          - generic [ref=e298]:
            - generic [ref=e299]: cookie
            - generic [ref=e301]: XSRF-TOKEN=eyJpdiI6IkUvTFRPMlU5UzlGdUhGdjdDcGd5TWc9PSIsInZhbHVlIjoieWlsaW9TNnVrZWk4ZmdJdEE3V2daY2lJaU0xcUd5WDkzd2ZNcnNIK212WUJ6Q3lWN04yc1JBTlpZZHlsUHJZRlJaTnZ0dTlRcmZQU3Q2VWFyZEhOQVFzMW1Fb0R3VEp5RGFBNEpoRzBWR0ZGZzhENjBMWTZFdVFBb3c3VlJKRU8iLCJtYWMiOiJiZjc1NzUyMDc0OTg2ODAwZGY4NTBhODQzOWJhMmE4ZmNkZWFkY2M5NTViZDZmODEyMDY5ZjczN2I1MWU3ZWEwIiwidGFnIjoiIn0%3D; kise-hmis-session=eyJpdiI6IldJamgvbFYwemM2aEdkelpqYzlhR0E9PSIsInZhbHVlIjoiNXVLUkNhc0JJOW5zWGNmRzg5cGo3VkhWa3E2U1dGUlAzaVdCc3JoTW8yR2JCL0ZlZ0tCZ3kxVGI2Q0pnOGhzb2VFNjVRNlE3WGExaGdiNXZZVFBDdTlXMzdRQWs5SU5wV2JIYlFhdjhiU2lWdFVodEZEUGNZUi9uaDV0dWhEVVkiLCJtYWMiOiIzZDMxYTdlNWYxNzk1YmViNTQxOWMwODk1YTMxMDMxODhhZjA1ODg4ZTMxMzY4MzhlZWNmYTczNWI5NWE4ODZkIiwidGFnIjoiIn0%3D
      - generic [ref=e302]:
        - heading "Body" [level=2] [ref=e303]
        - generic [ref=e304]: // No request body
      - generic [ref=e305]:
        - heading "Routing" [level=2] [ref=e306]
        - generic [ref=e307]:
          - generic [ref=e308]:
            - generic [ref=e309]: controller
            - generic [ref=e311]: App\Filament\Resources\PaymentResource\Pages\ListPayments
          - generic [ref=e312]:
            - generic [ref=e313]: route name
            - generic [ref=e315]: filament.admin.resources.payments.index
          - generic [ref=e316]:
            - generic [ref=e317]: middleware
            - generic [ref=e319]: panel:admin, Illuminate\Cookie\Middleware\EncryptCookies, Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse, Illuminate\Session\Middleware\StartSession, Filament\Http\Middleware\AuthenticateSession, Illuminate\View\Middleware\ShareErrorsFromSession, Illuminate\Foundation\Http\Middleware\VerifyCsrfToken, Illuminate\Routing\Middleware\SubstituteBindings, Filament\Http\Middleware\DisableBladeIconComponents, Filament\Http\Middleware\DispatchServingFilamentEvent, Filament\Http\Middleware\Authenticate
      - generic [ref=e320]:
        - heading "Routing parameters" [level=2] [ref=e321]
        - generic [ref=e322]: // No routing parameters
    - generic [ref=e325]:
      - img [ref=e327]
      - img [ref=e329]
  - generic [ref=e331]:
    - generic [ref=e333]:
      - generic [ref=e335]:
        - generic [ref=e336] [cursor=pointer]:
          - generic: 
          - generic [ref=e337]: 500 Internal Server Error
        - generic [ref=e338] [cursor=pointer]:
          - generic: 
          - generic [ref=e339]: "1"
        - generic [ref=e340] [cursor=pointer]:
          - generic: 
        - generic [ref=e341] [cursor=pointer]:
          - generic: 
          - generic [ref=e342]: "4"
        - generic [ref=e343] [cursor=pointer]:
          - generic: 
          - generic [ref=e344]: "484"
        - generic [ref=e345] [cursor=pointer]:
          - generic: 
          - generic [ref=e346]: "7"
        - generic [ref=e347] [cursor=pointer]:
          - generic: 
          - generic [ref=e348]: "2"
        - generic [ref=e349] [cursor=pointer]:
          - generic: 
          - generic [ref=e350]: "1"
        - text: 
        - generic [ref=e351] [cursor=pointer]:
          - generic: 
          - generic [ref=e352]: "4"
      - generic [ref=e353]:
        - generic [ref=e355] [cursor=pointer]:
          - generic: 
        - generic [ref=e358] [cursor=pointer]:
          - generic: 
        - generic [ref=e359] [cursor=pointer]:
          - generic: 
          - generic: 1.37s
        - generic [ref=e360]:
          - generic: 
          - generic: 13MB
        - generic [ref=e361]:
          - generic: 
          - generic: 12.x
        - generic [ref=e362] [cursor=pointer]:
          - generic: 
          - generic: GET admin/payments
    - text:                                                                                                                                                                                                 
  - text: 
```

# Test source

```ts
  811 | 
  812 |       // Verify: returning client should NOT appear in intake queue
  813 |       await goto(page, `${BASE}/admin/intake-queues`);
  814 |       await lwWait(page, 1500);
  815 |       const intakeRow = page.locator('table tbody tr').filter({ hasText: new RegExp(RLAST, 'i') }).first();
  816 |       const inIntake  = await intakeRow.isVisible({ timeout: 4000 }).catch(() => false);
  817 | 
  818 |       if (!inIntake) {
  819 |         console.log('  ✅ Return client NOT in intake queue — routed to billing as expected');
  820 |       } else {
  821 |         console.log('  ℹ️  Return client still in intake queue (client_type may not be "returning" in DB)');
  822 |         console.log('  ℹ️  To fully test: update client_type="returning" in DB before triage');
  823 |       }
  824 |     }
  825 |   } else {
  826 |     console.log('ℹ️  Return client not in triage queue');
  827 |   }
  828 | 
  829 |   const body = await page.locator('body').textContent() ?? '';
  830 |   expect(checkPageErrors(body, page.url()).has500).toBeFalsy();
  831 |   console.log('✅ Stage 8 — Return client flow tested');
  832 | });
  833 | 
  834 | // ─────────────────────────────────────────────────────────────────────────
  835 | // STAGE 9 — Compliance: All Key Pages Load Without 500
  836 | // ─────────────────────────────────────────────────────────────────────────
  837 | test('Stage 9 — Compliance: All Key Pages', async ({ page }) => {
  838 |   await login(page);
  839 | 
  840 |   const pages: { label: string; url: string }[] = [
  841 |     { label: 'Dashboard',           url: '/admin' },
  842 |     { label: 'Clients',             url: '/admin/clients' },
  843 |     { label: 'Clients Create',      url: '/admin/clients/create' },
  844 |     { label: 'Triage Queue',        url: '/admin/triage-queues' },
  845 |     { label: 'Triages',             url: '/admin/triages' },
  846 |     { label: 'Triages Create',      url: '/admin/triages/create' },
  847 |     { label: 'Intake Queue',        url: '/admin/intake-queues' },
  848 |     { label: 'Intake Assessments',  url: '/admin/intake-assessments' },
  849 |     { label: 'Intake Create',       url: '/admin/intake-assessments/create' },
  850 |     { label: 'Billing',             url: '/admin/billings' },
  851 |     { label: 'Cashier Queue',       url: '/admin/cashier-queues' },
  852 |     { label: 'Service Queue',       url: '/admin/service-queues' },
  853 |     { label: 'Service Dashboard',   url: '/admin/service-point-dashboards' },
  854 |     { label: 'Visits',              url: '/admin/visits' },
  855 |     { label: 'Dynamic Assessments', url: '/admin/dynamic-assessments' },
  856 |     { label: 'Services',            url: '/admin/services' },
  857 |     { label: 'Departments',         url: '/admin/departments' },
  858 |     { label: 'Users',               url: '/admin/users' },
  859 |     { label: 'Branches',            url: '/admin/branches' },
  860 |     { label: 'Insurance Claims',    url: '/admin/insurance-claims' },
  861 |     { label: 'Payments',            url: '/admin/payments' },
  862 |   ];
  863 | 
  864 |   const results: { label: string; status: string; detail: string }[] = [];
  865 | 
  866 |   for (const p of pages) {
  867 |     try {
  868 |       await goto(page, `${BASE}${p.url}`);
  869 |       await page.waitForTimeout(1500);
  870 |     } catch { /* ERR_ABORTED handled by goto */ }
  871 | 
  872 |     const bodyText   = (await page.locator('body').textContent().catch(() => '')) ?? '';
  873 |     const currentUrl = page.url();
  874 |     const errs       = checkPageErrors(bodyText, currentUrl);
  875 | 
  876 |     let status: string;
  877 |     let detail: string;
  878 | 
  879 |     if (errs.has500)       { status = '❌ 500';   detail = 'Server error'; }
  880 |     else if (errs.isLogin) { status = '⚠️  AUTH';  detail = 'Session lost'; }
  881 |     else if (errs.has403)  { status = '⚠️  403';   detail = 'Forbidden'; }
  882 |     else if (errs.has404)  { status = '⚠️  404';   detail = 'Not Found'; }
  883 |     else                   { status = '✅ OK';     detail = currentUrl.replace(BASE, ''); }
  884 | 
  885 |     results.push({ label: p.label, status, detail });
  886 |     await snap(page, `09-${p.label.replace(/[^a-z0-9]/gi, '-').toLowerCase()}`);
  887 |   }
  888 | 
  889 |   // Print compliance report
  890 |   console.log('\n');
  891 |   console.log('╔══════════════════════════════════════════════════════════════╗');
  892 |   console.log('║         KISE HMIS — COMPLIANCE NAVIGATION REPORT             ║');
  893 |   console.log('╠══════════════════════════════════════════════════════════════╣');
  894 |   for (const r of results) {
  895 |     const lbl  = r.label.padEnd(22);
  896 |     const stat = r.status.padEnd(12);
  897 |     console.log(`║  ${stat} ${lbl} ${r.detail}`);
  898 |   }
  899 |   console.log('╚══════════════════════════════════════════════════════════════╝');
  900 | 
  901 |   const errors  = results.filter(r => r.status.includes('500'));
  902 |   const warns   = results.filter(r => r.status.includes('403') || r.status.includes('AUTH') || r.status.includes('404'));
  903 |   const passing = results.filter(r => r.status.includes('OK'));
  904 | 
  905 |   console.log(`\n  Summary:`);
  906 |   console.log(`  ✅ Passing : ${passing.length}/${results.length}`);
  907 |   console.log(`  ⚠️  Warnings: ${warns.length}  (403 / auth / 404)`);
  908 |   console.log(`  ❌ Errors  : ${errors.length}  (500 server errors)`);
  909 | 
  910 |   if (errors.length > 0) {
> 911 |     throw new Error(`Server errors detected on: ${errors.map(e => e.label).join(', ')}`);
      |           ^ Error: Server errors detected on: Intake Assessments, Billing, Service Queue, Departments, Payments
  912 |   }
  913 | 
  914 |   console.log('\n✅ Stage 9 — Compliance sweep COMPLETE');
  915 | });
  916 | 
```