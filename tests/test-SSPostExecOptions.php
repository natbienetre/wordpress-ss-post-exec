<?php

class CSSPostExecOptionsTest extends WP_UnitTestCase {
	public function test_write_no_certificates() {
		require_once __DIR__ . '/../classes/SSPostExecOptions.php';

		$value = new SSPostExecOptions( array() );

		$this->assertNull( $value->write_certificates() );
	}

	public function test_write_certificates() {
		require_once __DIR__ . '/../classes/SSPostExecOptions.php';

		$tmp_dir = sys_get_temp_dir();

		$value = new SSPostExecOptions( array(
			'certificate_authority_data' => 'the-certificate-authority-content',
			'certificate_authority_path' => "{$tmp_dir}/certificate-authority.crt",
			'certificate_data' => 'the-certificate-content',
			'certificate_path' =>  "{$tmp_dir}/certificate.crt",
			'private_key_data' => 'the-private-key-content',
			'private_key_path' => "{$tmp_dir}/private.key",
		) );

		if ( file_exists( "{$tmp_dir}/certificate-authority.crt" ) ) { unlink( "{$tmp_dir}/certificate-authority.crt" ); }
		if ( file_exists( "{$tmp_dir}/certificate.crt" ) ) { unlink( "{$tmp_dir}/certificate.crt" ); }
		if ( file_exists( "{$tmp_dir}/private.key" ) ) { unlink( "{$tmp_dir}/private.key" ); }

		$this->assertFileDoesNotExist( "{$tmp_dir}/certificate-authority.key" );
		$this->assertFileDoesNotExist( "{$tmp_dir}/certificate.key" );
		$this->assertFileDoesNotExist( "{$tmp_dir}/private.key" );

		$this->assertNull( $value->write_certificates() );

		$this->assertFileExists( "{$tmp_dir}/certificate-authority.crt" );
		$this->assertFileExists( "{$tmp_dir}/certificate.crt" );
		$this->assertFileExists( "{$tmp_dir}/private.key" );

		$this->assertEquals( 'the-certificate-authority-content', file_get_contents( "{$tmp_dir}/certificate-authority.crt" ) );
		$this->assertEquals( 'the-certificate-content', file_get_contents( "{$tmp_dir}/certificate.crt" ) );
		$this->assertEquals( 'the-private-key-content', file_get_contents( "{$tmp_dir}/private.key" ) );
	}
}
