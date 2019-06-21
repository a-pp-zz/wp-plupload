<?php
namespace WP_Plupload;
use AppZz\Helpers\Arr;

/**
 * @package Uploader
 * @version 1.6
 */
class Uploader {

	private $_upload_dir;

	private $_mimes = array (
		'gif'  =>array('image/gif'),
		'jpg'  =>array('image/jpeg'),
		'jpeg' =>array('image/jpeg'),
		'png'  =>array('image/png'),
		'pdf'  =>array('application/pdf'),
		'docx' =>array('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'),
		'xls'  =>array('application/vnd.ms-excel'),
		'xlsx' =>array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'),
		'csv'  =>array('text/csv', 'text/plain'),
		'txt'  =>array('text/plain', 'text/csv')
	);

	const VERSION = '1.6.1';
	const FILEFIELD = 'wp_plupload';

	public function __construct ()
	{
		global $plupload_activated;

		if ( ! isset ($plupload_activated)) {
			$plupload_activated = TRUE;
		}

		if ($plupload_activated === TRUE) {
			add_action('admin_enqueue_scripts', array ($this, 'scripts'));
			add_action('wp_enqueue_scripts', array ($this, 'scripts'));
			add_action('wp_ajax_plupload',  array ($this, 'handle'));
			add_action('wp_ajax_nopriv_plupload', array ($this, 'handle'));
			$this->_mimes = apply_filters('plupload_mimes', $this->_mimes);
		}
	}

	public static function factory ()
	{
		return new Uploader ();
	}

	public function get_mimes ()
	{
		return $this->_mimes;
	}

	public function scripts ()
	{
		wp_enqueue_script ("jquery");
		wp_enqueue_script ("plupload");

		foreach (array('html5', 'html4') as $runtime) :
			wp_enqueue_script ("plupload-{$runtime}");
		endforeach;

		$version = Uploader::VERSION;
		//$version .= '-'. mt_rand (9999, 9999999999);

		wp_enqueue_style ("wp-plupload-plugin", plugins_url ("../assets/wp-plupload.min.css", __FILE__), array(), $version);
		global $is_IE;

		if ($is_IE) {
			wp_enqueue_style ("wp-plupload-plugin-ie", plugins_url ("../assets/wp-plupload-ie.min.css", __FILE__), array(), $version);
		}

		wp_enqueue_script ("wp-plupload-plugin", plugins_url ("../assets/wp-plupload.min.js", __FILE__), array ('jquery', 'plupload'), $version);

		$this->_params();
	}

	public static function insert_file ($params)
	{
		$defaults = array (
			'id'       =>1,
			'title'    =>'Выбрать файл',
			'maxsize'  =>'2M',
			'name'     =>'',
			'receiver' =>'',
			'multi'    =>0,
			'before'   =>'',
			'after'    =>'',
			'types'    =>'jpg',
			'dir'	   =>'',
			'ow'       =>'',
			'preview'  =>0,
			'preview_width' => 300
		);

		$params = wp_parse_args($params, $defaults);
		extract ($params);

		$types = (array) $types;
		$types = implode (',', $types);

		$html = '';

		$html = sprintf ('<div class="wp-plupload-container media-upload-form" id="plupload-%s" data-types="%s" data-multi="%d" data-maxsize="%s" data-receiver="%s" data-filefield="%s" data-dir="%s" data-ow="%s" data-name="%s" data-preview="%s" data-preview-width="%d" data-nonce="%s">', esc_attr($id), esc_attr($types), intval($multi), esc_attr($maxsize), esc_attr ($receiver), esc_attr (self::FILEFIELD), esc_attr ($dir), esc_attr ($ow), esc_attr ($name), esc_attr ($preview), intval ($preview_width), wp_create_nonce('wp-plupload-'.$types));
		$html .= $before;

		$html .= '<div class="plupload-features-holder">
		    <div class="plupload-features">
			    <div>Максимальный размер файла: <span class="plupload-max-file-size"></span></div>
			    <div>Разрешенные типы файлов: <span class="plupload-allowed-formats"></span></div>
		    </div>
	    </div>';

	    $html .= sprintf ('<a id="plupload-pickfiles-%s" role="button" class="button button-primary plupload-pickfiles" href="#">%s</a>', esc_attr($id), $title);

	    if ($preview) {
			$html .= '<div class="plupload-preview"></div>';
	    }

	    $html .= '<div class="plupload-filelist hide-if-no-js"></div>';
	    $html .= $after;
	    $html .= '</div>';

		return $html;
	}

	public function handle ()
	{
		// Get parameters
		$chunk = (int) Arr::get($_REQUEST, 'chunk', 0);
		$name = Arr::get($_REQUEST, 'name');
		$newname = Arr::get($_REQUEST, 'newname');
		$types = Arr::get($_REQUEST, 'types');
		$ow = Arr::get($_REQUEST, 'ow');
		$dir = Arr::get($_REQUEST, 'dir', '');

		$this->_upload_dir($dir);

		if ( !check_ajax_referer( 'wp-plupload-' . $types, '_wpnonce', FALSE ) ) {
			$this->_result (array('status' => 403, 'message' => 'Ошибка доступа.'));
		}

		nocache_headers();

		if ($newname) {

			if ($newname == 'random') {
				$newname = $this->_uniqid_real (20);
			}

			$name = pathinfo ($newname, PATHINFO_FILENAME) . '.' . pathinfo ($name, PATHINFO_EXTENSION);
		}

		if ($ow) {
			$path = $this->_upload_dir . DIRECTORY_SEPARATOR . $name;
		}
		else
			$path = $this->_unique_filename ($name);

		// Look for the content type header
		if (isset($_SERVER["HTTP_CONTENT_TYPE"]))
			$content_type = $_SERVER["HTTP_CONTENT_TYPE"];

		if (isset($_SERVER["CONTENT_TYPE"]))
			$content_type = $_SERVER["CONTENT_TYPE"];

		// Handle non multipart uploads older WebKit versions didn't support multipart in HTML5
		if (strpos($content_type, "multipart") !== false)
		{
			if ( !empty($_FILES) AND isset ($_FILES[self::FILEFIELD]))
				$uploaded = $_FILES[self::FILEFIELD]['tmp_name'];
			else
				$this->_result (array('status' => 404, 'message' => 'Не найден указанный файл'));

			if ($uploaded && @is_uploaded_file($uploaded))
			{
				// Open temp file
				$out = fopen($path, $chunk == 0 ? "wb" : "ab");
				if ($out) {
					// Read binary input stream and append it to temp file
					$in = fopen($uploaded, "rb");

					if ($in) {
						while ($buff = fread($in, 4096)) {
							fwrite($out, $buff);
						}
					} else {
						$this->_result (array('status' => 500, 'message' => 'Ошибка при загрузке файла.'));
					}

					fclose($in);
					fclose($out);
					@unlink($uploaded);

				} else {
					$this->_result (array('status' => 500, 'message' => 'Ошибка при загрузке файла.'));
				}
			} else {
				$this->_result (array('status' => 500, 'message' => 'Ошибка при загрузке файла.'));
			}

		} else {
			// Open temp file
			$out = fopen($path, $chunk == 0 ? "wb" : "ab");

			if ($out) {
				// Read binary input stream and append it to temp file
				$in = fopen("php://input", "rb");

				if ($in) {
					while ($buff = fread($in, 4096))
						fwrite($out, $buff);
				} else
					$this->_result (array('status' => 500, 'message' => 'Ошибка при загрузке файла.'));

				fclose($in);
				fclose($out);

			} else {
				$this->_result (array('status' => 500, 'code' => 102, 'message' => 'Ошибка при загрузке файла.'));
			}
		}

		$checked = $this->_check_type ($path, $types);

		if ($checked->passed) {
			//$filename = _wp_relative_upload_path ($path);
			$filename = str_replace(ABSPATH, '', $path);
			$this->_result (array('status' => 201, 'filename'=>$filename, 'url'=>home_url ($filename), 'mime'=>$checked->mime_detected));
		}
		else {
			@unlink ($path);
			$this->_result (array('status' => 400, 'checked'=>$checked, 'message' => 'Запрещенный тип файла.'));
		}
	}

	private function _uniqid_real ($lenght = 20)
	{
	    if (function_exists("random_bytes")) {
	        $bytes = random_bytes(ceil($lenght / 2));
	    } elseif (function_exists("openssl_random_pseudo_bytes")) {
	        $bytes = openssl_random_pseudo_bytes(ceil($lenght / 2));
	    } else {
	        return uniqid ('plupl', true);
	    }

	    return substr(bin2hex($bytes), 0, $lenght);
	}

	private function _params ()
	{
		$uploader_cfg = array(
			'queue_limit_exceeded' => __('You have attempted to queue too many files.'),
			'file_exceeds_size_limit' => __('%s exceeds the maximum upload size for this site.'),
			'zero_byte_file' => __('This file is empty. Please try another.'),
			'invalid_filetype' => __('This file type is not allowed. Please try another.'),
			'not_an_image' => __('This file is not an image. Please try another.'),
			'image_memory_exceeded' => __('Memory exceeded. Please try another smaller file.'),
			'image_dimensions_exceeded' => __('This is larger than the maximum size. Please try another.'),
			'default_error' => __('An error occurred in the upload. Please try again later.'),
			'missing_upload_url' => __('There was a configuration error. Please contact the server administrator.'),
			'upload_limit_exceeded' => __('You may only upload 1 file.'),
			'http_error' => __('HTTP error.'),
			'upload_failed' => __('Upload failed.'),
			/* translators: 1: Opening link tag, 2: Closing link tag */
			'big_upload_failed' => __('Please try uploading this file with the %1$sbrowser uploader%2$s.'),
			'big_upload_queued' => __('%s exceeds the maximum upload size for the multi-file uploader when used in your browser.'),
			'io_error' => __('IO error.'),
			'security_error' => __('Security error.'),
			'file_cancelled' => __('File canceled.'),
			'upload_stopped' => __('Upload stopped.'),
			'dismiss' => __('Dismiss'),
			'crunching' => __('Crunching&hellip;'),
			'deleted' => __('moved to the trash.'),
			'error_uploading' => __('&#8220;%s&#8221; has failed to upload.'),
			'ajaxurl' => admin_url('admin-ajax.php')
		);

		wp_localize_script('wp-plupload-plugin', 'pluploadConfig', $uploader_cfg);
	}

	private function _unique_filename ($filename)
	{
		if (is_writeable ($this->_upload_dir)) {
			return $this->_upload_dir . DIRECTORY_SEPARATOR . wp_unique_filename ($this->_upload_dir, $filename);
		}

		return false;
	}

	private function _result ($result)
	{
		header('Content-Type: application/json; charset=utf-8');
		die (json_encode ($result));
	}

	private function _upload_dir ($upload_dir = '')
	{
		$upload_dir = preg_replace('#[^\w\-\_\/]+#iu', '', $upload_dir);
		$wp_upload_dir = wp_upload_dir();

		$upload_dirs = array ();

		if ( !empty ($upload_dir)) {
			$upload_dirs[] = $wp_upload_dir['basedir'] . '/' . $upload_dir;
		}

		$upload_dirs[] = $wp_upload_dir['path'];

		foreach ($upload_dirs as $dir) {
			if (is_dir ($dir) AND is_writeable($dir)) {
				$this->_upload_dir = $dir;
				break;
			}
		}

		return $this;
	}

	private function _check_type ($filename, $types)
	{
		$ret = new \stdClass;

		if (empty($this->_mimes)) {
			$ret->passed = $ret->ext = $ret->mime_needed = $ret->mime_detected = TRUE;
			return $ret;
		}

		$ret->passed = $ret->ext = $ret->mime_needed = $ret->mime_detected = FALSE;

		if ($types) {
			$types = explode (',', $types);
			if ( ! $types)
				$types = (array) $types;
		}

		if ( ! $types)
			return $ret;

		$types = array_map ('trim', $types);
		$types = array_flip($types);

		$mimes = array_intersect_key($this->_mimes, $types);

		if ( ! $mimes) {
			return $ret;
		}

		$ret->ext = mb_strtolower (pathinfo ($filename, PATHINFO_EXTENSION));

		foreach ($mimes as $ext=>$mime) {
			if ($ext == $ret->ext) {
				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				$mime_detected = finfo_file($finfo, $filename);
				finfo_close($finfo);

				//$ret->passed = (strcasecmp($mime, $mime_detected) === 0);
				$ret->passed = in_array ($mime_detected, $mime);
				$ret->mime_needed = $mime;
				$ret->mime_detected = $mime_detected;
				return $ret;
			}
		}

		return $ret;
	}
}
