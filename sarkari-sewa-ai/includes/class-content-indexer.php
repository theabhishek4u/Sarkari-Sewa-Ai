<?php
/**
 * Content Indexer and Lifecycle Synchronization.
 *
 * @package Sarkari_Sewa_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sarkari_Sewa_AI_Content_Indexer {

	/**
	 * Register WordPress post lifecycle hooks.
	 */
	public function init() {
		$settings = get_option( 'sarkari_sewa_ai_settings', array() );

		if ( ! empty( $settings['auto_indexing'] ) ) {
			add_action( 'save_post', array( $this, 'handle_save_post' ), 10, 3 );
			add_action( 'wp_trash_post', array( $this, 'handle_delete_post' ) );
			add_action( 'before_delete_post', array( $this, 'handle_delete_post' ) );
		}
	}

	/**
	 * Handle post save / update.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @param bool    $update Whether this is an existing post being updated.
	 */
	public function handle_save_post( $post_id, $post, $update ) {
		// Ignore autosaves, revisions, or non-WP_Post
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$settings = get_option( 'sarkari_sewa_ai_settings', array() );
		$allowed_post_types = ! empty( $settings['enabled_post_types'] ) ? $settings['enabled_post_types'] : array( 'post', 'page' );

		if ( ! in_array( $post->post_type, $allowed_post_types, true ) ) {
			return;
		}

		// Only index published posts
		if ( $post->post_status !== 'publish' ) {
			$this->handle_delete_post( $post_id );
			return;
		}

		// Index post asynchronously or synchronously
		$this->index_single_post( $post_id );
	}

	/**
	 * Handle post delete or trash.
	 *
	 * @param int $post_id
	 */
	public function handle_delete_post( $post_id ) {
		global $wpdb;

		$index_table  = $wpdb->prefix . 'sarkari_ai_index';
		$chunks_table = $wpdb->prefix . 'sarkari_ai_chunks';

		// Remove from local database
		$wpdb->delete( $index_table, array( 'post_id' => $post_id ), array( '%d' ) );
		$wpdb->delete( $chunks_table, array( 'post_id' => $post_id ), array( '%d' ) );

		// Remove from vector database
		$vector_provider = Sarkari_Sewa_AI_Vector_Factory::get_provider();
		$vector_provider->delete_vectors_by_post_id( $post_id );

		// Invalidate cache
		Sarkari_Sewa_AI_Cache::invalidate_all_query_caches();

		Sarkari_Sewa_AI_Logger::log(
			'index_deleted',
			"Removed index for post ID {$post_id}",
			array( 'post_id' => $post_id )
		);
	}

	/**
	 * Index a single post.
	 *
	 * @param int|WP_Post $post Post ID or WP_Post.
	 * @return array Status of indexing operation.
	 */
	public function index_single_post( $post ) {
		global $wpdb;

		$index_table  = $wpdb->prefix . 'sarkari_ai_index';
		$chunks_table = $wpdb->prefix . 'sarkari_ai_chunks';

		$parsed = Sarkari_Sewa_AI_Content_Parser::parse_post( $post );
		if ( ! $parsed ) {
			return array( 'success' => false, 'message' => 'Post not publishable or invalid' );
		}

		$post_id      = $parsed['post_id'];
		$content_hash = $parsed['content_hash'];

		// Check if already indexed with matching hash
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, content_hash FROM {$index_table} WHERE post_id = %d", $post_id ), ARRAY_A );

		if ( $existing && $existing['content_hash'] === $content_hash ) {
			return array( 'success' => true, 'message' => 'Post already indexed and unchanged', 'skipped' => true );
		}

		// 1. Create semantic chunks
		$chunks = Sarkari_Sewa_AI_Chunk_Manager::create_chunks( $parsed );
		if ( empty( $chunks ) ) {
			return array( 'success' => false, 'message' => 'No readable content to index' );
		}

		// 2. Generate embeddings for chunks if semantic search is active
		$settings = get_option( 'sarkari_sewa_ai_settings', array() );
		$chunk_texts = array_column( $chunks, 'chunk_content' );
		$embeddings  = array();

		if ( ! empty( $settings['enable_semantic_search'] ) ) {
			$embedding_provider = Sarkari_Sewa_AI_Embedding_Factory::get_provider();
			$emb_res            = $embedding_provider->generate_embeddings( $chunk_texts );

			if ( ! empty( $emb_res['success'] ) && ! empty( $emb_res['embeddings'] ) ) {
				$embeddings = $emb_res['embeddings'];
			}
		}

		// 3. Upsert to vector database
		$vector_documents = array();
		if ( ! empty( $embeddings ) ) {
			foreach ( $chunks as $i => $chunk ) {
				if ( isset( $embeddings[ $i ] ) ) {
					$vector_documents[] = array(
						'post_id'     => $post_id,
						'chunk_index' => $chunk['chunk_index'],
						'content'     => $chunk['chunk_content'],
						'metadata'    => $parsed['metadata'],
						'embedding'   => $embeddings[ $i ],
					);
				}
			}

			$vector_provider = Sarkari_Sewa_AI_Vector_Factory::get_provider();
			$vector_provider->upsert_vectors( $vector_documents );
		}

		// 4. Save to local chunks table (replace old chunks)
		$wpdb->delete( $chunks_table, array( 'post_id' => $post_id ), array( '%d' ) );

		foreach ( $chunks as $chunk ) {
			$wpdb->insert(
				$chunks_table,
				array(
					'post_id'       => $post_id,
					'chunk_index'   => $chunk['chunk_index'],
					'chunk_content' => $chunk['chunk_content'],
					'chunk_tokens'  => $chunk['chunk_tokens'],
					'metadata_json' => wp_json_encode( $chunk['metadata'] ),
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%d', '%s', '%s' )
			);
		}

		// 5. Update index tracker table
		$wpdb->replace(
			$index_table,
			array(
				'post_id'      => $post_id,
				'post_type'    => $parsed['post_type'],
				'title'        => $parsed['title'],
				'url'          => $parsed['url'],
				'category'     => $parsed['category'],
				'content_hash' => $content_hash,
				'status'       => 'indexed',
				'chunk_count'  => count( $chunks ),
				'last_error'   => null,
				'indexed_at'   => current_time( 'mysql' ),
				'updated_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		update_option( 'sarkari_sewa_ai_last_sync', current_time( 'mysql' ) );
		Sarkari_Sewa_AI_Cache::invalidate_all_query_caches();

		return array(
			'success'     => true,
			'post_id'     => $post_id,
			'title'       => $parsed['title'],
			'chunk_count' => count( $chunks ),
		);
	}

	/**
	 * Process a batch of posts for bulk indexing.
	 *
	 * @param int $offset
	 * @param int $batch_size
	 * @return array
	 */
	public function process_batch( $offset = 0, $batch_size = 25 ) {
		$settings = get_option( 'sarkari_sewa_ai_settings', array() );
		$post_types = ! empty( $settings['enabled_post_types'] ) ? $settings['enabled_post_types'] : array( 'post', 'page' );

		// Count total publish posts
		$total_posts = 0;
		foreach ( $post_types as $pt ) {
			$counts = wp_count_posts( $pt );
			$total_posts += isset( $counts->publish ) ? (int) $counts->publish : 0;
		}

		// Fetch current batch
		$posts = get_posts( array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $batch_size,
			'offset'         => $offset,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
		) );

		if ( empty( $posts ) ) {
			return array(
				'completed'       => true,
				'processed_count' => 0,
				'total_posts'     => $total_posts,
				'next_offset'     => $offset,
				'percentage'      => 100,
			);
		}

		$processed = 0;
		$errors    = array();

		foreach ( $posts as $pid ) {
			$result = $this->index_single_post( $pid );
			if ( $result['success'] ) {
				$processed++;
			} else {
				$errors[] = array( 'post_id' => $pid, 'error' => $result['message'] );
			}
		}

		$next_offset = $offset + count( $posts );
		$is_done     = $next_offset >= $total_posts;
		$percentage  = $total_posts > 0 ? min( 100, round( ( $next_offset / $total_posts ) * 100, 1 ) ) : 100;

		return array(
			'completed'       => $is_done,
			'batch_count'     => count( $posts ),
			'processed_count' => $processed,
			'total_posts'     => $total_posts,
			'next_offset'     => $next_offset,
			'percentage'      => $percentage,
			'errors'          => $errors,
		);
	}
}
