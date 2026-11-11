<?php
/**
 * php .tests/plugin-updates.php
 *
 * Lightweight wiring checks for the GitHub plugin updater (no WordPress bootstrap).
 */

$file = file_get_contents( dirname( __DIR__ ) . '/includes/class-ctm-updates.php' );

assert( false !== strpos( $file, "delete_site_transient_update_plugins" ), 'Check again must clear GitHub cache' );
assert( false !== strpos( $file, 'ctm_check_updates' ), 'Force check action missing' );
assert( false !== strpos( $file, 'clear_update_cache' ), 'Cache clear helper missing' );
assert( false !== strpos( $file, 'function github_release' ) || false !== strpos( $file, 'github_release(' ), 'GitHub release fetch missing' );
assert( false !== strpos( $file, 'pre_set_site_transient_update_plugins' ), 'Plugin update transient hook missing' );
assert( false !== strpos( $file, 'woo-complete-the-look.zip' ), 'Installable ZIP asset name missing' );
assert( false !== strpos( $file, 'plugins_api' ), 'plugins_api details missing' );

$main = file_get_contents( dirname( __DIR__ ) . '/woo-complete-the-look.php' );
assert( false !== strpos( $main, 'class-ctm-updates.php' ), 'Bootstrap must load updater' );
assert( false !== strpos( $main, 'CTM_Updates::instance' ), 'Bootstrap must init updater' );
assert( false !== strpos( $main, 'Update URI:' ), 'Update URI header missing' );

echo "PASS: plugin updater cache + force-check wiring\n";
