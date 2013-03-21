<?php defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH.'/libraries/REST_Controller.php';

define('API_KEY','ddfa2aaffe9cd021f2b42d7a3c5f5712abca5d43');

class Janrain extends REST_Controller{
	
	public function __construct(){
		parent::__construct();
		
		$this->load->model('auth_model');
		$this->load->model('social_model');
		$this->load->model('player_model');
		$this->load->model('tracker_model');
		$this->load->model('tool/utility','utility');
		$this->load->model('tool/node_stream','node');
		$this->load->model('tool/error','error');
	}
		
	public function token_post(){
		
		var_dump($this->input->post());
		$host = $this->input->server('HTTP_HOST');
		var_dump($host);
		$client = $this->social_model->getClientFromHost($host);
		if(!$client)
			$this->response($this->error->setError('ACCESS_DENIED',$required),200);
		
		$client_id = $client['client_id'];
		$site_id = $client['site_id'];
		$validToken = $this->auth_model->createToken($client_id, $site_id);
		if(!$validToken)
			$this->response($this->error->setError('INVALID_TOKEN'),200);
		
		$required = $this->input->checkParam(array('token'));
		if($required)
			$this->response($this->error->setError('TOKEN_REQUIRED',$required),200);
		
		$token = $this->input->post('token');
		if(strlen($token) != 40) //test the length of the token; it should be 40 characters
			$this->response($this->error->setError('INVALID_TOKEN'),200);

		$post_data = array(
			'token'  => $token,
			'apiKey' => API_KEY,
			'format' => 'json',
			'extended' => 'true'); //Extended is not available to Basic.

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_URL, 'https://rpxnow.com/api/v2/auth_info');
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_FAILONERROR, true);
		$result = curl_exec($curl);
		if ($result == false){
			echo "\n".'Curl error: ' . curl_error($curl);
			echo "\n".'HTTP code: ' . curl_errno($curl);
			echo "\n"; var_dump($post_data);
		}
		curl_close($curl);

		$auth_info = json_decode($result, true);
		var_dump($auth_info);
		if($auth_info['stat'] != 'ok')
			$this->response($this->error->setError('USER_NOT_EXIST'),200);
		
		$profile = $auth_info['profile'];
		$provider = $profile['providerName'];
		
		$input = array();
		if($provider == 'Facebook'){
			$identifier = explode('=', $profile['identifier']);
			$identifier = $identifier[1];
			$input['facebook_id'] = $identifier;
			echo 'facebook id: ';
			var_dump($identifier);
			$pb_player_id = $this->social_model->getPBPlayerIdFromFacebookId($identifier, $client_id, $site_id);
		}
		if($provider == 'Twitter'){
			$identifier = explode('=', $profile['identifier']);
			$identifier = $identifier[1];
			$input['twitter_id'] = $identifier;
			echo 'twitter id: ';
			var_dump($identifier);
			$pb_player_id = $this->social_model->getPBPlayerIdFromTwitterId($identifier, $client_id, $site_id);
		}
		if($pb_player_id == 0){
			$input['client_id'] = $client_id;
			$input['site_id'] = $site_id;
			$input['player_id'] = $identifier;
			$input['image'] = $profile['photo'];
			$input['username'] = $profile['preferredUsername'];
			$input['email'] = (isset($profile['email']) && $profile['email']) ? $profile['email'] : 'no_email@playbasis.com';
			$input['first_name'] = (isset($profile['name']['givenName']) && $profile['name']['givenName']) ? $profile['name']['givenName'] : $profile['displayName'];
			$input['last_name'] = (isset($profile['name']['familyName']) && $profile['name']['familyName']) ? $profile['name']['familyName'] : "";
			$input['gender'] = 0;
			if(isset($profile['gender'])){
				$input['gender'] = ($profile['gender'] == 'male') ? 1 : 2;
			}
			if(isset($profile['birthday'])){
				$input['birth_date'] = $profile['birthday'];
			}
			$pb_player_id = $this->player_model->createPlayer($input);
		}
		echo 'pb_player_id';
		var_dump($pb_player_id);
		
		//login
		$eventMessage = $this->utility->getEventMessage('login');
		$this->tracker_model->trackEvent('LOGIN', $eventMessage, array('client_id'=>$client_id, 'site_id'=>$site_id, 'pb_player_id'=>$pb_player_id, 'action_log_id'=>0));
		$this->node->publish(array('pb_player_id'=>$pb_player_id, 'action_name'=>'login', 'message'=>$eventMessage), $validToken);
		
		var_dump($eventMessage);
	}
}
