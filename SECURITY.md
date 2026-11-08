# Web Security Quick-Reference Checklist

## 1. Client-Side & Front-End Security
*   **Content Security Policy (CSP):** Implement strict CSP headers to restrict where scripts, styles, and resources can be loaded from (Mitigates XSS).
*   **Input Sanitization:** Sanitize and validate all user inputs on both front-end and back-end before rendering or processing them.
*   **Secure Cookies:** Always use `Secure`, `HttpOnly`, and `SameSite=Strict/Lax` flags for session cookies.

## 2. Network & Data Transit
*   **Enforce HTTPS:** Use TLS 1.3 (or 1.2 minimum) and implement HTTP Strict Transport Security (HSTS) headers.
*   **API Security:** Secure all endpoints with proper authorization headers (e.g., OAuth2, JWT) and enforce strict Rate Limiting to prevent Brute-Force/DDoS attacks.

## 3. Server-Side & Database Security
*   **Parameterized Queries:** Use prepared statements/ORMs to eliminate SQL Injection (SQLi) vulnerabilities.
*   **Least Privilege:** Ensure the database user and application processes run with the minimum necessary permissions.
*   **Dependency Management:** Regularly scan and update third-party packages (e.g., using `npm audit`, `Snyk`, or GitHub Dependabot) to patch vulnerable components.

## 4. Access Control & Configuration
*   **Authentication:** Enforce strong password policies and Multi-Factor Authentication (MFA/2FA) wherever possible.
*   **Broken Access Control:** Implement server-side role-based checks (RBAC) on every API request. Never trust client-side state for authorization.
*   **Disable Debug Mode:** Turn off stack traces, debug logs, and detailed error messages in production environments to prevent information disclosure.
