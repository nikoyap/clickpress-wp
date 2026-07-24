<?php
/**
 * Plugin Name: ClickPress WP
 * Plugin URI: https://github.com/nikoyap/clickup-featured-image-importer
 * Description: Automatically imports ClickUp attachment images into the WordPress Media Library and assigns them as featured images for posts created through the REST API.
 * Version: 1.0.0
 * Author: Niko Yap
 * Author URI: https://github.com/nikoyap
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: clickpress-wp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ClickPress_WP {
	const SOURCE_URL_META   = '_clickpress_source_url';
	const SOURCE_FIELD_META = '_clickpress_source_field';
	const PROCESSING_META   = '_clickpress_processing';
	const RETRY_HOOK        = 'clickpress_retry_import';

	public function __construct() {
		add_filter( 'rest_pre_dispatch', array( $this, 'capture_rest_request' ), 10, 3 );
		add_action( 'rest_after_insert_post', array( $this, 'handle_rest_post' ), 20, 3 );
		add_action( self::RETRY_HOOK, array( $this, 'process_post' ), 10, 1 );
	}

	public function capture_rest_request( $result, $server, $request ) {
		if ( ! $request instanceof WP_REST_Request ) {
			return $result;
		}

		if (
			'POST' !== strtoupper( $request->get_method() ) ||
			! preg_match( '#^/wp/v2/posts/?$#', $request->get_route() )
		) {
			return $result;
		}

		$GLOBALS['clickpress_request_data'] = $this->find_clickup_url( $request->get_params() );

		return $result;
	}

	public function handle_rest_post( $post, $request, $creating ) {
		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
			return;
		}

		$found = isset( $GLOBALS['clickpress_request_data'] )
			? $GLOBALS['clickpress_request_data']
			: array(
				'url'   => '',
				'field' => '',
			);

		if ( ! empty( $found['url'] ) ) {
			update_post_meta( $post->ID, self::SOURCE_URL_META, esc_url_raw( $found['url'] ) );
			update_post_meta( $post->ID, self::SOURCE_FIELD_META, sanitize_text_field( $found['field'] ) );
		}

		$this->process_post( $post->ID );

		if ( ! has_post_thumbnail( $post->ID ) && ! wp_next_scheduled( self::RETRY_HOOK, array( $post->ID ) ) ) {
			wp_schedule_single_event( time() + 20, self::RETRY_HOOK, array( $post->ID ) );
		}
	}

	public function process_post( $post_id ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );

		if ( ! $post || 'post' !== $post->post_type || has_post_thumbnail( $post_id ) ) {
			return;
		}

		if ( get_post_meta( $post_id, self::PROCESSING_META, true ) ) {
			return;
		}

		update_post_meta( $post_id, self::PROCESSING_META, time() );

		try {
			$original_url = get_post_meta( $post_id, self::SOURCE_URL_META, true );

			if ( ! $original_url ) {
				$original_url = $this->extract_clickup_url( $post->post_excerpt );
			}

			if ( ! $original_url ) {
				$original_url = $this->extract_clickup_url( $post->post_content );
			}

			if ( ! $original_url ) {
				return;
			}

			$attempts     = array( $original_url );
			$stripped_url = $this->strip_query( $original_url );

			if ( $stripped_url && $stripped_url !== $original_url ) {
				$attempts[] = $stripped_url;
			}

			$download = null;

			foreach ( $attempts as $url ) {
				$download = $this->download_image( $url );

				if ( ! is_wp_error( $download ) ) {
					break;
				}
			}

			if ( is_wp_error( $download ) || ! $download ) {
				return;
			}

			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$file_array = array(
				'name'     => $this->build_filename( $download['url'], $download['mime'] ),
				'tmp_name' => $download['tmp'],
			);

			$attachment_id = media_handle_sideload( $file_array, $post_id, $post->post_title );

			if ( is_wp_error( $attachment_id ) ) {
				@unlink( $download['tmp'] );
				return;
			}

			update_post_meta(
				$attachment_id,
				'_wp_attachment_image_alt',
				sanitize_text_field( $post->post_title )
			);

			if ( ! set_post_thumbnail( $post_id, $attachment_id ) ) {
				wp_delete_attachment( $attachment_id, true );
				return;
			}

			$source_field = get_post_meta( $post_id, self::SOURCE_FIELD_META, true );

			if ( 'excerpt' === $source_field && ! empty( $post->post_excerpt ) ) {
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_excerpt' => '',
					)
				);
			}

			delete_post_meta( $post_id, self::SOURCE_URL_META );
			delete_post_meta( $post_id, self::SOURCE_FIELD_META );
		} finally {
			delete_post_meta( $post_id, self::PROCESSING_META );
		}
	}

	private function download_image( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 45,
				'redirection' => 5,
				'sslverify'   => true,
				'headers'     => array(
					'Accept'     => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
					'User-Agent' => 'Mozilla/5.0 WordPress ClickPress WP/1.0',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );

		if ( $status < 200 || $status >= 300 || '' === $body ) {
			return new WP_Error( 'clickpress_download_failed', 'The attachment could not be downloaded.' );
		}

		$tmp = wp_tempnam( $this->build_filename( $url, '' ) );

		if ( ! $tmp ) {
			return new WP_Error( 'clickpress_temp_failed', 'A temporary file could not be created.' );
		}

		if ( false === file_put_contents( $tmp, $body ) ) {
			@unlink( $tmp );
			return new WP_Error( 'clickpress_write_failed', 'The downloaded image could not be saved.' );
		}

		$image_info = @getimagesize( $tmp );

		if ( ! $image_info || empty( $image_info['mime'] ) || 0 !== strpos( $image_info['mime'], 'image/' ) ) {
			@unlink( $tmp );
			return new WP_Error( 'clickpress_invalid_image', 'The attachment response was not a valid image.' );
		}

		return array(
			'tmp'  => $tmp,
			'mime' => $image_info['mime'],
			'url'  => $url,
		);
	}

	private function find_clickup_url( $value, $path = '' ) {
		if ( is_string( $value ) ) {
			$url = $this->extract_clickup_url( $value );

			return $url
				? array(
					'url'   => $url,
					'field' => $path ? $path : '(root)',
				)
				: array(
					'url'   => '',
					'field' => '',
				);
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $key => $child ) {
				$child_path = '' === $path ? (string) $key : $path . '.' . $key;
				$found      = $this->find_clickup_url( $child, $child_path );

				if ( ! empty( $found['url'] ) ) {
					return $found;
				}
			}
		}

		return array(
			'url'   => '',
			'field' => '',
		);
	}

	private function extract_clickup_url( $value ) {
		$value = html_entity_decode(
			wp_strip_all_tags( (string) $value ),
			ENT_QUOTES | ENT_HTML5,
			'UTF-8'
		);

		if ( ! preg_match( '~https?://[^\s<>"\']+~i', $value, $matches ) ) {
			return '';
		}

		$url  = esc_url_raw( rtrim( $matches[0], '.,;)]}' ) );
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );

		return false !== stripos( $host, 'clickup-attachments.com' ) ? $url : '';
	}

	private function strip_query( $url ) {
		$parts = wp_parse_url( $url );

		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) || empty( $parts['path'] ) ) {
			return '';
		}

		$clean = $parts['scheme'] . '://' . $parts['host'];

		if ( ! empty( $parts['port'] ) ) {
			$clean .= ':' . absint( $parts['port'] );
		}

		return esc_url_raw( $clean . $parts['path'] );
	}

	private function build_filename( $url, $mime ) {
		$path     = (string) wp_parse_url( $url, PHP_URL_PATH );
		$filename = sanitize_file_name( wp_basename( $path ) );

		if ( $filename && false !== strpos( $filename, '.' ) ) {
			return $filename;
		}

		$mime = strtolower( trim( explode( ';', (string) $mime )[0] ) );

		$extensions = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
			'image/avif' => 'avif',
			'image/bmp'  => 'bmp',
			'image/tiff' => 'tiff',
		);

		$extension = isset( $extensions[ $mime ] ) ? $extensions[ $mime ] : 'jpg';

		return 'clickpress-featured-image-' . gmdate( 'Ymd-His' ) . '.' . $extension;
	}
}

new ClickPress_WP();
