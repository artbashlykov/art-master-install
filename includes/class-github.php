<?php
/**
 * GitHub Releases helpers for ART catalog repositories.
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
	 * Successful release lookups in the current request.
	 *
	 * @var int
	 */
	private static $fetch_ok = 0;

	/**
	 * Failed release lookups in the current request.
	 *
	 * @var int
	 */
	private static $fetch_errors = 0;

	/**
	 * Last transport / HTTP error message for diagnostics.
	 *
	 * @var string
	 */
	private static $last_error = '';

	/**
	 * GitHub personal access token (optional; needed for private repos / higher rate limits).
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
		$token = trim( $token );

		// Do not use sanitize_text_field() — it can alter valid tokens.
		$token = preg_replace( '/\s+/', '', $token );

		return is_string( $token ) ? $token : '';
	}

	/**
	 * HTTP headers for GitHub API requests.
	 *
	 * @return array<string, string>
	 */
	public static function get_api_headers() {
		$headers = array(
			'Accept'               => 'application/vnd.github+json',
			'User-Agent'           => 'ART-Master-Install/' . ART_MASTER_INSTALL_VERSION,
			'X-GitHub-Api-Version' => '2022-11-28',
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
		self::$last_error   = '';
	}

	/**
	 * @return array{ok: int, errors: int, last_error: string}
	 */
	public static function get_fetch_stats() {
		return array(
			'ok'         => self::$fetch_ok,
			'errors'     => self::$fetch_errors,
			'last_error' => self::$last_error,
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

		if ( ! $force_refresh && self::is_usable_cache( $cached ) ) {
			return self::normalize_cached_release( $cached );
		}

		// Keep previous-epoch data as a soft fallback after a force clear.
		if ( ! self::is_usable_cache( $cached ) ) {
			$cached = self::get_previous_epoch_cache( $github_repo );
		}

		$tag_name = self::fetch_latest_tag( $github_repo );

		if ( '' !== $tag_name ) {
			++self::$fetch_ok;

			$release = array(
				'tag_name' => $tag_name,
			);

			self::store_release_cache( $cache_key, $release, false );

			return $release;
		}

		return self::handle_fetch_failure( $cache_key, $cached );
	}

	/**
	 * Invalidate every release cache by bumping the key epoch.
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

		$cache_key = self::get_cache_key( $github_repo );
		delete_site_transient( $cache_key );

		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( $cache_key, 'site-transient' );
		}
	}

	/**
	 * Resolve the latest release tag via API, then HTML redirect / atom fallbacks.
	 *
	 * @param string $github_repo Owner/repo.
	 * @return string Tag name or empty string.
	 */
	private static function fetch_latest_tag( $github_repo ) {
		$tag = self::fetch_tag_from_api( $github_repo );

		if ( '' !== $tag ) {
			return $tag;
		}

		$tag = self::fetch_tag_from_latest_redirect( $github_repo );

		if ( '' !== $tag ) {
			return $tag;
		}

		return self::fetch_tag_from_atom( $github_repo );
	}

	/**
	 * @param string $github_repo Owner/repo.
	 * @return string
	 */
	private static function fetch_tag_from_api( $github_repo ) {
		$response = self::remote_get(
			sprintf( 'https://api.github.com/repos/%s/releases/latest', $github_repo ),
			array(
				'headers' => self::get_api_headers(),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::remember_error( $response->get_error_message() );
			return '';
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		// Invalid / expired token makes even public repos return 401 — retry without auth.
		if ( in_array( $code, array( 401, 403 ), true ) && '' !== self::get_access_token() ) {
			$headers = self::get_api_headers();
			unset( $headers['Authorization'] );

			$response = self::remote_get(
				sprintf( 'https://api.github.com/repos/%s/releases/latest', $github_repo ),
				array(
					'headers' => $headers,
				)
			);

			if ( is_wp_error( $response ) ) {
				self::remember_error( $response->get_error_message() );
				return '';
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
		}

		if ( 200 !== $code ) {
			self::remember_error(
				sprintf(
					/* translators: 1: HTTP status code, 2: repository */
					__( 'GitHub API HTTP %1$d for %2$s', 'art-master-install' ),
					$code,
					$github_repo
				)
			);
			return '';
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			self::remember_error( __( 'GitHub API returned an empty release payload.', 'art-master-install' ) );
			return '';
		}

		return (string) $body['tag_name'];
	}

	/**
	 * Public repos expose /releases/latest as a 302 to /releases/tag/{version}.
	 *
	 * @param string $github_repo Owner/repo.
	 * @return string
	 */
	private static function fetch_tag_from_latest_redirect( $github_repo ) {
		$url = sprintf( 'https://github.com/%s/releases/latest', $github_repo );

		$response = self::remote_get(
			$url,
			array(
				'redirection' => 0,
				'headers'     => array(
					'User-Agent' => 'ART-Master-Install/' . ART_MASTER_INSTALL_VERSION,
					'Accept'     => 'text/html',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::remember_error( $response->get_error_message() );
			return '';
		}

		$code     = (int) wp_remote_retrieve_response_code( $response );
		$location = (string) wp_remote_retrieve_header( $response, 'location' );

		if ( '' === $location && $code >= 300 && $code < 400 ) {
			$headers = wp_remote_retrieve_headers( $response );
			if ( is_object( $headers ) && isset( $headers['location'] ) ) {
				$location = (string) $headers['location'];
			}
		}

		$tag = self::extract_tag_from_url( $location );

		if ( '' !== $tag ) {
			return $tag;
		}

		// Some stacks follow redirects even when redirection => 0.
		$final_url = self::get_effective_url( $response );
		$tag       = self::extract_tag_from_url( $final_url );

		if ( '' !== $tag ) {
			return $tag;
		}

		self::remember_error(
			sprintf(
				/* translators: %s: repository */
				__( 'Could not resolve latest release redirect for %s', 'art-master-install' ),
				$github_repo
			)
		);

		return '';
	}

	/**
	 * @param string $github_repo Owner/repo.
	 * @return string
	 */
	private static function fetch_tag_from_atom( $github_repo ) {
		$response = self::remote_get(
			sprintf( 'https://github.com/%s/releases.atom', $github_repo ),
			array(
				'headers' => array(
					'User-Agent' => 'ART-Master-Install/' . ART_MASTER_INSTALL_VERSION,
					'Accept'     => 'application/atom+xml,application/xml,text/xml,*/*',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::remember_error( $response->get_error_message() );
			return '';
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$body = (string) wp_remote_retrieve_body( $response );

		if ( preg_match( '#<entry>.*?<id>[^<]*/releases/tag/([^<]+)</id>#s', $body, $matches ) ) {
			return rawurldecode( trim( $matches[1] ) );
		}

		if ( preg_match( '#<link[^>]+href="[^"]*/releases/tag/([^"]+)"#', $body, $matches ) ) {
			return rawurldecode( trim( $matches[1] ) );
		}

		return '';
	}

	/**
	 * @param string               $url  Request URL.
	 * @param array<string, mixed> $args wp_remote_get args.
	 * @return array|WP_Error
	 */
	private static function remote_get( $url, array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'timeout' => 20,
			)
		);

		$response = wp_remote_get( $url, $args );

		if ( ! is_wp_error( $response ) ) {
			return $response;
		}

		$message = $response->get_error_message();

		// Local/Windows often fails GitHub TLS because of certificate revocation checks.
		if ( self::is_ssl_error( $message ) ) {
			$args['sslverify'] = false;
			$retry             = wp_remote_get( $url, $args );

			if ( ! is_wp_error( $retry ) ) {
				return $retry;
			}

			return $retry;
		}

		return $response;
	}

	/**
	 * @param string $message Transport error message.
	 * @return bool
	 */
	private static function is_ssl_error( $message ) {
		$message = strtolower( (string) $message );

		return false !== strpos( $message, 'ssl' )
			|| false !== strpos( $message, 'certificate' )
			|| false !== strpos( $message, 'revocation' )
			|| false !== strpos( $message, 'curl error 35' )
			|| false !== strpos( $message, 'curl error 60' );
	}

	/**
	 * @param array|WP_Error $response HTTP response.
	 * @return string
	 */
	private static function get_effective_url( $response ) {
		if ( ! is_array( $response ) || empty( $response['http_response'] ) || ! is_object( $response['http_response'] ) ) {
			return '';
		}

		if ( ! method_exists( $response['http_response'], 'get_response_object' ) ) {
			return '';
		}

		$object = $response['http_response']->get_response_object();

		if ( is_object( $object ) && ! empty( $object->url ) ) {
			return (string) $object->url;
		}

		return '';
	}

	/**
	 * @param string $url Redirect or final URL.
	 * @return string
	 */
	private static function extract_tag_from_url( $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return '';
		}

		if ( preg_match( '#/releases/tag/([^/?#]+)#', $url, $matches ) ) {
			return rawurldecode( $matches[1] );
		}

		return '';
	}

	/**
	 * @param string $message Error text.
	 */
	private static function remember_error( $message ) {
		$message = trim( (string) $message );

		if ( '' === $message ) {
			return;
		}

		self::$last_error = $message;
	}

	/**
	 * @param string                     $cache_key Transient key.
	 * @param array<string, mixed>|mixed $cached    Previous cache value.
	 * @return array<string, string>
	 */
	private static function handle_fetch_failure( $cache_key, $cached ) {
		// Prefer last known good tag over a hard failure — catalog stays usable.
		if ( is_array( $cached ) && ! empty( $cached['tag_name'] ) ) {
			++self::$fetch_ok;
			return self::normalize_cached_release( $cached );
		}

		++self::$fetch_errors;
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

		if ( empty( $cached['tag_name'] ) ) {
			return false;
		}

		if ( ! empty( $cached['failed'] ) ) {
			return false;
		}

		return self::is_cache_fresh( $cached );
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
	 * Read release cache from the previous epoch after a force clear.
	 *
	 * @param string $github_repo Owner/repo.
	 * @return array<string, mixed>|false
	 */
	private static function get_previous_epoch_cache( $github_repo ) {
		$epoch = self::get_cache_epoch();

		if ( $epoch <= 1 ) {
			return false;
		}

		$key    = 'art_mi_rel_' . ( $epoch - 1 ) . '_' . md5( self::sanitize_repo( $github_repo ) );
		$cached = get_site_transient( $key );

		return self::is_usable_cache( $cached ) ? $cached : false;
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
