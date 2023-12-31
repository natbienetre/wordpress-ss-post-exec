<?php

class SSPostExecAdminPage {
    const OPTIONS_PAGE_ID   = 'sspostexec-options';
    const MENU_SLUG = 'ss-post-exec';

    const GENERAL_SECTION  = 'general';
    const SECURITY_SECTION = 'security';

    const TRIGGER_ACTION       = 'sspostexec-trigger';
    const CHECK_OPTIONS_ACTION = 'sspostexec-options-check';

    private string|bool $load_hook_suffix = false;

    public static function register_hooks(): self {
        $instance = new self();

        add_action( 'admin_menu', array( $instance, 'add_admin_menu' ) );

        add_action( 'admin_init', array( $instance, 'settings_init' ) );

        add_action( 'wp_ajax_' . self::CHECK_OPTIONS_ACTION, array( $instance, 'check_options_action' ) );
        add_action( 'wp_ajax_' . self::TRIGGER_ACTION, array( $instance, 'trigger_job_action' ) );

        add_action( 'admin_enqueue_scripts', array( $instance, 'enqueue_admin_script' ) );

        add_filter( 'plugin_action_links_' . plugin_basename( SSPOSTEXEC_PLUGIN_FILE ), array( $instance, 'plugin_settings' ) );

        return $instance;
    }

    function plugin_settings( array $links ): array {
        array_unshift( $links, '<a href="' . esc_url( $this->get_url() ) . '&amp;sub=options">' . __( 'Settings', 'sspostexec' ) . '</a>' );

        return $links;
	}

    function enqueue_admin_script( string $hook ) {
        if ( ! ( $this->load_hook_suffix && $hook == $this->load_hook_suffix) && 'post.php' != $hook ) {
            return;
        }

        wp_enqueue_script( 'sspostexec-admin', plugins_url( '/js/admin.js', SSPOSTEXEC_PLUGIN_FILE ), array(
            'jquery',
            'code-editor',
        ), '1.0' );

        $code_editor_settings = wp_get_code_editor_settings( array(
            'codemirror' => array(
                'mode'           => 'text/x-yaml',
                'indentUnit'     => 2,
                'lineNumbers'    => true,
                'indentWithTabs' => false,
                'lineWrapping'   => true,
            ),
            'wpautop'          => false,
            'tinymce'          => false,
            'quicktags'        => false,
            'media_buttons'    => false,
            'drag_drop_upload' => false,
        ) );
        wp_add_inline_script( 'sspostexec-admin', 'const sspostexec_codeeditor_settings = '. json_encode( $code_editor_settings ) );

        wp_enqueue_style( 'sspostexec-admin', plugins_url( '/css/admin.css', SSPOSTEXEC_PLUGIN_FILE ), array(
            'code-editor',
        ) );

        // wp_set_script_translations( 'sspostexec-admin-settings', 'pass2cf', plugin_dir_path( SSPOSTEXEC_PLUGIN_FILE ) . 'languages/' );

        if ( SSPostExecOptions::load()->use_local_credentials ) {
            wp_add_inline_style( 'sspostexec-admin-settings', '.non-local-credentials{display:none}' );
        }
    }

    public function trigger_job_action() {
        $nonce = sanitize_text_field( @$_POST['_wpnonce'] );

        if( ! wp_verify_nonce( $nonce, SSPostExecOptions::OPTION_NAME . '-options' ) ) { // nonce name is generated by settings_fields()
            wp_send_json_error( new WP_Error( 'nonce_failed', __( 'Nonce failed, please refresh and retry.', 'sspostexec' ) ) );
            return;
        }

        if( ! current_user_can( 'administrator' ) ) {
            wp_send_json_error( new WP_Error( 'unauthorized', __( 'You are not allowed to do this.', 'sspostexec' ) ) );
            return;
        }

        $options = new SSPostExecOptions( $this->sanitize_setting( $_POST[ SSPostExecOptions::OPTION_NAME ], false ) );

        $client = SSPostExecJobRunner::create( $options );
        if ( is_wp_error( $client ) ) {
            wp_send_json_error( $client );
            return;
        }

        $job = $client->run_job( $options->manifest );
        if ( is_wp_error( $job ) ) {
            wp_send_json_error( $job );
            return;
        }

	    wp_send_json_success( array(
            'job'     => (array) $job,
            'message' => '<a href="' . esc_attr( get_edit_post_link( $job->ID, 'ajax' ) ) . '">' . __( 'Job created.', 'sspostexec' ) . '</a>',
        ) );
    }

    public function check_options_action() {
        $nonce = sanitize_text_field( @$_POST['_wpnonce'] );

        if( ! wp_verify_nonce( $nonce, SSPostExecOptions::OPTION_NAME . '-options' ) ) { // nonce name is generated by settings_fields()
            wp_send_json_error( new WP_Error( 'nonce_failed', __( 'Nonce failed, please refresh and retry.', 'sspostexec' ) ) );
            return;
        }

        if( ! current_user_can( 'administrator' ) ) {
            wp_send_json_error( new WP_Error( 'unauthorized', __( 'You are not allowed to do this.', 'sspostexec' ) ) );
            return;
        }

        $opts = new SSPostExecOptions( $this->sanitize_setting( $_POST[ SSPostExecOptions::OPTION_NAME ] ) );

        $error = $this->check_options( $opts );
        if ( is_wp_error( $error ) ) {
            wp_send_json_error( $error );
            return;
        }

	    wp_send_json_success( array(
            'message' => __( 'The Kubernetes cluster is ready to use.', 'sspostexec' ),
        ) );
    }

    public function get_url( array $params = array() ): string {
        $params['page'] = self::MENU_SLUG;

        return admin_url( 'options-general.php?' . http_build_query( $params ) );
    }
    
    function add_admin_menu() {
        $this->load_hook_suffix = add_options_page(
            _x( 'Simply Static Post Exec', 'Label in the admin menu', 'sspostexec' ),
            _x( 'SS Post Exec', 'Title of the admin page', 'sspostexec' ),
            'manage_options',
            self::MENU_SLUG,
            array( $this, 'options_page' ),
        );
    }

    function options_page() {
        ?>
            <h1><? echo esc_html_x( 'Simply Static Post Exec', 'Header on top of the admin page', 'sspostexec' ); ?></h1>

            <div class="wrap" id="sspostexec-content">
                <p>
                    <?php esc_html_e( 'Execute a script after Simply Static has finished running.', 'sspostexec' ); ?>
                    <a href="<?php echo add_query_arg( array( 'post_type' => SSPostExecJob::POST_TYPE ), admin_url( 'edit.php' ) ); ?>"><?php esc_html_e( 'View previous executions.', 'sspostexec' ); ?></a>
                </p>
                <form action="options.php" method="post">
                    <?php
                        settings_fields( SSPostExecOptions::OPTION_NAME );
                        do_settings_sections( self::OPTIONS_PAGE_ID );
                    ?>
                    <p class="submit">
                        <input type="reset" class="button" value="<?php esc_attr_e( 'Reset', 'sspostexec' ) ?>" />
                        <span class="button-group">
                            <input type="button" disabled="disabled" id="sspostexec-options-check-button" class="button" value="<?php esc_attr_e( 'Check the settings', 'sspostexec' ) ?>" data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>" data-action="<?php echo esc_attr( self::CHECK_OPTIONS_ACTION ); ?>" />
                            <?php submit_button( null, 'primary', 'submit', false ); ?>
                            <input type="button" disabled="disabled" id="sspostexec-trigger-button" class="button" value="<?php esc_attr_e( 'Trigger once', 'sspostexec' ) ?>" data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>" data-action="<?php echo esc_attr( self::TRIGGER_ACTION ); ?>" />
                        </span>
                    </p>
                </form>
            </div>
        <?php
    }

    public function sanitize_setting( array $sanitized, $test = true ): array {
        $sanitized['verify']                = isset( $sanitized['verify'] ) && $sanitized['verify'] == 'on';
        $sanitized['enabled']               = isset( $sanitized['enabled'] ) && $sanitized['enabled'] == 'on';
        $sanitized['kubernetes_api']        = sanitize_url( $sanitized['kubernetes_api'], array( 'https', 'http' ) );
        $sanitized['use_local_credentials'] = isset( $sanitized['use_local_credentials'] ) && $sanitized['use_local_credentials'] == 'on';

        global $wp_filesystem;
        
        $certs_dir = WP_Filesystem() === true ? $wp_filesystem->wp_content_dir() : path_join( sys_get_temp_dir(), 'sspostexec' );
        
        $previous = SSPostExecOptions::load();

        if ( empty( $sanitized['certificate_authority_path'] ) ) {
            $sanitized['certificate_authority_path'] = empty( $previous->certificate_authority_path ) ? wp_unique_filename( $certs_dir, 'certificate_authority.pem' ) : $previous->certificate_authority_path;
        }
        if ( empty( $sanitized['certificate_path'] ) ) {
            $sanitized['certificate_path'] = empty( $previous->certificate_path ) ? wp_unique_filename( $certs_dir, 'certificate.pem' ) : $previous->certificate_path;
        }
        if ( empty( $sanitized['private_key_path'] ) ) {
            $sanitized['private_key_path'] = empty( $previous->private_key_path ) ? wp_unique_filename( $certs_dir, 'private_key.pem' ) : $previous->private_key_path;
        }

        if ( ! empty( $sanitized['certificate_authority_data'] ) ) {
            $certificate_authority_data = base64_decode( trim( $sanitized['certificate_authority_data'] ), true );
            if ( $certificate_authority_data !== false ) {
                $sanitized['certificate_authority_data'] = $certificate_authority_data;
            }

            if ( $test ) {
                if ( function_exists( 'openssl_x509_read' ) ) {
                    $certificate_authority = openssl_x509_read( $sanitized['certificate_authority_data'] );
                    if ( ! $certificate_authority ) {
                        add_settings_error(
                            self::OPTIONS_PAGE_ID,
                            'sspostexec-certificate-authority-data',
                            /* translators: %s is the error message */
                            sprintf( _x( 'Failed to parse certificate authority: %s', 'Error message', 'sspostexec' ), openssl_error_string() ),
                            'warning',
                        );
                    }
                } else {
                    add_settings_error(
                        self::OPTIONS_PAGE_ID,
                        'sspostexec-openssl',
                        sprintf( _x( 'The certificate authority cannot be checked because the openssl extension is not available', 'Error message', 'sspostexec' ) ),
                        'warning',
                    );
                }
            }
        }
        if ( ! empty( $sanitized['certificate_data'] ) ) {
            $certificate_data = base64_decode( trim( $sanitized['certificate_data'] ), true );
            if ( $certificate_data !== false ) {
                $sanitized['certificate_data'] = $certificate_data;
            }

            if ( $test ) {
                if ( function_exists( 'openssl_x509_read' ) ) {
                    $certificate = openssl_x509_read( $sanitized['certificate_data'] );
                    if ( ! $certificate ) {
                        add_settings_error(
                            self::OPTIONS_PAGE_ID,
                            'sspostexec-certificate-data',
                            /* translators: %s is the error message */
                            sprintf( _x( 'Failed to parse client certificate: %s', 'Error message', 'sspostexec' ), openssl_error_string() ),
                            'warning',
                        );
                    }
                } else {
                    add_settings_error(
                        self::OPTIONS_PAGE_ID,
                        'sspostexec-openssl',
                        sprintf( _x( 'The client certificate cannot be checked because the openssl extension is not available', 'Error message', 'sspostexec' ) ),
                        'warning',
                    );
                }
            }
        }
        if ( ! empty( $sanitized['private_key_data'] ) ) {
            $private_key_data = base64_decode( trim( $sanitized['private_key_data'] ), true );
            if ( $private_key_data !== false ) {
                $sanitized['private_key_data'] = $private_key_data;
            }

            if ( $test ) {
                if ( $certificate ) {
                    if ( function_exists( 'openssl_x509_check_private_key' ) ) {
                        if ( ! openssl_x509_check_private_key( $certificate, $sanitized['private_key_data'] ) ) {
                            add_settings_error(
                                self::OPTIONS_PAGE_ID,
                                'sspostexec-private-key-data',
                                /* translators: %s is the error message */
                                sprintf( _x( 'The private key does not match the client certificate: %s', 'Error message', 'sspostexec' ), openssl_error_string() ),
                                'warning',
                            );
                        }
                    } else {
                        add_settings_error(
                            self::OPTIONS_PAGE_ID,
                            'sspostexec-openssl',
                            sprintf( _x( 'The private key cannot be checked because the openssl extension is not available', 'Error message', 'sspostexec' ) ),
                            'warning',
                        );
                    }
                }
            }
        }

        if ( $test ) {
            $error = $this->check_options( new SSPostExecOptions( $sanitized ) );
            if ( is_wp_error( $error ) ) {
                add_settings_error(
                    self::OPTIONS_PAGE_ID,
                    'sspostexec-check',
                    /* translators: %s is the error message */
                    sprintf( _x( 'Failed to request Kubernetes API: %s', 'Error message', 'sspostexec' ), $error->get_error_message() ),
                    'error',
                );
            }
        }

        return $sanitized;
    }

    public function check_options( SSPostExecOptions $options ): ?WP_error {
        $client = SSPostExecJobRunner::create( $options );
        if ( is_wp_error( $client ) ) {
            return $client;
        }

        $error = $client->run_job( $options->manifest, true );
        if ( is_wp_error( $error ) ) {
            return $error;
        }

        return null;
    }

    protected function label_for( string $id, string $label ): string {
        return '<label for="' . esc_attr( $id ) . '">' . $label . '</label>';
    }

    function settings_init() {
        register_setting( SSPostExecOptions::OPTION_NAME, SSPostExecOptions::OPTION_NAME, array(
            'type'              => 'array',
            'default'           => SSPostExecOptions::defaults(),
            'sanitize_callback' => array( $this, 'sanitize_setting' ),
        ) );

        add_settings_section(
            self::GENERAL_SECTION,
            esc_html_x( 'General', 'Header for the setting section', 'sspostexec' ),
            array( $this, 'general_section_callback' ),
            self::OPTIONS_PAGE_ID,
        );

        add_settings_field(
            'enabled',
            $this->label_for( 'sspostexec-enabled', esc_html_x( 'Check to enable the plugin', 'Label for the setting field', 'sspostexec' ) ),
            array( $this, 'enabled_render' ),
            self::OPTIONS_PAGE_ID,
            self::GENERAL_SECTION,
        );

        add_settings_field(
            'kubernetes_api',
            $this->label_for( 'sspostexec-kubernetes-api', esc_html_x( 'Kubernetes API URL', 'Label for the setting field', 'sspostexec' ) ),
            array( $this, 'kubernetes_api_render' ),
            self::OPTIONS_PAGE_ID,
            self::GENERAL_SECTION,
        );

        add_settings_field(
            'manifest',
            $this->label_for( 'sspostexec-manifest', esc_html_x( 'Manifest to apply', 'Label for the setting field', 'sspostexec' ) ),
            array( $this, 'manifest_render' ),
            self::OPTIONS_PAGE_ID,
            self::GENERAL_SECTION,
        );

        add_settings_section(
            self::SECURITY_SECTION,
            esc_html_x( 'Security', 'Header for the setting section', 'sspostexec' ),
            array( $this, 'security_section_callback' ),
            self::OPTIONS_PAGE_ID,
        );

        add_settings_field(
            'use_local_credentials',
            $this->label_for( 'sspostexec-use-local-credentials', esc_html_x( 'Use local credentials', 'Label for the setting field', 'sspostexec' ) ),
            array( $this, 'use_local_credentials_render' ),
            self::OPTIONS_PAGE_ID,
            self::SECURITY_SECTION,
        );

        add_settings_field(
            'verify',
            $this->label_for( 'sspostexec-verify', esc_html_x( 'Verify remote certificate', 'Label for the setting field', 'sspostexec' ) ),
            array( $this, 'verify_render' ),
            self::OPTIONS_PAGE_ID,
            self::SECURITY_SECTION,
        );

        add_settings_field(
            'certificate_authority_path',
            $this->label_for( 'sspostexec-certificate-authority-path', esc_html_x( 'Path to the certificate authority', 'Label for the setting field', 'sspostexec' ) ),
            array( $this, 'certificate_authority_path_render' ),
            self::OPTIONS_PAGE_ID,
            self::SECURITY_SECTION,
            array(
                'class' => 'non-local-credentials',
            ),
        );

        add_settings_field(
            'certificate_authority_data',
            $this->label_for( 'sspostexec-certificate-authority-data', esc_html_x( 'Certificate authority', 'Label for the setting field', 'sspostexec' ) ),
            array( $this, 'certificate_authority_data_render' ),
            self::OPTIONS_PAGE_ID,
            self::SECURITY_SECTION,
            array(
                'class' => 'non-local-credentials',
            ),
        );

        add_settings_field(
            'certificate_path',
            $this->label_for( 'sspostexec-certificate-path', esc_html_x( 'Path to the client certificate', 'Label for the setting field', 'sspostexec' ) ),
            array( $this, 'certificate_path_render' ),
            self::OPTIONS_PAGE_ID,
            self::SECURITY_SECTION,
            array(
                'class' => 'non-local-credentials',
            ),
        );

        add_settings_field(
            'certificate_data',
            $this->label_for( 'sspostexec-certificate-data', esc_html_x( 'Client certificate', 'Label for the setting field', 'sspostexec' ) ),
            array( $this, 'certificate_data_render' ),
            self::OPTIONS_PAGE_ID,
            self::SECURITY_SECTION,
            array(
                'class' => 'non-local-credentials',
            ),
        );

        add_settings_field(
            'private_key_path',
            $this->label_for( 'sspostexec-private-key-path', esc_html_x( 'Path to the private key', 'Label for the setting field', 'sspostexec' ) ),
            array( $this, 'private_key_path_render' ),
            self::OPTIONS_PAGE_ID,
            self::SECURITY_SECTION,
            array(
                'class' => 'non-local-credentials',
            ),
        );

        add_settings_field(
            'private_key_data',
            $this->label_for( 'sspostexec-private-key-data', esc_html_x( 'Private key', 'Label for the setting field', 'sspostexec' ) ),
            array( $this, 'private_key_data_render' ),
            self::OPTIONS_PAGE_ID,
            self::SECURITY_SECTION,
            array(
                'class' => 'non-local-credentials',
            ),
        );

        add_settings_field(
            'private_key_password',
            $this->label_for( 'sspostexec-private-key-password', esc_html_x( 'Password of the private key', 'Label for the setting field', 'sspostexec' ) ),
            array( $this, 'private_key_password_render' ),
            self::OPTIONS_PAGE_ID,
            self::SECURITY_SECTION,
            array(
                'class' => 'non-local-credentials',
            ),
        );
    }

    function general_section_callback() {
        ?>
            <p><?php esc_html_e( 'General settings to configure the command to run.', 'sspostexec' ); ?></p>
        <?php
    }

    function enabled_render() {
        ?>
        <input type="checkbox" id="sspostexec-enabled" <?php checked( SSPostExecOptions::load()->enabled ); ?> name="<?php echo esc_attr( SSPostExecOptions::OPTION_NAME . '[enabled]' ); ?>">
        <?php
    }

    function kubernetes_api_render() {
        ?>
        <input type="text" id="sspostexec-kubernetes-api" name="<?php echo esc_attr( SSPostExecOptions::OPTION_NAME . '[kubernetes_api]' ); ?>" value="<?php echo esc_attr( SSPostExecOptions::load()->kubernetes_api ); ?>">
        <?php
    }

    function manifest_render() {
        ?>
        <textarea id="sspostexec-manifest" class="code teatarea-large" name="<?php echo esc_attr( SSPostExecOptions::OPTION_NAME . '[manifest]' ); ?>" cols=65><?php echo esc_html( SSPostExecOptions::load()->manifest ); ?></textarea>
        <?php
    }

    function security_section_callback() {
        ?>
            <p><?php esc_html_e( 'These settings define how to communicate with kubernetes.', 'sspostexec' ); ?></p>
        <?php
    }

    function use_local_credentials_render() {
        ?>
        <input type="checkbox" id="sspostexec-use-local-credentials" <?php checked( SSPostExecOptions::load()->use_local_credentials ); ?> name="<?php echo esc_attr( SSPostExecOptions::OPTION_NAME . '[use_local_credentials]' ); ?>">
        <?php
    }

    function verify_render() {
        ?>
        <input type="checkbox" id="sspostexec-verify" <?php checked( SSPostExecOptions::load()->verify ); ?> name="<?php echo esc_attr( SSPostExecOptions::OPTION_NAME . '[verify]' ); ?>">
        <?php
    }

    function certificate_authority_path_render() {
        ?>
        <input id="sspostexec-certificate-authority-path" name="<?php echo esc_attr( SSPostExecOptions::OPTION_NAME . '[certificate_authority_path]' ); ?>" value="<?php echo esc_attr( SSPostExecOptions::load()->certificate_authority_path ); ?>" type="text" />
        <?php
    }

    function certificate_authority_data_render() {
        ?>
        <textarea id="sspostexec-certificate-authority-data" class="code teatarea-large" name="<?php echo esc_attr( SSPostExecOptions::OPTION_NAME . '[certificate_authority_data]' ); ?>" cols="65"><?php echo esc_html( SSPostExecOptions::load()->certificate_authority_data ); ?></textarea>
        <?php
    }

    function certificate_path_render() {
        ?>
        <input id="sspostexec-certificate-path" name="<?php echo esc_attr( SSPostExecOptions::OPTION_NAME . '[certificate_path]' ); ?>" value="<?php echo esc_attr( SSPostExecOptions::load()->certificate_path ); ?>" type="text" />
        <?php
    }

    function certificate_data_render() {
        ?>
        <textarea id="sspostexec-certificate-data" class="code teatarea-large" name="<?php echo esc_attr( SSPostExecOptions::OPTION_NAME . '[certificate_data]' ); ?>" cols="65"><?php echo esc_html( SSPostExecOptions::load()->certificate_data ); ?></textarea>
        <?php
    }

    function private_key_path_render() {
        ?>
        <input id="sspostexec-private-key-path" name="<?php echo esc_attr( SSPostExecOptions::OPTION_NAME . '[private_key_path]' ); ?>" value="<?php echo esc_attr( SSPostExecOptions::load()->private_key_path ); ?>" type="text" />
        <?php
    }

    function private_key_data_render() {
        ?>
        <textarea id="sspostexec-private-key-data" class="code teatarea-large" name="<?php echo esc_attr( SSPostExecOptions::OPTION_NAME . '[private_key_data]' ); ?>" cols="65"><?php echo esc_html( SSPostExecOptions::load()->private_key_data ); ?></textarea>
        <?php
    }

    function private_key_password_render() {
        ?>
        <input id="sspostexec-private-key-password" name="<?php echo esc_attr( SSPostExecOptions::OPTION_NAME . '[private_key_password]' ); ?>" value="<?php echo esc_attr( SSPostExecOptions::load()->private_key_password ); ?>" type="password" placeholder="passphrase" />
        <?php
    }
}
