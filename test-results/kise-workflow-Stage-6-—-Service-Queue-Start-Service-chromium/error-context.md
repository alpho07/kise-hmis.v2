# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: kise-workflow.spec.ts >> Stage 6 — Service Queue: Start Service
- Location: tests/e2e/kise-workflow.spec.ts:564:1

# Error details

```
Error: Service queue returned 500
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
        - heading "TypeError" [level=1] [ref=e20]
        - generic [ref=e22]: vendor/filament/tables/src/Table/Concerns/HasRecords.php:76
        - paragraph [ref=e23]: Cannot use "::class" on null
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
        - generic [ref=e36]: CODE 0
      - generic [ref=e38]:
        - generic [ref=e39]:
          - img [ref=e40]
          - text: "500"
        - generic [ref=e43]:
          - img [ref=e44]
          - text: GET
        - generic [ref=e47]: http://127.0.0.1:8000/admin/service-queues
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
            - generic [ref=e68]: 80 vendor frames
            - button [ref=e69]:
              - img [ref=e70]
          - generic [ref=e74]:
            - generic [ref=e75] [cursor=pointer]:
              - generic [ref=e78]:
                - code [ref=e82]:
                  - generic [ref=e83]: public/index.php
                - generic [ref=e85]: public/index.php:20
              - button [ref=e87]:
                - img [ref=e88]
            - code [ref=e96]:
              - generic [ref=e97]: "15"
              - generic [ref=e98]: 16// Bootstrap Laravel and handle the request...
              - generic [ref=e99]: 17/** @var Application $app */
              - generic [ref=e100]: 18$app = require_once __DIR__.'/../bootstrap/app.php';
              - generic [ref=e101]: "19"
              - generic [ref=e102]: 20$app->handleRequest(Request::capture());
              - generic [ref=e103]: "21"
          - generic [ref=e105] [cursor=pointer]:
            - img [ref=e106]
            - generic [ref=e110]: 1 vendor frame
            - button [ref=e111]:
              - img [ref=e112]
      - generic [ref=e116]:
        - generic [ref=e117]:
          - generic [ref=e118]:
            - img [ref=e120]
            - heading "Queries" [level=3] [ref=e122]
          - generic [ref=e124]: 1-7 of 7
        - generic [ref=e125]:
          - generic [ref=e126]:
            - generic [ref=e127]:
              - generic [ref=e128]:
                - img [ref=e129]
                - generic [ref=e131]: mysql
              - code [ref=e135]:
                - generic [ref=e136]: "select * from `sessions` where `id` = '5ezlT1mUnxp9o7WnwmLFOJ96aVkgTnBGvqdugEHz' limit 1"
            - generic [ref=e137]: 2.93ms
          - generic [ref=e138]:
            - generic [ref=e139]:
              - generic [ref=e140]:
                - img [ref=e141]
                - generic [ref=e143]: mysql
              - code [ref=e147]:
                - generic [ref=e148]: "select * from `users` where `id` = 1 limit 1"
            - generic [ref=e149]: 1.54ms
          - generic [ref=e150]:
            - generic [ref=e151]:
              - generic [ref=e152]:
                - img [ref=e153]
                - generic [ref=e155]: mysql
              - code [ref=e159]:
                - generic [ref=e160]: "select * from `cache` where `key` in ('kise-hmis-cache-spatie.permission.cache')"
            - generic [ref=e161]: 0.83ms
          - generic [ref=e162]:
            - generic [ref=e163]:
              - generic [ref=e164]:
                - img [ref=e165]
                - generic [ref=e167]: mysql
              - code [ref=e171]:
                - generic [ref=e172]: "select `permissions`.*, `model_has_permissions`.`model_id` as `pivot_model_id`, `model_has_permissions`.`permission_id` as `pivot_permission_id`, `model_has_permissions`.`model_type` as `pivot_model_type` from `permissions` inner join `model_has_permissions` on `permissions`.`id` = `model_has_permissions`.`permission_id` where `model_has_permissions`.`model_id` in (1) and `model_has_permissions`.`model_type` = 'App\\Models\\User'"
            - generic [ref=e173]: 1.03ms
          - generic [ref=e174]:
            - generic [ref=e175]:
              - generic [ref=e176]:
                - img [ref=e177]
                - generic [ref=e179]: mysql
              - code [ref=e183]:
                - generic [ref=e184]: "select `roles`.*, `model_has_roles`.`model_id` as `pivot_model_id`, `model_has_roles`.`role_id` as `pivot_role_id`, `model_has_roles`.`model_type` as `pivot_model_type` from `roles` inner join `model_has_roles` on `roles`.`id` = `model_has_roles`.`role_id` where `model_has_roles`.`model_id` in (1) and `model_has_roles`.`model_type` = 'App\\Models\\User'"
            - generic [ref=e185]: 1.81ms
          - generic [ref=e186]:
            - generic [ref=e187]:
              - generic [ref=e188]:
                - img [ref=e189]
                - generic [ref=e191]: mysql
              - code [ref=e195]:
                - generic [ref=e196]: "select `name`, `id` from `services` where `is_active` = 1 and `services`.`deleted_at` is null"
            - generic [ref=e197]: 4.4ms
          - generic [ref=e198]:
            - generic [ref=e199]:
              - generic [ref=e200]:
                - img [ref=e201]
                - generic [ref=e203]: mysql
              - code [ref=e207]:
                - generic [ref=e208]: "select `name`, `id` from `users` where exists (select * from `roles` inner join `model_has_roles` on `roles`.`id` = `model_has_roles`.`role_id` where `users`.`id` = `model_has_roles`.`model_id` and `model_has_roles`.`model_type` = 'App\\Models\\User' and `name` = 'service_provider')"
            - generic [ref=e209]: 1.68ms
    - generic [ref=e211]:
      - generic [ref=e212]:
        - heading "Headers" [level=2] [ref=e213]
        - generic [ref=e214]:
          - generic [ref=e215]:
            - generic [ref=e216]: host
            - generic [ref=e218]: 127.0.0.1:8000
          - generic [ref=e219]:
            - generic [ref=e220]: connection
            - generic [ref=e222]: keep-alive
          - generic [ref=e223]:
            - generic [ref=e224]: sec-ch-ua
            - generic [ref=e226]: "\"HeadlessChrome\";v=\"147\", \"Not.A/Brand\";v=\"8\", \"Chromium\";v=\"147\""
          - generic [ref=e227]:
            - generic [ref=e228]: sec-ch-ua-mobile
            - generic [ref=e230]: "?0"
          - generic [ref=e231]:
            - generic [ref=e232]: sec-ch-ua-platform
            - generic [ref=e234]: "\"Windows\""
          - generic [ref=e235]:
            - generic [ref=e236]: upgrade-insecure-requests
            - generic [ref=e238]: "1"
          - generic [ref=e239]:
            - generic [ref=e240]: user-agent
            - generic [ref=e242]: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.7727.15 Safari/537.36
          - generic [ref=e243]:
            - generic [ref=e244]: accept-language
            - generic [ref=e246]: en-US
          - generic [ref=e247]:
            - generic [ref=e248]: accept
            - generic [ref=e250]: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7
          - generic [ref=e251]:
            - generic [ref=e252]: sec-fetch-site
            - generic [ref=e254]: none
          - generic [ref=e255]:
            - generic [ref=e256]: sec-fetch-mode
            - generic [ref=e258]: navigate
          - generic [ref=e259]:
            - generic [ref=e260]: sec-fetch-user
            - generic [ref=e262]: "?1"
          - generic [ref=e263]:
            - generic [ref=e264]: sec-fetch-dest
            - generic [ref=e266]: document
          - generic [ref=e267]:
            - generic [ref=e268]: accept-encoding
            - generic [ref=e270]: gzip, deflate, br, zstd
          - generic [ref=e271]:
            - generic [ref=e272]: cookie
            - generic [ref=e274]: XSRF-TOKEN=eyJpdiI6IjdKZG10VThSUkNYOHFpbzFzN1VjSWc9PSIsInZhbHVlIjoiNTI4eHBOcTJNUSt0azF6R1JrY0tNZDUwWURld3d4dUd6N1RYWjE1NHlhcnZaRnpyOFBXQVhPZ3BUWGY4T3M4SXZYRjJUUDBHVVNZYTdMSG5uZDNNZ1JjRXhLbm5UN3IwMUxRWG9KRnRvSTJ5dU9ZaTdKS2RvampWcGdGN084eGIiLCJtYWMiOiI2ZjBhMzMwNTAxMjI3ZmNhZTVkYjcxNDhjMGZkYTk4MTVhZDUxNzFlNzc3NGY1YmY2NTViNzYxY2M0ZjcwODI2IiwidGFnIjoiIn0%3D; kise-hmis-session=eyJpdiI6ImltYjhVaUVaVUxGQmZ2T1hHS1grTnc9PSIsInZhbHVlIjoiVVhxWEFNU2JUQ2xPQ0hMUlBPZzAwZXBRMlN0eVYrdXo3L2dDNDRlOUtSKzFrNVl3d2ZQemRoVjRtTjlDYTdJY29JL1ZVT0p6Ry9qd3lJQTNYWWxKYWlKSnVWWHZ4QWpQUGt6WHRVZHZwNmtKMk1xUXprR0FUak5wWUJUMDh2ZlAiLCJtYWMiOiI4ZTY4NGUzMzc2NDQ1NTYxODNiMDViMDU2MTgxZjE4YjVkYTc0MzBjMjg0YmY4YTYwOTJiYWM1NzU4MjU1ODkyIiwidGFnIjoiIn0%3D
      - generic [ref=e275]:
        - heading "Body" [level=2] [ref=e276]
        - generic [ref=e277]: // No request body
      - generic [ref=e278]:
        - heading "Routing" [level=2] [ref=e279]
        - generic [ref=e280]:
          - generic [ref=e281]:
            - generic [ref=e282]: controller
            - generic [ref=e284]: App\Filament\Resources\ServiceQueueResource\Pages\ListServiceQueues
          - generic [ref=e285]:
            - generic [ref=e286]: route name
            - generic [ref=e288]: filament.admin.resources.service-queues.index
          - generic [ref=e289]:
            - generic [ref=e290]: middleware
            - generic [ref=e292]: panel:admin, Illuminate\Cookie\Middleware\EncryptCookies, Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse, Illuminate\Session\Middleware\StartSession, Filament\Http\Middleware\AuthenticateSession, Illuminate\View\Middleware\ShareErrorsFromSession, Illuminate\Foundation\Http\Middleware\VerifyCsrfToken, Illuminate\Routing\Middleware\SubstituteBindings, Filament\Http\Middleware\DisableBladeIconComponents, Filament\Http\Middleware\DispatchServingFilamentEvent, Filament\Http\Middleware\Authenticate
      - generic [ref=e293]:
        - heading "Routing parameters" [level=2] [ref=e294]
        - generic [ref=e295]: // No routing parameters
    - generic [ref=e298]:
      - img [ref=e300]
      - img [ref=e302]
  - generic [ref=e304]:
    - generic [ref=e306]:
      - generic [ref=e308]:
        - generic [ref=e309] [cursor=pointer]:
          - text: 
          - generic: Request
          - generic [ref=e310]: 500 Internal Server Error
        - generic [ref=e311] [cursor=pointer]:
          - text: 
          - generic: Messages
          - generic [ref=e312]: "1"
        - generic [ref=e313] [cursor=pointer]:
          - text: 
          - generic: Timeline
        - generic [ref=e314] [cursor=pointer]:
          - text: 
          - generic: Exceptions
          - generic [ref=e315]: "1"
        - generic [ref=e316] [cursor=pointer]:
          - text: 
          - generic: Views
          - generic [ref=e317]: "399"
        - generic [ref=e318] [cursor=pointer]:
          - text: 
          - generic: Queries
          - generic [ref=e319]: "8"
        - generic [ref=e320] [cursor=pointer]:
          - text: 
          - generic: Models
          - generic [ref=e321]: "2"
        - text:  
        - generic [ref=e322] [cursor=pointer]:
          - text: 
          - generic: Gate
          - generic [ref=e323]: "8"
      - generic [ref=e324]:
        - generic [ref=e326] [cursor=pointer]:
          - generic: 
        - generic [ref=e329] [cursor=pointer]:
          - generic: 
        - generic [ref=e330] [cursor=pointer]:
          - generic: 
          - generic: 647ms
        - generic [ref=e331]:
          - generic: 
          - generic: 12MB
        - generic [ref=e332]:
          - generic: 
          - generic: 12.x
        - generic [ref=e333] [cursor=pointer]:
          - generic: 
          - generic: GET admin/service-queues
    - text:                                                                                                                                                                                              
  - text: 
```

# Test source

```ts
  472 |         const cashLabel = page.locator('label').filter({ hasText: /Cash.*Counter|Counter.*Payment/i }).first();
  473 |         if (await cashLabel.isVisible({ timeout: 2000 }).catch(() => false)) {
  474 |           await cashLabel.click();
  475 |         }
  476 |       }
  477 |     } catch { /* Payment optional */ }
  478 |     await page.waitForTimeout(300);
  479 | 
  480 |     // Section L — Assessment Summary
  481 |     await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  482 |     await page.waitForTimeout(500);
  483 |     await fillInput(page, 'Intake Summary', 'Overall summary', 'Client requires physiotherapy for shoulder rehabilitation. Assessment completed.', 'assessment_summary');
  484 |     await page.waitForTimeout(200);
  485 | 
  486 |     await snap(page, '04-form-filled');
  487 | 
  488 |     // Save
  489 |     const saveBtn = page.getByRole('button', { name: /Save|Create|Submit/i }).first();
  490 |     await saveBtn.click();
  491 |     await lwWait(page, 4000);
  492 |     await snap(page, '04-saved');
  493 |   }
  494 | 
  495 |   const finalBody = await page.locator('body').textContent() ?? '';
  496 |   expect(checkPageErrors(finalBody, page.url()).has500).toBeFalsy();
  497 |   console.log(`✅ Stage 4 — Intake done. URL: ${page.url()}`);
  498 | });
  499 | 
  500 | // ─────────────────────────────────────────────────────────────────────────
  501 | // STAGE 5 — Cashier Queue: Process Payment
  502 | // ─────────────────────────────────────────────────────────────────────────
  503 | test('Stage 5 — Cashier: Process Payment', async ({ page }) => {
  504 |   await login(page);
  505 |   await goto(page, `${BASE}/admin/cashier-queues`);
  506 |   await lwWait(page, 1200);
  507 |   await snap(page, '05-cashier-queue');
  508 | 
  509 |   const body = await page.locator('body').textContent() ?? '';
  510 |   const errs = checkPageErrors(body, page.url());
  511 |   if (errs.has500) throw new Error('Cashier queue returned 500');
  512 |   if (errs.has403) {
  513 |     console.log('⚠️  Cashier queue: 403 for this role');
  514 |     return;
  515 |   }
  516 | 
  517 |   // Find client in cashier queue
  518 |   const row = page.locator('table tbody tr').filter({ hasText: new RegExp(LAST, 'i') }).first();
  519 |   const found = await row.isVisible({ timeout: 6000 }).catch(() => false);
  520 | 
  521 |   if (!found) {
  522 |     console.log('ℹ️  Client not in cashier queue yet (intake may have routed to billing admin first)');
  523 |     await snap(page, '05-cashier-empty');
  524 |     console.log('✅ Stage 5 — Cashier queue checked (client not yet here)');
  525 |     return;
  526 |   }
  527 | 
  528 |   console.log('  → Client found in Cashier queue');
  529 |   await snap(page, '05-client-in-cashier');
  530 | 
  531 |   // Click "Process Payment"
  532 |   const payBtn = row.locator('button').filter({ hasText: /Process Payment|Pay/i }).first();
  533 |   if (await payBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
  534 |     await payBtn.click();
  535 |     await lwWait(page, 1000);
  536 |     await snap(page, '05-payment-modal');
  537 | 
  538 |     // Select cash payment method
  539 |     await filamentSelect(page, 'Payment Method', 'Cash');
  540 |     await page.waitForTimeout(400);
  541 | 
  542 |     // Confirm payment
  543 |     const confirmBtn = page.locator('[role="dialog"]')
  544 |       .getByRole('button', { name: /Confirm|Submit|Process/i }).last();
  545 |     if (await confirmBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
  546 |       await confirmBtn.click();
  547 |       await lwWait(page, 2500);
  548 |     }
  549 |     await snap(page, '05-payment-done');
  550 |     console.log('  → Payment processed');
  551 |   } else {
  552 |     console.log('ℹ️  No "Process Payment" button visible');
  553 |     await row.locator('a, button').first().click();
  554 |     await lwWait(page);
  555 |     await snap(page, '05-cashier-detail');
  556 |   }
  557 | 
  558 |   console.log('✅ Stage 5 — Cashier complete');
  559 | });
  560 | 
  561 | // ─────────────────────────────────────────────────────────────────────────
  562 | // STAGE 6 — Service Queue: Start Service
  563 | // ─────────────────────────────────────────────────────────────────────────
  564 | test('Stage 6 — Service Queue: Start Service', async ({ page }) => {
  565 |   await login(page);
  566 |   await goto(page, `${BASE}/admin/service-queues`);
  567 |   await lwWait(page, 1500);
  568 |   await snap(page, '06-service-queue');
  569 | 
  570 |   const body = await page.locator('body').textContent() ?? '';
  571 |   const errs = checkPageErrors(body, page.url());
> 572 |   if (errs.has500) throw new Error('Service queue returned 500');
      |                          ^ Error: Service queue returned 500
  573 |   if (errs.has403) {
  574 |     console.log('⚠️  Service queue: 403');
  575 |     return;
  576 |   }
  577 | 
  578 |   const row = page.locator('table tbody tr').filter({ hasText: new RegExp(LAST, 'i') }).first();
  579 |   const found = await row.isVisible({ timeout: 6000 }).catch(() => false);
  580 | 
  581 |   if (!found) {
  582 |     console.log('ℹ️  Client not in service queue yet');
  583 |     console.log('✅ Stage 6 — Service queue checked');
  584 |     return;
  585 |   }
  586 | 
  587 |   console.log('  → Client found in Service queue');
  588 |   await snap(page, '06-client-in-service');
  589 | 
  590 |   // Click "Start Service"
  591 |   const startBtn = row.locator('button').filter({ hasText: /Start Service/i }).first();
  592 |   if (await startBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
  593 |     await startBtn.click();
  594 |     await lwWait(page, 800);
  595 |     await snap(page, '06-start-service-modal');
  596 | 
  597 |     // Select service provider (default is the current user)
  598 |     const confirmBtn = page.locator('[role="dialog"]')
  599 |       .getByRole('button', { name: /Confirm|Start/i }).last();
  600 |     if (await confirmBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
  601 |       await confirmBtn.click();
  602 |       await lwWait(page, 2000);
  603 |     }
  604 |     await snap(page, '06-service-started');
  605 |     console.log('  → Service started');
  606 |   } else {
  607 |     // Maybe already in_service — try Open Hub
  608 |     const hubBtn = row.locator('a, button').filter({ hasText: /Hub|Open/i }).first();
  609 |     if (await hubBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
  610 |       await hubBtn.click();
  611 |       await lwWait(page, 1500);
  612 |       await snap(page, '06-specialist-hub');
  613 |       const hubBody = await page.locator('body').textContent() ?? '';
  614 |       expect(checkPageErrors(hubBody, page.url()).has500).toBeFalsy();
  615 |       console.log('  → Specialist Hub opened');
  616 |       // Go back to service queue
  617 |       await page.goBack();
  618 |       await lwWait(page, 1000);
  619 |     }
  620 |   }
  621 | 
  622 |   console.log('✅ Stage 6 — Service queue complete');
  623 | });
  624 | 
  625 | // ─────────────────────────────────────────────────────────────────────────
  626 | // STAGE 7 — Service Delivery: Complete & Exit
  627 | // ─────────────────────────────────────────────────────────────────────────
  628 | test('Stage 7 — Service Delivery: Complete & Exit', async ({ page }) => {
  629 |   await login(page);
  630 |   await goto(page, `${BASE}/admin/service-queues`);
  631 |   await lwWait(page, 1500);
  632 | 
  633 |   const body0 = await page.locator('body').textContent() ?? '';
  634 |   expect(checkPageErrors(body0, page.url()).has500).toBeFalsy();
  635 | 
  636 |   const row = page.locator('table tbody tr').filter({ hasText: new RegExp(LAST, 'i') }).first();
  637 |   if (await row.isVisible({ timeout: 6000 }).catch(() => false)) {
  638 |     // Click "Complete" (visible when status = in_service)
  639 |     const completeBtn = row.locator('button').filter({ hasText: /Complete/i }).first();
  640 |     if (await completeBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
  641 |       await completeBtn.click();
  642 |       await lwWait(page, 800);
  643 |       await snap(page, '07-complete-modal');
  644 | 
  645 |       // Fill completion notes (optional)
  646 |       const notesArea = page.locator('[role="dialog"] textarea').first();
  647 |       if (await notesArea.isVisible({ timeout: 2000 }).catch(() => false)) {
  648 |         await notesArea.fill('Physiotherapy session completed. Client advised on home exercises.');
  649 |       }
  650 | 
  651 |       const confirmBtn = page.locator('[role="dialog"]')
  652 |         .getByRole('button', { name: /Confirm|Complete/i }).last();
  653 |       if (await confirmBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
  654 |         await confirmBtn.click();
  655 |         await lwWait(page, 2500);
  656 |       }
  657 |       await snap(page, '07-service-completed');
  658 |       console.log('  → Service completed and client exited');
  659 |     } else {
  660 |       // Try "Start Service" first if not yet started
  661 |       const startBtn = row.locator('button').filter({ hasText: /Start Service/i }).first();
  662 |       if (await startBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
  663 |         await startBtn.click();
  664 |         await lwWait(page, 800);
  665 |         const confirmStart = page.locator('[role="dialog"]').getByRole('button', { name: /Confirm|Start/i }).last();
  666 |         if (await confirmStart.isVisible({ timeout: 3000 }).catch(() => false)) {
  667 |           await confirmStart.click();
  668 |           await lwWait(page, 2000);
  669 |         }
  670 |         // Now click Complete
  671 |         await row.locator('button').filter({ hasText: /Complete/i }).first().click().catch(() => {});
  672 |         await lwWait(page, 800);
```