<?php

namespace PosterStudio;

/**
 * Frontend Handler
 *
 * @package My_PDF_Plugin
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

use Exception;
use WP_Post;

/**
 * Frontend_Handler Class
 *
 * Handles all frontend functionality for the PDF generator plugin.
 *
 * @since 1.0.0
 */
class Frontend_Handler
{

	/**
	 * Constructor
	 *
	 * Initialize filters and actions.
	 *
	 * @since 1.0.0
	 */
	public function __construct()
	{
		// Remove the_content filter.

		// Register frontend scripts and styles.
		add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

		// Add metabox for Generate PDF button in the post editor.
		add_action('add_meta_boxes', array($this, 'add_pdf_metabox'));

		// Enqueue admin scripts and styles.
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

		// Register hidden admin page for the editor
		add_action('admin_menu', array($this, 'register_editor_page'));

		// Handle AJAX request for generating PDF.
		add_action('wp_ajax_poster_studio_generate_pdf', array($this, 'handle_generate_pdf'));
		add_action('wp_ajax_nopriv_poster_studio_generate_pdf', array($this, 'handle_public_generate_pdf'));
	}

	/**
	 * Register the hidden editor page.
	 */
	public function register_editor_page()
	{
		add_submenu_page(
			null, // Parent slug (null for hidden)
			__('Poster Studio', 'poster-studio'),
			__('Poster Studio', 'poster-studio'),
			'edit_posts',
			'poster-editor',
			array($this, 'render_poster_editor_page')
		);
	}

	/**
	 * Add PDF Metabox
	 *
	 * Adds a metabox with the "Generate PDF" button to the post editor.
	 *
	 * @since 1.0.0
	 */
	public function add_pdf_metabox()
	{
		add_meta_box(
			'poster_studio_metabox',
			__('Generate PDF', 'poster-studio'),
			array($this, 'render_pdf_metabox'),
			array('post', 'page'),
			'side',
			'high'
		);
	}

	/**
	 * Render PDF Metabox
	 *
	 * Outputs the HTML for the "Generate PDF" button in the post editor.
	 *
	 * @since 1.0.0
	 */
	public function render_pdf_metabox($post)
	{
		$editor_url = admin_url('admin.php?page=poster-editor&post_id=' . $post->ID);
		?>
		<div class="pdf-metabox-simple">
			<p><?php _e('Crea i personalitza el cartell per a aquesta activitat en una pantalla completa.', 'poster-studio'); ?></p>
			<a href="<?php echo esc_url($editor_url); ?>" target="_blank" class="button button-primary button-large btn-gem-red">
				<span class="dashicons dashicons-art"></span> <?php _e('Dissenyar Cartell', 'poster-studio'); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Render the Full-Screen Editor Page
	 */
	public function render_poster_editor_page()
	{
		if (!isset($_GET['post_id'])) {
			wp_die(__('ID de post no vàlid.', 'poster-studio'));
		}

		$post_id = intval($_GET['post_id']);
		$post = get_post($post_id);

		if (!$post || !current_user_can('edit_post', $post_id)) {
			wp_die(__('No teniu permís per editar aquest post.', 'poster-studio'));
		}

		$nonce = wp_create_nonce('poster_studio_generate_pdf');
		$thumbnail_id = get_post_thumbnail_id($post);
		$image_url = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'full') : '';

		// Default values for editor controls
		$title_size = 30; // Default title size
		$image_height = 141; // Default image height
		$image_width = 210; // Default image width (A4)

		// Fecha y detalles logic (same as before)
		$data_inici = get_post_meta($post->ID, 'activitat_data_inici', true);
		if ($data_inici) {
			$date_obj = \DateTime::createFromFormat('Ymd', $data_inici);
			$data_text = $date_obj ? $date_obj->format('d/m/Y') : $data_inici;
		} else {
			$data_text = get_the_date('d/m/Y', $post);
		}

		$details_items = array();
		$meta_mappings = array(
			'activitat-localitzacio' => 'Lloc',
			'activitat-nivell' => 'Dificultat',
			'activitat-places' => 'Places',
		);
		foreach ($meta_mappings as $key => $label) {
			$value = get_post_meta($post->ID, $key, true);
			if ($value) {
				$details_items[] = $label . ': ' . $value;
			}
		}
		// Create both HTML (for preview) and Plain Text (for textarea)
		$details_text = implode("\n", $details_items);
		$details_html = implode('<br>', array_map('esc_html', $details_items));

		$page_format = 'A4'; // Default to A4

		?>
		<div class="wrap gem-poster-studio-wrap">
			<div id="gem-poster-studio-app" class="pdf-editor-fullscreen" data-post-id="<?php echo esc_attr($post->ID); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" <?php
				  $post_url = get_permalink($post->ID);
				  // Si devuelve la URL básica en el admin, intentamos reconstruir la amigable
		  		if (false !== strpos($post_url, '?p=')) {
					  list($sample_permalink, $sample_post_name) = get_sample_permalink($post->ID);
					  if ($sample_post_name) {
						  $post_url = str_replace(array('%postname%', '%pagename%'), $sample_post_name, $sample_permalink);
					  }
				  }
				  ?> data-post-url="<?php echo esc_url($post_url); ?>" data-post-status="<?php echo esc_attr($post->post_status); ?>">

				<div class="pdf-editor-header">
					<div class="header-left">
						<a href="<?php echo get_edit_post_link($post->ID); ?>" class="back-link"><span class="dashicons dashicons-arrow-left-alt"></span> Tornar a la notícia</a>
					</div>
					<div class="header-center">
						<h1><?php _e('Poster Studio', 'poster-studio'); ?></h1>
					</div>
					<div class="header-right">
						<!-- Header Right Empty or for future use -->
					</div>
				</div>

				<div class="pdf-editor-main">
					<!-- Workspace (Centered) -->
					<div class="pdf-editor-workspace">
						<!-- Zoom Controls (Floating) -->
						<div class="pdf-zoom-controls">
							<button type="button" id="zoom-in" title="Zoom In"><span class="dashicons dashicons-plus"></span></button>
							<button type="button" id="zoom-out" title="Zoom Out"><span class="dashicons dashicons-minus"></span></button>
							<button type="button" id="zoom-reset" title="Restablir Zoom"><span class="dashicons dashicons-marker"></span></button>
						</div>

						<div class="pdf-preview-canvas size-a4" id="pdf-canvas"> <!-- Default A4 -->
							<div class="pdf-preview-page">
								<!-- Top Image -->
								<div class="pdf-preview-image-container" style="height: <?php echo esc_attr($image_height); ?>mm; width: <?php echo esc_attr($image_width); ?>mm; margin: 0 auto;">
									<?php if ($image_url): ?>
										<img src="<?php echo esc_url($image_url); ?>" id="pdf-preview-image" style="top: 0;">
										<div class="image-drag-handle" data-html2canvas-ignore="true">↕ Arrossega per enquadrar</div>
									<?php else: ?>
										<div class="pdf-preview-no-image">Sense imatge destacada</div>
									<?php endif; ?>
								</div>

								<!-- Content Area - Non-editable preview -->
								<div class="pdf-preview-content">
									<div id="preview-title" style="font-size: <?php echo esc_attr($title_size); ?>pt;"><?php echo esc_html(strtoupper(get_the_title($post))); ?></div>

									<div class="pdf-preview-columns">
										<div class="pdf-col-left">
											<p id="preview-date" class="pdf-date-field"><?php echo esc_html($data_text); ?></p>
											<div class="pdf-divider"></div>
											<div id="preview-details" class="pdf-details-field">
												<?php echo $details_html; ?>
											</div>
										</div>
										<div class="pdf-col-right">
											<h3>INFORMACIÓ</h3>
											<p>Tots els dimecres de 19:00 a 21:00 al local del GEM i al web<br>
												<span class="pdf-web-link">www.gem-malgrat.cat</span>
											</p>

											<div class="qr-brand-row">
												<div id="preview-qr" class="pdf-qr-placeholder">
													<!-- QR will be generated here by JS -->
												</div>
												<div class="organitza-box">
													<div class="organitza-label">ORGANITZA:</div>
													<div class="footer-logo-box">
														<img src="<?php echo plugin_dir_url(dirname(__FILE__)) . 'assets/images/logo-gem.jpg'; ?>" alt="GEM">
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Sidebar Controls (Right) -->
					<div class="pdf-editor-sidebar">
						<button type="button" id="pdf-generate-final" class="button button-primary button-large btn-gem-red btn-block">
							<span class="dashicons dashicons-pdf"></span> EXPORTAR A PDF
						</button>

						<h2>Arxiu i Paper</h2>
						<div class="control-group">
							<label for="pdf-page-size">Mida del Paper</label>
							<select id="pdf-page-size">
								<option value="A4" <?php selected($page_format, 'A4'); ?>>A4 (Full Estandard)</option>
								<option value="A3" <?php selected($page_format, 'A3'); ?>>A3 (Poster Gran)</option>
							</select>
						</div>

						<h2>Configuració de Text</h2>

						<div class="control-group">
							<label for="edit-title">Títol del Cartell</label>
							<textarea id="edit-title" rows="3"><?php echo esc_html(strtoupper(get_the_title($post))); ?></textarea>
						</div>

						<div class="control-group">
							<label for="edit-date">Data / Horari</label>
							<input type="text" id="edit-date" value="<?php echo esc_attr($data_text); ?>">
						</div>

						<div class="control-group">
							<label for="edit-details">Detalls (Lloc, Dificultat...)</label>
							<textarea id="edit-details" rows="5"><?php echo esc_textarea($details_text); ?></textarea>
						</div>

						<h2>Estils Visuals</h2>

						<div class="control-group">
							<label>Tipografia</label>
							<select id="pdf-font-family">
								<option value="helvetica" selected>Helvetica / Arial</option>
								<option value="times">Times New Roman</option>
								<option value="courier">Courier</option>
								<option value="dejavusans">DejaVù Sans</option>
							</select>
						</div>

						<div class="control-group">
							<label>Mida Títol: <span id="val-title-size"><?php echo esc_html($title_size); ?></span>pt</label>
							<input type="range" id="pdf-title-size" min="20" max="80" value="<?php echo esc_attr($title_size); ?>">
						</div>

						<div class="control-group">
							<label>Alçada Imatge: <span id="val-image-height"><?php echo esc_html($image_height); ?></span>mm</label>
							<input type="range" id="pdf-image-height" min="60" max="220" value="<?php echo esc_attr($image_height); ?>">
						</div>

						<div class="control-group">
							<label>Amplada Imatge: <span id="val-image-width"><?php echo esc_html($image_width); ?></span>mm</label>
							<input type="range" id="pdf-image-width" min="50" max="297" value="<?php echo esc_attr($image_width); ?>">
						</div>

						<div class="editor-info-box">
							<h3>Informació</h3>
							<ul>
								<li>El QR es genera només si el post està publicat.</li>
								<li>Pots moure la imatge arrossegant-la al cartel.</li>
							</ul>
						</div>

						<div class="pdf-message"></div>
					</div>
				</div>

				<!-- Full-screen Loading Overlay -->
				<div class="pdf-loading-indicator">
					<div class="pdf-spinner"></div>
					<p>Generant el vostre cartell professional...</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Deprecated Visual Metabox
	 * Logic moved to render_poster_editor_page
	 */
	public function render_pdf_metabox_OLD($post)
	{
		// logic removed
	}

	/**
	 * Render Customization Modal (Deprecated/Removed in favor of inline editor)
	 */
	private function render_customization_modal($post)
	{
		// No longer used, logic moved into render_pdf_metabox
	}

	/**
	 * Enqueue Admin Scripts
	 *
	 * Enqueues the necessary scripts and styles for the admin metabox.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_admin_scripts($hook)
	{
		// Only load on post editing pages OR our custom editor page.
		if ('post.php' !== $hook && 'post-new.php' !== $hook && 'admin_page_poster-editor' !== $hook) {
			return;
		}

		wp_enqueue_style(
			'poster-studio-styles',
			plugin_dir_url(dirname(__FILE__)) . 'assets/css/poster-studio.css',
			array(),
			time() // Force refresh during development
		);

		wp_enqueue_script(
			'poster-studio-scripts',
			plugin_dir_url(dirname(__FILE__)) . 'assets/js/poster-studio.js',
			array('jquery'),
			time(), // Force refresh
			true
		);

		// Enqueue html2canvas for visual fidelity capturing
		wp_enqueue_script(
			'html2canvas',
			'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
			array(),
			'1.4.1',
			true
		);

		// Localize script for AJAX and translations.
		wp_localize_script(
			'poster-studio-scripts',
			'posterStudio',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('poster_studio_generate_pdf'),
				'generating_text' => __('Generating PDF...', 'poster-studio'),
				'generated_text' => __('Download PDF', 'poster-studio'),
				'error_text' => __('Error generating PDF. Please try again.', 'poster-studio'),
			)
		);
	}

	/**
	 * Enqueue Scripts and Styles
	 *
	 * Register and enqueue the necessary CSS and JavaScript files.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts()
	{
		// Only enqueue on single post/page views.
		if (!is_singular(array('post', 'page'))) {
			return;
		}

		// Enqueue the CSS file.
		wp_enqueue_style(
			'poster-studio-styles',
			plugin_dir_url(dirname(__FILE__)) . 'assets/css/poster-studio.css',
			array(),
			'1.0.0',
			'all'
		);

		// Enqueue the dashicons for the PDF icon.
		wp_enqueue_style('dashicons');

		// Enqueue the JavaScript file.
		wp_enqueue_script(
			'poster-studio-scripts',
			plugin_dir_url(dirname(__FILE__)) . 'assets/js/poster-studio.js',
			array('jquery'),
			'1.0.0',
			true
		);

		// Localize script for AJAX and translations.
		wp_localize_script(
			'poster-studio-scripts',
			'posterStudio',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('poster_studio_generate_pdf'),
				'generating_text' => __('Generating PDF...', 'poster-studio'),
				'generated_text' => __('Download PDF', 'poster-studio'),
				'error_text' => __('Error generating PDF. Please try again.', 'poster-studio'),
			)
		);
	}

	/**
	 * Handle Generate PDF (Authenticated Users)
	 *
	 * Process AJAX requests for generating PDFs for authenticated users.
	 *
	 * @since 1.0.0
	 */
	public function handle_generate_pdf()
	{
		// Check nonce for security.
		$nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
		if (!wp_verify_nonce($nonce, 'poster_studio_generate_pdf')) {
			$u = get_current_user_id();
			error_log('PosterStudio: Nonce verification failed for poster_studio_generate_pdf. Received: ' . $nonce . ' (UID: ' . $u . ')');
			wp_send_json_error(array('message' => sprintf(__('Fallo de seguridad. (Acción: %s, UID: %d)', 'poster-studio'), 'poster_studio_generate_pdf', $u)));
		}

		// Get and validate post ID.
		if (!isset($_POST['post_id']) || !is_numeric($_POST['post_id'])) {
			wp_send_json_error(array('message' => __('Invalid post ID.', 'poster-studio')));
		}

		$post_id = intval($_POST['post_id']);
		$post = get_post($post_id);

		// Check if post exists.
		if (!$post) {
			wp_send_json_error(array('message' => __('Post not found.', 'poster-studio')));
		}

		// Check if the post is available for generation (published OR user has edit permissions).
		$can_view = ('publish' === $post->post_status && is_post_publicly_viewable($post_id));
		$can_edit = current_user_can('edit_post', $post_id);

		if (!$can_view && !$can_edit) {
			wp_send_json_error(array('message' => __('This post is not available for PDF generation.', 'poster-studio')));
		}

		try {
			// Get custom options from request
			$custom_options = isset($_POST['custom_options']) ? $_POST['custom_options'] : array();
			$image_data = isset($_POST['image_data']) ? $_POST['image_data'] : '';

			// Generate temporary file URL for the PDF.
			$pdf_url = $this->generate_pdf_file($post, $custom_options, $image_data);

			wp_send_json_success(array(
				'url' => $pdf_url,
				'message' => __('PDF generado correctamente.', 'poster-studio'),
			));
		} catch (Exception $e) {
			wp_send_json_error(array('message' => $e->getMessage()));
		}
	}

	/**
	 * Handle Generate PDF (Public Access)
	 *
	 * Process AJAX requests for generating PDFs for non-authenticated users.
	 * Only allows PDF generation for public posts.
	 *
	 * @since 1.0.0
	 */
	public function handle_public_generate_pdf()
	{
		// For non-authenticated users, we'll add the same security checks
		// but only allow PDFs for publicly viewable content.
		$this->handle_generate_pdf();
	}

	/**
	 * Generate PDF File
	 *
	 * Creates a PDF file from the post content.
	 * This is a placeholder that would call the PDF_Generator class in a real implementation.
	 *
	 * @since 1.0.0
	 * @param WP_Post $post The post object.
	 * @param array $options Custom options.
	 * @param string $image_data Base64 encoded image data.
	 * @return string URL to the generated PDF file.
	 */
	private function generate_pdf_file($post, $options = array(), $image_data = '')
	{
		// Check if the PDF_Generator class exists.
		if (!class_exists('PosterStudio\PDF_Generator')) {
			throw new Exception(__('PDF Generator is not available.', 'poster-studio'));
		}

		// Create an instance of the PDF_Generator class.
		$pdf_generator = new PDF_Generator();

		// Generate the PDF file using the post ID and options.
		$pdf_file_path = $pdf_generator->generate_from_post($post->ID, $options, $image_data);

		// Check if the file was successfully generated.
		if (is_wp_error($pdf_file_path) || !file_exists($pdf_file_path)) {
			throw new Exception(__('Failed to generate the PDF file.', 'poster-studio'));
		}

		// Use wp_upload_dir() to get the correct base URL, avoiding path/URL replacement issues in Docker
		$upload_dir = wp_upload_dir();
		$relative_path = str_replace(wp_normalize_path($upload_dir['basedir']), '', wp_normalize_path($pdf_file_path));
		$pdf_url = $upload_dir['baseurl'] . $relative_path;

		// Return the URL to the generated PDF file.
		return esc_url($pdf_url);
	}
}

// End of file class-frontend-handler.php
