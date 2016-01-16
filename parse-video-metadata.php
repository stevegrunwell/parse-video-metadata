<?php
/**
 * Plugin Name: Parse Video Metadata
 * Description: Extract meta data from videos uploaded to the media gallery.
 * Version:     0.1.0
 * Author:      Steve Grunwell
 * Author URI:  https://stevegrunwell.com
 * License:     MIT
 * Text Domain: parse-video-metadata
 *
 * @package ParseVideoMetadata
 * @author  Steve Grunwell
 */

namespace ParseVideoMetadata;

/**
 * This plugin is unnecessary once wp_get_media_creation_timestamp() is defined.
 *
 * @link https://core.trac.wordpress.org/attachment/ticket/35218
 */
if ( function_exists( '\wp_get_media_creation_timestamp' ) ) {
	return;
}

/**
 * Retrieve the creation timestamp out of a video upon upload.
 *
 * @param array $metadata      An array of attachment meta data.
 * @param int   $attachment_id Current attachment ID.
 */
function get_video_metadata( $metadata, $attachment_id ) {
	if ( ! wp_attachment_is( 'video', $attachment_id ) ) {
		return $metadata;
	}

	if ( ! class_exists( 'getID3', false ) ) {
		require_once ABSPATH . WPINC . '/ID3/getid3.php';
	}

	$file = get_attached_file( $attachment_id );
	$id3  = new \getID3();
	$data = $id3->analyze( $file );

	if ( empty( $metadata['created_timestamp'] ) ) {
		$metadata['created_timestamp'] = wp_get_media_creation_timestamp( $data );
	}

	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', __NAMESPACE__ . '\get_video_metadata', 10, 2 );

/**
 * Attempt to parse a date out of ID3 data.
 *
 * The getID3 library doesn't have a standard method for getting creation dates,
 * so the location of this data can vary based on the MIME type.
 *
 * @link https://github.com/JamesHeinrich/getID3/blob/master/structure.txt
 *
 * @param array $metadata The metadata returned by getID3::analyze().
 * @return int|bool A UNIX timestamp for the media's creation date if available
 *                  or a boolean FALSE if a timestamp could not be determined.
 */
function wp_get_media_creation_timestamp( $metadata ) {
	$creation_date = false;

	if ( empty( $metadata['fileformat'] ) ) {
		return $creation_date;
	}

	switch ( $metadata['fileformat'] ) {
		case 'asf':
			if ( isset( $metadata['asf']['file_properties_object']['creation_date_unix'] ) ) {
				$creation_date = (int) $metadata['asf']['file_properties_object']['creation_date_unix'];
			}
		break;

		case 'matroska':
			if ( isset( $metadata['matroska']['comments']['creation_time']['0'] ) ) {
				$creation_date = strtotime( $metadata['matroska']['comments']['creation_time']['0'] );
			}
		break;

		case 'quicktime':
		case 'mp4':
			if ( isset( $metadata['quicktime']['moov']['subatoms']['0']['creation_time_unix'] ) ) {
				$creation_date = (int) $metadata['quicktime']['moov']['subatoms']['0']['creation_time_unix'];
			}
		break;
	}

	return $creation_date;
}
