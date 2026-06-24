<?php
/**
 * GitHub Releases helpers for public ART repositories.
 *
 * @package Art_Master_Install
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Art_Master_Install_Github
 */
class Art_Master_Install_Github {

	const CACHE_TTL         = 21600; // 6 hours.
	const CACHE_TTL_FAILURE = 900; // 15 minutes.

	/**
	 * @param string $github_repo Owner/repo.
	 * @param string $zip_name    Release asset file name.
	 * @return string
	 */
	public static function get_release_zip_url( $github_repo, $zip_name ) {
		$github_repo = self::sanitize_repo( $github_repo );
		$zip_name    = sanitize_file_name( $zip_name );

		return sprintf(
			'https://github.com/%s/releases/latest/download/%s',
			$github_repo,
			$zip_name
		);
	}

	/**
	 * @param string $github_repo Owner/repo.
	 * @param bool   $force_refresh Skip cached release data.
	 * @return string Latest version without leading "v", or empty string on failure.
	 */
	public static function get_latest_version( $github_repo, $force_refresh = false ) {
		$release = self::get_latest_release( $github_repo, $force_refresh );

		if ( empty( $release['tag_name'] ) ) {
			return '';
		}

		return self::normalize_version( (string) $release['tag_name'] );
	}

	/**
	 * @param string $tag Git tag name.
	 * @return string
	 */
	public static function normalize_version( $tag ) {
		return ltrim( (string) $tag, "vV \t\n\r\0\x0B" );
	}

	/**
	 * @param string $github_repo Owner/repo.
	 * @param bool   $force_refresh Skip cached release data.
	 * @return array<string, mixed>
	 */
	public static function get_latest_release( $github_repo, $force_refresh = false ) {
		$github_repo = self::sanitize_repo( $github_repo );

		if ( '' === $github_repo ) {
			return array();
		}

		$cache_key = self::get_cache_key( $github_repo );

		if ( $force_refresh ) {
			delete_site_transient( $cache_key );
		}

		$cached = get_site_transient( $cache_key );

		if ( is_array( $cached ) && self::is_cache_fresh( $cached ) ) {
			return self::normalize_cached_release( $cached );
		}

		$response = wp_remote_get(
			sprintf( 'https://api.github.com/repos/%s/releases/latest', $github_repo ),
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'ART-Master-Install/' . ART_MASTER_INSTALL_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::store_release_cache( $cache_key, array(), true );
			return is_array( $cached ) ? self::normalize_cached_release( $cached ) : array();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			self::store_release_cache( $cache_key, array(), true );
			return is_array( $cached ) ? self::normalize_cached_release( $cached ) : array();
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			self::store_release_cache( $cache_key, array(), true );
			return is_array( $cached ) ? self::normalize_cached_release( $cached ) : array();
		}

		$release = array(
			'tag_name' => isset( $body['tag_name'] ) ? (string) $body['tag_name'] : '',
			'html_url' => isset( $body['html_url'] ) ? (string) $body['html_url'] : '',
		);

		self::store_release_cache( $cache_key, $release, false );

		return $release;
	}

	/**
	 * Drop cached release data for a repository.
	 *
	 * @param string $github_repo Owner/repo.
	 */
	public static function clear_release_cache( $github_repo ) {
		$github_repo = self::sanitize_repo( $github_repo );

		if ( '' === $github_repo ) {
			return;
		}

		delete_site_transient( self::get_cache_key( $github_repo ) );
	}

	/**
	 * @param string               $cache_key Transient key.
	 * @param array<string, mixed> $release   Release payload.
	 * @param bool                 $failed    Whether the fetch failed.
	 */
	private static function store_release_cache( $cache_key, array $release, $failed ) {
		$payload = array(
			'tag_name'  => isset( $release['tag_name'] ) ? (string) $release['tag_name'] : '',
			'html_url'  => isset( $release['html_url'] ) ? (string) $release['html_url'] : '',
			'cached_at' => time(),
			'failed'    => $failed,
		);

		$ttl = $failed ? self::CACHE_TTL_FAILURE : self::CACHE_TTL;

		set_site_transient( $cache_key, $payload, $ttl );
	}

	/**
	 * @param array<string, mixed> $cached Cached payload.
	 * @return bool
	 */
	private static function is_cache_fresh( array $cached ) {
		$cached_at = isset( $cached['cached_at'] ) ? (int) $cached['cached_at'] : 0;

		if ( $cached_at <= 0 ) {
			return ! empty( $cached['tag_name'] );
		}

		$ttl = ! empty( $cached['failed'] ) ? self::CACHE_TTL_FAILURE : self::CACHE_TTL;

		return ( time() - $cached_at ) < $ttl;
	}

	/**
	 * @param array<string, mixed> $cached Cached payload.
	 * @return array<string, string>
	 */
	private static function normalize_cached_release( array $cached ) {
		return array(
			'tag_name' => isset( $cached['tag_name'] ) ? (string) $cached['tag_name'] : '',
			'html_url' => isset( $cached['html_url'] ) ? (string) $cached['html_url'] : '',
		);
	}

	/**
	 * @param string $github_repo Owner/repo.
	 * @return string
	 */
	private static function get_cache_key( $github_repo ) {
		return 'art_mi_release_' . md5( self::sanitize_repo( $github_repo ) );
	}

	/**
	 * @param string $github_repo Owner/repo.
	 * @return string
	 */
	private static function sanitize_repo( $github_repo ) {
		$github_repo = strtolower( trim( (string) $github_repo ) );

		if ( ! preg_match( '/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/', $github_repo ) ) {
			return '';
		}

		return $github_repo;
	}
}
