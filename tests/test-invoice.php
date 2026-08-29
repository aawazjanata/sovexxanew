<?php
use PHPUnit\Framework\TestCase;

class InvoiceTest extends TestCase {

	public function test_generate_invoice_number() {
		$invoices = new Sovexxa\Invoices();
		$no = $invoices->generate_invoice_number();
		$this->assertIsString( $no );
		$this->assertStringContainsString( 'INV', $no );
	}
}