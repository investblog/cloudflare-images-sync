# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| 1.0.x   | Yes                |
| < 1.0   | No                 |

## Reporting a Vulnerability

If you discover a security vulnerability in this plugin, please report it responsibly.

**Do not open a public GitHub issue for security vulnerabilities.**

### How to Report

Email: **security@301.st**

Please include:

- A description of the vulnerability
- Steps to reproduce the issue
- The affected version(s)
- Any potential impact you have identified

### What to Expect

- **Acknowledgment** within 48 hours of your report
- **Assessment** within 5 business days
- **Fix timeline** communicated after assessment
- **Credit** in the changelog (unless you prefer anonymity)

### Disclosure Policy

- We will not publicly disclose the issue until a fix is available
- We ask that you do not disclose the vulnerability publicly until we have had a reasonable opportunity to address it
- We will coordinate disclosure timing with you

## Security Measures

This plugin implements the following security practices:

- All admin actions require WordPress nonce verification
- All admin pages require `manage_options` capability
- API tokens are encrypted at rest using libsodium (AES-256-CBC fallback)
- User input is sanitized and validated before processing
- Database queries use prepared statements with `$wpdb->prepare()`
- Output is escaped using WordPress escaping functions
