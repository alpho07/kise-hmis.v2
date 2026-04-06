# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: kise-workflow.spec.ts >> Stage 1 — Create New Client
- Location: tests/e2e/kise-workflow.spec.ts:47:1

# Error details

```
TimeoutError: locator.click: Timeout 20000ms exceeded.
Call log:
  - waiting for getByRole('button', { name: /Create|Save/i }).first()

```

```
Error: apiRequestContext._wrapApiCall: ENOENT: no such file or directory, open '/Users/alphy/Documents/kise-hmis.v2/test-results/.playwright-artifacts-0/traces/496d41c02016cf336985-09489b18ad5b1ba3156b-recording1.network'
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
          - generic: 686ms
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