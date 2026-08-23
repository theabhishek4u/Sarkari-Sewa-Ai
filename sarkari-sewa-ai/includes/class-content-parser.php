<?php
/**
 * Content Parser and Metadata Extractor.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sarkari_Sewa_AI_Content_Parser {

	/**
	 * Parse and clean a WP_Post object into structured data.
	 *
	 * @param WP_Post|int $post Post object or post ID.
	 * @return array|false Structured post data or false on failure.
	 */
	public static function parse_post( $post ) {
		if ( is_numeric( $post ) ) {
			$post = get_post( $post );
		}

		if ( ! $post instanceof WP_Post || $post->post_status !== 'publish' ) {
			return false;
		}

		$post_id    = $post->ID;
		$title      = get_the_title( $post_id );
		$permalink  = get_permalink( $post_id );
		$post_type  = $post->post_type;

		// Extract raw categories and tags
		$categories = wp_get_post_terms( $post_id, 'category', array( 'fields' => 'names' ) );
		$tags       = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) );

		$primary_category = ! empty( $categories ) && ! is_wp_error( $categories ) ? $categories[0] : 'General';
		$category_list    = ! is_wp_error( $categories ) ? implode( ', ', $categories ) : '';
		$tag_list         = ! is_wp_error( $tags ) ? implode( ', ', $tags ) : '';

		// Clean content
		$raw_content   = $post->post_content;
		$clean_content = self::clean_html_content( $raw_content );

		// Extract intelligent metadata (Dates, Eligibility, Department)
		$metadata = self::extract_metadata( $title, $clean_content, $post_id );
		$metadata['category']     = $primary_category;
		$metadata['categories']   = $categories;
		$metadata['tags']         = $tags;
		$metadata['post_id']      = $post_id;
		$metadata['post_type']    = $post_type;
		$metadata['url']          = $permalink;
		$metadata['title']        = $title;
		$metadata['published_at'] = get_the_date( 'c', $post_id );
		$metadata['updated_at']   = get_the_modified_date( 'c', $post_id );

		return array(
			'post_id'       => $post_id,
			'post_type'     => $post_type,
			'title'         => $title,
			'url'           => $permalink,
			'category'      => $primary_category,
			'clean_content' => $clean_content,
			'metadata'      => $metadata,
			'content_hash'  => md5( $title . $clean_content . $category_list . $tag_list ),
		);
	}

	/**
	 * Clean HTML content, removing scripts, styles, iframes, ads, and normalizing whitespace.
	 *
	 * @param string $html
	 * @return string
	 */
	public static function clean_html_content( $html ) {
		if ( empty( $html ) ) {
			return '';
		}

		// Remove scripts, styles, and embedded media
		$cleaned = preg_replace( '@<(script|style|iframe|noscript)[^>]*?>.*?</\\1>@si', '', $html );

		// Convert table headers & cells to spaced text
		$cleaned = preg_replace( '/<\/(th|td|tr|p|div|h[1-6]|li)>/i', "\n", $cleaned );

		// Strip all remaining HTML tags
		$cleaned = wp_strip_all_tags( $cleaned );

		// Decode HTML entities
		$cleaned = html_entity_decode( $cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Remove duplicate newlines and excessive whitespace
		$cleaned = preg_replace( "/[\r\n]+/", "\n", $cleaned );
		$cleaned = preg_replace( "/[ \t]+/", " ", $cleaned );

		return trim( $cleaned );
	}

	/**
	 * Extract structured metadata from text where reliably present.
	 *
	 * @param string $title
	 * @param string $content
	 * @param int    $post_id
	 * @return array
	 */
	private static function extract_metadata( $title, $content, $post_id ) {
		$data = array(
			'department' => '',
			'state'      => '',
			'education'  => '',
			'last_date'  => null,
			'status'     => 'active',
		);

		// Check for common Indian Government recruiting bodies
		$departments = array(
			'Railway' => array( 'RRB', 'RRC', 'Railway', 'Northern Railway', 'Western Railway' ),
			'SSC'     => array( 'SSC', 'Staff Selection Commission', 'CGL', 'CHSL', 'MTS', 'GD Constable' ),
			'UPSC'    => array( 'UPSC', 'Union Public Service Commission', 'NDA', 'CDS', 'Civil Services' ),
			'Banking' => array( 'IBPS', 'SBI', 'RBI', 'Bank PO', 'Clerk' ),
			'Defence' => array( 'Indian Army', 'Indian Navy', 'Indian Air Force', 'Agniveer', 'ITBP', 'CISF', 'BSF', 'CRPF' ),
			'Police'  => array( 'UP Police', 'Bihar Police', 'Delhi Police', 'MP Police', 'Police Constable', 'SI' ),
			'Teaching'=> array( 'BPSC Teacher', 'CTET', 'REET', 'UPTET', 'KVS', 'NVS', 'Super TET' ),
		);

		$combined_text = $title . ' ' . substr( $content, 0, 1500 );

		foreach ( $departments as $dept_name => $keywords ) {
			foreach ( $keywords as $kw ) {
				if ( stripos( $combined_text, $kw ) !== false ) {
					$data['department'] = $dept_name;
					break 2;
				}
			}
		}

		// State detection
		$states = array( 'Uttar Pradesh', 'Bihar', 'Rajasthan', 'Madhya Pradesh', 'Haryana', 'Delhi', 'Jharkhand', 'Uttarakhand', 'Maharashtra' );
		foreach ( $states as $st ) {
			if ( stripos( $combined_text, $st ) !== false || stripos( $combined_text, explode( ' ', $st )[0] ) !== false ) {
				$data['state'] = $st;
				break;
			}
		}

		// Education eligibility detection
		$education_levels = array(
			'10th Pass' => array( '10th', 'Matric', 'High School', 'Class 10' ),
			'12th Pass' => array( '12th', 'Intermediate', '10+2', 'Class 12' ),
			'Graduate'  => array( 'Graduate', 'Degree', 'B.A', 'B.Sc', 'B.Com', 'B.Tech', 'Graduation' ),
			'Post Graduate' => array( 'Post Graduate', 'Master', 'M.A', 'M.Sc', 'M.Com', 'M.Tech' ),
			'Diploma'   => array( 'Diploma', 'Polytechnic', 'ITI' ),
		);

		foreach ( $education_levels as $level => $terms ) {
			foreach ( $terms as $term ) {
				if ( stripos( $combined_text, $term ) !== false ) {
					$data['education'] = $level;
					break 2;
				}
			}
		}

		// Last date regex extraction: e.g. "Last Date : 25/09/2026" or "Last Date to Apply: 15-10-2026"
		if ( preg_match( '/(?:Last Date|Closing Date|Apply Online Till)[^\d]{1,25}(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i', $combined_text, $matches ) ) {
			$raw_date = $matches[1];
			$timestamp = strtotime( str_replace( '/', '-', $raw_date ) );
			if ( $timestamp && $timestamp > 0 ) {
				$data['last_date'] = gmdate( 'Y-m-d', $timestamp );
				// Determine if expired
				if ( $timestamp < current_time( 'timestamp' ) ) {
					$data['status'] = 'expired';
				}
			}
		}

		return $data;
	}
}
