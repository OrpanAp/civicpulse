# CivicPulse Security Posture and Review Checklist

**Purpose:** Record the security controls currently present in CivicPulse, distinguish them from unfinished work, and provide a practical review checklist.

**Important:** This document is a development status record, not a penetration-test report, independent security audit, compliance statement, or claim of production readiness. A feature being present in source code does not prove that its deployment configuration is secure.

## 1. Controls currently present in the codebase

The following controls were confirmed by inspecting the current `main` branch source. Test coverage and deployment verification must still be considered separately.

### HTTP response headers

The configurable security-header middleware currently sets:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=(self)`

The middleware decorates responses returned through the normal application pipeline. Do not assume that unhandled exceptions converted outside that pipeline receive these headers.

### Trusted proxy handling

- The trusted proxy list defaults to empty; forwarded metadata is not trusted by default.
- Forwarded client IP and scheme are considered only when the immediate peer is explicitly trusted.
- Forwarded host handling is disabled by default and requires explicit opt-in as well as a trusted immediate proxy.
- IP/CIDR validation supports IPv4 and IPv6.
- The current implementation handles selected `X-Forwarded-*` headers; it does not implement the standardized `Forwarded` header.
- Production proxy/load-balancer configuration has not been verified.

### Sessions

The current session manager uses PHP's native session handler and enforces:

- PHP strict session mode.
- Cookie-only session identifiers.
- URL-based session ID propagation disabled.
- `HttpOnly` enabled by default.
- `SameSite=Lax` by default.
- Secure-cookie behavior derived from the resolved HTTPS request unless explicitly configured true; `SameSite=None` is rejected unless the cookie is Secure.
- Session ownership and close/write handling through session middleware.

These controls do not by themselves provide idle or absolute session expiration, authentication, or authorization. Session ID regeneration is available through the session abstraction, but an authentication lifecycle that invokes it has not yet been implemented.

### CSRF protection

- Tokens are generated with 32 cryptographically random bytes and represented as 64 hexadecimal characters.
- Validation uses `hash_equals()`.
- `GET`, `HEAD`, and `OPTIONS` bypass token validation; other methods require an attached session and a valid token.
- The token may be supplied through the `X-CSRF-Token` header or the parsed form field `_token`.
- Missing, malformed, conflicting, or invalid tokens are rejected with HTTP 403 and `Cache-Control: no-store`.
- The front controller registers session middleware before CSRF middleware.

A separate token-rotation policy and Origin/Referer validation defense in depth have not yet been established. CSRF protection is not a substitute for authorization.

### View and stored HTML safety

- Ordinary template interpolation uses HTML escaping by default.
- The explicit `@build` path passes stored HTML through an allowlist-based DOM sanitizer.
- The sanitizer restricts elements, attributes, and URLs; removes dangerous element subtrees; validates UTF-8 and input size; and does not evaluate PHP or execute arbitrary stored code.
- Asset resolution restricts CSS and JavaScript files to configured public roots and validates logical paths.
- These protections do not make arbitrary application output or uploaded files safe.

## 2. Security work not yet implemented or not yet verified

Treat these as open work unless a later implementation and test record explicitly closes them:

- **Content Security Policy (CSP):** no CSP policy is currently configured.
- **HSTS and HTTPS enforcement:** no HSTS policy or application-level HTTPS redirect/enforcement has been established. TLS configuration is a deployment responsibility and has not been verified.
- **Authentication:** login, logout, password hashing and reset flows, account recovery, MFA, and account lifecycle controls are not yet implemented.
- **Authorization:** server-side permissions, policies, role-based access control, and resource ownership checks are not yet implemented.
- **Rate limiting:** application-level limits for login, sensitive actions, and public endpoints are not yet implemented.
- **Session expiration and lifecycle:** idle/absolute expiration and regeneration on authentication or privilege changes need implementation and tests.
- **CSRF hardening:** token rotation around authentication transitions and Origin/Referer validation need review.
- **Uploads:** secure upload validation, storage isolation, file-size limits, content inspection, and safe download behavior need a dedicated design before uploads are enabled.
- **Database deployment:** use a least-privilege database account, protect credentials, and verify production transport/network restrictions. Parameterized queries remain required for untrusted values.
- **Secrets and configuration:** keep secrets out of source control and logs; verify production environment permissions and rotation procedures.
- **Error handling and logging:** production responses must not expose stack traces or secrets; logs need appropriate redaction, access control, and retention.
- **Dependencies and operations:** dependency vulnerability review, patching, backups, restore testing, monitoring, and incident response procedures remain operational responsibilities.
- **Production verification:** no independent penetration test, production deployment review, or security certification is claimed.

## 3. Review checklist before production

- [ ] Enforce HTTPS at the deployment edge and verify the complete proxy chain.
- [ ] Define and test a restrictive CSP compatible with the actual application assets.
- [ ] Enable HSTS only after HTTPS is correctly deployed for the intended host scope.
- [ ] Implement authentication and rotate the session ID after login and privilege changes.
- [ ] Implement idle and absolute session expiration with tested invalidation behavior.
- [ ] Implement server-side authorization for every protected operation; never rely on hidden UI controls.
- [ ] Add rate limits and abuse controls to authentication and other sensitive endpoints.
- [ ] Complete CSRF lifecycle review, including token rotation and Origin/Referer defense in depth where appropriate.
- [ ] Design and test secure file upload and download handling before accepting attachments.
- [ ] Use least-privilege database credentials and protect all secrets.
- [ ] Verify production error handling, logging redaction, monitoring, backups, and restore procedures.
- [ ] Run the full automated test suite and targeted security tests in the deployment environment.
- [ ] Perform a threat-model review and arrange an independent security assessment before handling real user reports or sensitive data.

## 4. How to maintain this document

1. Mark a control as implemented only after its source, configuration, and relevant tests have been inspected.
2. Record whether verification was local, integration-level, or performed in the actual deployment environment.
3. Keep planned work unchecked. Do not convert a checklist item to complete merely because a class or configuration key exists.
4. Record known limitations and skipped environment-dependent tests.
5. Revisit this document whenever authentication, authorization, uploads, public APIs, or deployment architecture changes.

For the detailed development history and milestone evidence, see [`context.md`](context.md).
