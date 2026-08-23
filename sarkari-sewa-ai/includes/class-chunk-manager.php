<?php
/**
 * Intelligent Content Chunk Manager.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sarkari_Sewa_AI_Chunk_Manager {

	const TARGET_CHUNK_SIZE = 1200; // Characters (~250-300 words)
	const CHUNK_OVERLAP     = 200;  // Characters overlap

	/**
	 * Break parsed post content into chunks with contextual metadata.
	 *
	 * @param array $parsed_post Structured data from Content_Parser.
	 * @return array Array of chunk items.
	 */
	public static function create_chunks( $parsed_post ) {
		if ( empty( $parsed_post ) || ! isset( $parsed_post['clean_content'] ) ) {
			return array();
		}

		$post_id   = $parsed_post['post_id'];
		$title     = $parsed_post['title'];
		$url       = $parsed_post['url'];
		$category  = $parsed_post['category'];
		$content   = $parsed_post['clean_content'];
		$metadata  = isset( $parsed_post['metadata'] ) ? $parsed_post['metadata'] : array();

		// Header prefix to inject at start of each chunk for better embedding context
		$context_prefix = "Post: {$title} | Category: {$category}\n";
		if ( ! empty( $metadata['department'] ) ) {
			$context_prefix .= "Department: {$metadata['department']} | ";
		}
		if ( ! empty( $metadata['education'] ) ) {
			$context_prefix .= "Education: {$metadata['education']} | ";
		}
		if ( ! empty( $metadata['last_date'] ) ) {
			$context_prefix .= "Last Date: {$metadata['last_date']} | ";
		}
		$context_prefix = rtrim( $context_prefix, " | " ) . "\n\n";

		$chunks = array();
		$content_len = mb_strlen( $content );

		// If content is short enough, create a single chunk
		if ( $content_len <= self::TARGET_CHUNK_SIZE ) {
			$full_chunk_text = $context_prefix . $content;
			$chunks[] = array(
				'post_id'       => $post_id,
				'chunk_index'   => 0,
				'chunk_content' => $full_chunk_text,
				'chunk_tokens'  => self::estimate_tokens( $full_chunk_text ),
				'metadata'      => $metadata,
			);
			return $chunks;
		}

		// Split by paragraphs first for clean semantic breaks
		$paragraphs = explode( "\n", $content );
		$current_chunk = '';
		$chunk_index   = 0;

		foreach ( $paragraphs as $para ) {
			$para = trim( $para );
			if ( empty( $para ) ) {
				continue;
			}

			if ( mb_strlen( $current_chunk . "\n" . $para ) > self::TARGET_CHUNK_SIZE && ! empty( $current_chunk ) ) {
				$full_chunk_text = $context_prefix . $current_chunk;
				$chunks[] = array(
					'post_id'       => $post_id,
					'chunk_index'   => $chunk_index,
					'chunk_content' => $full_chunk_text,
					'chunk_tokens'  => self::estimate_tokens( $full_chunk_text ),
					'metadata'      => $metadata,
				);
				$chunk_index++;

				// Carry over overlap
				$current_chunk = mb_substr( $current_chunk, -self::CHUNK_OVERLAP ) . "\n" . $para;
			} else {
				$current_chunk = empty( $current_chunk ) ? $para : $current_chunk . "\n" . $para;
			}
		}

		// Add last remaining chunk
		if ( ! empty( $current_chunk ) ) {
			$full_chunk_text = $context_prefix . $current_chunk;
			$chunks[] = array(
				'post_id'       => $post_id,
				'chunk_index'   => $chunk_index,
				'chunk_content' => $full_chunk_text,
				'chunk_tokens'  => self::estimate_tokens( $full_chunk_text ),
				'metadata'      => $metadata,
			);
		}

		return $chunks;
	}

	/**
	 * Approximate token count (~4 characters per token).
	 *
	 * @param string $text
	 * @return int
	 */
	public static function estimate_tokens( $text ) {
		return (int) ceil( mb_strlen( $text ) / 4 );
	}
}
