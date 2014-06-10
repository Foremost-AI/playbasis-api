<?php defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . '/libraries/REST_Controller.php';

/**
 * CodeIgniter Rest Controller
 *
 * A fully RESTful server implementation for CodeIgniter using one library, one config file and one controller.
 *
 * @package        	CodeIgniter
 * @subpackage    	Libraries
 * @category    	Libraries
 * @author        	Phil Sturgeon
 * @license         http://philsturgeon.co.uk/code/dbad-license
 * @link			https://github.com/philsturgeon/codeigniter-restserver
 * @version 		2.6.1
 */
abstract class REST2_Controller extends REST_Controller
{
	private $id;
	private $site_id;

	/**
	 * Constructor function
	 * @todo Document more please.
	 */
	public function __construct()
	{
		print('<pre>');
		print("REST2_Controller - begin<br>\n");
		parent::__construct();
		$this->load->model('REST_model');
		// TODO: some log here
		$log = array(
			'uri' => $this->uri->uri_string(),
			'method' => $this->request->method,
			'params' => $this->_args ? serialize($this->_args) : null,
			'api_key' => isset($this->rest->key) ? $this->rest->key : '',
			'ip_address' => $this->input->ip_address(),
			'time' => function_exists('now') ? now() : time(),
		);
		print(print_r($this->request, true)."<br>\n");
		print(print_r($log, true)."<br>\n");
		print(print_r($_GET, true)."<br>\n");
		print(print_r($_POST, true)."<br>\n");
		print(print_r($_SERVER, true)."<br>\n");
		print(print_r($this->input, true)."<br>\n");
		print("REST2_Controller - end<br>\n");
		print('</pre>');
	}

	/**
	 * Fire Method
	 *
	 * Fires the designated controller method with the given arguments.
	 *
	 * @param array $method The controller method to fire
	 * @param array $args The arguments to pass to the controller method
	 */
	protected function _fire_method($method, $args)
	{
		/* NOTE:
			We should use try {} finally {} here, so we don't have to override 'response'.
			However, 'finally' requires PHP 5.5, whenever we move on to PHP 5.5, please refactor here.
		*/
		print('<pre>');
		print("_fire_method: begin<br>\n");
		$data = array(
			'method' => $this->request->method,
			'scheme' => $_SERVER['REQUEST_SCHEME'],
			'uri' => $this->uri->uri_string(),
			'query' => $_SERVER['QUERY_STRING'],
			'request' => $this->request->body,
			'response' => null,
			'format' => null,
			'ip' => $this->input->ip_address(),
			'agent' => $_SERVER['HTTP_USER_AGENT'],
		);
		$token = $this->input->post('token'); // token = POST only
		$api_key = $this->input->get('api_key'); // api_key = GET or POST
		if (empty($api_key)) {
			$api_key = $this->input->post('api_key');
		}
		$validToken = !empty($token) ? $this->auth_model->findToken($token) : (!empty($api_key) ? $this->auth_model->createTokenFromAPIKey($api_key) : null);
		$data = array_merge($data, array(
			'client_id' => !empty($validToken) ? $validToken['client_id'] : null,
			'site_id' => !empty($validToken) ? $validToken['site_id'] : null,
			'api_key' => !empty($api_key) ? $api_key : null,
			'token' => !empty($token) ? $token : null,
		));
		$this->id = $this->REST_model->logRequest($data);
		$this->site_id = $data['site_id'];
		try {
			call_user_func_array($method, $args);
		} catch (Exception $e) {
			// TODO: handle error from application code
			// log error
			// reformat output
			$output = $this->format_data($e->getMessage(), $this->response->format);
			$this->REST_model->logResponse($this->id, $this->site_id, array(
				'response' => $output,
				'format' => $this->response->format,
			));
			$log = array(
				'time' => function_exists('now') ? now() : time(),
			);
			print(print_r($log, true)."<br>\n");
			print(print_r($this->response, true)."<br>\n");
			print("_fire_method: exception<br>\n");
			//throw new Exception($e);
			//print_r($e);
			exit($output);
		}
		print("_fire_method: end<br>\n");
		print('</pre>');
	}

	private function format_data($data, $format)
	{
		$output = $data;

		// If the format method exists, call and return the output in that format
		if (method_exists($this, '_format_'.$format))
		{
			// Set the correct format header
			header('Content-Type: '.$this->_supported_formats[$format]);

			$output = $this->{'_format_'.$format}($data);
		}

		// If the format method exists, call and return the output in that format
		elseif (method_exists($this->format, 'to_'.$format))
		{
			// Set the correct format header
			header('Content-Type: '.$this->_supported_formats[$format]);

			$output = $this->format->factory($data)->{'to_'.$format}();
		}

		return $output;
	}

	/**
	 * Response
	 *
	 * Takes pure data and optionally a status code, then creates the response.
	 *
	 * @param array $data
	 * @param null|int $http_code
	 */
	public function response($data = array(), $http_code = null)
	{
		print('<pre>');
		print("response: begin<br>\n");
		global $CFG;

		// If data is empty and not code provide, error and bail
		if (empty($data) && $http_code === null)
		{
			$http_code = 404;

			// create the output variable here in the case of $this->response(array());
			$output = NULL;
		}

		// Otherwise (if no data but 200 provided) or some data, carry on camping!
		else
		{
			// Is compression requested?
			if ($CFG->item('compress_output') === TRUE && $this->_zlib_oc == FALSE)
			{
				if (extension_loaded('zlib'))
				{
					if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) AND strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== FALSE)
					{
						ob_start('ob_gzhandler');
					}
				}
			}

			is_numeric($http_code) OR $http_code = 200;

			$output = $this->format_data($data, $this->response->format);
		}

		header('HTTP/1.1: ' . $http_code);
		header('Status: ' . $http_code);

		// If zlib.output_compression is enabled it will compress the output,
		// but it will not modify the content-length header to compensate for
		// the reduction, causing the browser to hang waiting for more data.
		// We'll just skip content-length in those cases.
		if ( ! $this->_zlib_oc && ! $CFG->item('compress_output'))
		{
			header('Content-Length: ' . strlen($output));
		}

		$log = array(
			'output' => $output,
			'time' => function_exists('now') ? now() : time(),
		);
		print(print_r($log, true)."<br>\n");
		print(print_r($this->response, true)."<br>\n");
		print("response: end<br>\n");
		print('</pre>');
		/////////////////
		$this->REST_model->logResponse($this->id, $this->site_id, array(
			'response' => $output,
			'format' => $this->response->format,
		));
		/////////////////
		exit($output);
	}
}
