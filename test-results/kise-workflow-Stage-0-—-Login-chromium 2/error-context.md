# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: kise-workflow.spec.ts >> Stage 0 — Login
- Location: tests/e2e/kise-workflow.spec.ts:32:1

# Error details

```
Error: apiRequestContext._wrapApiCall: file data stream has unexpected number of bytes
```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
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
              - textbox "Email address*" [ref=e72]: admin@kise.ac.ke
            - generic [ref=e75]:
              - generic [ref=e78]:
                - text: Password
                - superscript [ref=e79]: "*"
              - generic [ref=e81]:
                - textbox "Password*" [ref=e83]: password
                - button "Show password" [disabled] [ref=e86]:
                  - generic [ref=e87]: Show password
                  - img [ref=e88]
            - generic [ref=e95]:
              - checkbox "Remember me" [disabled]
              - generic [ref=e96]: Remember me
          - button "Sign in" [disabled] [ref=e99]:
            - img [ref=e100]
            - generic [ref=e103]: Sign in
        - generic [ref=e104]:
          - generic [ref=e105]: System Online
          - generic [ref=e107]: © 2026 Kenya Institute of Special Education
  - generic:
    - status
  - generic [ref=e108]:
    - generic [ref=e110]:
      - generic [ref=e112]:
        - generic [ref=e113] [cursor=pointer]:
          - text: 
          - generic: Request
        - text: 
        - generic [ref=e114] [cursor=pointer]:
          - text: 
          - generic: Timeline
        - text: 
        - generic [ref=e115] [cursor=pointer]:
          - text: 
          - generic: Views
          - generic [ref=e116]: "8"
        - generic [ref=e117] [cursor=pointer]:
          - text: 
          - generic: Queries
          - generic [ref=e118]: "3"
        - text: 
        - generic [ref=e119] [cursor=pointer]:
          - text: 
          - generic: Livewire
          - generic [ref=e120]: "2"
        - text:  
      - generic [ref=e121]:
        - generic [ref=e123] [cursor=pointer]:
          - generic: 
        - generic [ref=e126] [cursor=pointer]:
          - generic: 
        - generic [ref=e127] [cursor=pointer]:
          - generic: 
          - generic: 356ms
        - generic [ref=e128]:
          - generic: 
          - generic: 4MB
        - generic [ref=e129]:
          - generic: 
          - generic: 12.x
        - generic [ref=e130] [cursor=pointer]:
          - generic: 
          - generic: GET admin/login
    - text:                                                    
  - text: 
```