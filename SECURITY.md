# Security Policy

## Supported versions

| Version | Supported          |
|---------|--------------------|
| 4.x     | :white_check_mark: |
| < 4.0   | :x:                |

## Reporting a vulnerability

Please **do not** report security vulnerabilities through public GitHub issues, discussions or pull requests.

Report them privately using GitHub's [Report a vulnerability](https://github.com/monicahq/laravel-cloudflare/security/advisories/new) form.

Please include:

- the affected version(s) of `monicahq/laravel-cloudflare`, and your Laravel and PHP versions;
- the relevant configuration (`config/laravelcloudflare.php`, trusted proxies setup);
- a description of the issue and its impact;
- steps to reproduce, or a proof of concept.

<!-- TODO(maintainer): confirm the expected response time before publishing, e.g. "You should receive a response within N days." -->

## Scope

Examples of issues we consider in scope:

- spoofing the client IP address seen by the application (for instance through `Cf-Connecting-Ip` or `X-Forwarded-*` headers);
- widening the set of trusted proxies beyond Cloudflare's published ranges and the proxies explicitly configured by the application;
- making the application unavailable through the download or caching of Cloudflare's IP ranges.

Hosting-level protections, such as restricting the origin server to Cloudflare traffic, are outside the package's control; see the *Security considerations* section of the [README](README.md).
