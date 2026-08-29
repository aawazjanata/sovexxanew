(function($){
    $(document).ready(function(){

        // Select all admins checkbox
        $(document).on('change', '#sovexxa-select-all-admins', function(){
            $('.sovexxa-admin-checkbox').prop('checked', $(this).is(':checked'));
        });

        // Single unassign (button per-row)
        $(document).on('click', '.sovexxa-unassign-admin', function(e){
            e.preventDefault();
            if (!confirm('Are you sure you want to unassign this user as Society Admin?')) {
                return;
            }
            var user_id = $(this).data('userid');
            $.post(sovexxa_admin.ajax_url, {
                action: 'sovexxa_unassign_society_admin',
                nonce: sovexxa_admin.mapping_nonce,
                user_id: user_id
            }, function(res){
                if (res.success) {
                    alert('User unassigned.');
                    location.reload();
                } else {
                    alert('Error: ' + (res.data && res.data.message ? res.data.message : 'Unknown'));
                }
            });
        });

        // Bulk Unassign selected admins
        $('#sovexxa-bulk-unassign-btn').on('click', function(e){
            e.preventDefault();
            var ids = [];
            $('.sovexxa-admin-checkbox:checked').each(function(){
                ids.push($(this).val());
            });
            if (ids.length === 0) {
                alert('Please select at least one admin to unassign.');
                return;
            }
            if (!confirm('Are you sure you want to unassign the selected admins?')) {
                return;
            }
            $.post(sovexxa_admin.ajax_url, {
                action: 'sovexxa_unassign_society_admin',
                nonce: sovexxa_admin.mapping_nonce,
                user_ids: ids
            }, function(res){
                if (res.success) {
                    var summary = res.data;
                    alert('Bulk unassign completed. Successes: ' + summary.successes.length + ', Failures: ' + summary.failures.length );
                    location.reload();
                } else {
                    alert('Error: ' + (res.data && res.data.message ? res.data.message : 'Unknown'));
                }
            });
        });

        // PapaParse header detection
        $('#sovexxa-csv-file').on('change', function(e){
            var file = this.files[0];
            var area = $('#sovexxa-csv-headers-area');
            area.empty();
            if (!file) {
                area.html('<p class="description">No file selected.</p>');
                return;
            }
            if (typeof Papa === 'undefined') {
                area.html('<p class="description">CSV parser not loaded.</p>');
                return;
            }
            Papa.parse(file, {
                preview: 1,
                header: false,
                skipEmptyLines: true,
                complete: function(results) {
                    var data = results && results.data && results.data[0] ? results.data[0] : null;
                    if (!data) {
                        area.html('<p class="description">No headers detected.</p>');
                        return;
                    }
                    var headers = Array.isArray(data) ? data : Object.keys(data);
                    renderHeaderMapping(headers);
                },
                error: function(err) {
                    area.html('<p class="description">Failed to parse CSV header: ' + err.message + '</p>');
                }
            });
        });

        function renderHeaderMapping(headers) {
            var area = $('#sovexxa-csv-headers-area');
            var html = '<p class="description">Detected CSV headers. Map them to Sovexxa fields:</p>';
            html += '<table class="form-table">';
            var fields = [
                { key: 'user_email', label: 'User Email (preferred)' },
                { key: 'user_login', label: 'User Login (optional)' },
                { key: 'society_id', label: 'Society ID (required)' },
                { key: 'flat_id', label: 'Flat ID (required)' },
                { key: 'member_id', label: 'Member ID (optional)' },
                { key: 'create_user', label: 'Create User if not exist (optional, values: 1/yes)' }
            ];
            fields.forEach(function(f){
                html += '<tr><th style="text-align:left;"><label>' + f.label + '</label></th><td>';
                html += '<select class="sovexxa-csv-map" data-field="' + f.key + '">';
                html += '<option value="">' + '(not mapped)' + '</option>';
                headers.forEach(function(h){
                    html += '<option value="' + h + '">' + h + '</option>';
                });
                html += '</select>';
                html += '</td></tr>';
            });
            html += '</table>';
            area.html(html);
        }

        // Upload file + mapping -> create job
        $('#sovexxa-bulk-import-btn').on('click', function(e){
            e.preventDefault();
            var fileInput = $('#sovexxa-csv-file')[0];
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                alert('Please select a CSV file.');
                return;
            }
            var mapping = {};
            $('.sovexxa-csv-map').each(function(){
                var field = $(this).data('field');
                var header = $(this).val();
                if (header) {
                    mapping[field] = header;
                }
            });
            if ( ! mapping['society_id'] || ! mapping['flat_id'] || ( ! mapping['user_email'] && ! mapping['user_login'] ) ) {
                if (!confirm('Required mappings (society_id, flat_id, and user_email OR user_login) are not fully set. Continue?')) {
                    return;
                }
            }
            var fd = new FormData();
            fd.append('action', 'sovexxa_upload_bulk_csv');
            fd.append('nonce', sovexxa_admin.mapping_nonce);
            fd.append('csv_file', fileInput.files[0]);
            fd.append('mapping', JSON.stringify(mapping));
            $('#sovexxa-bulk-import-result').html('<p>Uploading and scheduling background job...</p>');
            $.ajax({
                url: sovexxa_admin.ajax_url,
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                success: function(res) {
                    if (res.success) {
                        var job_id = res.data.job_id;
                        $('#sovexxa-bulk-import-result').html('<p>Job scheduled (ID: ' + job_id + '). Processing in background. Showing progress...</p><div id="sovexxa-job-progress"></div>');
                        pollJobProgress(job_id);
                    } else {
                        $('#sovexxa-bulk-import-result').html('<div class="error">Error: ' + (res.data && res.data.message ? res.data.message : 'Unknown') + '</div>');
                    }
                },
                error: function() {
                    $('#sovexxa-bulk-import-result').html('<div class="error">Upload failed.</div>');
                }
            });
        });

        function pollJobProgress(job_id) {
            var $progress = $('#sovexxa-job-progress');
            if (!$progress.length) return;
            $progress.html('<p>Checking status...</p>');
            var interval = setInterval(function(){
                $.post(sovexxa_admin.ajax_url, {
                    action: 'sovexxa_bulk_job_status',
                    nonce: sovexxa_admin.mapping_nonce,
                    job_id: job_id
                }, function(res){
                    if (!res || !res.success) {
                        $progress.html('<div class="error">Failed to get job status.</div>');
                        clearInterval(interval);
                        return;
                    }
                    var job = res.data;
                    var percent = job.total_rows > 0 ? Math.round( ( job.processed_rows / job.total_rows ) * 100 ) : 0;
                    var html = '<p>Status: ' + job.status + ' | Processed: ' + job.processed_rows + ' / ' + job.total_rows + ' (' + percent + '%)</p>';
                    html += '<p>Successes: ' + job.successes_count + ' Failures: ' + job.failures_count + '</p>';
                    if (job.failures_sample && job.failures_sample.length) {
                        html += '<details><summary>Failure samples</summary><ul>';
                        job.failures_sample.forEach(function(f){
                            html += '<li>Row: ' + (f.row || '?') + ' - ' + (f.reason || '') + '</li>';
                        });
                        html += '</ul></details>';
                    }
                    $progress.html(html);
                    if (job.status === 'completed' || job.status === 'failed') {
                        clearInterval(interval);
                    }
                }).fail(function(){
                    clearInterval(interval);
                    $progress.html('<div class="error">Error retrieving job status.</div>');
                });
            }, 3000);
        }

        // Delete job
        $(document).on('click', '.sovexxa-delete-job', function(e){
            e.preventDefault();
            if (!confirm('Are you sure you want to delete this job? This will remove stored file and job record.')) {
                return;
            }
            var jobid = $(this).data('jobid');
            $.post(sovexxa_admin.ajax_url, {
                action: 'sovexxa_delete_job',
                nonce: sovexxa_admin.mapping_nonce,
                job_id: jobid
            }, function(res){
                if (res.success) {
                    alert('Job deleted');
                    location.reload();
                } else {
                    alert('Delete failed: ' + (res.data && res.data.message ? res.data.message : 'Unknown'));
                }
            });
        });

        // Failure sample selected download
        $(document).on('click', '#sovexxa-download-selected-failures', function(e){
            e.preventDefault();
            var jobid = $(this).data('jobid');
            var rows = [];
            $('.sovexxa-failure-check:checked').each(function(){
                rows.push($(this).val());
            });
            if (rows.length === 0) {
                alert('Please select at least one failure row.');
                return;
            }
            $.post(sovexxa_admin.ajax_url, {
                action: 'sovexxa_create_filtered_failures_file',
                nonce: sovexxa_admin.mapping_nonce,
                job_id: jobid,
                rows: rows
            }, function(res){
                if (res.success) {
                    window.location = res.data.url;
                } else {
                    alert('Failed: ' + (res.data && res.data.message ? res.data.message : 'Unknown'));
                }
            });
        });

        // Retry selected failures
        $(document).on('click', '#sovexxa-retry-selected-failures', function(e){
            e.preventDefault();
            var jobid = $(this).data('jobid');
            var rows = [];
            $('.sovexxa-failure-check:checked').each(function(){
                rows.push($(this).val());
            });
            if (rows.length === 0) {
                alert('Please select at least one failure row to retry.');
                return;
            }
            if (!confirm('Retry selected failed rows? This will create a new retry job.')) return;
            $.post(sovexxa_admin.ajax_url, {
                action: 'sovexxa_retry_failed_job',
                nonce: sovexxa_admin.mapping_nonce,
                job_id: jobid,
                rows: rows
            }, function(res){
                if (res.success) {
                    alert('Retry job created: ' + res.data.job_id);
                    location.reload();
                } else {
                    alert('Failed: ' + (res.data && res.data.message ? res.data.message : 'Unknown'));
                }
            });
        });

        // Retry all failures
        $(document).on('click', '#sovexxa-retry-all-failures', function(e){
            e.preventDefault();
            var jobid = $(this).data('jobid');
            if (!confirm('Retry all failed rows? This will create a new retry job.')) return;
            $.post(sovexxa_admin.ajax_url, {
                action: 'sovexxa_retry_failed_job',
                nonce: sovexxa_admin.mapping_nonce,
                job_id: jobid
            }, function(res){
                if (res.success) {
                    alert('Retry job created: ' + res.data.job_id);
                    location.reload();
                } else {
                    alert('Failed: ' + (res.data && res.data.message ? res.data.message : 'Unknown'));
                }
            });
        });

        // Audit Undo
        $(document).on('click', '.sovexxa-audit-undo', function(e){
            e.preventDefault();
            var auditid = $(this).data('auditid');
            if (!confirm('Are you sure you want to undo this action?')) {
                return;
            }
            $.post(sovexxa_admin.ajax_url, {
                action: 'sovexxa_audit_undo',
                nonce: sovexxa_admin.mapping_nonce,
                audit_id: auditid
            }, function(res){
                if (res.success) {
                    alert('Undo succeeded: ' + res.data.message);
                    location.reload();
                } else {
                    alert('Undo failed: ' + (res.data && res.data.message ? res.data.message : 'Unknown'));
                }
            });
        });

    });
})(jQuery);