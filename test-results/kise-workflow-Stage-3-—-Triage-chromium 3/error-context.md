# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: kise-workflow.spec.ts >> Stage 3 — Triage
- Location: tests/e2e/kise-workflow.spec.ts:165:1

# Error details

```
TimeoutError: locator.click: Timeout 20000ms exceeded.
Call log:
  - waiting for getByRole('button', { name: /Save|Create|Submit/i }).first()

```

# Page snapshot

```yaml
- generic [ref=e1]:
  - main [ref=e4]:
    - generic [ref=e5]:
      - generic [ref=e6]:
        - generic [ref=e7]:
          - generic [ref=e8]: K
          - generic [ref=e9]:
            - generic [ref=e10]: KISE HMIS
            - generic [ref=e11]: Health Management System
        - generic [ref=e12]:
          - heading "Kenya Institute of Special Education" [level=1] [ref=e13]:
            - text: Kenya Institute
            - text: of Special Education
          - paragraph [ref=e14]: Facilitating service provision for persons with disabilities and special needs through research, assessment and training — now digitised end-to-end.
        - generic [ref=e15]:
          - generic [ref=e16]:
            - generic [ref=e17]: 4+
            - generic [ref=e18]: Branches
          - generic [ref=e19]:
            - generic [ref=e20]: "7"
            - generic [ref=e21]: Clinical Stages
          - generic [ref=e22]:
            - generic [ref=e23]: 12+
            - generic [ref=e24]: Staff Roles
          - generic [ref=e25]:
            - generic [ref=e26]: UCI
            - generic [ref=e27]: Client ID System
        - generic [ref=e28]:
          - generic [ref=e29]: Visit Workflow Pipeline
          - generic [ref=e30]:
            - generic [ref=e31]: Reception
            - generic [ref=e32]: ›
            - generic [ref=e33]: Triage
            - generic [ref=e34]: ›
            - generic [ref=e35]: Intake
            - generic [ref=e36]: ›
            - generic [ref=e37]: Billing
            - generic [ref=e38]: ›
            - generic [ref=e39]: Payment
            - generic [ref=e40]: ›
            - generic [ref=e41]: Service
            - generic [ref=e42]: ›
            - generic [ref=e43]: Done
        - generic [ref=e44]:
          - generic [ref=e45]:
            - generic [ref=e46]: Mission
            - generic [ref=e47]: Facilitating service provision for persons with disabilities through research, assessment & training.
          - generic [ref=e48]:
            - generic [ref=e49]: This System
            - generic [ref=e50]: End-to-end digital workflow — reception, triage, clinical intake, billing, payment and service delivery.
          - generic [ref=e51]:
            - generic [ref=e52]: Security
            - generic [ref=e53]: Role-based access control. Branch-scoped data isolation. Full audit trail on every record.
      - generic [ref=e54]:
        - generic [ref=e55]:
          - generic [ref=e56]: K
          - generic [ref=e57]: KISE HMIS
        - generic [ref=e58]: Welcome back
        - generic [ref=e59]: Sign in to your account
        - generic [ref=e60]:
          - generic [ref=e61]:
            - generic [ref=e64]:
              - generic [ref=e67]:
                - text: Email address
                - superscript [ref=e68]: "*"
              - textbox "Email address*" [active] [ref=e72]
            - generic [ref=e75]:
              - generic [ref=e78]:
                - text: Password
                - superscript [ref=e79]: "*"
              - generic [ref=e81]:
                - textbox "Password*" [ref=e83]
                - button "Show password" [ref=e86] [cursor=pointer]:
                  - generic [ref=e87]: Show password
                  - img [ref=e88]
            - generic [ref=e95]:
              - checkbox "Remember me" [ref=e96]
              - generic [ref=e97]: Remember me
          - button "Sign in" [ref=e100] [cursor=pointer]:
            - generic [ref=e101]: Sign in
        - generic [ref=e102]:
          - generic [ref=e103]: System Online
          - generic [ref=e105]: © 2026 Kenya Institute of Special Education
  - generic:
    - status
  - generic [ref=e106]:
    - generic [ref=e108]:
      - generic [ref=e110]:
        - generic [ref=e111] [cursor=pointer]:
          - text: 
          - generic: Request
        - text: 
        - generic [ref=e112] [cursor=pointer]:
          - text: 
          - generic: Timeline
        - text: 
        - generic [ref=e113] [cursor=pointer]:
          - text: 
          - generic: Views
          - generic [ref=e114]: "8"
        - generic [ref=e115] [cursor=pointer]:
          - text: 
          - generic: Queries
          - generic [ref=e116]: "2"
        - text: 
        - generic [ref=e117] [cursor=pointer]:
          - text: 
          - generic: Livewire
          - generic [ref=e118]: "2"
        - text:  
      - generic [ref=e119]:
        - generic [ref=e121] [cursor=pointer]:
          - generic: 
        - generic [ref=e124] [cursor=pointer]:
          - generic: 
        - generic [ref=e125] [cursor=pointer]:
          - generic: 
          - generic: 295ms
        - generic [ref=e126]:
          - generic: 
          - generic: 4MB
        - generic [ref=e127]:
          - generic: 
          - generic: 12.x
        - generic [ref=e128] [cursor=pointer]:
          - generic: 
          - generic: GET admin/login
    - text:                                              
  - text: 
```

# Test source

```ts
  137 | 
  138 |     // Fill Visit Type in modal
  139 |     await filamentSelect(page, 'Visit Type', 'New Visit').catch(() => {});
  140 |     await page.waitForTimeout(300);
  141 |     await filamentSelect(page, 'Purpose of Visit', 'Assessment').catch(() => {});
  142 |     await page.waitForTimeout(300);
  143 | 
  144 |     // Confirm / submit
  145 |     const dialogConfirm = page.locator('[role="dialog"]')
  146 |       .getByRole('button', { name: /Confirm|Submit|Start/i }).last();
  147 |     if (await dialogConfirm.isVisible({ timeout: 3000 }).catch(() => false)) {
  148 |       await dialogConfirm.click();
  149 |       await lwWait(page, 2000);
  150 |     }
  151 |     await snap(page, '02-visit-started');
  152 |     console.log('  → Visit created, client should be in Triage queue');
  153 |   } else {
  154 |     console.log('ℹ️  No "Start Visit" button found on row');
  155 |   }
  156 | 
  157 |   const body = await page.locator('body').textContent() ?? '';
  158 |   expect(checkPageErrors(body, page.url()).has500).toBeFalsy();
  159 |   console.log('✅ Stage 2 — Reception complete');
  160 | });
  161 | 
  162 | // ─────────────────────────────────────────────────────────────────────────
  163 | // STAGE 3 — Triage Queue → Fill Triage Form
  164 | // ─────────────────────────────────────────────────────────────────────────
  165 | test('Stage 3 — Triage', async ({ page }) => {
  166 |   await login(page);
  167 |   await goto(page, `${BASE}/admin/triage-queues`);
  168 |   await lwWait(page, 1200);
  169 |   await snap(page, '03-triage-queue');
  170 | 
  171 |   const body0 = await page.locator('body').textContent() ?? '';
  172 |   expect(checkPageErrors(body0, page.url()).has500).toBeFalsy();
  173 | 
  174 |   // Find the client row in triage queue
  175 |   const row = page.locator('table tbody tr').filter({ hasText: new RegExp(LAST, 'i') }).first();
  176 |   const found = await row.isVisible({ timeout: 8000 }).catch(() => false);
  177 | 
  178 |   if (found) {
  179 |     console.log('  → Client found in Triage queue');
  180 |     // Click "Start Triage" — navigates to /admin/triages/create?visit={id}
  181 |     const startBtn = row.locator('a, button').filter({ hasText: /Start Triage|Triage/i }).first();
  182 |     if (await startBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
  183 |       await startBtn.click();
  184 |       await lwWait(page, 1200);
  185 |     } else {
  186 |       await row.locator('a').first().click();
  187 |       await lwWait(page, 1200);
  188 |     }
  189 |   } else {
  190 |     console.log('ℹ️  Client not in triage queue — going to triages/create');
  191 |     await goto(page, `${BASE}/admin/triages/create`);
  192 |     await lwWait(page);
  193 |   }
  194 | 
  195 |   // Capture visitId from URL (?visit=XXX)
  196 |   const triageUrl = page.url();
  197 |   const vm = triageUrl.match(/[?&]visit=(\d+)/);
  198 |   if (vm) {
  199 |     sharedVisitId = parseInt(vm[1]);
  200 |     console.log(`  → Visit ID captured: ${sharedVisitId}`);
  201 |   }
  202 | 
  203 |   await snap(page, '03-triage-form');
  204 | 
  205 |   // Fill vitals (wire:model selectors for reliability)
  206 |   const vitals: [string, string, string][] = [
  207 |     ['Temperature', 'temperature', '36.8'],
  208 |     ['Heart Rate', 'heart_rate', '78'],
  209 |     ['Respiratory Rate', 'respiratory_rate', '16'],
  210 |     ['Systolic', 'blood_pressure_systolic', '120'],
  211 |     ['Diastolic', 'blood_pressure_diastolic', '80'],
  212 |     ['SpO₂', 'oxygen_saturation', '98'],
  213 |     ['Weight', 'weight', '70'],
  214 |     ['Height', 'height', '175'],
  215 |   ];
  216 | 
  217 |   for (const [label, model, value] of vitals) {
  218 |     await fillInput(page, label, value, value, model);
  219 |     await page.waitForTimeout(80);
  220 |   }
  221 | 
  222 |   // Presenting complaint
  223 |   const notesEl = page.locator('textarea[wire\\:model*="notes"], textarea[wire\\:model\\.live*="notes"]').first();
  224 |   if (await notesEl.isVisible({ timeout: 2000 }).catch(() => false)) {
  225 |     await notesEl.fill('Recurring shoulder pain following RTA. Referred by hospital.');
  226 |   } else {
  227 |     await fillInput(page, 'Complaints', 'complaints', 'Recurring shoulder pain.', 'notes');
  228 |   }
  229 | 
  230 |   // Triage status — select "Stable"
  231 |   await filamentSelect(page, 'Triage Status', 'Stable').catch(() => {});
  232 |   await page.waitForTimeout(300);
  233 | 
  234 |   await snap(page, '03-triage-filled');
  235 | 
  236 |   // Save triage
> 237 |   await page.getByRole('button', { name: /Save|Create|Submit/i }).first().click();
      |                                                                           ^ TimeoutError: locator.click: Timeout 20000ms exceeded.
  238 |   await lwWait(page, 3000);
  239 |   await snap(page, '03-triage-saved');
  240 | 
  241 |   const body = await page.locator('body').textContent() ?? '';
  242 |   const errs = checkPageErrors(body, page.url());
  243 |   expect(errs.has500).toBeFalsy();
  244 |   console.log(`✅ Stage 3 — Triage saved. Next stage should be Intake. URL: ${page.url()}`);
  245 | });
  246 | 
  247 | // ─────────────────────────────────────────────────────────────────────────
  248 | // STAGE 4 — Intake Queue → Fill Intake Assessment
  249 | // ─────────────────────────────────────────────────────────────────────────
  250 | test('Stage 4 — Intake Assessment', async ({ page }) => {
  251 |   await login(page);
  252 |   await goto(page, `${BASE}/admin/intake-queues`);
  253 |   await lwWait(page, 1200);
  254 |   await snap(page, '04-intake-queue');
  255 | 
  256 |   const body0 = await page.locator('body').textContent() ?? '';
  257 |   expect(checkPageErrors(body0, page.url()).has500).toBeFalsy();
  258 | 
  259 |   // Try to find client in intake queue
  260 |   const row = page.locator('table tbody tr').filter({ hasText: new RegExp(LAST, 'i') }).first();
  261 |   const inQueue = await row.isVisible({ timeout: 8000 }).catch(() => false);
  262 | 
  263 |   let intakeUrl: string;
  264 | 
  265 |   if (inQueue) {
  266 |     console.log('  → Client found in Intake queue');
  267 |     // Click "Start Intake" (creates IntakeAssessment and navigates to editor)
  268 |     const startBtn = row.locator('button, a').filter({ hasText: /Start Intake|Intake/i }).first();
  269 |     if (await startBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
  270 |       await startBtn.click();
  271 |       await lwWait(page, 2000);
  272 |       await snap(page, '04-intake-form-launched');
  273 |     }
  274 |     intakeUrl = page.url();
  275 |   } else {
  276 |     console.log('ℹ️  Not in intake queue — using standard create form');
  277 |   }
  278 | 
  279 |   // Determine if we ended up on the editor or the standard create form
  280 |   const currentUrl = page.url();
  281 | 
  282 |   if (currentUrl.includes('intake-assessment-editor')) {
  283 |     // ── INTAKE ASSESSMENT EDITOR (multi-section) ──────────────────────
  284 |     console.log('  → On intake assessment editor');
  285 |     await snap(page, '04-editor-page');
  286 | 
  287 |     const editorBody = await page.locator('body').textContent() ?? '';
  288 |     expect(checkPageErrors(editorBody, currentUrl).has500).toBeFalsy();
  289 | 
  290 |     // The editor shows sections A-L in a sidebar
  291 |     // Section A is auto-completed. Let's try to save each section.
  292 |     // We'll navigate through available sections and save what we can.
  293 | 
  294 |     // Try clicking Section B (client identification)
  295 |     const sectionBBtn = page.locator('button').filter({ hasText: /^B[:\s—\-]|^B$/ }).first();
  296 |     if (await sectionBBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
  297 |       await sectionBBtn.click();
  298 |       await lwWait(page, 800);
  299 |       // Save section B
  300 |       const saveBBtn = page.locator('button').filter({ hasText: /Save.*Section|Save/i }).first();
  301 |       if (await saveBBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
  302 |         await saveBBtn.click();
  303 |         await lwWait(page, 1000);
  304 |       }
  305 |     }
  306 | 
  307 |     // Try to click through remaining sections and save each
  308 |     for (const section of ['C','D','E','F','G','H','I','J','K','L']) {
  309 |       const btn = page.locator('button').filter({ hasText: new RegExp(`^${section}[:\\s—\\-]|^${section}$`, 'i') }).first();
  310 |       if (await btn.isVisible({ timeout: 2000 }).catch(() => false)) {
  311 |         await btn.click();
  312 |         await lwWait(page, 600);
  313 | 
  314 |         // For section H — fill referral source and reason
  315 |         if (section === 'H') {
  316 |           // Check "Self / Family" checkbox
  317 |           const selfCheckbox = page.locator('label').filter({ hasText: /Self.*Family|Self\/Family/i }).first();
  318 |           if (await selfCheckbox.isVisible({ timeout: 2000 }).catch(() => false)) {
  319 |             await selfCheckbox.click();
  320 |             await page.waitForTimeout(300);
  321 |           }
  322 |           // Fill reason for visit
  323 |           await fillInput(page, 'Reason for Visit', 'Primary reason', 'Referred for physiotherapy post-RTA.', 'reason_for_visit');
  324 |           await page.waitForTimeout(200);
  325 |         }
  326 | 
  327 |         // For section I — select primary service
  328 |         if (section === 'I') {
  329 |           await filamentSelect(page, 'Primary Service', 'Physiotherapy').catch(() => {});
  330 |           await page.waitForTimeout(400);
  331 |         }
  332 | 
  333 |         // For section J — payment pathway
  334 |         if (section === 'J') {
  335 |           await filamentSelect(page, 'Payment', 'cash').catch(async () => {
  336 |             await filamentSelect(page, 'Payment Pathway', 'Cash').catch(() => {});
  337 |           });
```