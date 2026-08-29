(function($){
	$(function(){
		// create invoice
		$('#sovexxa-create-invoice-btn').on('click', function(e){
			e.preventDefault();
			var society = $('#sovexxa-invoice-society').val();
			var flat = $('#sovexxa-invoice-flat').val();
			var items = $('#sovexxa-invoice-items').val();
			var tax = $('#sovexxa-invoice-tax').val();
			try {
				var parsed = JSON.parse(items);
			} catch (err) {
				alert('Invalid items JSON');
				return;
			}
			$.post(sovexxa_admin.ajax_url, {
				action: 'sovexxa_create_invoice',
				nonce: sovexxa_admin.mapping_nonce,
				society_id: society,
				flat_id: flat,
				items: JSON.stringify(parsed),
				tax: tax
			}, function(res){
				if (res.success) {
					alert('Invoice created: ' + res.data.invoice_id);
					loadInvoices();
				} else {
					alert('Error: ' + (res.data && res.data.message ? res.data.message : 'Unknown'));
				}
			});
		});

		function loadInvoices() {
			$('#sovexxa-invoices-list').html('<p>Loading...</p>');
			$.post(sovexxa_admin.ajax_url, {
				action: 'sovexxa_list_invoices',
				nonce: sovexxa_admin.mapping_nonce
			}, function(res){
				if (!res.success) {
					$('#sovexxa-invoices-list').html('<p>Error loading invoices</p>');
					return;
				}
				var rows = res.data;
				if (!rows || rows.length === 0) {
					$('#sovexxa-invoices-list').html('<p>No invoices.</p>');
					return;
				}
				var html = '<table class="widefat"><thead><tr><th>ID</th><th>No</th><th>Issue</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
				rows.forEach(function(r){
					html += '<tr><td>' + r.id + '</td><td>' + (r.invoice_number||'') + '</td><td>' + (r.issue_date||'') + '</td><td>' + (r.total||'') + '</td><td>' + (r.status||'') + '</td>';
					html += '<td><button class="button sovexxa-view-invoice" data-id="' + r.id + '">View</button> ';
					if (r.status !== 'paid') {
						html += '<button class="button sovexxa-mark-paid" data-id="' + r.id + '">Mark Paid</button>';
					}
					html += '</td></tr>';
				});
				html += '</tbody></table>';
				$('#sovexxa-invoices-list').html(html);
			});
		}

		$(document).on('click', '.sovexxa-mark-paid', function(){
			if (!confirm('Mark this invoice as paid?')) return;
			var id = $(this).data('id');
			$.post(sovexxa_admin.ajax_url, {
				action: 'sovexxa_mark_invoice_paid',
				nonce: sovexxa_admin.mapping_nonce,
				invoice_id: id
			}, function(res){
				if (res.success) {
					alert('Marked paid');
					loadInvoices();
				} else {
					alert('Failed: ' + (res.data && res.data.message ? res.data.message : 'Unknown'));
				}
			});
		});

		$(document).on('click', '.sovexxa-view-invoice', function(){
			var id = $(this).data('id');
			$.post(sovexxa_admin.ajax_url, {
				action: 'sovexxa_get_invoice',
				nonce: sovexxa_admin.mapping_nonce,
				invoice_id: id
			}, function(res){
				if (!res.success) { alert('Failed to load invoice'); return; }
				var inv = res.data;
				var items = inv.items || [];
				var html = '<h3>Invoice ' + (inv.invoice_number || '') + '</h3>';
				html += '<table class="widefat"><thead><tr><th>#</th><th>Description</th><th>Qty</th><th>Unit</th><th>Line</th></tr></thead><tbody>';
				items.forEach(function(it, idx){
					html += '<tr><td>' + (idx+1) + '</td><td>' + it.description + '</td><td>' + it.quantity + '</td><td>' + it.unit_price + '</td><td>' + it.line_total + '</td></tr>';
				});
				html += '</tbody></table>';
				html += '<p>Total: ' + inv.total + '</p>';
				var w = window.open('', '_blank');
				w.document.write(html);
			});
		});

		// initial load
		loadInvoices();
	});
})(jQuery);