# Sovexxa Society Management System — Developer Guide (continued)

This plugin is delivered incrementally. Developer helpers provided in this batch let you run tests, install optional PDF library, and create a distribution ZIP.

Optional dependencies
- DOMPDF (for PDF generation)
  - Install via composer in plugin root:
    composer require dompdf/dompdf
- PHPUnit for tests:
  - composer require --dev phpunit/phpunit

Quick setup (dev)
1. Clone plugin into wp-content/plugins/sovexxa-society-management
2. From plugin root, run:
   composer install
3. Activate plugin in WP admin.
4. Configure settings -> Sovexxa -> Job Notification Email and Chunk size.

Run tests (basic)
- vendor/bin/phpunit --configuration tests/phpunit.xml

Build distribution ZIP
- php tools/build-zip.php /path/to/plugin /path/to/output/sovexxa.zip

CI
- A GitHub Actions workflow (.github/workflows/ci.yml) is included to run php -l and phpunit on push/PR.

Notes & next steps
- DOMPDF is optional; if installed pdf helpers will work. If not, invoice HTML is returned for manual PDF conversion.
- This repository is still a work in progress. Many modules are skeletons to be extended with UI polish, advanced validation, and integration tests.
- For production: ensure WP Cron or Action Scheduler is available and configured; ensure uploads directory is writable.

Security
- All AJAX endpoints implemented so far use check_ajax_referer and capability checks. Review and harden remaining endpoints and front-end exposures before going to production.

If you want me to continue, I will:
- Finish remaining modules not yet implemented (full billing engine, receipts PDF generation + email/WhatsApp notifications).
- Complete resident front-end UX (shortcodes, templates).
- Add detailed integration tests and lint fixes.
- Produce final ZIP and the completion report with commit hashes, full file tree, test outputs, and installation/upgrade instructions.

Tell me to "continue" and I will produce the next batch (billing engine refinements, receipts PDF/email, WhatsApp notifications, more tests, final packaging and completion report).