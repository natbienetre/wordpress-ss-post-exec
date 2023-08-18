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
 * Text Domain:       ss-post-exec
 * Domain Path:       /languages
 * Requires Plugins:  simply-static
 */

define( 'SS_PSOT_EXEC_FILE', __FILE__ );

add_action( 'ss_finished_transferring_files_locally', 'ss_post_exec_after_transfer' );
function ss_post_exec_after_transfer( $path ) {
    
}
