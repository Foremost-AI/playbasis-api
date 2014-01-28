<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Player_model extends MY_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->config->load('playbasis');
        $this->load->library('memcached_library');
		$this->load->helper('memcache');
		$this->load->library('mongo_db');
	}
	public function createPlayer($data)
	{
		$this->checkClientUserLimitWarning($data['client_id'], $data['site_id']);
		$this->set_site_mongodb($data['site_id']);
		$mongoDate = new MongoDate(time());
		return $this->mongo_db->insert('playbasis_player', array(
			'client_id' => $data['client_id'],
			'site_id' => $data['site_id'],
			'cl_player_id' => $data['player_id'],
			'image' => $data['image'],
			'email' => $data['email'],
			'username' => $data['username'],
			'exp'			=> 0,
			'level'			=> 1,
			'status'		=> true,			
			'first_name'	=> (isset($data['first_name']))	 ? $data['first_name']	: $data['username'],
			'last_name'		=> (isset($data['last_name']))	 ? $data['last_name']	: null,
			'nickname'		=> (isset($data['nickname']))	 ? $data['nickname']	: null,
			'facebook_id'	=> (isset($data['facebook_id'])) ? $data['facebook_id'] : null,
			'twitter_id'	=> (isset($data['twitter_id']))	 ? $data['twitter_id']	: null,
			'instagram_id'	=> (isset($data['instagram_id']))? $data['instagram_id']: null,
			'password'		=> (isset($data['password']))	 ? $data['password']	: null,
			'gender'		=> (isset($data['gender']))		 ? intval($data['gender']) : 0,
			'birth_date'	=> (isset($data['birth_date']))  ? new MongoDate(strtotime($data['birth_date'])) : null,
			'date_added'	=> $mongoDate,
			'date_modified' => $mongoDate
		));
	}
	public function readPlayer($id, $site_id, $fields)
	{
        if(!$id)
            return array();
		$this->set_site_mongodb($site_id);
        if($fields)
			$this->mongo_db->select($fields);
        $this->mongo_db->select(array(), array('_id'));
		$this->mongo_db->where('_id', $id);
		$result = $this->mongo_db->get('playbasis_player');
		if(!$result)
			return $result;
		$result = $result[0];
		if(isset($result['date_added']))
		{
			$result['registered'] = date('Y-m-d H:i:s', $result['date_added']->sec);
			unset($result['date_added']);
	    }
		if(isset($result['birth_date']) && $result['birth_date'])
			$result['birth_date'] = date('Y-m-d', $result['birth_date']->sec);
		return $result;
    }
    public function readListPlayer($list_id, $site_id, $fields)
    {
        if(empty($list_id))
            return array();
        $this->set_site_mongodb($site_id);
        if($fields)
            $this->mongo_db->select($fields);
        $this->mongo_db->select(array(),array('_id'));
        $this->mongo_db->where_in('cl_player_id', $list_id);
        $this->mongo_db->where('site_id', $site_id);
        $result = $this->mongo_db->get('playbasis_player');
        return $result;
    }
	public function readPlayers($site_id, $fields, $offset = 0, $limit = 10)
	{
		$this->set_site_mongodb($site_id);
		if($fields)
			$this->mongo_db->select($fields);
		$this->mongo_db->limit($limit, $offset);
        $result = $this->mongo_db->get('playbasis_player');
        return $result;
    }
	public function updatePlayer($id, $site_id, $fieldData)
	{
		if(!$id)
			return false;
		$fieldData['date_modified'] = new MongoDate(time());
		$this->set_site_mongodb($site_id);
		$this->mongo_db->where('_id', $id);
		$this->mongo_db->set($fieldData);
		return $this->mongo_db->update('playbasis_player');
	}
	public function deletePlayer($id, $site_id)
	{
		if(!$id)
			return false;
		$this->set_site_mongodb($site_id);
		$this->mongo_db->where('_id', $id);
		return $this->mongo_db->delete('playbasis_player');
	}
	public function getPlaybasisId($clientData)
	{
		if(!$clientData)
			return null;
		$this->set_site_mongodb($clientData['site_id']);
		$this->mongo_db->select(array('_id'));
		$this->mongo_db->where(array(
			'client_id' => $clientData['client_id'],
			'site_id' => $clientData['site_id'],
			'cl_player_id' => $clientData['cl_player_id']
		));
		$id = $this->mongo_db->get('playbasis_player');
		return ($id) ? $id[0]['_id'] : null;
	}
	public function getClientPlayerId($pb_player_id, $site_id)
	{
		if(!$pb_player_id)
			return null;
		$this->set_site_mongodb($site_id);
		$this->mongo_db->select(array('cl_player_id'));
		$this->mongo_db->where('_id', $pb_player_id);
		$id = $this->mongo_db->get('playbasis_player');
		return ($id) ? $id[0]['cl_player_id'] : null;
	}
	public function getPlayerPoints($pb_player_id, $site_id)
	{
		$this->set_site_mongodb($site_id);
		$this->mongo_db->select(array(
			'reward_id',
			'value'
		));
        $this->mongo_db->select(array(),array('_id'));
		$this->mongo_db->where(array(
			'pb_player_id' => $pb_player_id,
			'badge_id' => null,
		));
		$result = $this->mongo_db->get('playbasis_reward_to_player');

		return $result;
	}
	public function getPlayerPoint($pb_player_id, $reward_id, $site_id)
	{
		$this->set_site_mongodb($site_id);
		$this->mongo_db->select(array(
			'reward_id',
			'value'
		));
        $this->mongo_db->select(array(),array('_id'));
		$this->mongo_db->where(array(
			'pb_player_id' => $pb_player_id,
			'reward_id' => $reward_id
		));
		$result = $this->mongo_db->get('playbasis_reward_to_player');

		return $result;
	}
	public function getLastActionPerform($pb_player_id, $site_id)
	{
		$this->set_site_mongodb($site_id);
		$this->mongo_db->select(array(
			'action_id',
			'action_name',
			'date_added'
		));
        $this->mongo_db->select(array(),array('_id'));
		$this->mongo_db->where('pb_player_id', $pb_player_id);
		$this->mongo_db->order_by(array('date_added' => 'desc'));
		$result = $this->mongo_db->get('playbasis_action_log');
		if(!$result)
			return $result;
		$result = $result[0];
		$result['time'] = date('Y-m-d H:i:s', $result['date_added']->sec);
		unset($result['date_added']);
		return $result;
	}
	public function getActionPerform($pb_player_id, $action_id, $site_id)
	{
		$this->set_site_mongodb($site_id);
		$this->mongo_db->select(array(
			'action_id',
			'action_name',
			'date_added'
        ));
        $this->mongo_db->select(array(),array('_id'));
		$this->mongo_db->where(array(
            'pb_player_id' => $pb_player_id,
            'action_id' => $action_id
        ));
		$this->mongo_db->order_by(array('date_added' => 'desc'));
		$result = $this->mongo_db->get('playbasis_action_log');
		if(!$result)
			return $result;
		$result = $result[0];
		$result['time'] = date('Y-m-d H:i:s', $result['date_added']->sec);
		unset($result['date_added']);
		return $result;
	}
	public function getActionCount($pb_player_id, $action_id, $site_id)
	{
		$fields = array(
			'pb_player_id' => $pb_player_id,
			'action_id' => $action_id
		);
		$this->set_site_mongodb($site_id);
		$this->mongo_db->where($fields);
		$count = $this->mongo_db->count('playbasis_action_log');
		$this->mongo_db->select(array(
			'action_id',
			'action_name'
		));
        $this->mongo_db->select(array(),array('_id'));
		$this->mongo_db->where($fields);
		$result = $this->mongo_db->get('playbasis_action_log');
		$result = ($result) ? $result[0] : array();
		$result['count'] = $count;
		return $result;
	}
	public function getBadge($pb_player_id, $site_id)
	{
		$this->set_site_mongodb($site_id);
		$this->mongo_db->select(array(
			'badge_id',
			'value',
			'claimed',
			'redeemed'
		));
        $this->mongo_db->select(array(),array('_id'));
		$this->mongo_db->where('pb_player_id', $pb_player_id);
		$badges = $this->mongo_db->get('playbasis_reward_to_player');
        if(!$badges)
            return array();
		$playerBadges = array();

		foreach($badges as $badge)
        {
            if(isset($badge['badge_id'])){

                //get badge data
                $this->mongo_db->select(array(
                    'image',
                    'name',
                    'description',
                ));
                $this->mongo_db->select(array(),array('_id'));
                $this->mongo_db->where(array(
                    'badge_id' => $badge['badge_id'],
                    'site_id' => $site_id,
//                    'deleted' => false
                ));
                $result = $this->mongo_db->get('playbasis_badge_to_client');

                if(!$result)
                    continue;
                $result = $result[0];
                $badge['image'] = $this->config->item('IMG_PATH') . $result['image'];
                $badge['name'] = $result['name'];
                $badge['description'] = $result['description'];
                $badge['amount'] = $badge['value'];
                unset($badge['value']);
                array_push($playerBadges, $badge);
            }
        }
		return $playerBadges;
	}
	public function claimBadge($pb_player_id, $badge_id, $site_id)
	{
		$mongoDate = new MongoDate(time());
		$this->set_site_mongodb($site_id);
		$this->mongo_db->where(array(
			'pb_player_id'=>$pb_player_id,
			'badge_id'=>$badge_id
		));
		$this->mongo_db->set('date_modified', $mongoDate);
		$this->mongo_db->inc('claimed', 1);
		return $this->mongo_db->update('playbasis_reward_to_player');
	}
	public function redeemBadge($pb_player_id, $badge_id, $site_id)
	{
		$mongoDate = new MongoDate(time());
		$this->set_site_mongodb($site_id);
		$this->mongo_db->where(array(
			'pb_player_id'=>$pb_player_id,
			'badge_id'=>$badge_id
		));
		$this->mongo_db->set('date_modified', $mongoDate);
		$this->mongo_db->inc('redeemed', 1);
		return $this->mongo_db->update('playbasis_reward_to_player');
	}
	public function getLastEventTime($pb_player_id, $site_id, $eventType)
	{
		$this->set_site_mongodb($site_id);
		$this->mongo_db->select(array('date_added'));
		$this->mongo_db->where(array(
			'pb_player_id' => $pb_player_id,
			'event_type' => $eventType
		));
		$this->mongo_db->order_by(array('date_added' => 'desc'));
		$result = $this->mongo_db->get('playbasis_event_log');
		if($result)
			return date('Y-m-d H:i:s', $result[0]['date_added']->sec);
		return '0000-00-00 00:00:00';
	}
	public function completeObjective($pb_player_id, $objective_id, $client_id, $site_id)
	{
		$this->set_site_mongodb($site_id);
		$mongoDate = new MongoDate(time());
		return $this->mongo_db->insert('playbasis_objective_to_player', array(
			'client_id' => $client_id,
			'site_id' => $site_id,
			'pb_player_id' => $pb_player_id,
			'objective_id' => $objective_id,
			'date_added' => $mongoDate,
			'date_modified' => $mongoDate
		));
	}
	public function getLeaderboard($ranked_by, $limit, $client_id, $site_id)
	{
		//get reward id
		$this->set_site_mongodb($site_id);
		$this->mongo_db->select(array('reward_id'));
		$this->mongo_db->where(array(
			'name' => $ranked_by,
			'site_id' => $site_id,
			'client_id' => $client_id
		));
		$result = $this->mongo_db->get('playbasis_reward_to_client');
		if(!$result)
			return array();
		$result = $result[0];
		//get points for the reward id
		$this->mongo_db->select(array(
			'cl_player_id',
			'value'
		));
        $this->mongo_db->select(array(),array('_id'));
		$this->mongo_db->where(array(
			'reward_id' => $result['reward_id'],
			'client_id' => $client_id,
			'site_id' => $site_id
		));
		$this->mongo_db->order_by(array('value' => 'desc'));
		$this->mongo_db->limit($limit);
		$result = $this->mongo_db->get('playbasis_reward_to_player');
		$count = count($result);
		for($i=0; $i < $count; ++$i)
		{
			$result[$i]['player_id'] = $result[$i]['cl_player_id'];
			$result[$i][$ranked_by] = $result[$i]['value'];
			unset($result[$i]['cl_player_id']);
			unset($result[$i]['value']);
		}
		return $result;
	}
	public function getLeaderboards($limit, $client_id, $site_id)
	{
		//get all rewards
		$this->set_site_mongodb($site_id);
		$this->mongo_db->select(array(
			'reward_id',
			'name'
		));
		$this->mongo_db->where(array(
			'site_id' => $site_id,
			'client_id' => $client_id,
			'group' => 'POINT'
		));
		$rewards = $this->mongo_db->get('playbasis_reward_to_client');
		if(!$rewards)
			return array();
		$result = array();
		foreach($rewards as $reward)
		{
			//get points for the reward id
			$reward_id = $reward['reward_id'];
			$name = $reward['name'];
			$this->mongo_db->select(array(
				'cl_player_id',
				'value'
			));
            $this->mongo_db->select(array(),array('_id'));
			$this->mongo_db->where(array(
				'reward_id' => $reward_id,
				'client_id' => $client_id,
				'site_id' => $site_id
			));
			$this->mongo_db->order_by(array('value' => 'desc'));
			$this->mongo_db->limit($limit);
			$ranking = $this->mongo_db->get('playbasis_reward_to_player');
			$count = count($ranking);
			for($i=0; $i < $count; ++$i)
			{
				$ranking[$i]['player_id'] = $ranking[$i]['cl_player_id'];
				$ranking[$i][$name] = $ranking[$i]['value'];
				unset($ranking[$i]['cl_player_id']);
				unset($ranking[$i]['value']);
			}
			$result[$name] = $ranking;
		}
		return $result;
	}
	private function checkClientUserLimitWarning($client_id, $site_id)
	{
		$this->set_site_mongodb($site_id);
		$this->mongo_db->select(array(
			'limit_users',
			'last_send_limit_users'
		));
		$this->mongo_db->where(array(
			'client_id' => $client_id,
			'_id' => $site_id
		));
		$result = $this->mongo_db->get('playbasis_client_site');
        assert($result);
		$result = $result[0];
		$limit = $result['limit_users'];
		if(!$limit)
			return; //client has no user limit
		$last_send = $result['last_send_limit_users']?$result['last_send_limit_users']->sec:null;
		$next_send = $last_send + (7 * 24 * 60 * 60); //next week from last send
		if($next_send > time())
			return; //not time to send yet
		$this->mongo_db->where(array(
			'client_id' => $client_id,
			'site_id' => $site_id
		));
		$usersCount = $this->mongo_db->count('playbasis_player');
		if($usersCount > ($limit * 0.95))
		{
			$this->mongo_db->select(array('user_id'));
			$this->mongo_db->where(array(
                'client_id' => $client_id
            ));
			$result = $this->mongo_db->get('user_to_client');
            $user_id_list=array();
			foreach ($result as $r)
                array_push($user_id_list,$r['user_id']);
			$this->mongo_db->select(array('email'));
			$this->mongo_db->where_in(
                'user_id', $user_id_list
            );
			$result = $this->mongo_db->get('user');
            $email_list=array();
			foreach ($result as $r)
                array_push($email_list,$r['email']);
            $email_string = implode(",", $email_list);
            $this->load->library('email');
            $this->load->library('parser');
			$data = array(
				'user_left' => ($limit-$usersCount),
				'user_count' => $usersCount,
				'user_limit' => $limit
			);
            $config['mailtype'] = 'html';
            $config['charset'] = 'utf-8';
            $email = $email_string;
            $subject = "Playbasis user limit alert";
            $htmlMessage = $this->parser->parse('limit_user_alert.html', $data, true);

			//email client to upgrade account
            $this->email->initialize($config);
            $this->email->clear();
            $this->email->from('info@playbasis.com', 'Playbasis');
            $this->email->to($email);
            $this->email->bcc('cscteam@playbasis.com');
            $this->email->subject($subject);
            $this->email->message($htmlMessage);
            $this->email->send();

            $this->updateLastAlertLimitUser($client_id, $site_id);
		}
	}
	private function updateLastAlertLimitUser($client_id, $site_id)
    {
		$mongoDate = new MongoDate(time());
		$this->set_site_mongodb($site_id);
		$this->mongo_db->where(array(
			'client_id' => $client_id,
			'_id' => $site_id
		));
		$this->mongo_db->update('playbasis_client_site', array(
			'last_send_limit_users' => $mongoDate
		));
    }

    public function getPointHistoryFromPlayerID($pb_player_id, $site_id, $reward_id, $offset, $limit){


    	if($reward_id){
    		$this->mongo_db->where('reward_id', $reward_id);	
    	}
    	$this->mongo_db->where('pb_player_id', $pb_player_id);
    	$this->mongo_db->where('site_id', $site_id);
    	$this->mongo_db->where_ne('reward_id', null);
    	$this->mongo_db->limit($limit, $offset);
    	$this->mongo_db->select(array('reward_id', 'reward_name', 'value', 'message', 'date_added','action_log_id'));
    	$this->mongo_db->select(array(), array('_id'));
    	$event_log = $this->mongo_db->get('playbasis_event_log');


		foreach($event_log as &$event){
			$actionAndStringFilter = $this->getActionNameAndStringFilter($event['action_log_id']);

			if($actionAndStringFilter){
				$event['action_name'] = $actionAndStringFilter['action_name'];
				$event['string_filter'] = $actionAndStringFilter['url'];	
			}
			unset($event['action_log_id']);
		}


		return $event_log;
    }

    private function getActionNameAndStringFilter($action_log_id){
    	$this->mongo_db->select(array('action_name', 'url'));
    	$this->mongo_db->select(array(), array('_id'));
    	$this->mongo_db->where('_id', new MongoID($action_log_id));
    	$returnThis = $this->mongo_db->get('playbasis_action_log');
    	return ($returnThis)?$returnThis[0]:array();
    }

}
?>
