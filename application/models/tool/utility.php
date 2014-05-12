<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Utility extends CI_Model
{
	public function getEventMessage($eventType, $amount = 'some', $pointName = 'points', $badgeName = 'a', $newLevel = '', $objectiveName = '', $goodsName = '')
	{
		switch($eventType)
		{
		case 'badge':
			return "earned $badgeName badge";
		case 'point':
			return "earned $amount $pointName";
		case 'level':
			return ($newLevel) ? "is now level $newLevel" : 'gained a level';
		case 'login':
			return 'logged in';
		case 'logout':
			return 'logged out';
		case 'objective':
			return 'completed an objective "'.$objectiveName.'"';
        case 'goods':
            return "redeem $goodsName goods";
		default:
			return 'did a thing';
		}
	}

	public function elapsed_time($key = "default") {
		static $last = array();
		$now = microtime(true);
		$ret = null;
		if (!array_key_exists($key, $last)) $last[$key] = null;
		if ($last[$key] != null) $ret = $now - $last[$key];
		$last[$key] = $now;
		return $ret;
	}

	public function url_exists($url, $prefix='') {
		if (substr($url, 0, 4) != 'http') $url = $prefix.$url;
		$file_headers = @get_headers($url);
		log_message('debug', 'url = '.print_r($url, true).', header = '.print_r($file_headers[0], true));
		return strpos($file_headers[0], ' 20') || strpos($file_headers[0], ' 30');
	}

	public function save_dir($dir, $mode=0755) {
		if (!is_dir($dir)) {
			mkdir($dir, $mode, true);
		}
	}

	public function save_file($dir, $file, $content, $mode=0755) {
		$this->save_dir($dir, $mode);
		file_put_contents("$dir/$file", $content);
	}

	/* http://stackoverflow.com/questions/19083175/generate-random-string-in-php-for-file-name */
	public function random_string($length) {
		$key = '';
		$keys = array_merge(range(0, 9), range('a', 'z'));
		for ($i = 0; $i < $length; $i++) {
			$key .= $keys[array_rand($keys)];
		}
		return $key;
	}

	/* require: $this->load->library('amazon_ses'); */
	public function email($from, $to, $subject, $message) {
		$this->amazon_ses->from($from);
		$this->amazon_ses->to($to);
		$this->amazon_ses->subject($subject);
		$this->amazon_ses->message($message);
		return $this->amazon_ses->send();
	}

	/* require: $this->load->library('pdf'); */
	public function html2pdf($html, $output=false) {
		$this->pdf->load_html($html);
		$this->pdf->render();
		return $output ? $this->pdf->output() : null;
	}

	/* require: $this->load->library('mpdf'); */
	public function html2mpdf($html, $output=false) {
		//$this->mpdf->WriteHTML($html);
		//return $output ? $this->mpdf->Output('', 'S') : null;
		require_once(APPPATH.'/libraries/mpdf/mpdf.php');
		$mpdf = new mPDF('s','A4','','',25,15,21,22,10,10);
		$mpdf->WriteHTML($html);
		return $output ? $mpdf->Output('', 'S') : null;
	}

	function file_get_contents_curl($image_file, $image_url) {
		if(!file_exists($image_file)){
			$fp = fopen($image_file, 'w+');

			$ch = curl_init($image_url);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_FILE, $fp);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_exec($ch);

			curl_close($ch);
			fclose($fp);
		}
	}
}
?>