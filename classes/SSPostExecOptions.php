<?php

class SSPostExecOptions {
    const OPTION_NAME = 'sspostexec_options';
    
    public readonly bool $enabled;

    public readonly string $kubernetes_api;

    public readonly string $manifest;

    public readonly bool $verify;

    public readonly bool $use_local_credentials;

    public readonly string $certificate_authority_path;
    public readonly ?string $certificate_authority_data;
    public readonly string $certificate_path;
    public readonly ?string $certificate_data;
    public readonly string $certificate_password;
    public readonly string $private_key_path;
    public readonly ?string $private_key_data;
    public readonly string $private_key_password;
    public readonly string $token;

    public function __construct( array $options ) {
        $options = wp_parse_args( $options, self::defaults() );

        $this->enabled = $options['enabled'];

        $this->manifest       = $options['manifest'];
        $this->kubernetes_api = $options['kubernetes_api'];
        
        $this->use_local_credentials = $options['use_local_credentials'];

        $this->verify = $options['verify'];

        $this->certificate_path           = $options['certificate_path'];
        $this->private_key_path           = $options['private_key_path'];
        $this->certificate_authority_path = $options['certificate_authority_path'];

        $this->certificate_data           = $options['certificate_data'];
        $this->private_key_data           = $options['private_key_data'];
        $this->certificate_authority_data = $options['certificate_authority_data'];

        $this->certificate_password = $options['certificate_password'];
        $this->private_key_password = $options['private_key_password'];

        $this->token = $options['token'];
    }

    public function write_certificates(): ?WP_Error {
        if ( WP_Filesystem() !== true ) {
            return new WP_Error( 'filesystem', __( 'Failed to initialize the filesystem', 'sspostexec' ) );
        }

        global $wp_filesystem;

        if ( ! empty( $this->certificate_authority_data ) ) {
            $wp_filesystem->mkdir( dirname( $this->certificate_authority_path ) );
            $wp_filesystem->put_contents( $this->certificate_authority_path, $this->certificate_authority_data );
        }

        if ( ! empty( $this->certificate_data ) ) {
            if ( empty( $this->private_key_data ) ) {
                return new WP_Error( 'sspostexec-certificate', __( 'Certificate data is set but private key data is not', 'sspostexec' ) );
            }

            $wp_filesystem->mkdir( dirname( $this->certificate_path ) );
            $wp_filesystem->put_contents( $this->certificate_path, $this->certificate_data );
        }

        if ( ! empty( $this->private_key_data ) ) {
            if ( empty( $this->certificate_data ) ) {
                return new WP_Error( 'sspostexec-certificate', __( 'Private key data is set but certificate data is not', 'sspostexec' ) );
            }

            $wp_filesystem->mkdir( dirname( $this->private_key_path ) );
            $wp_filesystem->put_contents( $this->private_key_path, $this->private_key_data );
        }

        return null;
    }

    public function obfuscate( string $message ): string {

        return $message;
    }

    public static function defaults(): array {
        return array(
            'enabled' => false,

            'manifest'       => trim( file_get_contents( __DIR__ . '/data/manifest.default.yaml' ) ),
            'kubernetes_api' => 'https://kubernetes.default.svc',

            'verify'                => true,
            'use_local_credentials' => true,

            'token'                      => '',
            'certificate_data'           => '',
            'certificate_path'           => '',
            'private_key_data'           => '',
            'private_key_path'           => '',
            'certificate_password'       => '',
            'private_key_password'       => '',
            'certificate_authority_data' => '',
            'certificate_authority_path' => '',
        );
    }

    public static function load(): SSPostExecOptions {
        global $sspostexec_opts;

        if ( empty( $sspostexec_opts ) ) {
            $sspostexec_opts = new self( (array) get_option( self::OPTION_NAME, self::defaults() ) );
        }

        return $sspostexec_opts;
    }

    public static function add_options() {
        add_option( self::OPTION_NAME, self::defaults() );
    }
}
