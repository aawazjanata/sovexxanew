# Sovexxa Society Management System

Sovexxa is a multi-society Society ERP/Management System built as a WordPress plugin.

Features included in this delivery:
- Background CSV bulk import with:
  - Client-side header mapping via PapaParse
  - Server-side chunked processing
  - Full failures CSV (original columns + reason + row number)
  - Retry failed rows (create retry job)
  - Action Scheduler detection/fallback to WP Cron
- Audit Log viewer with Undo for supported actions
- Admin UI for jobs, job progress, failures download, retry actions
- Roles: sovexxa_super_admin, sovexxa_society_admin, sovexxa_flat_resident
- Safe activation/deactivation and DB creation via dbDelta
- Nonce-protected AJAX endpoints and capability checks
- Email summary for job completion

Installation
1. Copy the plugin folder into `wp-content/plugins/sovexxa-society-management/`.
2. Activate the plugin from WordPress admin.
3. Ensure uploads folder is writable (for CSV storage).
4. Configure notifications (option `sovexxa_admin_notification_email`) by adding via Settings or calling update_option.

Notes
- Action Scheduler is detected if present; to bundle Action Scheduler into `includes/vendor/action-scheduler/` place the library files there.
- The plugin keeps failures files under `wp-content/uploads/sovexxa_bulk/`.
- The plugin will not delete production data on deactivation/uninstall unless you explicitly modify `uninstall.php`.

License
MIT / Proprietary as appropriate.
