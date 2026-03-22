<?php
/**
 * Firecrawl integration – scrape URLs via the Firecrawl API with WordPress transient caching.
 *
 * @package NaanoAIWebsiteBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Firecrawl API calls and caches results so the same URL is never scraped twice.
 */
class Naano_Firecrawl {

	private const API_URL       = 'https://api.firecrawl.dev/v2/scrape';
	private const CACHE_PREFIX  = 'naano_fc_';
	private const CACHE_TTL     = 90 * DAY_IN_SECONDS; // 90 days.
	private const TIMEOUT       = 30;

	/**
	 * Check whether Firecrawl is configured (API key is set).
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		return (bool) get_option( 'naano_firecrawl_api_key', '' );
	}

	/**
	 * Scrape a URL and return the cleaned HTML.
	 *
	 * Results are cached in a WordPress transient keyed by URL hash,
	 * so the same URL will not be scraped more than once within the TTL.
	 *
	 * @param string $url The URL to scrape.
	 * @return string HTML content, or empty string on failure.
	 */
	public static function scrape( string $url ): string {
		if ( ! preg_match( '/^https?:\/\//i', $url ) ) {
			return '';
		}

		$api_key = get_option( 'naano_firecrawl_api_key', '' );
		if ( ! $api_key ) {
			return '';
		}

		// Check cache first.
		$cache_key = self::CACHE_PREFIX . md5( $url );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$html = self::call_api( $url, $api_key );

		// Cache even empty results to avoid retrying broken URLs.
		set_transient( $cache_key, $html, self::CACHE_TTL );

		return $html;
	}

	/**
	 * Call the Firecrawl scrape API.
	 *
	 * @param string $url     URL to scrape.
	 * @param string $api_key Firecrawl API key.
	 * @return string HTML content or empty string.
	 */
	private static function call_api( string $url, string $api_key ): string {
		$body = wp_json_encode( [
			'url'             => $url,
			'formats'         => [ 'html' ],
			'onlyMainContent' => true,
		] );

		$response = wp_remote_post( self::API_URL, [
			'timeout' => self::TIMEOUT,
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			],
			'body'    => $body,
		] );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return '';
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['success'] ) || empty( $data['data']['html'] ) ) {
			return '';
		}

		return $data['data']['html'];
	}
}
