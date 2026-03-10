<?php
/**
 * Plugin Name: Static Archive
 * Description: Generate a self-contained static HTML archive of your site's posts in the uploads directory.
 * Version: 1.0.0
 * Author: Alex Kirk
 * Author URI: https://alex.kirk.at/
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-generator.php';
require_once __DIR__ . '/includes/class-cli.php';

class Static_Archive {

	public function __construct() {
		add_action( 'transition_post_status', array( $this, 'on_post_status_change' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'on_post_delete' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'wp_ajax_static_archive_batch', array( $this, 'ajax_batch' ) );
		add_action( 'wp_ajax_static_archive_verify', array( $this, 'ajax_verify' ) );
		add_action( 'wp_ajax_static_archive_delete_all', array( $this, 'ajax_delete_all' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_plugin_action_links' ) );
	}

	/**
	 * Add a link on the plugins list page.
	 */
	public function add_plugin_action_links( $links ) {
		$url = admin_url( 'tools.php?page=static-archive' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">Archive</a>' );
		return $links;
	}

	/**
	 * When a post is published or updated, regenerate its files.
	 */
	public function on_post_status_change( $new_status, $old_status, $wp_post ) {
		$post_types = Static_Archive_Generator::get_post_types();
		if ( ! in_array( $wp_post->post_type, $post_types, true ) ) {
			return;
		}

		$generator = new Static_Archive_Generator();

		if ( 'publish' === $new_status ) {
			$generator->copy_stylesheet();
			$generator->generate_post( $wp_post->ID );
			$generator->generate_index();
			if ( 'page' !== $wp_post->post_type ) {
				$year = gmdate( 'Y', strtotime( $wp_post->post_date ) );
				$generator->generate_year_archive( $year );
			}
		} elseif ( 'publish' === $old_status && 'publish' !== $new_status ) {
			$generator->delete_post_files( $wp_post->ID );
			$generator->generate_index();
			if ( 'page' !== $wp_post->post_type ) {
				$year = gmdate( 'Y', strtotime( $wp_post->post_date ) );
				$generator->generate_year_archive( $year );
			}
		}
	}

	/**
	 * When a post is deleted, remove its files.
	 */
	public function on_post_delete( $post_id ) {
		$wp_post    = get_post( $post_id );
		$post_types = Static_Archive_Generator::get_post_types();
		if ( ! $wp_post || ! in_array( $wp_post->post_type, $post_types, true ) ) {
			return;
		}

		$generator = new Static_Archive_Generator();
		$generator->delete_post_files( $post_id );
		$generator->generate_index();
		if ( 'page' !== $wp_post->post_type ) {
			$year = gmdate( 'Y', strtotime( $wp_post->post_date ) );
			$generator->generate_year_archive( $year );
		}
	}

	/**
	 * Add the admin page under Tools.
	 */
	public function add_admin_page() {
		add_management_page(
			'Static Archive',
			'Static Archive',
			'manage_options',
			'static-archive',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Handle saving settings.
	 */
	private function maybe_save_settings() {
		if ( ! isset( $_POST['static_archive_save_settings'] ) ) {
			return false;
		}
		check_admin_referer( 'static_archive_settings' );

		$old_suffix = Static_Archive_Generator::get_filename_suffix();
		$new_suffix = isset( $_POST['static_archive_filename_suffix'] )
			? sanitize_text_field( wp_unslash( $_POST['static_archive_filename_suffix'] ) )
			: '';
		if ( $new_suffix && '-' !== $new_suffix[0] ) {
			$new_suffix = '-' . $new_suffix;
		}
		if ( $new_suffix !== $old_suffix && ! empty( $_POST['static_archive_delete_old_suffix'] ) ) {
			( new Static_Archive_Generator() )->delete_all();
		}
		update_option( 'static_archive_filename_suffix', $new_suffix );

		// Post types.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Each element is sanitized via sanitize_key() below.
		$raw_types      = isset( $_POST['static_archive_post_types'] ) ? wp_unslash( $_POST['static_archive_post_types'] ) : array();
		$all_post_types = get_post_types( array( 'public' => true ), 'names' );
		$selected_types = array();
		if ( is_array( $raw_types ) ) {
			foreach ( $raw_types as $type ) {
				$type = sanitize_key( $type );
				if ( isset( $all_post_types[ $type ] ) && 'attachment' !== $type ) {
					$selected_types[] = $type;
				}
			}
		}
		if ( empty( $selected_types ) ) {
			$selected_types = array( 'post', 'page' );
		}
		update_option( 'static_archive_post_types', $selected_types );

		// Output format from checkboxes.
		$html_on = ! empty( $_POST['static_archive_format_html'] );
		$md_on   = ! empty( $_POST['static_archive_format_markdown'] );
		if ( $html_on && $md_on ) {
			$format = 'both';
		} elseif ( $md_on ) {
			$format = 'markdown';
		} else {
			$format = 'html';
		}
		update_option( 'static_archive_output_format', $format );
	}

	public function render_admin_page() {
		$this->maybe_save_settings();

		$generator  = new Static_Archive_Generator();
		$output_dir = $generator->get_output_dir();
		$suffix     = Static_Archive_Generator::get_filename_suffix();
		$upload_dir = wp_get_upload_dir();
		$index_url  = $upload_dir['baseurl'] . '/' . $generator->get_index_filename();
		$post_types = Static_Archive_Generator::get_post_types();

		$total_posts = 0;
		foreach ( $post_types as $type ) {
			$counts       = wp_count_posts( $type );
			$total_posts += (int) $counts->publish;
		}
		$output_format = get_option( 'static_archive_output_format', 'html' );
		?>
		<style>
			.sa-wrap { max-width: 800px; }
			.sa-intro {
				font-size: 14px;
				color: #50575e;
				line-height: 1.6;
				margin: 0.5rem 0 1.5rem;
			}
			.sa-card {
				background: #fff;
				border: 1px solid #e0e0e0;
				border-radius: 8px;
				padding: 1.5rem;
				margin-bottom: 1.5rem;
			}
			.sa-card h2 {
				margin-top: 0;
				padding: 0;
				font-size: 1.1rem;
			}
			.sa-meta {
				display: flex;
				gap: 2rem;
				flex-wrap: wrap;
				margin-bottom: 0.75rem;
			}
			.sa-meta-item {
				display: flex;
				flex-direction: column;
				gap: 0.15rem;
			}
			.sa-meta-label {
				font-size: 11px;
				text-transform: uppercase;
				letter-spacing: 0.05em;
				color: #757575;
			}
			.sa-meta-value {
				font-size: 14px;
			}
			.sa-meta-value a { text-decoration: none; }
			.sa-meta-value a:hover { text-decoration: underline; }
			.sa-status {
				display: flex;
				gap: 1.5rem;
				flex-wrap: wrap;
				margin: 1rem 0;
			}
			.sa-stat {
				display: flex;
				flex-direction: column;
				align-items: center;
				padding: 0.75rem 1.25rem;
				border-radius: 6px;
				background: #f6f7f7;
				min-width: 90px;
			}
			.sa-stat-num {
				font-size: 1.5rem;
				font-weight: 600;
				line-height: 1;
			}
			.sa-stat-label {
				font-size: 12px;
				color: #757575;
				margin-top: 0.25rem;
			}
			.sa-stat.sa-ok .sa-stat-num { color: #00a32a; }
			.sa-stat.sa-warn .sa-stat-num { color: #dba617; }
			.sa-stat.sa-error .sa-stat-num { color: #d63638; }
			.sa-actions {
				display: flex;
				gap: 0.5rem;
				align-items: center;
				margin-top: 1rem;
			}
			.sa-progress {
				display: none;
				margin-top: 1rem;
				background: #f0f0f1;
				border-radius: 6px;
				overflow: hidden;
				height: 28px;
			}
			.sa-progress-bar {
				background: linear-gradient(135deg, #2271b1, #135e96);
				height: 100%;
				width: 0%;
				transition: width 0.3s;
				line-height: 28px;
				color: #fff;
				text-align: center;
				font-size: 12px;
				font-weight: 500;
			}
			.sa-log {
				margin-top: 1rem;
				max-height: 300px;
				overflow-y: auto;
				font-size: 13px;
				line-height: 1.6;
				white-space: pre-wrap;
				color: #50575e;
			}
			.sa-suffix-preview {
				margin-top: 0.5rem;
				font-size: 13px;
				color: #757575;
			}
			.sa-checkbox-list {
				display: flex;
				flex-wrap: wrap;
				gap: 0.25rem 1.5rem;
				margin: 0.5rem 0;
			}
			.sa-checkbox-list label {
				font-size: 14px;
			}
		</style>

		<div class="wrap sa-wrap">
			<h1>Static Archive</h1>
			<p class="sa-intro">This plugin generates a standalone HTML copy of all your posts, stored directly in the uploads directory alongside your images. The archive is fully self-contained &mdash; no WordPress needed to browse it. New posts are archived automatically when published, or you can regenerate everything at once below.</p>

			<div class="sa-card">
				<h2>Archive</h2>
				<div class="sa-meta">
					<div class="sa-meta-item">
						<span class="sa-meta-label">Output directory</span>
						<span class="sa-meta-value"><?php echo esc_html( $output_dir ); ?></span>
					</div>
					<div class="sa-meta-item">
						<span class="sa-meta-label">Main index</span>
						<span class="sa-meta-value"><a href="<?php echo esc_url( $index_url ); ?>" target="_blank"><?php echo esc_html( $generator->get_index_filename() ); ?></a></span>
					</div>
				</div>

				<div id="static-archive-status" class="sa-status">
					<div class="sa-stat"><span class="sa-stat-num"><?php echo esc_html( $total_posts ); ?></span><span class="sa-stat-label">posts</span></div>
				</div>

				<div class="sa-actions">
					<button id="static-archive-verify" class="button">Verify</button>
					<button id="static-archive-generate" class="button button-primary">Generate All</button>
					<button id="static-archive-delete-all" class="button" style="color: #d63638; border-color: #d63638;">Delete All Files</button>
				</div>

				<div id="static-archive-progress" class="sa-progress">
					<div id="static-archive-bar" class="sa-progress-bar"></div>
				</div>

				<div id="static-archive-log" class="sa-log"></div>
				<div id="static-archive-delete-log" class="sa-log"></div>
			</div>

			<div class="sa-card">
				<h2>Settings</h2>
				<form method="post">
					<?php wp_nonce_field( 'static_archive_settings' ); ?>

					<p><strong>Post types</strong></p>
					<div class="sa-checkbox-list">
						<?php
						$all_types = get_post_types( array( 'public' => true ), 'objects' );
						foreach ( $all_types as $type_obj ) :
							if ( 'attachment' === $type_obj->name ) {
								continue;
							}
							$checked = in_array( $type_obj->name, $post_types, true );
							?>
							<label>
								<input type="checkbox" name="static_archive_post_types[]" value="<?php echo esc_attr( $type_obj->name ); ?>" <?php checked( $checked ); ?>>
								<?php echo esc_html( $type_obj->labels->name ); ?>
							</label>
						<?php endforeach; ?>
					</div>

					<p style="margin-top: 1rem;"><strong>Output format</strong></p>
					<div class="sa-checkbox-list">
						<label><input type="checkbox" name="static_archive_format_html" value="1" <?php checked( in_array( $output_format, array( 'html', 'both' ), true ) ); ?>> HTML</label>
						<label><input type="checkbox" name="static_archive_format_markdown" value="1" <?php checked( in_array( $output_format, array( 'markdown', 'both' ), true ) ); ?>> Markdown</label>
					</div>

					<p style="margin-top: 1rem;"><strong>Filename suffix</strong></p>
					<p style="margin: 0.5rem 0;">
						<input type="text" id="static_archive_filename_suffix" name="static_archive_filename_suffix" value="<?php echo esc_attr( $suffix ); ?>" class="regular-text" placeholder="e.g. -xK4mQ9p">
					</p>
					<p class="sa-suffix-preview">
						All generated files will be named like post-123<?php echo esc_html( $suffix ); ?>.html<br>
						Leave empty for plain filenames. Changing this requires a full regeneration.
					</p>
					<p id="sa-delete-old-suffix-row" style="display:none; margin: 0.5rem 0;">
						<label><input type="checkbox" name="static_archive_delete_old_suffix" value="1" checked> Delete existing archive files with the old suffix</label>
					</p>
					<p style="margin-top: 1rem;">
						<input type="submit" name="static_archive_save_settings" class="button" value="Save Settings">
					</p>
				</form>
			</div>
		</div>

		<script>
		(function() {
			var suffixInput = document.getElementById('static_archive_filename_suffix');
			var deleteOldRow = document.getElementById('sa-delete-old-suffix-row');
			var originalSuffix = suffixInput ? suffixInput.value : '';
			if (suffixInput) {
				suffixInput.addEventListener('input', function() {
					deleteOldRow.style.display = this.value !== originalSuffix ? '' : 'none';
				});
			}

			var nonce = <?php echo wp_json_encode( wp_create_nonce( 'static_archive' ) ); ?>;
			var statusEl = document.getElementById('static-archive-status');
			var logEl = document.getElementById('static-archive-log');
			var progressEl = document.getElementById('static-archive-progress');
			var barEl = document.getElementById('static-archive-bar');
			var generateBtn = document.getElementById('static-archive-generate');
			var verifyBtn = document.getElementById('static-archive-verify');
			var running = false;

			function log(msg) {
				logEl.textContent += msg + '\n';
				logEl.scrollTop = logEl.scrollHeight;
			}

			function stat(num, label, cls) {
				return '<div class="sa-stat' + (cls ? ' ' + cls : '') + '"><span class="sa-stat-num">' + num + '</span><span class="sa-stat-label">' + label + '</span></div>';
			}

			function verify() {
				fetch(ajaxurl + '?action=static_archive_verify&_wpnonce=' + nonce)
					.then(function(r) { return r.json(); })
					.then(function(data) {
						if (!data.success) {
							statusEl.innerHTML = stat('!', 'error');
							return;
						}
						var r = data.data;
						var html = stat(r.total_posts, r.limited ? 'sampled' : 'entries');
						html += stat(r.total_archived, 'archived', r.total_archived === r.total_posts ? 'sa-ok' : '');
						if (r.missing.length) html += stat(r.missing_capped ? '>10' : r.missing.length, 'missing', 'sa-error');
						if (r.outdated.length) html += stat(r.outdated.length, 'outdated', 'sa-warn');
						if (r.orphaned.length) html += stat(r.orphaned.length, 'orphaned', 'sa-warn');
						statusEl.innerHTML = html;

						if (r.missing.length) {
							log('Missing files' + (r.missing_capped ? ' (showing first 10):' : ':'));
							r.missing.slice(0, 10).forEach(function(m) { log('  [' + m.id + '] ' + m.slug + ' (' + m.title + ')'); });
						}
						if (r.outdated.length) {
							log('Outdated files:');
							r.outdated.forEach(function(m) { log('  [' + m.id + '] ' + m.slug + ' (' + m.title + ')'); });
						}
						if (r.orphaned.length) {
							log('Orphaned files:');
							r.orphaned.forEach(function(f) { log('  ' + f); });
						}
						if (!r.missing.length && !r.outdated.length && !r.orphaned.length) {
							log('Archive is complete and up to date.');
						}
					});
			}

			function generateBatch(offset) {
				fetch(ajaxurl + '?action=static_archive_batch&_wpnonce=' + nonce + '&offset=' + offset)
					.then(function(r) { return r.json(); })
					.then(function(data) {
						if (!data.success) {
							log('Error: ' + (data.data || 'Unknown error'));
							running = false;
							generateBtn.disabled = false;
							return;
						}
						var r = data.data;
						var pct = Math.round(r.processed / r.total * 100);
						barEl.style.width = pct + '%';
						barEl.textContent = r.processed + ' / ' + r.total;

						var range = r.first_date === r.last_date ? r.first_date : r.first_date + ' – ' + r.last_date;
						var parts = [];
						if (r.stats.created) parts.push(r.stats.created + ' created');
						if (r.stats.updated) parts.push(r.stats.updated + ' updated');
						if (r.stats.unchanged) parts.push(r.stats.unchanged + ' unchanged');
						log(r.processed + '/' + r.total + ' (' + range + '): ' + parts.join(', '));

						if (r.has_more) {
							generateBatch(r.next_offset);
						} else {
							log('Generating indexes and year archives…');
							log('Done!');
							running = false;
							generateBtn.disabled = false;
							verify();
						}
					})
					.catch(function(err) {
						log('Error: ' + err.message);
						running = false;
						generateBtn.disabled = false;
					});
			}

			generateBtn.addEventListener('click', function() {
				if (running) return;
				running = true;
				generateBtn.disabled = true;
				logEl.textContent = '';
				progressEl.style.display = 'block';
				barEl.style.width = '0%';
				barEl.textContent = '';
				log('Starting full generation...');
				generateBatch(0);
			});

			verifyBtn.addEventListener('click', function() {
				logEl.textContent = '';
				verify();
			});

			var deleteAllBtn = document.getElementById('static-archive-delete-all');
			var deleteLogEl = document.getElementById('static-archive-delete-log');

			deleteAllBtn.addEventListener('click', function() {
				if ( ! window.confirm('Delete all generated archive files? This cannot be undone, but you can regenerate them at any time.') ) {
					return;
				}
				deleteAllBtn.disabled = true;
				deleteLogEl.textContent = '';
				fetch(ajaxurl + '?action=static_archive_delete_all&_wpnonce=' + nonce)
					.then(function(r) { return r.json(); })
					.then(function(data) {
						if (!data.success) {
							deleteLogEl.textContent = 'Error: ' + (data.data || 'Unknown error');
						} else {
							deleteLogEl.textContent = 'Deleted ' + data.data.deleted + ' file' + (data.data.deleted === 1 ? '' : 's') + '.';
							verify();
						}
						deleteAllBtn.disabled = false;
					})
					.catch(function(err) {
						deleteLogEl.textContent = 'Error: ' + err.message;
						deleteAllBtn.disabled = false;
					});
			});

		})();
		</script>
		<?php
	}

	/**
	 * AJAX: Generate a batch of posts.
	 */
	public function ajax_batch() {
		check_ajax_referer( 'static_archive' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$offset    = isset( $_GET['offset'] ) ? absint( $_GET['offset'] ) : 0;
		$generator = new Static_Archive_Generator();
		$result    = $generator->generate_batch( $offset );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Verify the archive.
	 */
	public function ajax_verify() {
		check_ajax_referer( 'static_archive' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$generator = new Static_Archive_Generator();
		$report    = $generator->verify();

		wp_send_json_success( $report );
	}

	/**
	 * AJAX: Delete all generated archive files.
	 */
	public function ajax_delete_all() {
		check_ajax_referer( 'static_archive' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$generator = new Static_Archive_Generator();
		$deleted   = $generator->delete_all();

		wp_send_json_success( array( 'deleted' => $deleted ) );
	}
}

new Static_Archive();
