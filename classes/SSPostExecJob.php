<?php

use Symfony\Component\Yaml\Yaml;

class SSPostExecJob {
    const cap_type = 'sspostexec-job';
    const CAPABILITY_TYPE = 'sspostexec-job';
    const MIME_TYPE = 'text/x-yaml';

	public ?int $ID;

    public string $manifest;

    public array $k8s_data;

    public function __construct( ?int $ID, string $manifest, array $k8s_data ) {
        $this->ID = $ID;

        $this->manifest  = $manifest;
        $this->k8s_data = $k8s_data;
    }

    public static function register_hooks(): void {
        add_action( 'init', array( __CLASS__, 'register_post_type' ) );

        add_filter( 'wp_editor_settings', array( __CLASS__, 'editor_settings' ), 10, 2 );
    }

    public static function editor_settings( $settings, $editor_id ) {
        if ( 'content' == $editor_id && self::cap_type == get_current_screen()->post_type ) {   
            $settings['wpautop'] = false;
            $settings['media_buttons'] = false;
            $settings['drag_drop_upload'] = false;
            $settings['tinymce'] = false;
            $settings['quicktags'] = false;
        }

        return $settings;
    }

    public function __get( string $args ): mixed {
        switch ( $args ) {
            case 'title': return $this->k8s_data['metadata']['namespace'] . '/' . $this->k8s_data['metadata']['name'];
            default: throw new Exception( "Unknown property {$args}" );
        }
    }

    public function save( $status = 'publish' ): ?WP_Error {
        $k8s_date = $this->k8s_data['metadata']['creationTimestamp'];
        $datetime = DateTimeImmutable::createFromFormat( DateTimeInterface::ATOM, $k8s_date );
        if ( $datetime === false ) {
            return new WP_Error( 'invalid-creation-timestamp', __( 'Invalid creation timestamp', 'sspostexec' ), $k8s_date );
        }

        $post_data = array(
            'ID'             => $this->ID,
            'cap_type'      => SSPostExecJob::cap_type,
            'post_date'      => $datetime->format( 'Y-m-d H:i:s' ),
            'post_title'     => $this->title,
            'post_status'    => $status,
            'post_content'   => Yaml::dump( $this->k8s_data, 32, 2 ),
            'post_mime_type' => self::MIME_TYPE,

            'meta_input' => array(
                'k8s_manifest' => $this->manifest,
            ),
        );

        $id = wp_insert_post( $post_data, true, true );
        if ( is_wp_error( $id ) ) {
            return $id;
        }

        $this->ID = $id;

        return null;
    }

    public static function register_post_type(): ?WP_Error {
        $cap_type = self::CAPABILITY_TYPE;

        $cap_type = register_post_type( self::cap_type,
            array(
                'public'              => false,
                'rewrite'             => false,
                'show_ui'             => true,
                'supports'            => array( 'title', 'editor', 'custom-fields' ),
                'template'            => array(),
                'query_var'           => 'sspostexec_job',
                'can_export'          => true,
                'description'         => __( 'A job created by SS Post Exec', 'sspostexec' ),
                'has_archive'         => false,
                'hierarchical'        => false,
                'map_meta_cap'        => false,
                'show_in_menu'        => false,
                'show_in_rest'        => false,
                'template_lock'       => 'all',
                'capability_type'     => self::CAPABILITY_TYPE,
                'delete_with_user'    => false,
                'show_in_nav_menus'   => false,
                'show_in_admin_bar'   => false,
                'publicly_queryable'  => false,
                'exclude_from_search' => true,

                'labels' => array(
                    'name'          => __( 'SS Post Exec Jobs', 'sspostexec' ),
                    'singular_name' => __( 'SS Post Exec Job', 'sspostexec' ),
                ),
            )
        );
        
        if ( is_wp_error( $cap_type ) ) {
            return $cap_type;
        }

        return null;
    }
}
