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
		$this->load->library('mongo_db');
	}
	public function createPlayer($data, $limit=null)
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

        if(isset($fieldData['gender'])){
            $fieldData['gender'] = intval($fieldData['gender']);
        }
        if(isset($fieldData['birth_date'])){
            $fieldData['birth_date'] = new MongoDate(strtotime($fieldData['birth_date']));
        }

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
		$this->mongo_db->limit(1);
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
		return $this->mongo_db->get('playbasis_reward_to_player');
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
		$this->mongo_db->limit(1);
		return $this->mongo_db->get('playbasis_reward_to_player');
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
		$this->mongo_db->limit(1);
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
		$this->mongo_db->limit(1);
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
		$this->mongo_db->limit(1);
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
        $this->mongo_db->limit(1);
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
		$this->mongo_db->where_ne('badge_id', null);
		$badges = $this->mongo_db->get('playbasis_reward_to_player');
		if(!$badges)
			return array();
		$playerBadges = array();

		foreach($badges as $badge)
        {
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
//                'deleted' => false
            ));
            $this->mongo_db->limit(1);
            $result = $this->mongo_db->get('playbasis_badge_to_client');
            if(!$result) continue;

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
		return $playerBadges;
	}
	public function getBadgeCount($site_id, $pb_player_id, $badge_id)
	{
		$this->set_site_mongodb($site_id);
		$this->mongo_db->select(array('value'));
		$this->mongo_db->where('pb_player_id', $pb_player_id);
		$this->mongo_db->where('badge_id', $badge_id);
		$badges = $this->mongo_db->get('playbasis_reward_to_player');
		return $badges ? $badges[0]['value'] : 0;
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
        $this->mongo_db->limit(1);
        $result = $this->mongo_db->get('playbasis_badge_to_client');
        if(!$result)
            return false;

        $badgeInfo = $result[0];

        if(isset($badgeInfo['claim']) && $badgeInfo['claim']){
            $this->mongo_db->where(array(
                'pb_player_id'=>$pb_player_id,
                'badge_id'=>$badge_id
            ));
            $this->mongo_db->limit(1);
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
        return false;
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
        $this->mongo_db->limit(1);
        $result = $this->mongo_db->get('playbasis_badge_to_client');
        if(!$result)
            return false;
        $badgeInfo = $result[0];

        if(isset($badgeInfo['redeem']) && $badgeInfo['redeem']){
            $this->mongo_db->where(array(
                'pb_player_id'=>$pb_player_id,
                'badge_id'=>$badge_id
            ));
            $this->mongo_db->limit(1);
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
        return false;
	}
	public function getLastEventTime($pb_player_id, $site_id, $eventType)
	{
		$this->set_site_mongodb($site_id);

        $reset = $this->getResetRewardEvent($site_id);

		$this->mongo_db->select(array('date_added'));
        $this->mongo_db->where('pb_player_id', $pb_player_id);
        $this->mongo_db->where('event_type', $eventType);
        if($reset){
            $reset_where = array();
            $reset_not_id = array();
            foreach($reset as $k => $v){
                $reset_not_id[] = new MongoId($k);
                $reset_where[] = array('reward_id' => new MongoId($k), 'date_added' => array('$gte' => $v));
            }
            $reset_where[] = array('reward_id' => array('$nin' => $reset_not_id));

            $this->mongo_db->where(array('$or' => $reset_where));
        }
		$this->mongo_db->order_by(array('date_added' => 'desc'));
		$this->mongo_db->limit(1);
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
	private function removeDeletedPlayers($results, $limit, $rankedBy) {
		$total = count($results);
		$c = 0;
		for ($i = 0; $i < $total; $i++) {
			if ($c < $limit) {
				$this->mongo_db->select(array('cl_player_id'));
				if (isset($results[$i]['_id']['pb_player_id'])) {
					$results[$i]['pb_player_id'] = $results[$i]['_id']['pb_player_id'];
					unset($results[$i]['_id']);
				}
				$this->mongo_db->where(array('_id' => $results[$i]['pb_player_id']));
				$p = $this->mongo_db->get('playbasis_player');
				if ($p) {
					$p = $p[0];
					$results[$i]['player_id'] = $p['cl_player_id'];
					$results[$i][$rankedBy] = $results[$i]['value'];
					unset($results[$i]['cl_player_id']);
					unset($results[$i]['value']);
					$c++;
				} else {
					unset($results[$i]);
				}
			} else {
				unset($results[$i]);
			}
		}
		return array_values($results);
	}
	private function getRewardIdByName($client_id, $site_id, $name) {
		$this->mongo_db->select(array('reward_id'));
		$this->mongo_db->where(array(
			'name' => $name,
			'site_id' => $site_id,
			'client_id' => $client_id
		));
		$this->mongo_db->limit(1);
		$results = $this->mongo_db->get('playbasis_reward_to_client');
		return $results ? $results[0]['reward_id'] : null;
	}
	private function getTotalDays($year, $month) {
		$t = strtotime($year.'-'.(strlen($month) < 2 ? '0' : '').$month.'-15 00:00:00');
		$next_month = strtotime('+1 month', $t);
		$first = date('Y-m-01 00:00:00', $next_month);
		$d = strtotime('-1 day', strtotime($first));
		return intval(date('d', $d));
	}
	private function getWeek($d, $daysPerWeek=7) {
		for ($w = 0; $w < 4; $w++) {
			if ($d < ($w+1)*$daysPerWeek+1) {
				return $w;
			}
		}
		return 1;
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
		$limit = intval($limit);
		$this->set_site_mongodb($site_id);
		/* get reward_id */
		$reward_id = $this->getRewardIdByName($client_id, $site_id, $ranked_by);
		/* list top players */
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
		$result1 = $this->mongo_db->get('playbasis_reward_to_player');
		return $this->removeDeletedPlayers($result1, $limit, $ranked_by);
	}
	public function getWeeklyLeaderboard($ranked_by, $limit, $client_id, $site_id) {
		$limit = intval($limit);
		$this->set_site_mongodb($site_id);
		/* get reward_id */
		$reward_id = $this->getRewardIdByName($client_id, $site_id, $ranked_by);
		/* get latest RESET event for that reward_id (if exists) */
		$reset = $this->getResetRewardEvent($site_id, $reward_id);
		$resetTime = null;
		if ($reset) {
			$reset_time = array_values($reset);
			$resetTime = $reset_time[0]->sec;
		}
		/* list top players */
		$now = time();
		$totalDays = $this->getTotalDays(date('Y', $now), date('m', $now));
		$daysPerWeek = round($totalDays/4.0);
		$d = intval(date('d', $now));
		$w = $this->getWeek($d, $daysPerWeek);
		$d = $w*$daysPerWeek+1;
		$first = date('Y-m-'.($d < 10 ? '0' : '').$d, $now);
		$from = strtotime($first.' 00:00:00');
		if ($resetTime && $resetTime > $from) $from = $resetTime;
		$results = $this->mongo_db->aggregate('playbasis_event_log', array(
			array(
				'$match' => array(
					'event_type' => 'REWARD',
					'site_id' => $site_id,
					'reward_id' => $reward_id,
					'date_added' => array('$gte' => new MongoDate($from)),
				),
			),
			array(
				'$group' => array('_id' => array('pb_player_id' => '$pb_player_id'), 'value' => array('$sum' => '$value'))
			),
			array(
				'$sort' => array('value' => -1),
			),
			array(
				'$limit' => $limit+5,
			),
		));
		return $results ? $this->removeDeletedPlayers($results['result'], $limit, $ranked_by) : array();
	}
	public function getMonthlyLeaderboard($ranked_by, $limit, $client_id, $site_id) {
		$limit = intval($limit);
		$this->set_site_mongodb($site_id);
		/* get reward_id */
		$reward_id = $this->getRewardIdByName($client_id, $site_id, $ranked_by);
		/* get latest RESET event for that reward_id (if exists) */
		$reset = $this->getResetRewardEvent($site_id, $reward_id);
		$resetTime = null;
		if ($reset) {
			$reset_time = array_values($reset);
			$resetTime = $reset_time[0]->sec;
		}
		/* list top players */
		$now = time();
		$first = date('Y-m-01', $now);
		$from = strtotime($first.' 00:00:00');
		if ($resetTime && $resetTime > $from) $from = $resetTime;
		$results = $this->mongo_db->aggregate('playbasis_event_log', array(
			array(
				'$match' => array(
					'event_type' => 'REWARD',
					'site_id' => $site_id,
					'reward_id' => $reward_id,
					'date_added' => array('$gte' => new MongoDate($from)),
				),
			),
			array(
				'$group' => array('_id' => array('pb_player_id' => '$pb_player_id'), 'value' => array('$sum' => '$value'))
			),
			array(
				'$sort' => array('value' => -1),
			),
			array(
				'$limit' => $limit+5,
			),
		));
		return $results ? $this->removeDeletedPlayers($results['result'], $limit, $ranked_by) : array();
	}
	public function sortPlayersByReward($client_id, $site_id, $reward_id, $limit=null) {
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
		if ($limit) $this->mongo_db->limit($limit);
		return $this->mongo_db->get('playbasis_reward_to_player');
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
			$result[$name] = $this->removeDeletedPlayers($ranking, $limit, $name);
		}
		return $result;
	}
	public function getWeeklyLeaderboards($limit, $client_id, $site_id) {
		/* get all rewards */
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
		$now = time();
		$totalDays = $this->getTotalDays(date('Y', $now), date('m', $now));
		$daysPerWeek = round($totalDays/4.0);
		$d = intval(date('d', $now));
		$w = $this->getWeek($d, $daysPerWeek);
		$d = $w*$daysPerWeek+1;
		$first = date('Y-m-'.($d < 10 ? '0' : '').$d, $now);
		$from = strtotime($first.' 00:00:00');
		$result = array();
		foreach ($rewards as $reward) {
			$reward_id = $reward['reward_id'];
			$name = $reward['name'];
			/* get latest RESET event for that reward_id (if exists) */
			$reset = $this->getResetRewardEvent($site_id, $reward_id);
			$resetTime = null;
			if ($reset) {
				$reset_time = array_values($reset);
				$resetTime = $reset_time[0]->sec;
			}
			/* list top players */
			if ($resetTime && $resetTime > $from) $from = $resetTime;
			$results = $this->mongo_db->aggregate('playbasis_event_log', array(
				array(
					'$match' => array(
						'event_type' => 'REWARD',
						'site_id' => $site_id,
						'reward_id' => $reward_id,
						'date_added' => array('$gte' => new MongoDate($from)),
					),
				),
				array(
					'$group' => array('_id' => array('pb_player_id' => '$pb_player_id'), 'value' => array('$sum' => '$value'))
				),
				array(
					'$sort' => array('value' => -1),
				),
				array(
					'$limit' => $limit+5,
				),
			));
			$result[$name] = $results ? $this->removeDeletedPlayers($results['result'], $limit, $name) : array();
		}
		return $result;
	}
	public function getMonthlyLeaderboards($limit, $client_id, $site_id) {
		/* get all rewards */
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
		$now = time();
		$first = date('Y-m-01', $now);
		$from = strtotime($first.' 00:00:00');
		$result = array();
		foreach ($rewards as $reward) {
			$reward_id = $reward['reward_id'];
			$name = $reward['name'];
			/* get latest RESET event for that reward_id (if exists) */
			$reset = $this->getResetRewardEvent($site_id, $reward_id);
			$resetTime = null;
			if ($reset) {
				$reset_time = array_values($reset);
				$resetTime = $reset_time[0]->sec;
			}
			/* list top players */
			if ($resetTime && $resetTime > $from) $from = $resetTime;
			$results = $this->mongo_db->aggregate('playbasis_event_log', array(
				array(
					'$match' => array(
						'event_type' => 'REWARD',
						'site_id' => $site_id,
						'reward_id' => $reward_id,
						'date_added' => array('$gte' => new MongoDate($from)),
					),
				),
				array(
					'$group' => array('_id' => array('pb_player_id' => '$pb_player_id'), 'value' => array('$sum' => '$value'))
				),
				array(
					'$sort' => array('value' => -1),
				),
				array(
					'$limit' => $limit+5,
				),
			));
			$result[$name] = $results ? $this->removeDeletedPlayers($results['result'], $limit, $name) : array();
		}
		return $result;
	}
	public function getWeeklyPlayerReward($client_id, $site_id, $reward_id, $pb_player_id) {
		$this->set_site_mongodb($site_id);
		/* get latest RESET event for that reward_id (if exists) */
		$reset = $this->getResetRewardEvent($site_id, $reward_id);
		$resetTime = null;
		if ($reset) {
			$reset_time = array_values($reset);
			$resetTime = $reset_time[0]->sec;
		}
		/* list top players */
		$now = time();
		$totalDays = $this->getTotalDays(date('Y', $now), date('m', $now));
		$daysPerWeek = round($totalDays/4.0);
		$d = intval(date('d', $now));
		$w = $this->getWeek($d, $daysPerWeek);
		$d = $w*$daysPerWeek+1;
		$first = date('Y-m-'.($d < 10 ? '0' : '').$d, $now);
		$from = strtotime($first.' 00:00:00');
		if ($resetTime && $resetTime > $from) $from = $resetTime;
		$results = $this->mongo_db->aggregate('playbasis_event_log', array(
			array(
				'$match' => array(
					'event_type' => 'REWARD',
					'site_id' => $site_id,
					'reward_id' => $reward_id,
					'date_added' => array('$gte' => new MongoDate($from)),
					'pb_player_id' => $pb_player_id,
				),
			),
			array(
				'$group' => array('_id' => null, 'value' => array('$sum' => '$value'))
			),
		));
		return $results && isset($results['result'][0]) ? $results['result'][0]['value'] : 0;
	}
	public function getMonthlyPlayerReward($client_id, $site_id, $reward_id, $pb_player_id) {
		$this->set_site_mongodb($site_id);
		/* get latest RESET event for that reward_id (if exists) */
		$reset = $this->getResetRewardEvent($site_id, $reward_id);
		$resetTime = null;
		if ($reset) {
			$reset_time = array_values($reset);
			$resetTime = $reset_time[0]->sec;
		}
		/* list top players */
		$now = time();
		$first = date('Y-m-01', $now);
		$from = strtotime($first.' 00:00:00');
		if ($resetTime && $resetTime > $from) $from = $resetTime;
		$results = $this->mongo_db->aggregate('playbasis_event_log', array(
			array(
				'$match' => array(
					'event_type' => 'REWARD',
					'site_id' => $site_id,
					'reward_id' => $reward_id,
					'date_added' => array('$gte' => new MongoDate($from)),
					'pb_player_id' => $pb_player_id,
				),
			),
			array(
				'$group' => array('_id' => null, 'value' => array('$sum' => '$value'))
			),
		));
		return $results && isset($results['result'][0]) ? $results['result'][0]['value'] : 0;
	}
	public function countWeeklyPlayersHigherReward($client_id, $site_id, $reward_id, $value) {
		$this->set_site_mongodb($site_id);
		/* get latest RESET event for that reward_id (if exists) */
		$reset = $this->getResetRewardEvent($site_id, $reward_id);
		$resetTime = null;
		if ($reset) {
			$reset_time = array_values($reset);
			$resetTime = $reset_time[0]->sec;
		}
		/* list top players */
		$now = time();
		$totalDays = $this->getTotalDays(date('Y', $now), date('m', $now));
		$daysPerWeek = round($totalDays/4.0);
		$d = intval(date('d', $now));
		$w = $this->getWeek($d, $daysPerWeek);
		$d = $w*$daysPerWeek+1;
		$first = date('Y-m-'.($d < 10 ? '0' : '').$d, $now);
		$from = strtotime($first.' 00:00:00');
		if ($resetTime && $resetTime > $from) $from = $resetTime;
		$results = $this->mongo_db->aggregate('playbasis_event_log', array(
			array(
				'$match' => array(
					'event_type' => 'REWARD',
					'site_id' => $site_id,
					'reward_id' => $reward_id,
					'date_added' => array('$gte' => new MongoDate($from)),
				),
			),
			array(
				'$group' => array('_id' => array('pb_player_id' => '$pb_player_id'), 'value' => array('$sum' => '$value'))
			),
			array(
				'$match' => array(
					'value' => array('$gt' => $value),
				),
			),
			array(
				'$group' => array('_id' => null, 'value' => array('$sum' => 1))
			),
		));
		return $results && isset($results['result'][0]) ? $results['result'][0]['value'] : 0;
	}
	public function countMonthlyPlayersHigherReward($client_id, $site_id, $reward_id, $value) {
		$this->set_site_mongodb($site_id);
		/* get latest RESET event for that reward_id (if exists) */
		$reset = $this->getResetRewardEvent($site_id, $reward_id);
		$resetTime = null;
		if ($reset) {
			$reset_time = array_values($reset);
			$resetTime = $reset_time[0]->sec;
		}
		/* list top players */
		$now = time();
		$first = date('Y-m-01', $now);
		$from = strtotime($first.' 00:00:00');
		if ($resetTime && $resetTime > $from) $from = $resetTime;
		$results = $this->mongo_db->aggregate('playbasis_event_log', array(
			array(
				'$match' => array(
					'event_type' => 'REWARD',
					'site_id' => $site_id,
					'reward_id' => $reward_id,
					'date_added' => array('$gte' => new MongoDate($from)),
				),
			),
			array(
				'$group' => array('_id' => array('pb_player_id' => '$pb_player_id'), 'value' => array('$sum' => '$value'))
			),
			array(
				'$match' => array(
					'value' => array('$gt' => $value),
				),
			),
			array(
				'$group' => array('_id' => null, 'value' => array('$sum' => 1))
			),
		));
		return $results && isset($results['result'][0]) ? $results['result'][0]['value'] : 0;
	}

	public function findLatestProcessActionLogTime() {
		$this->mongo_db->limit(1);
		return $this->mongo_db->get('playbasis_player_dau_latest');
	}

	public function updateLatestProcessActionLogTime($d) {
		$this->mongo_db->limit(1);
		$r = $this->mongo_db->get('playbasis_player_dau_latest');
		if ($r) {
			$r = $r[0];
			if ($d->sec < $r['date_added']->sec) return false;
			$this->mongo_db->where(array('_id' => $r['_id']));
			$this->mongo_db->set('date_added', $d);
			$this->mongo_db->update('playbasis_player_dau_latest', array("w" => 0, "j" => false));
		} else {
			$this->mongo_db->insert('playbasis_player_dau_latest', array('date_added' => $d), array("w" => 0, "j" => false));
		}
		return true;
	}

	public function listActionLog($d) {
		$this->mongo_db->select(array(
			'pb_player_id',
			'client_id',
			'site_id',
			'action_id',
			'date_modified',
		));
		if ($d) $this->mongo_db->where_gt('date_modified', $d);
		$this->mongo_db->order_by(array('date_modified' => 'ASC'));
		return $this->mongo_db->get('playbasis_action_log', true);
	}

	public function computeDau($action, $d) {
		$this->mongo_db->select(array());
		$this->mongo_db->where(array(
			'pb_player_id'	=> $action['pb_player_id'],
			'client_id'		=> $action['client_id'],
			'site_id'		=> $action['site_id'],
			'action_id'		=> $action['action_id'],
			'date_added'	=> new MongoDate($d)
		));
		$this->mongo_db->limit(1);
		$r = $this->mongo_db->get('playbasis_player_dau');
		if ($r) {
			$r = $r[0];
			$this->mongo_db->where(array('_id' => $r['_id']));
			$this->mongo_db->inc('count', 1);
			$this->mongo_db->update('playbasis_player_dau', array("w" => 0, "j" => false));
		} else {
			$this->mongo_db->insert('playbasis_player_dau', array(
				'pb_player_id'	=> $action['pb_player_id'],
				'client_id'		=> $action['client_id'],
				'site_id'		=> $action['site_id'],
				'action_id'		=> $action['action_id'],
				'count'			=> 1,
				'date_added'	=> new MongoDate($d)
			), array("w" => 0, "j" => false));
		}
	}

	public function computeMau($action, $d) {
		$data = array();
		$end = strtotime(date('Y-m-d', strtotime('+30 day', $d)));
		$cur = $d;
		while ($cur != $end) {
            $data[] = array(
				'pb_player_id'	=> $action['pb_player_id'],
				'client_id'		=> $action['client_id'],
				'site_id'		=> $action['site_id'],
				'date_added'	=> new MongoDate($cur)
			);
			$cur = strtotime(date('Y-m-d', strtotime('+1 day', $cur)));
		}
		return $this->mongo_db->batch_insert('playbasis_player_mau', $data, array("w" => 0, "j" => false));
	}

	private function checkClientUserLimitWarning($client_id, $site_id, $limit)
	{
		if(!$limit)
			return; //client has no user limit

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
		$this->mongo_db->limit(1);
		$result = $this->mongo_db->get('playbasis_client_site');
		assert($result);
		$result = $result[0];
		$domain_name_client = $result['domain_name'];

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
            $this->email->from(EMAIL_FROM, 'Playbasis');
//            $this->email->to($email);
            $this->email->to('cscteam@playbasis.com','devteam@playbasis.com');
//            $this->email->bcc('cscteam@playbasis.com');
            $this->email->subject($subject);
            $this->email->message($htmlMessage);
            $this->email->send();*/

                $this->amazon_ses->from(EMAIL_FROM, 'Playbasis');
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
		$mongoDate = new MongoDate(time());

        $this->mongo_db->where(array(
			"client_id" => $client_id,
			"_id" => $site_id))->set(array(
                "last_send_limit_users" => $mongoDate
            ))->update("playbasis_client_site");
        return $mongoDate;
    }

    public function getPointHistoryFromPlayerID($pb_player_id, $site_id, $reward_id, $offset, $limit){

        $this->set_site_mongodb($site_id);

    	if($reward_id){
            $reset = $this->getResetRewardEvent($site_id, $reward_id);

            if($reset){
                $reset_time = array_values($reset);
                $starttime = $reset_time[0];

                $this->mongo_db->where('date_added', array('$gt' => $starttime));
            }

            $this->mongo_db->where('reward_id', $reward_id);
    	}else{

            $reset = $this->getResetRewardEvent($site_id, $reward_id);

            if($reset){
                $reset_where = array();
                $reset_not_id = array();
                foreach($reset as $k => $v){
                    $reset_not_id[] = new MongoId($k);
                    $reset_where[] = array('reward_id' => new MongoId($k), 'date_added' => array('$gte' => $v));
                }
                $reset_where[] = array('reward_id' => array('$nin' => $reset_not_id));

                $this->mongo_db->where(array('$or' => $reset_where));
            }

            $this->mongo_db->where_ne('reward_id', null);
        }
    	$this->mongo_db->where('pb_player_id', $pb_player_id);
    	$this->mongo_db->where('site_id', $site_id);
    	$this->mongo_db->where('event_type', 'REWARD');
        $this->mongo_db->where_gt('value', 0);
    	$this->mongo_db->limit((int)$limit);
        $this->mongo_db->offset((int)$offset);
    	$this->mongo_db->select(array('reward_id', 'reward_name', 'value', 'message', 'date_added','action_log_id', 'quest_id', 'mission_id', 'goods_id', 'event_type', 'quiz_id'));
    	$this->mongo_db->select(array(), array('_id'));
    	$event_log = $this->mongo_db->get('playbasis_event_log');

		foreach($event_log as &$event){
			$actionAndStringFilter = $this->getActionNameAndStringFilter($event['action_log_id']);

            $event['date_added'] = datetimeMongotoReadable($event['date_added']);
			if($actionAndStringFilter){
				$event['action_name'] = $actionAndStringFilter['action_name'];
				$event['string_filter'] = $actionAndStringFilter['url']."";
			}
            if(isset($event['quest_id']) && $event['quest_id']){
                if(isset($event['mission_id']) && $event['mission_id']){
                    $event['action_name'] = 'mission_reward';
                }else{
                    $event['action_name'] = 'quest_reward';
                }
                $event['action_icon'] = 'fa-trophy';
            }
            if(isset($event['goods_id']) && $event['goods_id']){
                $event['action_name'] = 'redeem_goods';
                $event['action_icon'] = 'fa-gift';
            }
            if(isset($event['quiz_id']) && $event['quiz_id']){
                $event['action_name'] = 'quiz_reward';
                $event['action_icon'] = 'fa-bar-chart';
            }
            if($event['event_type'] == 'LOGIN'){
                $event['action_name'] = 'login';
                $event['action_icon'] = 'fa-sign-in';
            }
			unset($event['action_log_id']);
            unset($event['quest_id']);
            unset($event['mission_id']);
            unset($event['goods_id']);
            unset($event['quiz_id']);
            unset($event['event_type']);

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
                $this->mongo_db->limit(1);
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
        $this->mongo_db->limit(1);
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
            $this->mongo_db->limit(1);
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

	private function _new_registration_all_customers($from=null, $to=null) {
		$this->mongo_db->where('status', true);
		if ($from) $this->mongo_db->where_gte('date_added', $this->new_mongo_date($from));
		if ($to) $this->mongo_db->where_lte('date_added', $this->new_mongo_date($to, '23:59:59'));
		return $this->mongo_db->count('playbasis_player');
	}

	public function new_registration_all_customers($from=null, $to=null, $site_ids=array()) {
		$this->set_site_mongodb(0);
		$n = $this->_new_registration_all_customers($from, $to);
		if (is_array($site_ids)) foreach ($site_ids as $site_id) {
			$this->set_site_mongodb(new MongoId($site_id)); // set to dedicated DB (if any)
			$n += $this->_new_registration_all_customers($from, $to);
		}
		return $n;
	}

	public function new_registration1($data, $from=null, $to=null) {
		$this->set_site_mongodb($data['site_id']);
		$map = new MongoCode("function() { this.date_added.setTime(this.date_added.getTime()-(-7*60*60*1000)); emit(this.date_added.getFullYear()+'-'+('0'+(this.date_added.getMonth()+1)).slice(-2)+'-'+('0'+this.date_added.getDate()).slice(-2), 1); }");
		$reduce = new MongoCode("function(key, values) { return Array.sum(values); }");
		$query = array('client_id' => $data['client_id'], 'site_id' => $data['site_id'], 'status' => true);
		if ($from || $to) $query['date_added'] = array();
		if ($from) $query['date_added']['$gte'] = $this->new_mongo_date($from);
		if ($to) $query['date_added']['$lte'] = $this->new_mongo_date($to, '23:59:59');
		$result = $this->mongo_db->command(array(
			'mapReduce' => 'playbasis_player',
			'map' => $map,
			'reduce' => $reduce,
			'query' => $query,
			'out' => array('inline' => 1),
		));
		$result = $result ? $result['results'] : array();
		if ($from && (!isset($result[0]['_id']) || $result[0]['_id'] != $from)) array_unshift($result, array('_id' => $from, 'value' => 0));
		if ($to && (!isset($result[count($result)-1]['_id']) || $result[count($result)-1]['_id'] != $to)) array_push($result, array('_id' => $to, 'value' => 0));
		return $result;
	}

	public function new_registration($data, $from=null, $to=null) {
		$this->set_site_mongodb($data['site_id']);
		$action_id = $this->findAction(array_merge($data, array('action_name' => 'register')));
		if (!$action_id) return array();
		$match = array(
			'client_id' => $data['client_id'],
			'site_id' => $data['site_id'],
			'action_id' => $action_id,
		);
		if (($from || $to) && !isset($match['date_added'])) $match['date_added'] = array();
		if ($from) $match['date_added']['$gte'] = new MongoDate(strtotime($from.' 00:00:00'));
		if ($to) $match['date_added']['$lte'] = new MongoDate(strtotime($to.' 23:59:59'));
		$_result = $this->mongo_db->aggregate('playbasis_player_dau', array(
			array(
				'$match' => $match,
			),
			array(
				'$group' => array('_id' => '$date_added', 'value' => array('$sum' => 1))
			),
		));
		$_result = $_result ? $_result['result'] : array();
		$result = array();
		if (is_array($_result)) foreach ($_result as $key => $value) {
			array_push($result, array('_id' => date('Y-m-d', $value['_id']->sec), 'value' => $value['value']));
		}
		usort($result, 'cmp1');
		if ($from && (!isset($result[0]['_id']) || $result[0]['_id'] != $from)) array_unshift($result, array('_id' => $from, 'value' => 0));
		if ($to && (!isset($result[count($result)-1]['_id']) || $result[count($result)-1]['_id'] != $to)) array_push($result, array('_id' => $to, 'value' => 0));
		return $result;
	}

	public function findAction($data)
	{
		$this->set_site_mongodb($data['site_id']);
		$this->mongo_db->select(array('action_id'));
		$this->mongo_db->where(array(
			'client_id' => $data['client_id'],
			'site_id' => $data['site_id'],
			'name' => strtolower($data['action_name'])
		));
		$this->mongo_db->limit(1);
		$result = $this->mongo_db->get('playbasis_action_to_client');
		return $result ? $result[0]['action_id'] : array();
	}

	/*public function daily_active_user_per_day($data, $from=null, $to=null) {
		return $this->active_user_per_day($data, 1, $from, $to);
	}*/

	public function daily_active_user_per_day($data, $from=null, $to=null) {
		$this->set_site_mongodb($data['site_id']);
		$match = array(
			'client_id' => $data['client_id'],
			'site_id' => $data['site_id'],
		);
		if (($from || $to) && !isset($match['date_added'])) $match['date_added'] = array();
		if ($from) $match['date_added']['$gte'] = new MongoDate(strtotime($from.' 00:00:00'));
		if ($to) $match['date_added']['$lte'] = new MongoDate(strtotime($to.' 23:59:59'));
		$_result = $this->mongo_db->aggregate('playbasis_player_dau', array(
			array(
				'$match' => $match,
			),
			array(
				'$group' => array('_id' => '$date_added', 'value' => array('$sum' => '$count'))
			),
		));
		$_result = $_result ? $_result['result'] : array();
		$result = array();
		if (is_array($_result)) foreach ($_result as $key => $value) {
			array_push($result, array('_id' => date('Y-m-d', $value['_id']->sec), 'value' => $value['value']));
		}
		usort($result, 'cmp1');
		if ($from && (!isset($result[0]['_id']) || $result[0]['_id'] != $from)) array_unshift($result, array('_id' => $from, 'value' => 0));
		if ($to && (!isset($result[count($result)-1]['_id']) || $result[count($result)-1]['_id'] != $to)) array_push($result, array('_id' => $to, 'value' => 0));
		return $result;
	}

	/*public function monthy_active_user_per_day($data, $from=null, $to=null) {
		return $this->active_user_per_day($data, 30, $from, $to);
	}*/

	public function monthy_active_user_per_day($data, $from=null, $to=null) {
		$this->set_site_mongodb($data['site_id']);
		$match = array(
			'client_id' => $data['client_id'],
			'site_id' => $data['site_id'],
		);
		if (($from || $to) && !isset($match['date_added'])) $match['date_added'] = array();
		if ($from) $match['date_added']['$gte'] = new MongoDate(strtotime($from.' 00:00:00'));
		if ($to) $match['date_added']['$lte'] = new MongoDate(strtotime($to.' 23:59:59'));
		$_result = $this->mongo_db->aggregate('playbasis_player_mau', array(
			array(
				'$match' => $match,
			),
			array(
				'$group' => array('_id' => '$date_added', 'value' => array('$sum' => 1))
			),
		));
		$_result = $_result ? $_result['result'] : array();
		$result = array();
		if (is_array($_result)) foreach ($_result as $key => $value) {
			array_push($result, array('_id' => date('Y-m-d', $value['_id']->sec), 'value' => $value['value']));
		}
		usort($result, 'cmp1');
		if ($from && (!isset($result[0]['_id']) || $result[0]['_id'] != $from)) array_unshift($result, array('_id' => $from, 'value' => 0));
		if ($to && (!isset($result[count($result)-1]['_id']) || $result[count($result)-1]['_id'] != $to)) array_push($result, array('_id' => $to, 'value' => 0));
		return $result;
	}

	/*public function monthy_active_user_per_week($data, $from=null, $to=null) {
		return $this->active_user_per_week($data, 30, $from, $to);
	}*/

	public function monthy_active_user_per_week($data, $from=null, $to=null) {
		$this->set_site_mongodb($data['site_id']);
		// http://stackoverflow.com/questions/15968465/mongo-map-reduce-error
		$map = new MongoCode("function() {
			this.date_added.setTime(this.date_added.getTime()-(-7*60*60*1000));
			var get_number_of_days = function(year, month) {
				var monthStart = new Date(year, month, 1);
				var monthEnd = new Date(year, month+1, 1);
				return (monthEnd-monthStart)/(1000*60*60*24);
			};
			var days = get_number_of_days(this.date_added.getFullYear(), this.date_added.getMonth());
			var week = Math.ceil(this.date_added.getDate()/7.0);
			if (week > 4) week = 4;
			var d = (week-1)*7+1;
			emit(this.date_added.getFullYear()+'-'+('0'+(this.date_added.getMonth()+1)).slice(-2)+'-'+('0'+d).slice(-2), {a: [this.pb_player_id.toString()]});
		}");
		$reduce = new MongoCode("function(key, values) {
			result = {a: []};
			check = {};
			values.forEach(function (v) {
				v.a.forEach(function (e) {
					if (!(e in check)) {
						result.a.push(e);
						check[e] = true;
					}
				})
			});
			return result;
		}");
		$match = array('client_id' => $data['client_id'], 'site_id' => $data['site_id']);
		if ($from || $to) $match['date_added'] = array();
		if ($from) $match['date_added']['$gte'] = new MongoDate(strtotime($from.' 00:00:00'));
		if ($to) $match['date_added']['$lte'] = new MongoDate(strtotime($to.' 23:59:59'));
		$_result = $this->mongo_db->command(array(
			'mapReduce' => 'playbasis_player_mau',
			'map' => $map,
			'reduce' => $reduce,
			'query' => $match,
			'out' => array('inline' => 1),
		));
		$_result = $_result ? $_result['results'] : array();
		$result = array();
		if (is_array($_result)) foreach ($_result as $key => $value) {
			array_push($result, array('_id' => $value['_id'], 'value' => count($value['value']['a'])));
		}
		usort($result, 'cmp1');
		$from2 = $from ? MY_Model::date_to_startdate_of_week($from) : null;
		$to2 = $to ? MY_Model::date_to_startdate_of_week($to) : null;
		if ($from2 && (!isset($result[0]['_id']) || $result[0]['_id'] != $from2)) array_unshift($result, array('_id' => $from2, 'value' => 0));
		if ($to2 && (!isset($result[count($result)-1]['_id']) || $result[count($result)-1]['_id'] != $to2)) array_push($result, array('_id' => $to2, 'value' => 0));
		return $result;
	}

	/*public function monthy_active_user_per_month($data, $from=null, $to=null) {
		return $this->active_user_per_month($data, 30, $from, $to);
	}*/

	public function monthy_active_user_per_month($data, $from=null, $to=null) {
		$this->set_site_mongodb($data['site_id']);
		// http://stackoverflow.com/questions/15968465/mongo-map-reduce-error
		$map = new MongoCode("function() {
			this.date_added.setTime(this.date_added.getTime()-(-7*60*60*1000));
			emit(this.date_added.getFullYear()+'-'+('0'+(this.date_added.getMonth()+1)).slice(-2), {a: [this.pb_player_id.toString()]});
		}");
		$reduce = new MongoCode("function(key, values) {
			result = {a: []};
			check = {};
			values.forEach(function (v) {
				v.a.forEach(function (e) {
					if (!(e in check)) {
						result.a.push(e);
						check[e] = true;
					}
				})
			});
			return result;
		}");
		$match = array('client_id' => $data['client_id'], 'site_id' => $data['site_id']);
		if ($from || $to) $match['date_added'] = array();
		if ($from) $match['date_added']['$gte'] = new MongoDate(strtotime($from.' 00:00:00'));
		if ($to) $match['date_added']['$lte'] = new MongoDate(strtotime($to.' 23:59:59'));
		$_result = $this->mongo_db->command(array(
			'mapReduce' => 'playbasis_player_mau',
			'map' => $map,
			'reduce' => $reduce,
			'query' => $match,
			'out' => array('inline' => 1),
		));
		$_result = $_result ? $_result['results'] : array();
		$result = array();
		if (is_array($_result)) foreach ($_result as $key => $value) {
			array_push($result, array('_id' => $value['_id'], 'value' => count($value['value']['a'])));
		}
		usort($result, 'cmp1');
		$from2 = $from ? MY_Model::get_year_month($from) : null;
		$to2 = $to ? MY_Model::get_year_month($to) : null;
		if ($from2 && (!isset($result[0]['_id']) || $result[0]['_id'] != $from2)) array_unshift($result, array('_id' => $from2, 'value' => 0));
		if ($to2 && (!isset($result[count($result)-1]['_id']) || $result[count($result)-1]['_id'] != $to2)) array_push($result, array('_id' => $to2, 'value' => 0));
		return $result;
	}

	private function active_user_per_day($data, $ndays, $from=null, $to=null) {
		$this->set_site_mongodb($data['site_id']);
		$str = $from ? explode('-', $from, 3) : "";
		$var_from = $from ? "var from = new Date(".$str[0].", ".(intval($str[1])-1).", ".$str[2].", 0, 0, 0);" : "";
		$str = $to ? explode('-', $to, 3) : "";
		$var_to = $to ? "var to = new Date(".$str[0].", ".(intval($str[1])-1).", ".$str[2].", 23, 59, 59);" : "";
		$check_from = $from ? "if (tmp.getTime() < from.getTime()) continue;" : "";
		$check_to = $to ? "if (tmp.getTime() > to.getTime()) break;" : "";
		// http://stackoverflow.com/questions/15968465/mongo-map-reduce-error
		$map = new MongoCode("function() {
			this.date_added.setTime(this.date_added.getTime()-(-7*60*60*1000));
			var tmp = new Date();
			$var_from
			$var_to
			for (var i = 0; i < ".$ndays."; i++) {
				tmp.setTime(this.date_added.getTime()+i*86400000);
				$check_from
				$check_to
				emit(tmp.getFullYear()+'-'+('0'+(tmp.getMonth()+1)).slice(-2)+'-'+('0'+tmp.getDate()).slice(-2), {a: [this.pb_player_id.toString()]});
			}
		}");
		$reduce = new MongoCode("function(key, values) {
			result = {a: []};
			check = {};
			values.forEach(function (v) {
				v.a.forEach(function (e) {
					if (!(e in check)) {
						result.a.push(e);
						check[e] = true;
					}
				})
			});
			return result;
		}");
		$query = array('client_id' => $data['client_id'], 'site_id' => $data['site_id']);
		if ($from || $to) $query['date_added'] = array();
		if ($from) $query['date_added']['$gte'] = $this->new_mongo_date($ndays > 1 ? date('Y-m-d', strtotime('-'.$ndays.' day', strtotime($from))) : $from);
		if ($to) $query['date_added']['$lte'] = $this->new_mongo_date($to, '23:59:59');
		$_result = $this->mongo_db->command(array(
			'mapReduce' => 'playbasis_action_log',
			'map' => $map,
			'reduce' => $reduce,
			'query' => $query,
			'out' => array('inline' => 1),
		));
		$_result = $_result ? $_result['results'] : array();
		$result = array();
		if (is_array($_result)) foreach ($_result as $key => $value) {
			array_push($result, array('_id' => $value['_id'], 'value' => count($value['value']['a'])));
		}
		usort($result, 'cmp1');
		if ($from && (!isset($result[0]['_id']) || $result[0]['_id'] != $from)) array_unshift($result, array('_id' => $from, 'value' => 0));
		if ($to && (!isset($result[count($result)-1]['_id']) || $result[count($result)-1]['_id'] != $to)) array_push($result, array('_id' => $to, 'value' => 0));
		return $result;
	}

	private function active_user_per_week($data, $ndays, $from=null, $to=null) {
		$this->set_site_mongodb($data['site_id']);
		$str = $from ? explode('-', $from, 3) : "";
		$var_from = $from ? "var from = new Date(".$str[0].", ".(intval($str[1])-1).", ".$str[2].", 0, 0, 0);" : "";
		$str = $to ? explode('-', $to, 3) : "";
		$var_to = $to ? "var to = new Date(".$str[0].", ".(intval($str[1])-1).", ".$str[2].", 23, 59, 59);" : "";
		$check_from = $from ? "if (tmp.getTime() < from.getTime()) continue;" : "";
		$check_to = $to ? "if (tmp.getTime() > to.getTime()) break;" : "";
		// http://stackoverflow.com/questions/15968465/mongo-map-reduce-error
		$map = new MongoCode("function() {
			this.date_added.setTime(this.date_added.getTime()-(-7*60*60*1000));
			var get_number_of_days = function(year, month) {
				var monthStart = new Date(year, month, 1);
				var monthEnd = new Date(year, month+1, 1);
				return (monthEnd-monthStart)/(1000*60*60*24);
			};
			var days,days_per_week,week,d;
			var tmp = new Date();
			$var_from
			$var_to
			for (var i = 0; i < ".$ndays."; i++) {
				tmp.setTime(this.date_added.getTime()+i*86400000);
				$check_from
				$check_to
				days = get_number_of_days(tmp.getFullYear(), tmp.getMonth());
				week = Math.ceil(tmp.getDate()/7.0);
				if (week > 4) week = 4;
				d = (week-1)*7+1;
				emit(tmp.getFullYear()+'-'+('0'+(tmp.getMonth()+1)).slice(-2)+'-'+('0'+d).slice(-2), {a: [this.pb_player_id.toString()]});
			}
		}");
		$reduce = new MongoCode("function(key, values) {
			result = {a: []};
			check = {};
			values.forEach(function (v) {
				v.a.forEach(function (e) {
					if (!(e in check)) {
						result.a.push(e);
						check[e] = true;
					}
				})
			});
			return result;
		}");
		$query = array('client_id' => $data['client_id'], 'site_id' => $data['site_id']);
		if ($from || $to) $query['date_added'] = array();
		if ($from) $query['date_added']['$gte'] = $this->new_mongo_date($ndays > 1 ? date('Y-m-d', strtotime('-'.$ndays.' day', strtotime($from))) : $from);
		if ($to) $query['date_added']['$lte'] = $this->new_mongo_date($to, '23:59:59');
		$_result = $this->mongo_db->command(array(
			'mapReduce' => 'playbasis_action_log',
			'map' => $map,
			'reduce' => $reduce,
			'query' => $query,
			'out' => array('inline' => 1),
		));
		$_result = $_result ? $_result['results'] : array();
		$result = array();
		if (is_array($_result)) foreach ($_result as $key => $value) {
			array_push($result, array('_id' => $value['_id'], 'value' => count($value['value']['a'])));
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
		$str = $from ? explode('-', $from, 3) : "";
		$var_from = $from ? "var from = new Date(".$str[0].", ".(intval($str[1])-1).", ".$str[2].", 0, 0, 0);" : "";
		$str = $to ? explode('-', $to, 3) : "";
		$var_to = $to ? "var to = new Date(".$str[0].", ".(intval($str[1])-1).", ".$str[2].", 23, 59, 59);" : "";
		$check_from = $from ? "if (tmp.getTime() < from.getTime()) continue;" : "";
		$check_to = $to ? "if (tmp.getTime() > to.getTime()) break;" : "";
		// http://stackoverflow.com/questions/15968465/mongo-map-reduce-error
		$map = new MongoCode("function() {
			this.date_added.setTime(this.date_added.getTime()-(-7*60*60*1000));
			var tmp = new Date();
			$var_from
			$var_to
			for (var i = 0; i < ".$ndays."; i++) {
				tmp.setTime(this.date_added.getTime()+i*86400000);
				$check_from
				$check_to
				emit(tmp.getFullYear()+'-'+('0'+(tmp.getMonth()+1)).slice(-2), {a: [this.pb_player_id.toString()]});
			}
		}");
		$reduce = new MongoCode("function(key, values) {
			result = {a: []};
			check = {};
			values.forEach(function (v) {
				v.a.forEach(function (e) {
					if (!(e in check)) {
						result.a.push(e);
						check[e] = true;
					}
				})
			});
			return result;
		}");
		$query = array('client_id' => $data['client_id'], 'site_id' => $data['site_id']);
		if ($from || $to) $query['date_added'] = array();
		if ($from) $query['date_added']['$gte'] = $this->new_mongo_date($ndays > 1 ? date('Y-m-d', strtotime('-'.$ndays.' day', strtotime($from))) : $from);
		if ($to) $query['date_added']['$lte'] = $this->new_mongo_date($to, '23:59:59');
		$_result = $this->mongo_db->command(array(
			'mapReduce' => 'playbasis_action_log',
			'map' => $map,
			'reduce' => $reduce,
			'query' => $query,
			'out' => array('inline' => 1),
		));
		$_result = $_result ? $_result['results'] : array();
		$result = array();
		if (is_array($_result)) foreach ($_result as $key => $value) {
			array_push($result, array('_id' => $value['_id'], 'value' => count($value['value']['a'])));
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
	public function get_reward_id_by_name($data, $name) {
		$this->set_site_mongodb($data['site_id']);
		$query = array('client_id' => $data['client_id'], 'site_id' => $data['site_id'], 'name' => $name);
		$this->mongo_db->select(array('reward_id'));
		$this->mongo_db->where($query);
		$this->mongo_db->limit(1);
		$results = $this->mongo_db->get('playbasis_reward_to_client');
		return $results ? $results[0]['reward_id'] : null;
	}
	public function get_reward_id_of_point($data) {
		return $this->get_reward_id_by_name($data, 'point');
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
					$reward_id = $this->get_reward_id_of_point($data);
					if (is_array($v)) foreach ($v as $n) {
						array_push($ids, $this->playerWithEnoughReward($data, $reward_id, $n));
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
        $this->mongo_db->limit(1);
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

    public function getById($site_id, $pb_player_id) {
        $this->set_site_mongodb($site_id);

        $this->mongo_db->where('_id', $pb_player_id);
        $results = $this->mongo_db->get('playbasis_player');
        return $results ? $results[0] : array();
    }

    public function getEmail($site_id, $pb_player_id) {
        $player = $this->getById($site_id, $pb_player_id);
        return $player && isset($player['email']) ? $player['email'] : null;
    }

    public function getPhone($site_id, $pb_player_id) {
        $player = $this->getById($site_id, $pb_player_id);
        return $player && isset($player['phone_number']) ? $player['phone_number'] : null;
    }

    public function findPlayersBySiteId($site_id) {
        $this->set_site_mongodb($site_id);
        $this->mongo_db->select(array('email', 'cl_player_id', 'username'));
        $this->mongo_db->where('site_id', $site_id);
        return $this->mongo_db->get('playbasis_player');
    }

    public function findRecentPlayers($days) {
        $this->set_site_mongodb(0);
        $d = strtotime("-".$days." day");
        $this->mongo_db->where_gt('date_added', new MongoDate($d));
        return $this->mongo_db->distinct('pb_player_id', 'playbasis_action_log');
    }

    public function findDistinctEmails($pb_player_ids) {
        $this->mongo_db->where_in('_id', $pb_player_ids);
        return $this->mongo_db->distinct('email', 'playbasis_player');
    }

    public function findProcessedEmails($emails) {
        $this->mongo_db->select(array());
        $this->mongo_db->where_in('_id', $emails);
        return $this->mongo_db->get('playbasis_player_fc');
    }

    public function findNewEmails($emails) {
        return array_diff($emails, array_merge(array('no-reply@playbasis.com', 'info@playbasis.com'), array_map('index_id', $this->findProcessedEmails($emails))));
    }

    public function insertOrUpdateFullContact($email, $detail) {
        if ($detail && isset($detail['result'])) {
            $mongoDate = new MongoDate(time());
            $this->mongo_db->where('_id', $email);
            $records = $this->mongo_db->get('playbasis_player_fc');
            if (!$records) {
                $this->mongo_db->insert('playbasis_player_fc', array_merge(array('_id' => $email, 'date_added' => $mongoDate, 'date_modified' => $mongoDate), $detail['result']));
            } else {
                if (isset($detail['result']['status']) && $detail['result']['status'] != 200) return;
                $r = $records[0];
                $this->mongo_db->where('_id', $email);
                $this->mongo_db->delete('playbasis_player_fc');
                $this->mongo_db->insert('playbasis_player_fc', array_merge(array('_id' => $email, 'date_added' => $r['date_added'], 'date_modified' => $mongoDate), $detail['result']));
            }
        }
    }
    public function registerDevice($data,$site_id)
    {
        $mongoDate = new MongoDate(time());

        $this->mongo_db->select(null);
        $this->mongo_db->where(array(
            'pb_player_id' => new MongoId($data['player_id']),
            'site_id' => new MongoId($data['site_id']),
            'client_id' => $data['client_id'],
            'udid' => $data['udid'],
            'device_token' => $data['device_token']
        ));
        $this->mongo_db->limit(1);
        $results = $this->mongo_db->get('playbasis_player_device');
        if(!$results)
        {
            $this->mongo_db->insert('playbasis_player_device', array(

                'pb_player_id' => new MongoId($data['player_id']),
                'site_id' => new MongoId($data['site_id']),
                'client_id' => new MongoId($data['site_id']),
                'udid' => $data['udid'],
                'device_token' => $data['device_token'],
                'device_description' => $data['device_description'],
                'device_name' => $data['device_name'],
                'status' => true,
                'date_added' => $mongoDate,
                'date_modified' => $mongoDate,

            ));
        }
        else{

            $this->set_site_mongodb($site_id);
            $this->mongo_db->where(array(
                'pb_player_id' => new MongoId($data['player_id']),
                'site_id' => new MongoId($data['site_id']),
                'client_id' => $data['client_id'],
                'udid' => $data['udid']
            ));
            $this->mongo_db->set('device_token',$data['device_token']);
            $this->mongo_db->set('device_description',$data['device_description']);
            $this->mongo_db->set('date_modified',$mongoDate);
            $this->mongo_db->update('playbasis_player_device');


        }

    }
}

function index_id($obj) {
    return $obj['_id'];
}
?>
