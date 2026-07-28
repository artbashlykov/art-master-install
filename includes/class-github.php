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

	const CACHE_TTL          = 21600; // 6 hours.
	const CACHE_TTL_FAILURE  = 900; // 15 minutes.
	const CACHE_EPOCH_OPTION = 'art_master_install_release_cache_epoch';

	/**
	 * Successful API fetches in the current request.
	 *
	 * @var int
	 */
	private static $fetch_ok = 0;

	/**
	 * Failed API fetches in the current request.
	 *
	 * @var int
	 */
	private static $fetch_errors = 0;

	/**
	 * GitHub personal access token (optional, raises API rate limits).
	 *
	 * Add to wp-config.php:
	 * define( 'ART_MASTER_INSTALL_GITHUB_TOKEN', 'your-github-token' );
	 *
	 * @return string
	 */
	public static function get_access_token() {
		$token = '';

		if ( defined( 'ART_MASTER_INSTALL_GITHUB_TOKEN' ) ) {
			$token = (string) ART_MASTER_INSTALL_GITHUB_TOKEN;
		}

		/**
		 * Filters GitHub token used for ART Master Install API requests.
		 *
		 * @param string $token GitHub personal access token.
		 */
		$token = (string) apply_filters( 'art_master_install_github_token', $token );

		return sanitize_text_field( $token );
	}

	/**
	 * HTTP headers required by the GitHub REST API.
	 *
	 * @return array<string, string>
	 */
	public static function get_api_headers() {
		$headers = array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'ART-Master-Install/' . ART_MASTER_INSTALL_VERSION,
		);

		$token = self::get_access_token();

		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		return $headers;
	}

	/**
	 * Reset per-request fetch counters before a catalog check.
	 */
	public static function reset_fetch_stats() {
		self::$fetch_ok     = 0;
		self::$fetch_errors = 0;
	}

	/**
	 * @return array{ok: int, errors: int}
	 */
	public static function get_fetch_stats() {
		return array(
			'ok'     => self::$fetch_ok,
			'errors' => self::$fetch_errors,
		);
	}

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
		$cached    = get_site_transient( $cache_key );

		// Forced refresh never reads cache. Cache keys include an epoch that
		// invalidate_all_release_caches() bumps, so object-cache stale hits after
		// delete_site_transient() cannot resurrect an old generation.
		if ( ! $force_refresh && self::is_usable_cache( $cached ) ) {
			return self::normalize_cached_release( $cached );
		}

		$response = wp_remote_get(
			sprintf( 'https://api.github.com/repos/%s/releases/latest', $github_repo ),
			array(
				'timeout' => 15,
				'headers' => self::get_api_headers(),
			)
		);

		if ( is_wp_error( $response ) ) {
			return self::handle_fetch_failure( $cache_key, $cached );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return self::handle_fetch_failure( $cache_key, $cached );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			return self::handle_fetch_failure( $cache_key, $cached );
		}

		++self::$fetch_ok;

		$release = array(
			'tag_name' => (string) $body['tag_name'],
		);

		self::store_release_cache( $cache_key, $release, false );

		return $release;
	}

	/**
	 * Invalidate every release cache by bumping the key epoch.
	 *
	 * Prefer this over delete_site_transient() loops: object caches may keep
	 * serving a deleted key until expiry, which previously made "Проверить
	 * обновления" reuse stale release versions.
	 */
	public static function clear_catalog_release_caches() {
		self::bump_cache_epoch();
	}

	/**
	 * Drop cached release data for a repository (current epoch only).
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
	 * @param string                    $cache_key Transient key.
	 * @param array<string, mixed>|mixed $cached    Previous cache value.
	 * @return array<string, string>
	 */
	private static function handle_fetch_failure( $cache_key, $cached ) {
		++self::$fetch_errors;

		// Never overwrite a known-good release with an empty failure payload.
		if ( is_array( $cached ) && ! empty( $cached['tag_name'] ) ) {
			return self::normalize_cached_release( $cached );
		}

		self::store_release_cache( $cache_key, array(), true );

		return array();
	}

	/**
	 * @param string               $cache_key Transient key.
	 * @param array<string, mixed> $release   Release payload.
	 * @param bool                 $failed    Whether the fetch failed.
	 */
	private static function store_release_cache( $cache_key, array $release, $failed ) {
		$payload = array(
			'tag_name'  => isset( $release['tag_name'] ) ? (string) $release['tag_name'] : '',
			'cached_at' => time(),
			'failed'    => (bool) $failed,
		);

		$ttl = $failed ? self::CACHE_TTL_FAILURE : self::CACHE_TTL;

		set_site_transient( $cache_key, $payload, $ttl );
	}

	/**
	 * @param mixed $cached Cached payload.
	 * @return bool
	 */
	private static function is_usable_cache( $cached ) {
		if ( ! is_array( $cached ) ) {
			return false;
		}

		if ( ! self::is_cache_fresh( $cached ) ) {
			return false;
		}

		// Empty failure markers must not block a retry forever.
		if ( ! empty( $cached['failed'] ) && empty( $cached['tag_name'] ) ) {
			return false;
		}

		return ! empty( $cached['tag_name'] );
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
		);
	}

	/**
	 * Bump release-cache epoch so previous transient keys become unreachable.
	 */
	private static function bump_cache_epoch() {
		$epoch = (int) get_site_option( self::CACHE_EPOCH_OPTION, 1 );

		if ( $epoch < 1 ) {
			$epoch = 1;
		}

		update_site_option( self::CACHE_EPOCH_OPTION, $epoch + 1 );
	}

	/**
	 * @return int
	 */
	private static function get_cache_epoch() {
		$epoch = (int) get_site_option( self::CACHE_EPOCH_OPTION, 1 );

		return max( 1, $epoch );
	}

	/**
	 * @param string $github_repo Owner/repo.
	 * @return string
	 */
	private static function get_cache_key( $github_repo ) {
		return 'art_mi_rel_' . self::get_cache_epoch() . '_' . md5( self::sanitize_repo( $github_repo ) );
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
