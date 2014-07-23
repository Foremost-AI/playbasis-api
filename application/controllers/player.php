<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . '/libraries/REST2_Controller.php';
class Player extends REST2_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('auth_model');
		$this->load->model('player_model');
		$this->load->model('tracker_model');
		$this->load->model('point_model');
		$this->load->model('action_model');
        $this->load->model('level_model');
		$this->load->model('tool/error', 'error');
		$this->load->model('tool/utility', 'utility');
		$this->load->model('tool/respond', 'resp');
		$this->load->model('tool/node_stream', 'node');
	}
	public function index_get($player_id = '')
	{
		if(!$player_id)
			$this->response($this->error->setError('PARAMETER_MISSING', array(
				'player_id'
			)), 200);
		//get playbasis player id
		$pb_player_id = $this->player_model->getPlaybasisId(array_merge($this->validToken, array(
			'cl_player_id' => $player_id
		)));
		if(!$pb_player_id)
			$this->response($this->error->setError('USER_NOT_EXIST'), 200);
		//read player information
		$player['player'] = $this->player_model->readPlayer($pb_player_id, $this->site_id, array(
			'username',
			'first_name',
			'last_name',
			'gender',
			'image',
			'exp',
			'level',
			'date_added',
			'birth_date'
		));

		//get last login/logout
		$player['player']['last_login'] = $this->player_model->getLastEventTime($pb_player_id, $this->site_id, 'LOGIN');
		$player['player']['last_logout'] = $this->player_model->getLastEventTime($pb_player_id, $this->site_id, 'LOGOUT');
		$player['player']['cl_player_id'] = $player_id;
		$this->response($this->resp->setRespond($player), 200);
	}
	public function index_post($player_id = '')
	{
		if(!$player_id)
			$this->response($this->error->setError('PARAMETER_MISSING', array(
				'player_id'
			)), 200);
		//get playbasis player id
		$pb_player_id = $this->player_model->getPlaybasisId(array_merge($this->validToken, array(
			'cl_player_id' => $player_id
		)));
		if(!$pb_player_id)
			$this->response($this->error->setError('USER_NOT_EXIST'), 200);
		//read player information
		$player['player'] = $this->player_model->readPlayer($pb_player_id, $this->site_id, array(
			'username',
			'first_name',
			'last_name',
			'gender',
			'image',
            'email',
			'exp',
			'level',
			'date_added',
			'birth_date'
		));

		//get last login/logout
		$player['player']['last_login'] = $this->player_model->getLastEventTime($pb_player_id, $this->site_id, 'LOGIN');
		$player['player']['last_logout'] = $this->player_model->getLastEventTime($pb_player_id, $this->site_id, 'LOGOUT');
		$player['player']['cl_player_id'] = $player_id;
		$this->response($this->resp->setRespond($player), 200);
	}
    /*public function list_get()
    {
        $required = $this->input->checkParam(array(
            'list_player_id'
        ));
        if($required)
            $this->response($this->error->setError('PARAMETER_MISSING', $required), 200);
        $list_player_id = explode(",", $this->input->get('list_player_id'));
        //read player information
        $player['player'] = $this->player_model->readListPlayer($list_player_id, $this->site_id, array(
            'username',
            'first_name',
            'last_name',
            'gender',
            'image',
            'exp',
            'level',
            'date_added AS registered',
            'birth_date'
        ));

        $this->response($this->resp->setRespond($player), 200);
    }*/

//     public function list_post()
//     {
//         $required = $this->input->checkParam(array(
//             'list_player_id'
//         ));
//         if($required)
//             $this->response($this->error->setError('PARAMETER_MISSING', $required), 200);
//         $list_player_id = explode(",", $this->input->post('list_player_id'));
//         //read player information
//         $player['player'] = $this->player_model->readListPlayer($list_player_id, $this->site_id, array(
//             'cl_player_id',
//             'username',
//             'first_name',
//             'last_name',
//             'gender',
//             'image',
//             'email',
//             'exp',
//             'level',
//             'date_added',
//             'birth_date'
//         ));

//         foreach ($player['player'] as &$p){
// //        	unset($p['_id']);
//         	if(!isset($p['birth_date'])){
//         		$p['birth_date'] = null;	
//         	}else{
//         		$p['birth_date'] = date('Y-m-d', $p['birth_date']->sec);
//         	}
//         	$p['registered'] = datetimeMongotoReadable($p['date_added']);
//         	unset($p['date_added']);
            
//         	$pb_player_id = $this->player_model->getPlaybasisId(array_merge($this->validToken, array(
// 			'cl_player_id' => $p['cl_player_id']
// 			)));

//         	$p['last_login'] = $this->player_model->getLastEventTime($pb_player_id, $this->site_id, 'LOGIN');
//         	$p['last_logout'] = $this->player_model->getLastEventTime($pb_player_id, $this->site_id, 'LOGOUT');
//         }

//         $this->response($this->resp->setRespond($player), 200);
//     }

    public function list_post()
    {
        $required = $this->input->checkParam(array(
            'list_player_id'
        ));
        if($required)
            $this->response($this->error->setError('PARAMETER_MISSING', $required), 200);
        $list_player_id = explode(",", $this->input->post('list_player_id'));
        //read player information
		for($i = 0; $i<count($list_player_id); $i++){
			$data = array('client_id'=>$this->validToken['client_id'], 'site_id'=>$this->site_id, 'cl_player_id'=>$list_player_id[$i]);
			$pb_player_id = $this->player_model->getPlaybasisId($data);

			$player['player'][] = $this->player_model->readPlayer($pb_player_id, $this->site_id, array('cl_player_id','username',
			'first_name',
			'last_name',
			'gender',
			'image',
            'email',
			'exp',
			'level',
			'date_added',
			'birth_date'));
			
			$player['player'][$i]['last_login']= $this->player_model->getLastEventTime($pb_player_id, $this->site_id, 'LOGIN');
			$player['player'][$i]['last_logout']= $this->player_model->getLastEventTime($pb_player_id, $this->site_id, 'LOGOUT');
		}     

        $this->response($this->resp->setRespond($player), 200);

    }

    
	public function details_get($player_id = '')
	{
		if(!$player_id)
			$this->response($this->error->setError('PARAMETER_MISSING', array(
				'player_id'
			)), 200);
		//get playbasis player id
		$pb_player_id = $this->player_model->getPlaybasisId(array_merge($this->validToken, array(
			'cl_player_id' => $player_id
		)));
		if(!$pb_player_id)
			$this->response($this->error->setError('USER_NOT_EXIST'), 200);

		//read player information
		$player['player'] = $this->player_model->readPlayer($pb_player_id, $this->site_id, array(
			'username',
			'first_name',
			'last_name',
			'gender',
			'image',
			'exp',
			'level',
			'date_added',
			'birth_date'
		));
        //percent exp of level
//        $level = $this->level_model->getLevelDetail($player['player']['level'], $this->validToken['client_id'], $this->validToken['site_id']);
        $level = $this->level_model->getLevelByExp($player['player']['exp'], $this->validToken['client_id'], $this->validToken['site_id']);
        $base_exp = $level['min_exp'];
        $max_exp = $level['max_exp'] - $base_exp;
        $now_exp = $player['player']['exp'] - $base_exp;
        if(isset($level['max_exp']) && $max_exp != 0){
            $percent_exp = (floatval($now_exp) * floatval (100)) / floatval($max_exp);
            $player['player']['percent_of_level'] = round($percent_exp,2);
        }else{
            $player['player']['percent_of_level'] = 100;
        }
        $player['player']['level'] = $level['level'];
        $player['player']['level_title'] = $level['level_title'];
        $player['player']['level_image'] = $level['level_image'];

        $player['player']['badges'] = $this->player_model->getBadge($pb_player_id, $this->site_id);
        $points = $this->player_model->getPlayerPoints($pb_player_id, $this->site_id);
        foreach($points as &$point)
        {
			$point['reward_name'] = $this->point_model->getRewardNameById(array_merge($this->validToken, array(
                'reward_id' => $point['reward_id']
            )));
            $point['reward_id'] = $point['reward_id']."";
            ksort($point);
        }
        $player['player']['points'] = $points;
		//get last login/logout
		$player['player']['last_login'] = $this->player_model->getLastEventTime($pb_player_id, $this->site_id, 'LOGIN');
		$player['player']['last_logout'] = $this->player_model->getLastEventTime($pb_player_id, $this->site_id, 'LOGOUT');
		$this->response($this->resp->setRespond($player), 200);
	}
	public function details_post($player_id = '')
	{
		if(!$player_id)
			$this->response($this->error->setError('PARAMETER_MISSING', array(
				'player_id'
			)), 200);
		//get playbasis player id
		$pb_player_id = $this->player_model->getPlaybasisId(array_merge($this->validToken, array(
			'cl_player_id' => $player_id
		)));
		if(!$pb_player_id)
			$this->response($this->error->setError('USER_NOT_EXIST'), 200);

		//read player information
		$player['player'] = $this->player_model->readPlayer($pb_player_id, $this->site_id, array(
			'username',
			'first_name',
			'last_name',
			'gender',
			'image',
            'email',
			'exp',
			'level',
			'date_added',
			'birth_date'
		));

        //percent exp of level
//        $level = $this->level_model->getLevelDetail($player['player']['level'], $this->validToken['client_id'], $this->validToken['site_id']);
        $level = $this->level_model->getLevelByExp($player['player']['exp'], $this->validToken['client_id'], $this->validToken['site_id']);
        $base_exp = $level['min_exp'];
        $max_exp = $level['max_exp'] - $base_exp;
        $now_exp = $player['player']['exp'] - $base_exp;
        if(isset($level['max_exp'])){
            $percent_exp = (floatval($now_exp) * floatval (100)) / floatval($max_exp);
            $player['player']['percent_of_level'] = round($percent_exp,2);
        }else{
            $player['player']['percent_of_level'] = 100;
        }
        $player['player']['level'] = $level['level'];
        $player['player']['level_title'] = $level['level_title'];
        $player['player']['level_image'] = $level['level_image'];

        $player['player']['badges'] = $this->player_model->getBadge($pb_player_id, $this->site_id);
        $points = $this->player_model->getPlayerPoints($pb_player_id, $this->site_id);
        foreach($points as &$point)
        {
            $point['reward_name'] = $this->point_model->getRewardNameById(array_merge($this->validToken, array(
                'reward_id' => $point['reward_id']
            )));
            $point['reward_id'] = $point['reward_id']."";
            ksort($point);
        }
        $player['player']['points'] = $points;
		//get last login/logout
		$player['player']['last_login'] = $this->player_model->getLastEventTime($pb_player_id, $this->site_id, 'LOGIN');
		$player['player']['last_logout'] = $this->player_model->getLastEventTime($pb_player_id, $this->site_id, 'LOGOUT');
		$this->response($this->resp->setRespond($player), 200);
	}
	public function register_post($player_id = '')
	{
		$required = $this->input->checkParam(array(
//			'image',
			'email',
			'username'
		));
		if(!$player_id)
			array_push($required, 'player_id');
		if($required)
			$this->response($this->error->setError('PARAMETER_MISSING', $required), 200);
		//get playbasis player id
		$pb_player_id = $this->player_model->getPlaybasisId(array_merge($this->validToken, array(
			'cl_player_id' => $player_id
		)));
		if($pb_player_id)
			$this->response($this->error->setError('USER_ALREADY_EXIST'), 200);
		$playerInfo = array(
			'email' => $this->input->post('email'),
			'image' => $this->input->post('image') ? $this->input->post('image') : "https://www.pbapp.net/images/default_profile.jpg",
			'username' => $this->input->post('username'),
			'player_id' => $player_id
		);
		$firstName = $this->input->post('first_name');
		if($firstName)
			$playerInfo['first_name'] = $firstName;
		$lastName = $this->input->post('last_name');
		if($lastName)
			$playerInfo['last_name'] = $lastName;
		$nickName = $this->input->post('nickname');
		if($nickName)
			$playerInfo['nickname'] = $nickName;
		$facebookId = $this->input->post('facebook_id');
		if($facebookId)
			$playerInfo['facebook_id'] = $facebookId;
		$twitterId = $this->input->post('twitter_id');
		if($twitterId)
			$playerInfo['twitter_id'] = $twitterId;
		$instagramId = $this->input->post('instagram_id');
		if($instagramId)
			$playerInfo['instagram_id'] = $instagramId;
		$password = $this->input->post('password');
		if($password)
			$playerInfo['password'] = $password;
		$gender = $this->input->post('gender');
		if($gender)
			$playerInfo['gender'] = $gender;
		$birthdate = $this->input->post('birth_date');
		if($birthdate)
		{
			$timestamp = strtotime($birthdate);
			$playerInfo['birth_date'] = date('Y-m-d', $timestamp);
		}
		$this->player_model->createPlayer(array_merge($this->validToken, $playerInfo));
		$this->response($this->resp->setRespond(), 200);
	}
	public function update_post($player_id = '')
	{
		if(!$player_id)
			$this->response($this->error->setError('PARAMETER_MISSING', array(
				'player_id'
			)), 200);
		//get playbasis player id
		$pb_player_id = $this->player_model->getPlaybasisId(array_merge($this->validToken, array(
			'cl_player_id' => $player_id
		)));
		if(!$pb_player_id)
			$this->response($this->error->setError('USER_NOT_EXIST'), 200);		
		$playerInfo = array();
		$email = $this->input->post('email');
		if($email)
			$playerInfo['email'] = $email;
		$image = $this->input->post('image');
		if($image)
			$playerInfo['image'] = $image;
		$username = $this->input->post('username');
		if($username)
			$playerInfo['username'] = $username;
		$exp = $this->input->post('exp');
		if(is_numeric($exp))
			$playerInfo['exp'] = intval($exp);
		$level = $this->input->post('level');
		if(is_numeric($level))
			$playerInfo['level'] = intval($level);
		$firstName = $this->input->post('first_name');
		if($firstName)
			$playerInfo['first_name'] = $firstName;
		$lastName = $this->input->post('last_name');
		if($lastName)
			$playerInfo['last_name'] = $lastName;
		$nickName = $this->input->post('nickname');
		if($nickName)
			$playerInfo['nickname'] = $nickName;
		$facebookId = $this->input->post('facebook_id');
		if($facebookId)
			$playerInfo['facebook_id'] = $facebookId;
		$twitterId = $this->input->post('twitter_id');
		if($twitterId)
			$playerInfo['twitter_id'] = $twitterId;
		$instagramId = $this->input->post('instagram_id');
		if($instagramId)
			$playerInfo['instagram_id'] = $instagramId;
		$password = $this->input->post('password');
		if($password)
			$playerInfo['password'] = $password;
		$gender = $this->input->post('gender');
		if($gender)
			$playerInfo['gender'] = intval($gender);
		$birthdate = $this->input->post('birth_date');
		if($birthdate)
		{
			$timestamp = strtotime($birthdate);
			$playerInfo['birth_date'] = date('Y-m-d', $timestamp);
		}
		$this->player_model->updatePlayer($pb_player_id, $this->validToken['site_id'], $playerInfo);
		$this->response($this->resp->setRespond(), 200);
	}
	public function delete_post($player_id = '')
	{
		if(!$player_id)
			$this->response($this->error->setError('PARAMETER_MISSING', array(
				'player_id'
			)), 200);
		//get playbasis player id
		$pb_player_id = $this->player_model->getPlaybasisId(array_merge($this->validToken, array(
			'cl_player_id' => $player_id
		)));
		if(!$pb_player_id)
			$this->response($this->error->setError('USER_NOT_EXIST'), 200);
		$this->player_model->deletePlayer($pb_player_id, $this->validToken['site_id']);
		$this->response($this->resp->setRespond(), 200);
	}
	public function login_post($player_id = '')
	{
		if(!$player_id)
			$this->response($this->error->setError('PARAMETER_MISSING', array(
				'player_id'
			)), 200);
		//get playbasis player id
		$pb_player_id = $this->player_model->getPlaybasisId(array_merge($this->validToken, array(
			'cl_player_id' => $player_id
		)));
		if(!$pb_player_id)
			$this->response($this->error->setError('USER_NOT_EXIST'), 200);
		//trigger and log event
		$eventMessage = $this->utility->getEventMessage('login');
		$this->tracker_model->trackEvent('LOGIN', $eventMessage, array(
			'client_id' => $this->validToken['client_id'],
			'site_id' => $this->validToken['site_id'],
			'pb_player_id' => $pb_player_id,
			'action_log_id' => null
		));
		//publish to node stream
		$this->node->publish(array(
			'pb_player_id' => $pb_player_id,
			'action_name' => 'login',
			'action_icon' => 'Signin',
			'message' => $eventMessage
		), $this->validToken['domain_name'], $this->validToken['site_id']);
		$this->response($this->resp->setRespond(), 200);
	}
	public function logout_post($player_id = '')
	{
		if(!$player_id)
			$this->response($this->error->setError('PARAMETER_MISSING', array(
				'player_id'
			)), 200);
		//get playbasis player id
		$pb_player_id = $this->player_model->getPlaybasisId(array_merge($this->validToken, array(
			'cl_player_id' => $player_id
		)));
		if(!$pb_player_id)
			$this->response($this->error->setError('USER_NOT_EXIST'), 200);
		//trigger and log event
		$eventMessage = $this->utility->getEventMessage('logout');
		$this->tracker_model->trackEvent('LOGOUT', $eventMessage, array(
			'client_id' => $this->validToken['client_id'],
			'site_id' => $this->validToken['site_id'],
			'pb_player_id' => $pb_player_id,
			'action_log_id' => null
		));
		//publish to node stream
		$this->node->publish(array(
			'pb_player_id' => $pb_player_id,
			'action_name' => 'logout',
			'action_icon' => 'Key',
			'message' => $eventMessage
		), $this->validToken['domain_name'], $this->validToken['site_id']);
		$this->response($this->resp->setRespond(), 200);
	}
	public function points_get($player_id = '')
	{
		if(!$player_id)
			$this->response($this->error->setError('PARAMETER_MISSING', array(
				'player_id'
			)), 200);
		//get playbasis player id
		$pb_player_id = $this->player_model->getPlaybasisId(array_merge($this->validToken, array(
			'cl_player_id' => $player_id
		)));
		if(!$pb_player_id)
			$this->response($this->error->setError('USER_NOT_EXIST'), 200);
		$input = array_merge($this->validToken, array(
			'pb_player_id' => $pb_player_id
		));
		//get player points
		$points['points'] = $this->player_model->getPlayerPoints($pb_player_id, $this->site_id);
		foreach($points['points'] as &$point)
		{
			$point['reward_name'] = $this->point_model->getRewardNameById(array_merge($input, array(
				'reward_id' => $point['reward_id']
			)));
            $point['reward_id'] = $point['reward_id']."";
			ksort($point);
		}
		$this->response($this->resp->setRespond($points), 200);
	}
	public function point_get($player_id = '', $reward = '')
	{
		$required = array();
		if(!$player_id)
			array_push($required, 'player_id');
		if(!$reward)
			array_push($required, 'reward');
		if($required)
			$this->response($this->error->setError('PARAMETER_MISSING', $required), 200);
		//get playbasis player id
		$pb_player_id = $this->player_model->getPlaybasisId(array_merge($this->validToken, array(
			'cl_player_id' => $player_id
		)));
		if(!$pb_player_id)
			$this->response($this->error->setError('USER_NOT_EXIST'), 200);
		$input = array_merge($this->validToken, array(
			'reward_name' => $reward
		));
		$reward_id = $this->point_model->findPoint($input);
		if(!$reward_id)
			$this->response($this->error->setError('REWARD_NOT_FOUND'), 200);
		$point['point'] = $this->player_model->getPlayerPoint($pb_player_id, $reward_id, $this->site_id);
        $point['point'][0]['reward_id'] = $reward_id."";
		$point['point'][0]['reward_name'] = $reward;
		ksort($point);
		$this->response($this->resp->setRespond($point), 200);
	}

	public function point_history_get($player_id =''){
		$required = array();
		if(!$player_id){
			array_push($required, 'player_id');
		}
		if($required){
			$this->response($this->error->setError('PARAMETER_MISSING', $required), 200);
		}

		$pb_player_id = $this->player_model->getPlaybasisId(array_merge($this->validToken, array(
			'cl_player_id' => $player_id
		)));
		if(!$pb_player_id){
			$this->response($this->error->setError('USER_NOT_EXIST'), 200);
		}


		$offset = ($this->input->get('offset'))?$this->input->get('offset'):0;			
		$limit = ($this->input->get('limit'))?$this->input->get('limit'):20;
        if($limit > 500){
            $limit = 500;
        }
		$reward_name = $this->input->get('point_name');

		$reward = array(
				'site_id'=>$this->site_id,
				'client_id'=>$this->validToken['client_id'],
				'reward_name'=>$reward_name
			);

		if($reward){
			$reward_id = $this->point_model->findPoint($reward);	
		}else{
			$reward_id = null;
		}

		$respondThis['points'] = $this->player_model->getPointHistoryFromPlayerID($pb_player_id, $this->site_id, $reward_id, $offset, $limit);

		$this->response($this->resp->setRespond($respondThis), 200);

	}

	public function action_get($player_id = '', $action = '', $option = 'time')
	{
		$required = array();
		if(!$player_id)
			array_push($required, 'player_id');
		if($required)
			$this->response($this->error->setError('PARAMETER_MISSING', $required), 200);
		//get playbasis player id
		$pb_player_id = $this->player_model->getPlaybasisId(array_merge($this->validToken, array(
			'cl_player_id' => $player_id
		)));
		if(!$pb_player_id)
			$this->response($this->error->setError('USER_NOT_EXIST'), 200);
		$actions = array();
		if($action)
		{
			$action_id = $this->action_model->findAction(array_merge($this->validToken, array(
				'action_name' => $action
			)));
			if(!$action_id)
				$this->response($this->error->setError('ACTION_NOT_FOUND'), 200);
			$actions['action'] = ($option == 'time') ? $this->player_model->getActionPerform($pb_player_id, $action_id, $this->site_id) : $this->player_model->getActionCount($pb_player_id, $action_id, $this->site_id);
		}
		else //get last action performed
		{
			if($option != 'time')
				$this->response($this->error->setError('ACTION_NOT_FOUND'), 200);
			$actions['action'] = $this->player_model->getLastActionPerform($pb_player_id, $this->site_id);
		}
		$this->response($this->resp->setRespond($actions), 200);
	}
	public function badge_get($player_id = '')
	{
		if(!$player_id)
			$this->response($this->error->setError('PARAMETER_MISSING', array(
				'player_id'
			)), 200);
		//get playbasis player id
		$pb_player_id = $this->player_model->getPlaybasisId(array_merge($this->validToken, array(
			'cl_player_id' => $player_id
		)));
		if(!$pb_player_id)
			$this->response($this->error->setError('USER_NOT_EXIST'), 200);
		//get player badge
		$badgeList = $this->player_model->getBadge($pb_player_id, $this->site_id);
		$this->response($this->resp->setRespond($badgeList), 200);
	}
	public function claimBadge_post($player_id='', $badge_id='')
	{
		if(!$player_id || !$badge_id)
			$this->response($this->error->setError('PARAMETER_MISSING', array(
				'player_id',
				'badge_id'
			)), 200);
		//get playbasis player id
		$pb_player_id = $this->player_model->getPlaybasisId(array_merge($this->validToken, array(
			'cl_player_id' => $player_id
		)));
		if(!$pb_player_id)
			$this->response($this->error->setError('USER_NOT_EXIST'), 200);
        try{
            $badge_id = new MongoId($badge_id);
        } catch (Exception $e) {
            $badge_id = $badge_id;
        }
		$result = $this->player_model->claimBadge($pb_player_id, $badge_id, $this->site_id, $this->client_id);
        if($result){
            $this->response($this->resp->setRespond($result), 200);
        }else{
            $this->response($this->error->setError('REWARD_NOT_FOUND'), 200);
        }
	}
	public function redeemBadge_post($player_id='', $badge_id='')
	{
		if(!$player_id || !$badge_id)
			$this->response($this->error->setError('PARAMETER_MISSING', array(
				'player_id',
				'badge_id'
			)), 200);
		//get playbasis player id
		$pb_player_id = $this->player_model->getPlaybasisId(array_merge($this->validToken, array(
			'cl_player_id' => $player_id
		)));
		if(!$pb_player_id)
			$this->response($this->error->setError('USER_NOT_EXIST'), 200);
        try{
            $badge_id = new MongoId($badge_id);
        } catch (Exception $e) {
            $badge_id = $badge_id;
        }
		$result = $this->player_model->redeemBadge($pb_player_id, $badge_id, $this->site_id, $this->client_id);
        if($result){
            $this->response($this->resp->setRespond($result), 200);
        }else{
            $this->response($this->error->setError('REWARD_NOT_FOUND'), 200);
        }
	}
    public function rank_get($ranked_by, $limit = 20)
    {
        if(!$ranked_by)
            $this->response($this->error->setError('PARAMETER_MISSING', array(
                'ranked_by'
            )), 200);
        if ($ranked_by == 'level') {
            $leaderboard = $this->player_model->getLeaderboardByLevel($limit, $this->validToken['client_id'], $this->validToken['site_id']);
        } else {
            $leaderboard = $this->player_model->getLeaderboard($ranked_by, $limit, $this->validToken['client_id'], $this->validToken['site_id']);
        }
        $this->response($this->resp->setRespond($leaderboard), 200);
    }
    public function ranks_get($limit = 20)
    {
        $leaderboards = $this->player_model->getLeaderboards($limit, $this->validToken['client_id'], $this->validToken['site_id']);
        $this->response($this->resp->setRespond($leaderboards), 200);
    }
    public function rankuser_get($player_id = '', $ranked_by = ''){
    	if($player_id == '' || $ranked_by == ''){
    		$this->response($this->error->setError('PARAMETER_MISSING', array(
    		    'ranked_by','player_id'
    		)), 200);
    	}else{
    		$player = $this->player_model->getUserRanking($ranked_by, $player_id, $this->validToken['client_id'], $this->validToken['site_id']);
    		if(!empty($player)){
    			$this->response($this->resp->setRespond($player), 200);	
    		}else{
    			$this->response($this->error->setError('USER_OR_REWARD_NOT_EXIST'), 200);
    		}
    	}
    }
    public function level_get($level='')
    {
        if(!$level)
            $this->response($this->error->setError('PARAMETER_MISSING', array(
                'level'
            )), 200);

        $level= $this->level_model->getLevelDetail($level, $this->validToken['client_id'], $this->validToken['site_id']);
        $this->response($this->resp->setRespond($level), 200);
    }
    public function levels_get()
    {
        $level= $this->level_model->getLevelsDetail($this->validToken['client_id'], $this->validToken['site_id']);
        $this->response($this->resp->setRespond($level), 200);
    }
    public function goods_get($player_id='')
    {
        if(!$player_id)
            $this->response($this->error->setError('PARAMETER_MISSING', array(
                'player_id'
            )), 200);
        //get playbasis player id
        $pb_player_id = $this->player_model->getPlaybasisId(array_merge($this->validToken, array(
            'cl_player_id' => $player_id
        )));
        if(!$pb_player_id)
            $this->response($this->error->setError('USER_NOT_EXIST'), 200);
        //get player goods
        $goodsList['goods'] = $this->player_model->getGoods($pb_player_id, $this->site_id);
        $this->response($this->resp->setRespond($goodsList), 200);
    }
	public function total_get()
	{
		$log = array();
		$sum = 0;
		$prev = null;
		foreach ($this->player_model->new_registration($this->validToken, $this->input->get('from'), $this->input->get('to')) as $key => $value) {
			$key = $value['_id'];
			if ($prev) {
				$d = date('Y-m-d', strtotime('+1 day', strtotime($prev)));
				while (strtotime($d) < strtotime($key)) {
					array_push($log, array($d => array('count' => 0)));
					$d = date('Y-m-d', strtotime('+1 day', strtotime($d)));
				}
			}
			$prev = $key;
			$sum += $value['value'];
			array_push($log, array($key => array('count' => $sum)));
		}
		$this->response($this->resp->setRespond($log), 200);
	}
	public function new_get()
	{
        // Limit
        $site_id = $this->validToken['site_id'];
        $plan_id = $this->client_model->getPermissionBySiteId($site_id);
        $limit = $this->client_model->getPlanLimitById(
            $site_id,
            $plan_id,
            'others',
            'insight'
        );

        $now = new Datetime();
        $startDate      = new DateTime($this->input->get('from', TRUE));
        $endDate        = new DateTime($this->input->get('to', TRUE));

		$log = array();
		$prev = null;
		foreach ($this->player_model->new_registration(
            $this->validToken,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')) as $key => $value) {
                $dDiff = $now->diff(new DateTime($value["_id"]));
                if ($limit && $dDiff->days > $limit) {
                    continue;
                }
			$key = $value['_id'];
			if ($prev) {
				$d = date('Y-m-d', strtotime('+1 day', strtotime($prev)));
				while (strtotime($d) < strtotime($key)) {
					array_push($log, array($d => array('count' => 0)));
					$d = date('Y-m-d', strtotime('+1 day', strtotime($d)));
				}
			}
			$prev = $key;
			array_push($log, array($key => array('count' => $value['value'])));
		}
		$this->response($this->resp->setRespond($log), 200);
	}
	/* unused */
	public function dau_get()
	{
		$log = array();
		$prev = null;
		foreach ($this->player_model->daily_active_user($this->validToken, $this->input->get('from'), $this->input->get('to')) as $key => $value) {
			$key = $value['_id'];
			if ($prev) {
				$d = date('Y-m-d', strtotime('+1 day', strtotime($prev)));
				while (strtotime($d) < strtotime($key)) {
					array_push($log, array($d => array('count' => 0)));
					$d = date('Y-m-d', strtotime('+1 day', strtotime($d)));
				}
			}
			$prev = $key;
			array_push($log, array($key => array('count' => ($value['value'] instanceof MongoId ? 1 : $value['value']))));
		}
		$this->response($this->resp->setRespond($log), 200);
	}
	public function dauDay_get()
	{
		$log = array();
		$prev = null;
		foreach ($this->player_model->daily_active_user_per_day($this->validToken, $this->input->get('from'), $this->input->get('to')) as $key => $value) {
			$key = $value['_id'];
			if ($prev) {
				$d = date('Y-m-d', strtotime('+1 day', strtotime($prev)));
				while (strtotime($d) < strtotime($key)) {
					array_push($log, array($d => array('count' => 0)));
					$d = date('Y-m-d', strtotime('+1 day', strtotime($d)));
				}
			}
			$prev = $key;
			array_push($log, array($key => array('count' => ($value['value'] instanceof MongoId ? 1 : $value['value']))));
		}
		$this->response($this->resp->setRespond($log), 200);
	}
	/* unused */
	public function mau_get()
	{
		$log = array();
		$prev = null;
		foreach ($this->player_model->monthy_active_user($this->validToken, $this->input->get('from'), $this->input->get('to')) as $key => $value) {
			$key = $value['_id'];
			if ($prev) {
				$d = date('Y-m', strtotime('+1 month', strtotime($prev.'-01 00:00:00')));
				while (strtotime($d.'-01 00:00:00') < strtotime($key.'-01 00:00:00')) {
					array_push($log, array($d => array('count' => 0)));
					$d = date('Y-m', strtotime('+1 month', strtotime($d.'-01 00:00:00')));
				}
			}
			$prev = $key;
			array_push($log, array($key => array('count' => ($value['value'] instanceof MongoId ? 1 : $value['value']))));
		}
		$this->response($this->resp->setRespond($log), 200);
	}
	public function mauDay_get()
	{
		$log = array();
		$prev = null;
		foreach ($this->player_model->monthy_active_user_per_day($this->validToken, $this->input->get('from'), $this->input->get('to')) as $key => $value) {
			$key = $value['_id'];
			if (strtotime($key.' 00:00:00') <= time()) { // suppress future calculated results
				if ($prev) {
					$d = date('Y-m-d', strtotime('+1 day', strtotime($prev)));
					while (strtotime($d) < strtotime($key)) {
						array_push($log, array($d => array('count' => 0)));
						$d = date('Y-m-d', strtotime('+1 day', strtotime($d)));
					}
				}
				$prev = $key;
				array_push($log, array($key => array('count' => ($value['value'] instanceof MongoId ? 1 : $value['value']))));
			} else break;
		}
		$this->response($this->resp->setRespond($log), 200);
	}
	public function mauWeek_get()
	{
		$log = array();
		$prev = null;
		foreach ($this->player_model->monthy_active_user_per_week($this->validToken, $this->input->get('from'), $this->input->get('to')) as $key => $value) {
			$key = $value['_id'];
			if (strtotime($key.' 00:00:00') <= time()) { // suppress future calculated results
				if ($prev) {
					$str = explode('-', $prev, 3);
					$year_month = $str[0].'-'.$str[1];
					$next_month = date('m', strtotime('+1 month', strtotime($prev)));
					$d = $str[2] == '01' ? $year_month.'-08' : ($str[2] == '08' ? $year_month.'-15' : ($str[2] == '15' ? $year_month.'-22' : $str[0].'-'.$next_month.'-01'));
					while (strtotime($d) < strtotime($key)) {
						array_push($log, array($d => array('count' => 0)));
						$str = explode('-', $d, 3);
						$year_month = $str[0].'-'.$str[1];
						$next_month = date('m', strtotime('+1 month', strtotime($prev)));
						$d = $str[2] == '01' ? $year_month.'-08' : ($str[2] == '08' ? $year_month.'-15' : ($str[2] == '15' ? $year_month.'-22' : $str[0].'-'.$next_month.'-01'));
					}
				}
				$prev = $key;
				array_push($log, array($key => array('count' => ($value['value'] instanceof MongoId ? 1 : $value['value']))));
			} else break;
		}
		$this->response($this->resp->setRespond($log), 200);
	}
	public function mauMonth_get()
	{
		$log = array();
		$prev = null;
		foreach ($this->player_model->monthy_active_user_per_month($this->validToken, $this->input->get('from'), $this->input->get('to')) as $key => $value) {
			$key = $value['_id'];
			if (strtotime($key.'-01 00:00:00') <= time()) { // suppress future calculated results
				if ($prev) {
					$d = date('Y-m', strtotime('+1 month', strtotime($prev.'-01 00:00:00')));
					while (strtotime($d.'-01 00:00:00') < strtotime($key.'-01 00:00:00')) {
						array_push($log, array($d => array('count' => 0)));
						$d = date('Y-m', strtotime('+1 month', strtotime($d.'-01 00:00:00')));
					}
				}
				$prev = $key;
				array_push($log, array($key => array('count' => ($value['value'] instanceof MongoId ? 1 : $value['value']))));
			} else break;
		}
		$this->response($this->resp->setRespond($log), 200);
	}
	public function test_get()
	{
		echo '<pre>';
		$credential = array(
			'key' => 'abc',
			'secret' => 'abcde'
		);
		$cl_player_id = 'test1234';
		$image = 'profileimage.jpg';
		$email = 'test123@email.com';
		$username = 'test-1234';
		$token = $this->auth_model->getApiInfo($credential);
		echo '<br>createPlayer:<br>';
		$pb_player_id = $this->player_model->createPlayer(array_merge($token, array(
			'player_id' => $cl_player_id,
			'image' => $image,
			'email' => $email,
			'username' => $username,
			'birth_date' => '1982-09-08',
			'gender' => 1
		)));
		print_r($pb_player_id);
		echo '<br>readPlayer:<br>';
		$result = $this->player_model->readPlayer($pb_player_id, $token['site_id'], array(
			'cl_player_id',
			'pb_player_id',
			'username',
			'email',
			'image',
			'date_added',
			'birth_date'
		));
		print_r($result);
		echo '<br>updatePlayer:<br>';
		$result = $this->player_model->updatePlayer($pb_player_id, $token['site_id'], array(
			'username' => 'test-4567',
			'email' => 'test4567@email.com'
		));
		$result = $this->player_model->readPlayer($pb_player_id, $token['site_id'], array(
			'username',
			'email'
		));
		print_r($result);
		echo '<br>deletePlayer:<br>';
		$result = $this->player_model->deletePlayer($pb_player_id, $token['site_id']);
		print_r($result);
		echo '<br>';
		$cl_player_id = '1';
		echo '<br>getPlaybasisId:<br>';
		$pb_player_id = $this->player_model->getPlaybasisId(array_merge($token, array(
			'cl_player_id' => $cl_player_id
		)));
		print_r($pb_player_id);
		echo '<br>getClientPlayerId:<br>';
		$cl_player_id = $this->player_model->getClientPlayerId($pb_player_id, $token['site_id']);
		print_r($cl_player_id);
		echo '<br>';
		echo '<br>getPlayerPoints:<br>';
		$result = $this->player_model->getPlayerPoints($pb_player_id, $token['site_id']);
		print_r($result);
		$reward_id = $this->point_model->findPoint(array_merge($token, array('reward_name'=>'exp')));
		echo '<br>getPlayerPoint:<br>';
		$result = $this->player_model->getPlayerPoint($pb_player_id, $reward_id, $token['site_id']);
		print_r($result);		
		echo '<br>getLastActionPerform:<br>';
		$result = $this->player_model->getLastActionPerform($pb_player_id, $token['site_id']);
		print_r($result);
		echo '<br>getActionPerform:<br>';
		$action_id = $this->action_model->findAction(array_merge($token, array('action_name' => 'like')));
		$result = $this->player_model->getActionPerform($pb_player_id, $action_id, $token['site_id']);
		print_r($result);
		echo '<br>getActionCount:<br>';
		$result = $this->player_model->getActionCount($pb_player_id, $action_id, $token['site_id']);
		print_r($result);
		echo '<br>getBadge:<br>';
		$result = $this->player_model->getBadge($pb_player_id, $token['site_id']);
		print_r($result);
		echo '<br>getLastEventTime<br>';
		$result = $this->player_model->getLastEventTime($pb_player_id, $token['site_id'], 'LOGIN');
		print_r($result);
		echo '<br>';
		echo '<br>getLeaderboard<br>';
		$result = $this->player_model->getLeaderboard('exp', 20, $token['client_id'], $token['site_id']);
		print_r($result);
		echo '<br>getLeaderboards<br>';
		$result = $this->player_model->getLeaderboards(20, $token['client_id'], $token['site_id']);
		print_r($result);
		echo '</pre>';
	}
}
?>
