<?php
defined('BASEPATH') OR exit('No direct script access allowed');

function cmp1($a, $b) {
	if ($a['_id'] == $b['_id']) {
		return 0;
	}
	return ($a['_id'] < $b['_id']) ? -1 : 1;
}

function change_key_for_getpoint_from_datetime($obj) {
    $_id = $obj['_id'];
    unset($obj['_id']);
    $obj['reward_id'] = $_id->{'$id'};

    $value = $obj['sum'];
    unset($obj['sum']);
    $obj['value'] = $value;

    return $obj;
}

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
	public function createPlayer($data, $limit)
	{
        try {
            $this->checkClientUserLimitWarning(
                $data['client_id'], $data['site_id'], $limit);
        } catch(Exception $e) {
            if ($e->getMessage() == "USER_EXCEED")
                return false;
            else
                throw new Exception($e->getMessage());
        }
		$this->set_site_mongodb($data['site_id']);
		$mongoDate = new MongoDate(time());
		return $this->mongo_db->insert('playbasis_player', array(
			'client_id' => $data['client_id'],
			'site_id' => $data['site_id'],
			'cl_player_id' => $data['player_id'],
			'image' => $data['image'],
			'email' => $data['email'],
			'username' => $data['username'],
			'exp'			=> intval(0),
			'level'			=> intval(1),
			'status'		=> true,
			'phone_number'  => (isset($data['phone_number']))	 ? $data['phone_number']	: null,
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
	public function readPlayer($id, $site_id, $fields=null)
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
			// $result['registered'] = date('Y-m-d H:i:s', $result['date_added']->sec);
			$result['registered'] = datetimeMongotoReadable($result['date_added']);
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
	public function setPlayerExp($client_id, $site_id, $pb_player_id, $value)
	{
		$d = new MongoDate(time());
		$this->set_site_mongodb($site_id);
		$this->mongo_db->where(array(
			'_id' => $pb_player_id,
		));
		$this->mongo_db->set('exp', $value);
		$this->mongo_db->set('date_modified', $d);
		print $this->mongo_db->update('playbasis_player');
	}
	public function deletePlayer($id, $site_id)
	{
		if(!$id)
			return false;
		$this->set_site_mongodb($site_id);
		$this->mongo_db->where('_id', $id);
		$this->mongo_db->delete('playbasis_player');

        $this->set_site_mongodb($site_id);
        $this->mongo_db->where('pb_player_id', $id);
        $this->mongo_db->delete_all('playbasis_badge_to_player');

        $this->set_site_mongodb($site_id);
        $this->mongo_db->where('pb_player_id', $id);
        $this->mongo_db->delete_all('playbasis_goods_to_player');

        $this->set_site_mongodb($site_id);
        $this->mongo_db->where('pb_player_id', $id);
        $this->mongo_db->delete_all('playbasis_quest_to_player');

        $this->set_site_mongodb($site_id);
        $this->mongo_db->where('pb_player_id', $id);
        $this->mongo_db->delete_all('playbasis_reward_to_player');

        $this->set_site_mongodb($site_id);
        $this->mongo_db->where('pb_player_id', $id);
        $this->mongo_db->delete_all('playbasis_redeem_to_player');

        $this->set_site_mongodb($site_id);
        $this->mongo_db->where('pb_player_id', $id);
        $this->mongo_db->delete_all('playbasis_quiz_to_player');

        return true;
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
	public function find_player_with_nin($client_id, $site_id, $nin, $limit)
	{
		$this->set_site_mongodb($site_id);
		$this->mongo_db->select(array('cl_player_id'));
		$this->mongo_db->where('client_id', $client_id);
		$this->mongo_db->where('site_id', $site_id);
		$this->mongo_db->where_not_in('_id', $nin);
		$this->mongo_db->limit($limit);
		return $this->mongo_db->get('playbasis_player');
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
    public function getPlayerPointFromDateTime($pb_player_id, $reward_id, $site_id, $starttime="", $endtime="")
    {
        $this->set_site_mongodb($site_id);

        $datecondition = array();
        $datestartcondition = array();
        $dateendcondition = array();

        $reset = $this->getResetRewardEvent($site_id, new MongoId($reward_id));
        if($reset){
            $reset_time = array_values($reset);
            if($starttime != ''){
                if($reset_time[0] > $starttime){
                    $starttime = $reset_time[0];
                }
            }else{
                $starttime = $reset_time[0];
            }
        }

        if($starttime != ''){
            $datestartcondition = array('date_added' => array('$gt' => $starttime));
        }
        if($endtime != ''){
            $dateendcondition = array('date_added' => array('$lte' => $endtime));
        }

        if($datestartcondition && $dateendcondition){
            $datecondition = array( '$and' => array( $datestartcondition, $dateendcondition));
        }else{
            if($datestartcondition){
                $datecondition = $datestartcondition;
            }else{
                $datecondition = $dateendcondition;
            }
        }

        $condition = array_merge($datecondition, array('reward_id' => $reward_id, 'pb_player_id' => $pb_player_id));

        $query = array(
            array('$match' => $condition),
            array('$group' => array( "_id" => '$reward_id', "sum" => array('$sum' => '$value'))),
        );
        $result = $this->mongo_db->aggregate('playbasis_event_log',$query);

        $result = $result['result'];
        $result = array_map('change_key_for_getpoint_from_datetime', $result);

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
        $result['action_id'] = $result['action_id']."";
		$result['time'] = datetimeMongotoReadable($result['date_added']);
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
        $result['action_id'] = $result['action_id']."";
        $result['time'] = datetimeMongotoReadable($result['date_added']);
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
        if($result){
            $result['action_id'] = $result['action_id']."";
        }
		$result['count'] = $count;
		return $result;
	}
    public function getActionCountFromDatetime($pb_player_id, $action_id, $action_filter, $site_id, $starttime="", $endtime="")
    {
        $fields = array(
            'pb_player_id' => $pb_player_id,
            'action_id' => $action_id
        );
        if(!empty($action_filter)){
            $fields['url'] = $action_filter;
        }
        $datecondition = array();
        if($starttime != ''){
            $datecondition = array_merge($datecondition, array('$gt' => $starttime));
        }
        if($endtime != ''){
            $datecondition = array_merge($datecondition, array('$lte' => $endtime));
        }

        $this->set_site_mongodb($site_id);
        $this->mongo_db->where($fields);
        if ($starttime != '' || $endtime != '' ) {
            $this->mongo_db->where('date_added', $datecondition);
        }
        $count = $this->mongo_db->count('playbasis_action_log');

        $this->mongo_db->select(array(
            'action_id',
            'action_name'
        ));
        $this->mongo_db->select(array(),array('_id'));
        $this->mongo_db->where($fields);
        if ($starttime != '' || $endtime != '' ) {
            $this->mongo_db->where('date_added', $datecondition);
        }
        $result = $this->mongo_db->get('playbasis_action_log');
        $result = ($result) ? $result[0] : array();
        if($result){
            $result['action_id'] = $result['action_id']."";
        }
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
                    'hint',
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
                $badge['badge_id'] = $badge['badge_id']."";
                $badge['image'] = $this->config->item('IMG_PATH') . $result['image'];
                $badge['name'] = $result['name'];
                $badge['description'] = $result['description'];
                $badge['amount'] = $badge['value'];
                $badge['hint'] = $result['hint'];
                unset($badge['value']);
                array_push($playerBadges, $badge);
            }
        }
		return $playerBadges;
	}
	public function claimBadge($pb_player_id, $badge_id, $site_id, $client_id)
	{

//		$mongoDate = new MongoDate(time());
//		$this->set_site_mongodb($site_id);
//		$this->mongo_db->where(array(
//			'pb_player_id'=>$pb_player_id,
//			'badge_id'=>$badge_id
//		));
//		$this->mongo_db->set('date_modified', $mongoDate);
//		$this->mongo_db->inc('claimed', 1);
//		return $this->mongo_db->update('playbasis_reward_to_player');

        $mongoDate = new MongoDate(time());
        $this->set_site_mongodb($site_id);

        $this->mongo_db->select(array(
            'substract',
            'quantity',
            'claim',
            'redeem'
        ));
        $this->mongo_db->where(array(
            'client_id' => $client_id,
            'site_id' => $site_id,
            'badge_id' => $badge_id,
            'deleted' => false
        ));
        $result = $this->mongo_db->get('playbasis_badge_to_client');
        if(!$result)
            return false;

        $badgeInfo = $result[0];

        if(isset($badgeInfo['claim']) && $badgeInfo['claim']){
            $this->mongo_db->where(array(
                'pb_player_id'=>$pb_player_id,
                'badge_id'=>$badge_id
            ));
            $result = $this->mongo_db->get('playbasis_reward_to_player');

            if(!$result)
                return false;

            $badge = $result[0];
            if(isset($badge['claimed']) && (int)($badge['claimed']) > 0){
                $this->mongo_db->where(array(
                    'pb_player_id'=>$pb_player_id,
                    'badge_id'=>$badge_id
                ));
                $this->mongo_db->set('date_modified', $mongoDate);
                $this->mongo_db->dec('claimed', 1);
                $this->mongo_db->inc('value', 1);
                if($badgeInfo['redeem']){
                    $this->mongo_db->inc('redeemed', 1);
                }
                $reward = $this->mongo_db->update('playbasis_reward_to_player');

                $track = array(
                    'pb_player_id'	=> $pb_player_id,
                    'client_id'		=> $client_id,
                    'site_id'		=> $site_id,
                    'badge_id'		=> $badge_id,
                    'type'	        => 'claim'
                );
                //log event - goods
                $this->tracker_model->trackBadge($track);

                return $reward;
            }
        }
	}
	public function redeemBadge($pb_player_id, $badge_id, $site_id, $client_id)
	{
//		$mongoDate = new MongoDate(time());
//		$this->set_site_mongodb($site_id);
//		$this->mongo_db->where(array(
//			'pb_player_id'=>$pb_player_id,
//			'badge_id'=>$badge_id
//		));
//		$this->mongo_db->set('date_modified', $mongoDate);
//		$this->mongo_db->inc('redeemed', 1);
//		return $this->mongo_db->update('playbasis_reward_to_player');

        $mongoDate = new MongoDate(time());
        $this->set_site_mongodb($site_id);

        $this->mongo_db->select(array(
            'substract',
            'quantity',
            'claim',
            'redeem'
        ));
        $this->mongo_db->where(array(
            'client_id' => $client_id,
            'site_id' => $site_id,
            'badge_id' => $badge_id,
            'deleted' => false
        ));
        $result = $this->mongo_db->get('playbasis_badge_to_client');
        if(!$result)
            return false;
        $badgeInfo = $result[0];

        if(isset($badgeInfo['redeem']) && $badgeInfo['redeem']){
            $this->mongo_db->where(array(
                'pb_player_id'=>$pb_player_id,
                'badge_id'=>$badge_id
            ));
            $result = $this->mongo_db->get('playbasis_reward_to_player');

            if(!$result)
                return false;

            $badge = $result[0];
            if(isset($badge['redeemed']) && (int)($badge['redeemed']) > 0){
                $this->mongo_db->where(array(
                    'pb_player_id'=>$pb_player_id,
                    'badge_id'=>$badge_id
                ));
                $this->mongo_db->set('date_modified', $mongoDate);
                $this->mongo_db->dec('redeemed', 1);
                $this->mongo_db->dec('value', 1);
                $reward =  $this->mongo_db->update('playbasis_reward_to_player');

                $track = array(
                    'pb_player_id'	=> $pb_player_id,
                    'client_id'		=> $client_id,
                    'site_id'		=> $site_id,
                    'badge_id'		=> $badge_id,
                    'type'	        => 'redeem'
                );
                //log event - goods
                $this->tracker_model->trackBadge($track);

                return $reward;
            }
        }
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
			return datetimeMongotoReadable($result[0]['date_added']);
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
	public function getLeaderboardByLevel($limit, $client_id, $site_id) {
		$this->set_site_mongodb($site_id);
		$this->mongo_db->select(array('cl_player_id','first_name','last_name','username','image','exp','level'));
		$this->mongo_db->where(array(
			'status' => true,
			'site_id' => $site_id,
			'client_id' => $client_id
		));
		$this->mongo_db->order_by(array('level' => -1, 'exp' => -1));
		$this->mongo_db->limit($limit);
		$result = $this->mongo_db->get('playbasis_player');
		$ret = array();
		foreach ($result as $i => $each) {
			$ret[] = array(
				'player_id' => $result[$i]['cl_player_id'],
				'level' => $result[$i]['level'],
			);
		}
		return $ret;
	}
	public function getLeaderboardByLevelForReport($limit, $client_id, $site_id) {
		$this->set_site_mongodb($site_id);
		$this->mongo_db->select(array('cl_player_id','first_name','last_name','username','image','exp','level'));
		$this->mongo_db->where(array(
			'status' => true,
			'site_id' => $site_id,
			'client_id' => $client_id
		));
		$this->mongo_db->order_by(array('level' => -1, 'exp' => -1));
		$this->mongo_db->limit($limit);
		return $this->mongo_db->get('playbasis_player');
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
            'pb_player_id',
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
		$this->mongo_db->limit($limit+5);
		$result1 = $this->mongo_db->get('playbasis_reward_to_player');

		$count = count($result1);
        $check = 0;
		for($i=0; $i < $count; ++$i)
		{
            if($check < $limit){
                $this->mongo_db->where(array(
                    '_id' => $result1[$i]['pb_player_id'],
                    'client_id' => $client_id,
                    'site_id' => $site_id
                ));
                $check_player = $this->mongo_db->count('playbasis_player');
                if($check_player > 0){
                    $result1[$i]['player_id'] = $result1[$i]['cl_player_id'];
                    $result1[$i][$ranked_by] = $result1[$i]['value'];
                    unset($result1[$i]['cl_player_id']);
                    unset($result1[$i]['value']);
                    $check++;
                }else{
                    unset($result1[$i]);
                }
            }else{
                unset($result1[$i]);
            }
		}

        $result = array_values($result1);
		return $result;
	}
	public function getUserRanking($ranked_by, $player_id, $client_id, $site_id)
	{
		$this->set_site_mongodb($site_id);
		$this->mongo_db->select(array('reward_id'));
		$this->mongo_db->where(array(
			'name' => $ranked_by,
			'site_id' => $site_id,
			'client_id' => $client_id
		));
		$result = $this->mongo_db->get('playbasis_reward_to_client');
		if(!$result){
			return array();
		}
		$result = $result[0];
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
		$result = $this->mongo_db->get('playbasis_reward_to_player');
		$rank = 1;
		$found_player = array();
		foreach($result as $player){
			if($player['cl_player_id'] == $player_id){
				$found_player['player_id'] = $player_id;
				$found_player['rank'] = $rank;
				$found_player['ranked_by'] = $ranked_by;
				$found_player['ranked_value'] = $player['value'];
				break;
			}
            $this->mongo_db->where(array(
                'cl_player_id' => $player['cl_player_id'],
                'client_id' => $client_id,
                'site_id' => $site_id
            ));
            $check_player = $this->mongo_db->count('playbasis_player');
            if($check_player > 0){
                $rank++;
            }
		}
		return $found_player;
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
				'pb_player_id',
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
			$this->mongo_db->limit($limit+5);
			$ranking = $this->mongo_db->get('playbasis_reward_to_player');
			$count = count($ranking);
            $check = 0;
			for($i=0; $i < $count; ++$i)
			{
                if($check < $limit){
                    $this->mongo_db->where(array(
                        '_id' => $ranking[$i]['pb_player_id'],
                        'client_id' => $client_id,
                        'site_id' => $site_id
                    ));
                    $check_player = $this->mongo_db->count('playbasis_player');
                    if($check_player > 0){
                        $ranking[$i]['player_id'] = $ranking[$i]['cl_player_id'];
                        $ranking[$i][$name] = $ranking[$i]['value'];
                        unset($ranking[$i]['cl_player_id']);
                        unset($ranking[$i]['value']);
                        $check++;
                    }else{
                        unset($ranking[$i]);
                    }
                }else{
                    unset($ranking[$i]);
                }
			}
            $ranking = array_values($ranking);
			$result[$name] = $ranking;
		}
		return $result;
	}
	private function checkClientUserLimitWarning($client_id, $site_id, $limit)
	{
		$this->set_site_mongodb($site_id);
		$this->mongo_db->select(array(
            'domain_name',
			/* 'limit_users', */  // use plan instead
			'last_send_limit_users'
		));
		$this->mongo_db->where(array(
			'client_id' => $client_id,
			'_id' => $site_id
		));
		$result = $this->mongo_db->get('playbasis_client_site');
        assert($result);
		$result = $result[0];
        $domain_name_client = $result['domain_name'];

		if(!$limit)
			return; //client has no user limit

		$last_send = $result['last_send_limit_users']?$result['last_send_limit_users']->sec:null;
		$next_send = $last_send + (7 * 24 * 60 * 60); //next week from last send

		$this->mongo_db->where(array(
			'client_id' => $client_id,
			'site_id' => $site_id
		));
		$usersCount = $this->mongo_db->count('playbasis_player');
		if($usersCount > ($limit * 0.95))
		{
            if (time() > $next_send) {
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

                //$this->load->library('email');
                $this->load->library('parser');
                $data = array(
                    'user_left' => ($limit-$usersCount),
                    'user_count' => $usersCount,
                    'user_limit' => $limit,
                    'domain_name_client' => $domain_name_client,
                );
                $config['mailtype'] = 'html';
                $config['charset'] = 'utf-8';
                $email = $email_list;
                $subject = "Playbasis user limit alert";
                $htmlMessage = $this->parser->parse('limit_user_alert.html', $data, true);

                //email client to upgrade account
            /*$this->email->initialize($config);
            $this->email->clear();
            $this->email->from('info@playbasis.com', 'Playbasis');
//            $this->email->to($email);
            $this->email->to('cscteam@playbasis.com','devteam@playbasis.com');
//            $this->email->bcc('cscteam@playbasis.com');
            $this->email->subject($subject);
            $this->email->message($htmlMessage);
            $this->email->send();*/

                $this->amazon_ses->from('info@playbasis.com', 'Playbasis');
                $this->amazon_ses->to('cscteam@playbasis.com,devteam@playbasis.com');
                $this->amazon_ses->subject($subject);
                $this->amazon_ses->message($htmlMessage);
                $this->amazon_ses->send();

                $this->updateLastAlertLimitUser($client_id, $site_id);
            }

            if ($usersCount >= $limit)
                throw new Exception("USER_EXCEED");
		}
	}
	private function updateLastAlertLimitUser($client_id, $site_id)
    {
		$this->set_site_mongodb($site_id);
		$mongoDate = new MongoDate();

        $this->mongo_db->where(array(
			"client_id" => $client_id,
			"_id" => $site_id))->set(array(
                "last_send_limit_users" => $mongoDate
            ))->update("playbasis_client_site");
        return $mongoDate;
    }

    public function getPointHistoryFromPlayerID($pb_player_id, $site_id, $reward_id, $offset, $limit){

    	if($reward_id){
    		$this->mongo_db->where('reward_id', $reward_id);	
    	}else{
            $this->mongo_db->where_ne('reward_id', null);
        }
    	$this->mongo_db->where('pb_player_id', $pb_player_id);
    	$this->mongo_db->where('site_id', $site_id);
    	$this->mongo_db->where('event_type', 'REWARD');
        $this->mongo_db->where_gt('value', 0);
    	$this->mongo_db->limit((int)$limit);
        $this->mongo_db->offset((int)$offset);
    	$this->mongo_db->select(array('reward_id', 'reward_name', 'value', 'message', 'date_added','action_log_id'));
    	$this->mongo_db->select(array(), array('_id'));
    	$event_log = $this->mongo_db->get('playbasis_event_log');


		foreach($event_log as &$event){
			$actionAndStringFilter = $this->getActionNameAndStringFilter($event['action_log_id']);

            $event['date_added'] = datetimeMongotoReadable($event['date_added']);
			if($actionAndStringFilter){
				$event['action_name'] = $actionAndStringFilter['action_name'];
				$event['string_filter'] = $actionAndStringFilter['url'];	
			}
			unset($event['action_log_id']);

            $event['reward_id'] = $event['reward_id']."";
		}


		return $event_log;
    }

    public function getGoods($pb_player_id, $site_id)
    {
        $this->set_site_mongodb($site_id);
        $this->mongo_db->select(array(
            'goods_id',
            'value'
        ));
        $this->mongo_db->select(array(),array('_id'));
        $this->mongo_db->where(array(
            'pb_player_id' => $pb_player_id,
        ));
        $goods_list = $this->mongo_db->get('playbasis_goods_to_player');

        if(!$goods_list)
            return array();
        $playerGoods = array();

        foreach($goods_list as $goods)
        {
            if(isset($goods['goods_id'])){

                //get goods data
                $this->mongo_db->select(array(
                    'image',
                    'name',
                    'description',
                ));
                $this->mongo_db->select(array(),array('_id'));
                $this->mongo_db->where(array(
                    'goods_id' => $goods['goods_id'],
                    'site_id' => $site_id,
                ));
                $result = $this->mongo_db->get('playbasis_goods_to_client');

                if(!$result)
                    continue;
                $result = $result[0];
                $goods['goods_id'] = $goods['goods_id']."";
                $goods['image'] = $this->config->item('IMG_PATH') . $result['image'];
                $goods['name'] = $result['name'];
                $goods['description'] = $result['description'];
                $goods['amount'] = $goods['value'];
                unset($goods['value']);
                array_push($playerGoods, $goods);
            }
        }
        return $playerGoods;
    }

    public function getGoodsByGoodsId($pb_player_id, $site_id, $goods_id)
    {
        $this->set_site_mongodb($site_id);
        $this->mongo_db->select(array(
            'goods_id',
            'value'
        ));
        $this->mongo_db->select(array(),array('_id'));
        $this->mongo_db->where(array(
            'pb_player_id' => $pb_player_id,
            'goods_id' => $goods_id
        ));
        $goods = $this->mongo_db->get('playbasis_goods_to_player');

        if(!$goods)
            return array();

        $goods = $goods[0];

        if(isset($goods['goods_id'])){
            //get goods data
            $this->mongo_db->select(array(
                'image',
                'name',
                'description',
            ));
            $this->mongo_db->select(array(),array('_id'));
            $this->mongo_db->where(array(
                'goods_id' => $goods['goods_id'],
                'site_id' => $site_id,
            ));
            $result = $this->mongo_db->get('playbasis_goods_to_client');

            if(!$result)
                return array();
            $result = $result[0];
            $goods['goods_id'] = $goods['goods_id']."";
            $goods['image'] = $this->config->item('IMG_PATH') . $result['image'];
            $goods['name'] = $result['name'];
            $goods['description'] = $result['description'];
            $goods['amount'] = $goods['value'];
            unset($goods['value']);
        }
        return $goods;
    }

    private function getActionNameAndStringFilter($action_log_id){
    	$this->mongo_db->select(array('action_name', 'url'));
    	$this->mongo_db->select(array(), array('_id'));
    	$this->mongo_db->where('_id', new MongoID($action_log_id));
    	$returnThis = $this->mongo_db->get('playbasis_action_log');
    	return ($returnThis)?$returnThis[0]:array();
    }

	public function new_registration($data, $from=null, $to=null) {
		$this->set_site_mongodb($data['site_id']);
		$map = new MongoCode("function() { this.date_added.setTime(this.date_added.getTime()-(-7*60*60*1000)); emit(this.date_added.getFullYear()+'-'+('0'+(this.date_added.getMonth()+1)).slice(-2)+'-'+('0'+this.date_added.getDate()).slice(-2), 1); }");
		$reduce = new MongoCode("function(key, values) { return Array.sum(values); }");
		$query = array('client_id' => $data['client_id'], 'site_id' => $data['site_id'], 'status' => true);
		if ($from || $to) $query['date_added'] = array();
		if ($from) $query['date_added']['$gte'] = $this->new_mongo_date($from);
		if ($to) $query['date_added']['$lte'] = $this->new_mongo_date($to, '23:59:59');
		$this->mongo_db->command(array(
			'mapReduce' => 'playbasis_player',
			'map' => $map,
			'reduce' => $reduce,
			'query' => $query,
			'out' => 'mapreduce_new_player_log',
		));
		$result = $this->mongo_db->get('mapreduce_new_player_log');
		if (!$result) $result = array();
		if ($from && (!isset($result[0]['_id']) || $result[0]['_id'] != $from)) array_unshift($result, array('_id' => $from, 'value' => 0));
		if ($to && (!isset($result[count($result)-1]['_id']) || $result[count($result)-1]['_id'] != $to)) array_push($result, array('_id' => $to, 'value' => 0));
		return $result;
	}

	/* unused */
	/* NOTE: 'from' and 'to' parameters are expected to be in a format of 'yyyy-mm' */
	public function monthy_active_user($data, $from=null, $to=null) {
		$this->set_site_mongodb($data['site_id']);
		$map = new MongoCode("function() { this.date_added.setTime(this.date_added.getTime()-(-7*60*60*1000)); emit(this.date_added.getFullYear()+'-'+('0'+(this.date_added.getMonth()+1)).slice(-2), this.pb_player_id.toString()); }");
		$reduce = new MongoCode("function(key, values) { return {'pb_player_id': values}; }");
		$query = array('client_id' => $data['client_id'], 'site_id' => $data['site_id']);
		if ($from || $to) $query['date_added'] = array();
		if ($from) $query['date_added']['$gte'] = $this->new_mongo_date($from.'-01');
		if ($to) $query['date_added']['$lte'] = $this->new_mongo_date($to.'-'.MY_Model::get_number_of_days($to), '23:59:59');
		$this->mongo_db->command(array(
			'mapReduce' => 'playbasis_action_log',
			'map' => $map,
			'reduce' => $reduce,
			'query' => $query,
			'out' => 'mapreduce_player_mau_log',
		));
		$_result = $this->mongo_db->get('mapreduce_player_mau_log');
		if (!$_result) $_result = array();
		$result = array();
		foreach ($_result as $key => $value) {
			$values = array();
			if (is_array($value['value']) && array_key_exists('pb_player_id', $value['value'])) {
				if (is_array($value['value']['pb_player_id'])) foreach ($value['value']['pb_player_id'] as $key => $pb_player_id) {
					if (is_array($pb_player_id) && array_key_exists('pb_player_id', $pb_player_id)) {
						if (is_array($pb_player_id['pb_player_id'])) foreach ($pb_player_id['pb_player_id'] as $key => $each) {
							array_push($values, $each);
						} else {
							array_push($values, $pb_player_id['pb_player_id']);
						}
					} else {
						array_push($values, $pb_player_id);
					}
				} else {echo 2;$values = $value['value']['pb_player_id'];}
			} else {
				array_push($values, $value['value']);
			}
			array_push($result, array('_id' => $value['_id'], 'value' => count(array_unique($values))));
		}
		usort($result, 'cmp1');
		if ($from && (!isset($result[0]['_id']) || $result[0]['_id'] != $from)) array_unshift($result, array('_id' => $from, 'value' => 0));
		if ($to && (!isset($result[count($result)-1]['_id']) || $result[count($result)-1]['_id'] != $to)) array_push($result, array('_id' => $to, 'value' => 0));
		return $result;
	}

	/* unused */
	public function daily_active_user($data, $from=null, $to=null) {
		$this->set_site_mongodb($data['site_id']);
		$map = new MongoCode("function() { this.date_added.setTime(this.date_added.getTime()-(-7*60*60*1000)); emit(this.date_added.getFullYear()+'-'+('0'+(this.date_added.getMonth()+1)).slice(-2)+'-'+('0'+this.date_added.getDate()).slice(-2), this.pb_player_id.toString()); }");
		$reduce = new MongoCode("function(key, values) { return {'pb_player_id': values}; }");
		$query = array('client_id' => $data['client_id'], 'site_id' => $data['site_id']);
		if ($from || $to) $query['date_added'] = array();
		if ($from) $query['date_added']['$gte'] = $this->new_mongo_date($from);
		if ($to) $query['date_added']['$lte'] = $this->new_mongo_date($to, '23:59:59');
		$this->mongo_db->command(array(
			'mapReduce' => 'playbasis_action_log',
			'map' => $map,
			'reduce' => $reduce,
			'query' => $query,
			'out' => 'mapreduce_player_dau_log',
		));
		$_result = $this->mongo_db->get('mapreduce_player_dau_log');
		if (!$_result) $_result = array();
		$result = array();
		foreach ($_result as $key => $value) {
			$values = array();
			if (is_array($value['value']) && array_key_exists('pb_player_id', $value['value'])) {
				if (is_array($value['value']['pb_player_id'])) foreach ($value['value']['pb_player_id'] as $key => $pb_player_id) {
					if (is_array($pb_player_id) && array_key_exists('pb_player_id', $pb_player_id)) {
						if (is_array($pb_player_id['pb_player_id'])) foreach ($pb_player_id['pb_player_id'] as $key => $each) {
							array_push($values, $each);
						} else {
							array_push($values, $pb_player_id['pb_player_id']);
						}
					} else {
						array_push($values, $pb_player_id);
					}
				} else {echo 2;$values = $value['value']['pb_player_id'];}
			} else {
				array_push($values, $value['value']);
			}
			array_push($result, array('_id' => $value['_id'], 'value' => count(array_unique($values))));
		}
		usort($result, 'cmp1');
		if ($from && (!isset($result[0]['_id']) || $result[0]['_id'] != $from)) array_unshift($result, array('_id' => $from, 'value' => 0));
		if ($to && (!isset($result[count($result)-1]['_id']) || $result[count($result)-1]['_id'] != $to)) array_push($result, array('_id' => $to, 'value' => 0));
		return $result;
	}

	public function daily_active_user_per_day($data, $from=null, $to=null) {
		return $this->active_user_per_day($data, 1, $from, $to);
	}

	public function monthy_active_user_per_day($data, $from=null, $to=null) {
		return $this->active_user_per_day($data, 30, $from, $to);
	}

	public function monthy_active_user_per_week($data, $from=null, $to=null) {
		return $this->active_user_per_week($data, 30, $from, $to);
	}

	public function monthy_active_user_per_month($data, $from=null, $to=null) {
		return $this->active_user_per_month($data, 30, $from, $to);
	}

	private function active_user_per_day($data, $ndays, $from=null, $to=null) {
		$this->set_site_mongodb($data['site_id']);
		$str = $to ? explode('-', $to, 3) : "";
		$var_to = $to ? "var to = new Date(".$str[0].", ".(intval($str[1])-1).", ".$str[2].", 23, 59, 59);" : "";
		$check_to = $to ? "if (tmp.getTime() > to.getTime()) break;" : "";
		$map = new MongoCode("function() {
			this.date_added.setTime(this.date_added.getTime()-(-7*60*60*1000));
			var tmp = new Date();
			$var_to
			for (var i = 0; i < ".$ndays."; i++) {
				tmp.setTime(this.date_added.getTime()+i*86400000);
				$check_to
				emit(tmp.getFullYear()+'-'+('0'+(tmp.getMonth()+1)).slice(-2)+'-'+('0'+tmp.getDate()).slice(-2), this.pb_player_id.toString());
			}
		}");
		$reduce = new MongoCode("function(key, values) { return {'pb_player_id': values}; }");
		$query = array('client_id' => $data['client_id'], 'site_id' => $data['site_id']);
		if ($from || $to) $query['date_added'] = array();
		if ($from) $query['date_added']['$gte'] = $this->new_mongo_date($from);
		if ($to) $query['date_added']['$lte'] = $this->new_mongo_date($to, '23:59:59');
		$this->mongo_db->command(array(
			'mapReduce' => 'playbasis_action_log',
			'map' => $map,
			'reduce' => $reduce,
			'query' => $query,
			'out' => 'mapreduce_active_user_per_day_'.$ndays.'_log',
		));
		$_result = $this->mongo_db->get('mapreduce_active_user_per_day_'.$ndays.'_log');
		if (!$_result) $_result = array();
		$result = array();
		foreach ($_result as $key => $value) {
			$values = array();
			if (is_array($value['value']) && array_key_exists('pb_player_id', $value['value'])) {
				if (is_array($value['value']['pb_player_id'])) foreach ($value['value']['pb_player_id'] as $key => $pb_player_id) {
					if (is_array($pb_player_id) && array_key_exists('pb_player_id', $pb_player_id)) {
						if (is_array($pb_player_id['pb_player_id'])) foreach ($pb_player_id['pb_player_id'] as $key => $each) {
							array_push($values, $each);
						} else {
							array_push($values, $pb_player_id['pb_player_id']);
						}
					} else {
						array_push($values, $pb_player_id);
					}
				} else $values = $value['value']['pb_player_id'];
			} else {
				array_push($values, $value['value']);
			}
			array_push($result, array('_id' => $value['_id'], 'value' => count(array_unique($values))));
		}
		usort($result, 'cmp1');
		if ($from && (!isset($result[0]['_id']) || $result[0]['_id'] != $from)) array_unshift($result, array('_id' => $from, 'value' => 0));
		if ($to && (!isset($result[count($result)-1]['_id']) || $result[count($result)-1]['_id'] != $to)) array_push($result, array('_id' => $to, 'value' => 0));
		return $result;
	}

	private function active_user_per_week($data, $ndays, $from=null, $to=null) {
		$this->set_site_mongodb($data['site_id']);
		$str = $to ? explode('-', $to, 3) : "";
		$var_to = $to ? "var to = new Date(".$str[0].", ".(intval($str[1])-1).", ".$str[2].", 23, 59, 59);" : "";
		$check_to = $to ? "if (tmp.getTime() > to.getTime()) break;" : "";
		$map = new MongoCode("function() {
			this.date_added.setTime(this.date_added.getTime()-(-7*60*60*1000));
			var get_number_of_days = function(year, month) {
				var monthStart = new Date(year, month, 1);
				var monthEnd = new Date(year, month+1, 1);
				return (monthEnd-monthStart)/(1000*60*60*24);
			};
			var days,days_per_week,week,d;
			var tmp = new Date();
			$var_to
			for (var i = 0; i < ".$ndays."; i++) {
				tmp.setTime(this.date_added.getTime()+i*86400000);
				$check_to
				days = get_number_of_days(tmp.getFullYear(), tmp.getMonth());
				week = Math.ceil(tmp.getDate()/7.0);
				if (week > 4) week = 4;
				d = (week-1)*7+1;
				emit(tmp.getFullYear()+'-'+('0'+(tmp.getMonth()+1)).slice(-2)+'-'+('0'+d).slice(-2), this.pb_player_id.toString());
			}
		}");
		$reduce = new MongoCode("function(key, values) { return {'pb_player_id': values}; }");
		$query = array('client_id' => $data['client_id'], 'site_id' => $data['site_id']);
		if ($from || $to) $query['date_added'] = array();
		if ($from) $query['date_added']['$gte'] = $this->new_mongo_date($from);
		if ($to) $query['date_added']['$lte'] = $this->new_mongo_date($to, '23:59:59');
		$this->mongo_db->command(array(
			'mapReduce' => 'playbasis_action_log',
			'map' => $map,
			'reduce' => $reduce,
			'query' => $query,
			'out' => 'mapreduce_active_user_per_week_'.$ndays.'_log',
		));
		$_result = $this->mongo_db->get('mapreduce_active_user_per_week_'.$ndays.'_log');
		if (!$_result) $_result = array();
		$result = array();
		foreach ($_result as $key => $value) {
			$values = array();
			if (is_array($value['value']) && array_key_exists('pb_player_id', $value['value'])) {
				if (is_array($value['value']['pb_player_id'])) foreach ($value['value']['pb_player_id'] as $key => $pb_player_id) {
					if (is_array($pb_player_id) && array_key_exists('pb_player_id', $pb_player_id)) {
						if (is_array($pb_player_id['pb_player_id'])) foreach ($pb_player_id['pb_player_id'] as $key => $each) {
							array_push($values, $each);
						} else {
							array_push($values, $pb_player_id['pb_player_id']);
						}
					} else {
						array_push($values, $pb_player_id);
					}
				} else $values = $value['value']['pb_player_id'];
			} else {
				array_push($values, $value['value']);
			}
			array_push($result, array('_id' => $value['_id'], 'value' => count(array_unique($values))));
		}
		usort($result, 'cmp1');
		$from2 = $from ? MY_Model::date_to_startdate_of_week($from) : null;
		$to2 = $to ? MY_Model::date_to_startdate_of_week($to) : null;
		if ($from2 && (!isset($result[0]['_id']) || $result[0]['_id'] != $from2)) array_unshift($result, array('_id' => $from2, 'value' => 0));
		if ($to2 && (!isset($result[count($result)-1]['_id']) || $result[count($result)-1]['_id'] != $to2)) array_push($result, array('_id' => $to2, 'value' => 0));
		return $result;
	}

	private function active_user_per_month($data, $ndays, $from=null, $to=null) {
		$this->set_site_mongodb($data['site_id']);
		$str = $to ? explode('-', $to, 3) : "";
		$var_to = $to ? "var to = new Date(".$str[0].", ".(intval($str[1])-1).", ".$str[2].", 23, 59, 59);" : "";
		$check_to = $to ? "if (tmp.getTime() > to.getTime()) break;" : "";
		$map = new MongoCode("function() {
			this.date_added.setTime(this.date_added.getTime()-(-7*60*60*1000));
			var tmp = new Date();
			$var_to
			for (var i = 0; i < ".$ndays."; i++) {
				tmp.setTime(this.date_added.getTime()+i*86400000);
				$check_to
				emit(tmp.getFullYear()+'-'+('0'+(tmp.getMonth()+1)).slice(-2), this.pb_player_id.toString());
			}
		}");
		$reduce = new MongoCode("function(key, values) { return {'pb_player_id': values}; }");
		$query = array('client_id' => $data['client_id'], 'site_id' => $data['site_id']);
		if ($from || $to) $query['date_added'] = array();
		if ($from) $query['date_added']['$gte'] = $this->new_mongo_date($from);
		if ($to) $query['date_added']['$lte'] = $this->new_mongo_date($to, '23:59:59');
		$this->mongo_db->command(array(
			'mapReduce' => 'playbasis_action_log',
			'map' => $map,
			'reduce' => $reduce,
			'query' => $query,
			'out' => 'mapreduce_active_user_per_month_'.$ndays.'_log',
		));
		$_result = $this->mongo_db->get('mapreduce_active_user_per_month_'.$ndays.'_log');
		if (!$_result) $_result = array();
		$result = array();
		foreach ($_result as $key => $value) {
			$values = array();
			if (is_array($value['value']) && array_key_exists('pb_player_id', $value['value'])) {
				if (is_array($value['value']['pb_player_id'])) foreach ($value['value']['pb_player_id'] as $key => $pb_player_id) {
					if (is_array($pb_player_id) && array_key_exists('pb_player_id', $pb_player_id)) {
						if (is_array($pb_player_id['pb_player_id'])) foreach ($pb_player_id['pb_player_id'] as $key => $each) {
							array_push($values, $each);
						} else {
							array_push($values, $pb_player_id['pb_player_id']);
						}
					} else {
						array_push($values, $pb_player_id);
					}
				} else $values = $value['value']['pb_player_id'];
			} else {
				array_push($values, $value['value']);
			}
			array_push($result, array('_id' => $value['_id'], 'value' => count(array_unique($values))));
		}
		usort($result, 'cmp1');
		$from2 = $from ? MY_Model::get_year_month($from) : null;
		$to2 = $to ? MY_Model::get_year_month($to) : null;
		if ($from2 && (!isset($result[0]['_id']) || $result[0]['_id'] != $from2)) array_unshift($result, array('_id' => $from2, 'value' => 0));
		if ($to2 && (!isset($result[count($result)-1]['_id']) || $result[count($result)-1]['_id'] != $to2)) array_push($result, array('_id' => $to2, 'value' => 0));
		return $result;
	}
	public function playerWithEnoughBadge($data, $badge_id, $n) {
		$this->set_site_mongodb($data['site_id']);
		$query = array('client_id' => $data['client_id'], 'site_id' => $data['site_id'], 'badge_id' => $badge_id, 'value' => array('$gte' => $n));
		$this->mongo_db->select(array('pb_player_id'));
		$this->mongo_db->select(array(),array('_id'));
		$this->mongo_db->where($query);
		$result = array();
		$arr = $this->mongo_db->get('playbasis_reward_to_player');
		if (is_array($arr)) foreach ($arr as $each) {
			array_push($result, $each['pb_player_id']);
		}
		return $result;
	}
	public function playerWithEnoughReward($data, $reward_id, $n) {
		$this->set_site_mongodb($data['site_id']);
		$query = array('client_id' => $data['client_id'], 'site_id' => $data['site_id'], 'reward_id' => $reward_id, 'value' => array('$gte' => $n));
		$this->mongo_db->select(array('pb_player_id'));
		$this->mongo_db->select(array(),array('_id'));
		$this->mongo_db->where($query);
		$result = array();
		$arr = $this->mongo_db->get('playbasis_reward_to_player');
		if (is_array($arr)) foreach ($arr as $each) {
			array_push($result, $each['pb_player_id']);
		}
		return $result;
	}
	public function get_reward_id_of_point($data) {
		$this->set_site_mongodb($data['site_id']);
		$query = array('client_id' => $data['client_id'], 'site_id' => $data['site_id'], 'name' => 'point');
		$this->mongo_db->select(array('reward_id'));
		$this->mongo_db->where($query);
		return $this->mongo_db->get('playbasis_reward_to_client');
	}
	public function playerWithEnoughCriteria($data, $criteria)
	{
		$this->set_site_mongodb($data['site_id']);
		$query = array('client_id' => $data['client_id'], 'site_id' => $data['site_id']);
		$ids = array();
		if (is_array($criteria)) foreach ($criteria as $k => $v) {
			switch ($k) {
				case 'exp':
					if (is_array($v)) foreach ($v as $n) {
						$query['exp'] = array('$gte' => $n);
						break;
					}
					break;
				case 'level':
					if (is_array($v)) foreach ($v as $n) {
						$query['level'] = array('$gte' => $n);
						break;
					}
					break;
				case 'point':
					$id = $this->get_reward_id_of_point($data);
					if (is_array($v)) foreach ($v as $n) {
						array_push($ids, $this->playerWithEnoughReward($data, $id[0]['reward_id'], $n));
						break;
					}
					break;
				case 'badge':
					if (is_array($v)) foreach ($v as $id => $n) {
						array_push($ids, $this->playerWithEnoughBadge($data, $id, $n));
						break;
					}
					break;
				case 'custom':
					if (is_array($v)) foreach ($v as $id => $n) {
						array_push($ids, $this->playerWithEnoughReward($data, $id, $n));
						break;
					}
					break;
				default:
					/* error, not support type */
					break;
			}
		}
		//echo 'YYY'; var_dump($ids); echo 'YYY';
		$ids_intersect = null;
		if (is_array($ids)) foreach ($ids as $each) {
			if ($ids_intersect == null) {
				$ids_intersect = $each;
			} else {
				$ids_intersect = array_intersect($ids_intersect, $each);
			}
		}
		//echo 'AAA'; var_dump($ids_intersect); echo 'AAA';
		if (!empty($ids)) {
			$query['_id'] = array('$in' => $ids_intersect);
		}
		//echo 'BBB'; var_dump($query); echo 'BBB';
		$result = $this->mongo_db->command(array(
			'count' => 'playbasis_player',
			'query' => $query
		));
		return $result['n'];
	}

    public function getAllQuests($pb_player_id, $site_id, $status="")
    {
        $this->set_site_mongodb($site_id);

        $this->mongo_db->where(array(
            'pb_player_id' => $pb_player_id,
            'site_id' => $site_id,
        ));
        $this->mongo_db->where_ne('deleted', true);
        $c_status = array("join", "unjoin", "finish");
        if($status != '' && in_array($status, $c_status) ){
            $this->mongo_db->where(array(
                'status' => $status,
            ));
        }

        return $this->mongo_db->get('playbasis_quest_to_player');
    }

    /*
     * Get all quests from _id
     * @param string $quest_id
     * @return array
     */
    public function getQuestsByID($site_id, $quest_id)
    {
        $this->set_site_mongodb($site_id);

        try {
            $quest_id = new MongoID($quest_id);
        } catch(MongoException $e) {
            return array();
        }

        $this->mongo_db->where(array(
            '_id' => $quest_id,
        ));

        $results = $this->mongo_db->get('playbasis_quest_to_client');
        if ($results) {
            for ($i=0; $i<sizeof($results); ++$i) {
                $results[$i]["quest_id"] = $results[$i]["_id"];
            }
        } else {
            $results = array();
        }

        return $results;
    }

    public function getMission($pb_player_id, $quest_id, $mission_id, $site_id)
    {
        $this->set_site_mongodb($site_id);

        $this->mongo_db->select(array('missions.$'));
        $this->mongo_db->where(array(
            'pb_player_id' => $pb_player_id,
            'site_id' => $site_id,
            'quest_id' => $quest_id,
            'missions.mission_id' => $mission_id
        ));
        $this->mongo_db->where_ne('deleted', true);
        $result = $this->mongo_db->get('playbasis_quest_to_player');
        return $result ? $result[0] : array();
    }

    public function getResetRewardEvent($site_id, $reward_id=null) {
        $this->set_site_mongodb($site_id);

        $this->mongo_db->select(array('reward_id','date_added'));
        $this->mongo_db->where('site_id', $site_id);
        $this->mongo_db->where('event_type', 'RESET');
        if ($reward_id) {
            $this->mongo_db->where('reward_id', $reward_id);
            $this->mongo_db->limit(1);
        }
        $this->mongo_db->order_by(array('date_added' => 'DESC')); // use 'date_added' instead of '_id'
        $results = $this->mongo_db->get('playbasis_event_log');
        $ret = array();
        if ($results){
            foreach ($results as $result) {
                $reward_id = $result['reward_id']->{'$id'};
                if (array_key_exists($reward_id, $ret)) continue;
                $ret[$reward_id] = $result['date_added'];
            }
        }

        return $ret;
    }
}
?>
