<?php
/**
 * Build GitHub Release zip for ART Master Install.
 *
 * Includes vendor/ (Plugin Update Checker). deploy-exclude.txt applies to WordPress.org only.
 *
 * Usage: php scripts/build-release.php [output-path]
 *
 * @package Art_Master_Install
 */

if ( 'cli' === PHP_SAPI && ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

defined( 'ABSPATH' ) || exit;

/**
 * Write a message to STDERR in CLI mode.
 *
 * @param string $art_master_install_message Message text.
 */
function art_master_install_build_release_stderr( $art_master_install_message ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI build script only.
	fwrite( STDERR, $art_master_install_message );
}

/**
 * Build release zip archive.
 *
 * @param array<int, string> $art_master_install_argv CLI arguments.
 * @return int Exit code.
 */
function art_master_install_build_release( array $art_master_install_argv ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		art_master_install_build_release_stderr( "ZipArchive is required.\n" );
		return 1;
	}

	$art_master_install_plugin_dir = dirname( __DIR__ );
	$art_master_install_slug       = basename( $art_master_install_plugin_dir );
	$art_master_install_output     = $art_master_install_argv[1] ?? ( sys_get_temp_dir() . DIRECTORY_SEPARATOR . $art_master_install_slug . '.zip' );

	$art_master_install_exclude_dirs          = array( '.git', '.cursor', '.idea', '.vscode', 'node_modules', 'scripts' );
	$art_master_install_exclude_file_patterns = array(
		'*.zip',
		'*.log',
		'tmp-*.php',
		'local-*.php',
	);

	/**
	 * Whether a path should be excluded from the release archive.
	 *
	 * @param string $art_master_install_relative_path Path relative to plugin root.
	 */
	$art_master_install_should_exclude = static function ( $art_master_install_relative_path ) use ( $art_master_install_exclude_dirs, $art_master_install_exclude_file_patterns ) {
		$art_master_install_relative_path = str_replace( '\\', '/', $art_master_install_relative_path );
		$art_master_install_parts         = explode( '/', $art_master_install_relative_path );

		foreach ( $art_master_install_parts as $art_master_install_part ) {
			if ( in_array( $art_master_install_part, $art_master_install_exclude_dirs, true ) ) {
				return true;
			}
		}

		$art_master_install_basename = basename( $art_master_install_relative_path );
		foreach ( $art_master_install_exclude_file_patterns as $art_master_install_pattern ) {
			if ( fnmatch( $art_master_install_pattern, $art_master_install_basename ) ) {
				return true;
			}
		}

		return false;
	};

	$art_master_install_zip    = new ZipArchive();
	$art_master_install_opened = $art_master_install_zip->open( $art_master_install_output, ZipArchive::OVERWRITE | ZipArchive::CREATE );

	if ( true !== $art_master_install_opened ) {
		art_master_install_build_release_stderr( 'Cannot create zip: ' . $art_master_install_output . "\n" );
		return 1;
	}

	$art_master_install_iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $art_master_install_plugin_dir, RecursiveDirectoryIterator::SKIP_DOTS )
	);

	foreach ( $art_master_install_iterator as $art_master_install_file_info ) {
		/**
		 * SplFileInfo instance for the current archive entry.
		 *
		 * @var SplFileInfo $art_master_install_file_info
		 */
		$art_master_install_absolute_path = $art_master_install_file_info->getPathname();
		$art_master_install_relative_path = substr( $art_master_install_absolute_path, strlen( $art_master_install_plugin_dir ) + 1 );

		if ( $art_master_install_should_exclude( $art_master_install_relative_path ) ) {
			continue;
		}

		$art_master_install_zip_path = $art_master_install_slug . '/' . str_replace( '\\', '/', $art_master_install_relative_path );

		if ( $art_master_install_file_info->isDir() ) {
			$art_master_install_zip->addEmptyDir( rtrim( $art_master_install_zip_path, '/' ) );
			continue;
		}

		$art_master_install_zip->addFile( $art_master_install_absolute_path, $art_master_install_zip_path );
	}

	$art_master_install_zip->close();

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI outputs a local filesystem path.
	echo $art_master_install_output, PHP_EOL;

	return 0;
}

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI exit code, not rendered output.
exit( art_master_install_build_release( $argv ) );
