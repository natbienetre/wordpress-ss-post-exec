<?php
/**
 * Plugin Name:       Simply Static Post Exec
 * Plugin URI:        https://github.com/natbienetre/wordpress-ss-post-exec
 * Description:       Execute a script after Simply Static has finished running.
 * Version:           0.0.1
 * Author:            Pierre PÉRONNET
 * Funding URI:       https://github.com/sponsors/holyhope
 * Author URI:        https://github.com/holyhope
 * License:           MPL-2.0
 * License URI:       https://www.mozilla.org/en-US/MPL/2.0/
 * Text Domain:       sspostexec
 * Domain Path:       /languages
 * Requires Plugins:  simply-static
 */

require 'autoload.php';

define( 'SSPOSTEXEC_PLUGIN_FILE', __FILE__ );

add_action( 'init', 'ss_post_exec_init_admin_page' );
function ss_post_exec_init_admin_page() {
    SSPostExecAdminPage::register_hooks();
}

register_activation_hook( __FILE__, 'ss_post_exec_add_options' );
function ss_post_exec_add_options() {
    SSPostExecOptions::add_options();
}

add_action( 'init', 'sspostexec_load_textdomain' );
function sspostexec_load_textdomain() {
    load_plugin_textdomain( 'sspostexec', false, dirname( plugin_basename( SSPOSTEXEC_PLUGIN_FILE ) ) . '/languages' );
}

add_action( 'ss_completed', 'sspostexec_completed' );
function sspostexec_completed( $status ) {
    if ( $status !== 'success' ) {
        return;
    }

    $options = SSPostExecOptions::load();

    if ( ! $options->enabled ) {
        return;
    }

    $runner = SSPostExecJobRunner::create( $options );
    if ( is_wp_error( $runner ) ) {
        throw new Exception( $runner->get_error_message(), $runner->get_error_code() );
    }

    $job = $runner->run_job( $options->manifest );
    if ( is_wp_error( $job ) ) {
        throw new Exception( $job->get_error_message(), $job->get_error_code() );
    }

    $error = $job->save();
    if ( is_wp_error( $error ) ) {
        throw new Exception( $error->get_error_message(), $error->get_error_code() );
    }
}

SSPostExecJob::register_hooks();
