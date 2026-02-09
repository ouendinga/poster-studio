<?php

namespace PosterStudio;

/**
 * PDF Generator Class
 *
 * @package My_PDF_Plugin
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

use TCPDF;
use WP_Error;
use WP_Post;
use Exception;

/**
 * PDF Generator class.
 *
 * Handles the generation of PDF documents from WordPress post content.
 *
 * @since 1.0.0
 */
class PDF_Generator extends TCPDF
{

	/**
	 * TCPDF instance.
	 *
	 * @var TCPDF
	 */
	private $pdf;

	/**
	 * Default PDF options.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Error messages.
	 *
	 * @var WP_Error
	 */
	private $errors;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct()
	{
		$this->errors = new WP_Error();
		$this->set_default_options();
	}

	/**
	 * Set default PDF options.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function set_default_options()
	{
		$this->options = array(
			'title' => get_bloginfo('name'),
			'author' => get_bloginfo('name'),
			'creator' => 'My PDF Plugin',
			'subject' => '',
			'keywords' => '',
			'page_orientation' => 'P', // P for Portrait, L for Landscape.
			'unit' => 'mm',
			'page_format' => 'A4',
			'unicode' => true,
			'encoding' => 'UTF-8',
			'font' => 'dejavusans',
			'font_size' => 10,
			'header_logo' => '',
			'header_title' => get_bloginfo('name'),
			'footer_text' => get_bloginfo('url'),
		);
	}

	/**
	 * Initialize TCPDF library.
	 */
	public function init_tcpdf($page_format = 'A3')
	{
		if (!class_exists('TCPDF')) {
			$tcpdf_path = plugin_dir_path(dirname(__FILE__)) . 'vendor/tecnickcom/tcpdf/tcpdf.php';
			if (file_exists($tcpdf_path)) {
				require_once $tcpdf_path;
			}
		}

		try {
			// Determine page size
			$format = strtoupper($page_format);
			if (!in_array($format, array('A3', 'A4')))
				$format = 'A3';

			$this->pdf = new TCPDF('P', 'mm', $format, true, 'UTF-8', false);

			$this->pdf->SetCreator('GEM Poster Studio');
			$this->pdf->SetAuthor('Grup Excursionista Malgratenc');
			$this->pdf->setPrintHeader(false);
			$this->pdf->setPrintFooter(false);
			$this->pdf->SetAutoPageBreak(false, 0);

			return true;
		} catch (Exception $e) {
			$this->errors->add('tcpdf_init_error', $e->getMessage());
			return false;
		}
	}

	/**
	 * Generate PDF from post content.
	 */
	public function generate_from_post($post_id, $options = array(), $image_data = '')
	{
		$post = get_post($post_id);
		if (!$post)
			return new WP_Error('invalid_post', 'Invalid post ID.');

		// Custom options
		$title_text = !empty($options['title']) ? $options['title'] : strtoupper($post->post_title);
		$date_text = !empty($options['date_text']) ? $options['date_text'] : get_the_date('d/m/Y', $post);
		$details_html = !empty($options['details']) ? nl2br($options['details']) : '';
		$font_family = !empty($options['font_family']) ? $options['font_family'] : 'helvetica';
		$page_format = !empty($options['page_size']) ? $options['page_size'] : 'A4';

		// Page dimensions
		$is_a3 = (strtoupper($page_format) === 'A3');
		$pg_w = $is_a3 ? 297 : 210;
		$pg_h = $is_a3 ? 420 : 297;

		if (!$this->init_tcpdf($page_format))
			return $this->errors;

		try {
			$this->pdf->SetTitle($title_text);
			$this->pdf->setPrintHeader(false);
			$this->pdf->setPrintFooter(false);

			// Force zero margins for full-page image rendering
			$this->pdf->SetMargins(0, 0, 0);
			$this->pdf->SetAutoPageBreak(false, 0);

			$this->pdf->AddPage();

			// NEW: If we have captured image data from the frontend, use it for 100% fidelity
			if (!empty($image_data)) {
				// Remove "data:image/jpeg;base64," if present
				if (strpos($image_data, 'base64,') !== false) {
					$image_data = substr($image_data, strpos($image_data, 'base64,') + 7);
				}
				$img_binary = base64_decode($image_data);

				// We render it to fill the entire page
				// Unit is mm, so we use $pg_w and $pg_h
				$this->pdf->Image('@' . $img_binary, 0, 0, $pg_w, $pg_h, 'JPG', '', '', false, 300, '', false, false, 0);
			} else {
				// FALLBACK: Original Manual Drawing Logic
				$title_size = !empty($options['title_size']) ? (int) $options['title_size'] : 30;
				$image_height = !empty($options['image_height']) ? (int) $options['image_height'] : 141;
				$image_width = !empty($options['image_width']) ? (int) $options['image_width'] : $pg_w;
				$image_top_px = isset($options['image_top']) ? (int) $options['image_top'] : 0;
				$image_top_mm = $image_top_px / 3.78;

				// 1. Featured Image (Full width)
				$thumbnail_id = get_post_thumbnail_id($post);
				if ($thumbnail_id) {
					$image_path = get_attached_file($thumbnail_id);
					if ($image_path && file_exists($image_path)) {
						// Use StartTransform to properly isolate the clipping area
						$this->pdf->StartTransform();
						$this->pdf->Rect(0, 0, $pg_w, $image_height, 'CNZ'); // Clipping area
						$img_x = ($pg_w - $image_width) / 2;
						// Set height to 0 to maintain aspect ratio based on width
						$this->pdf->Image($image_path, $img_x, $image_top_mm, $image_width, 0, '', '', 'T', false, 300, '', false, false, 0, true, false, false);
						$this->pdf->StopTransform(); // Stop clipping
					}
				}

				// Clear background for text
				$this->pdf->SetFillColor(255, 255, 255);
				$this->pdf->Rect(0, $image_height, $pg_w, $pg_h - $image_height, 'F');

				// 2. Title - Standard Bold
				$this->pdf->SetY($image_height + 6);
				$this->pdf->SetTextColor(144, 25, 30); // GEM Maroon
				$this->pdf->SetFont($font_family, 'B', $title_size);
				$this->pdf->SetCellHeightRatio(1.1);
				$this->pdf->MultiCell($pg_w - 40, 0, strtoupper($title_text), 0, 'C', 0, 1, 20);
				$this->pdf->SetCellHeightRatio(1.25);

				// 3. Two Columns Padding
				$content_y = $this->pdf->GetY() + 6;
				$pad_x = 25;
				$col_spacing = 15;
				$left_col_w = ($pg_w - ($pad_x * 2) - $col_spacing) * 0.6; // 60% width
				$right_col_w = ($pg_w - ($pad_x * 2) - $col_spacing) * 0.4; // 40% width

				// Vertical Separator Line
				$this->pdf->SetDrawColor(220, 220, 220);
				$this->pdf->SetLineWidth(0.3);
				$vx = $pad_x + $left_col_w + ($col_spacing / 2);
				$this->pdf->Line($vx, $content_y, $vx, $pg_h - 40);

				// Left Column (Date & Details)
				$this->pdf->SetXY($pad_x, $content_y);
				$this->pdf->SetTextColor(17, 17, 17); // #111
				$this->pdf->SetFont($font_family, 'B', 13.5);
				$this->pdf->Cell($left_col_w, 10, $date_text, 0, 0, 'L');

				$this->pdf->SetDrawColor(144, 25, 30);
				$this->pdf->SetLineWidth(1);
				$this->pdf->Line($pad_x, $content_y + 10, $pad_x + $left_col_w, $content_y + 10);

				$this->pdf->SetTextColor(51, 51, 51); // #333 for details
				$this->pdf->SetFont($font_family, '', 13.5);
				$details_y = $content_y + 15;
				$styled_details = '<div style="font-size: 13.5pt; line-height: 1.6; color: #333;">' . $details_html . '</div>';
				$this->pdf->writeHTMLCell($left_col_w, 0, $pad_x, $details_y, $styled_details, 0, 0);

				// Right Column (Info, QR & Logo aligned)
				$right_x = $pad_x + $left_col_w + $col_spacing;
				$this->pdf->SetXY($right_x, $content_y);
				$this->pdf->SetTextColor(17, 17, 17); // #111
				$this->pdf->SetFont($font_family, 'B', 13.5);
				$this->pdf->Cell($right_col_w, 10, 'INFORMACIÓ', 0, 0, 'L');

				$this->pdf->SetTextColor(51, 51, 51); // #333
				$this->pdf->SetFont($font_family, '', 11.5);
				$info_y = $content_y + 12;
				$info_text = "Tots els dimecres de 19:00 a 21:00 al local del GEM i al web\nwww.gem-malgrat.cat";
				$this->pdf->MultiCell($right_col_w, 0, $info_text, 0, 'L', 0, 1, $right_x, $info_y);

				// QR and Logo Side by Side
				$qr_size = $is_a3 ? 35 : 25;
				$logo_size = $is_a3 ? 30 : 20;
				$qr_logo_y = $this->pdf->GetY() + 8;

				// QR Code
				if ($post->post_status === 'publish' || $post->post_status === 'future') {
					$qr_url = get_permalink($post->ID);
					if (false !== strpos($qr_url, '?p=')) {
						list($sample_permalink, $sample_post_name) = get_sample_permalink($post->ID);
						if ($sample_post_name) {
							$qr_url = str_replace(array('%postname%', '%pagename%'), $sample_post_name, $sample_permalink);
						}
					}
					$style = array('border' => false, 'padding' => 1, 'fgcolor' => array(0, 0, 0), 'bgcolor' => false);
					$this->pdf->write2DBarcode($qr_url, 'QRCODE,L', $right_x, $qr_logo_y, $qr_size, $qr_size, $style, 'N');
				}

				// Branding side by side with QR
				$branding_x = $right_x + $qr_size + 8;
				$branding_y = $qr_logo_y + (($qr_size - ($logo_size + 6)) / 2);

				$this->pdf->SetXY($branding_x, $branding_y);
				$this->pdf->SetFont($font_family, 'B', 10.5); // 14px -> 10.5pt
				$this->pdf->SetTextColor(102, 102, 102); // #666
				$this->pdf->Cell(40, 5, 'ORGANITZA:', 0, 0, 'L');

				$logo_path = plugin_dir_path(dirname(__FILE__)) . 'assets/images/logo-gem.jpg';
				if (file_exists($logo_path)) {
					$this->pdf->Image($logo_path, $branding_x, $branding_y + 6, $logo_size, 0);
				}
			} // End of manual drawing fallback

			// Save and return
			$current_date = date('d-m-Y_H-i-s');
			$file = WP_CONTENT_DIR . '/uploads/pdfs/' . sanitize_file_name($title_text) . '_' . $page_format . '_' . $current_date . '.pdf';
			if (!file_exists(dirname($file)))
				wp_mkdir_p(dirname($file));

			$this->pdf->Output($file, 'F');
			return $file;

		} catch (Exception $e) {
			return new WP_Error('pdf_generation_error', $e->getMessage());
		}
	}

	/**
	 * Extracts activity details for the poster.
	 * 
	 * @param int $post_id
	 * @return string HTML for the details section.
	 */
	private function get_activity_details_html($post_id, $font_size = 11)
	{
		$details = array();

		$meta_mappings = array(
			'activitat-localitzacio' => 'Lloc',
			'activitat-nivell' => 'Dificultat',
			'activitat-places' => 'Places',
		);

		foreach ($meta_mappings as $key => $label) {
			$value = get_post_meta($post_id, $key, true);
			if ($value) {
				$details[] = '<strong>' . $label . ':</strong> ' . esc_html($value);
			}
		}

		// Try to find distance/elevation in post content or meta
		$content = get_post_field('post_content', $post_id);
		if (preg_match('/Distància[^<]*:?\s*([^<]+)/i', $content, $matches)) {
			$details[] = '<strong>Distància:</strong> ' . esc_html(trim($matches[1]));
		}
		if (preg_match('/Desnivell[^<]*:?\s*([^<]+)/i', $content, $matches)) {
			$details[] = '<strong>Desnivell:</strong> ' . esc_html(trim($matches[1]));
		}

		if (empty($details)) {
			// Fallback to excerpt or first paragraph
			$post = get_post($post_id);
			$details[] = wp_trim_words($post->post_content, 30);
		}

		return '<p style="font-size: ' . $font_size . 'pt; line-height: 1.5;">' . implode('<br>', $details) . '</p>';
	}

	/**
	 * Get any error messages.
	 *
	 * @since 1.0.0
	 * @return WP_Error WP_Error object with any error messages.
	 */
	public function get_errors()
	{
		return $this->errors;
	}

	/**
	 * Check if there are any errors.
	 *
	 * @since 1.0.0
	 * @return boolean True if there are errors, false otherwise.
	 */
	public function has_errors()
	{
		return $this->errors->has_errors();
	}
}
