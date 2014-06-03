<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Reward_model extends MY_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->load->library('memcached_library');
		$this->load->helper('memcache');
		$this->load->library('mongo_db');
	}
	public function listRewards($data)
	{
		$this->set_site_mongodb($data['site_id']);
		$this->mongo_db->select(array('name'));
		$this->mongo_db->select(array(),array('_id'));
		$this->mongo_db->where(array(
			'client_id' => $data['client_id'],
			'site_id' => $data['site_id'],
			'status' => true
		));
		$result = $this->mongo_db->get('playbasis_reward_to_client');
		if (!$result) $result = array();
		return $result;
	}
    public function getRewardName($data, $reward_id)
    {
        $this->set_site_mongodb($data['site_id']);
        $this->mongo_db->select(array('name'));
        $this->mongo_db->where(array(
            'client_id' => $data['client_id'],
            'site_id' => $data['site_id'],
            'reward_id' => $reward_id
        ));
        $result = $this->mongo_db->get('playbasis_reward_to_client');
        return $result ? $result[0]['name'] : array();
    }
	public function findByName($data, $reward_name)
	{
		$this->set_site_mongodb($data['site_id']);
		$this->mongo_db->select(array('reward_id'));
		$this->mongo_db->where(array(
			'client_id' => $data['client_id'],
			'site_id' => $data['site_id'],
			'name' => $reward_name
		));
		$result = $this->mongo_db->get('playbasis_reward_to_client');
		return $result ? $result[0]['reward_id'] : array();
	}
	public function rewardLog($data, $reward_name, $from=null, $to=null)
	{
		$reward_id = $this->findByName($data, $reward_name);
		$this->set_site_mongodb($data['site_id']);
		$map = new MongoCode("function() { this.date_added.setTime(this.date_added.getTime()-(-7*60*60*1000)); emit(this.date_added.getFullYear()+'-'+('0'+(this.date_added.getMonth()+1)).slice(-2)+'-'+('0'+this.date_added.getDate()).slice(-2), 1); }");
		$reduce = new MongoCode("function(key, values) { return Array.sum(values); }");
		$query = array('client_id' => $data['client_id'], 'site_id' => $data['site_id'], 'reward_id' => $reward_id);
		if ($from || $to) $query['date_added'] = array();
		if ($from) $query['date_added']['$gte'] = $this->new_mongo_date($from);
		if ($to) $query['date_added']['$lte'] = $this->new_mongo_date($to, '23:59:59');
		$this->mongo_db->command(array(
			'mapReduce' => 'playbasis_reward_to_player',
			'map' => $map,
			'reduce' => $reduce,
			'query' => $query,
			'out' => 'mapreduce_reward_log',
		));
		$result = $this->mongo_db->get('mapreduce_reward_log');
		if (!$result) $result = array();
		if ($from && (!isset($result[0]['_id']) || $result[0]['_id'] != $from)) array_unshift($result, array('_id' => $from, 'value' => 0));
		if ($to && (!isset($result[count($result)-1]['_id']) || $result[count($result)-1]['_id'] != $to)) array_push($result, array('_id' => $to, 'value' => 0));
		return $result;
	}
	public function badgeLog($data, $badge_id, $from=null, $to=null)
	{
		if (!($badge_id instanceof MongoId)) {
			$badge_id = new MongoId($badge_id);
		}
		$this->set_site_mongodb($data['site_id']);
		$map = new MongoCode("function() { this.date_added.setTime(this.date_added.getTime()-(-7*60*60*1000)); emit(this.date_added.getFullYear()+'-'+('0'+(this.date_added.getMonth()+1)).slice(-2)+'-'+('0'+this.date_added.getDate()).slice(-2), this.value); }");
		$reduce = new MongoCode("function(key, values) { return Array.sum(values); }");
		$query = array('client_id' => $data['client_id'], 'site_id' => $data['site_id'], 'badge_id' => $badge_id);
		if ($from || $to) $query['date_added'] = array();
		if ($from) $query['date_added']['$gte'] = $this->new_mongo_date($from);
		if ($to) $query['date_added']['$lte'] = $this->new_mongo_date($to, '23:59:59');
		$this->mongo_db->command(array(
			'mapReduce' => 'playbasis_reward_to_player',
			'map' => $map,
			'reduce' => $reduce,
			'query' => $query,
			'out' => 'mapreduce_badge_log',
		));
		$result = $this->mongo_db->get('mapreduce_badge_log');
		if (!$result) $result = array();
		if ($from && (!isset($result[0]['_id']) || $result[0]['_id'] != $from)) array_unshift($result, array('_id' => $from, 'value' => 'SKIP'));
		if ($to && (!isset($result[count($result)-1]['_id']) || $result[count($result)-1]['_id'] != $to)) array_push($result, array('_id' => $to, 'value' => 'SKIP'));
		return $result;
	}
	public function levelupLog($data, $from=null, $to=null)
	{
		$this->set_site_mongodb($data['site_id']);
		$map = new MongoCode("function() { this.date_added.setTime(this.date_added.getTime()-(-7*60*60*1000)); emit(this.date_added.getFullYear()+'-'+('0'+(this.date_added.getMonth()+1)).slice(-2)+'-'+('0'+this.date_added.getDate()).slice(-2), 1); }");
		$reduce = new MongoCode("function(key, values) { return Array.sum(values); }");
		$query = array('client_id' => $data['client_id'], 'site_id' => $data['site_id'], 'event_type' => 'LEVEL');
		if ($from || $to) $query['date_added'] = array();
		if ($from) $query['date_added']['$gte'] = $this->new_mongo_date($from);
		if ($to) $query['date_added']['$lte'] = $this->new_mongo_date($to, '23:59:59');
		$this->mongo_db->command(array(
			'mapReduce' => 'playbasis_event_log',
			'map' => $map,
			'reduce' => $reduce,
			'query' => $query,
			'out' => 'mapreduce_levelup_log',
		));
		$result = $this->mongo_db->get('mapreduce_levelup_log');
		if (!$result) $result = array();
		if ($from && (!isset($result[0]['_id']) || $result[0]['_id'] != $from)) array_unshift($result, array('_id' => $from, 'value' => 0));
		if ($to && (!isset($result[count($result)-1]['_id']) || $result[count($result)-1]['_id'] != $to)) array_push($result, array('_id' => $to, 'value' => 0));
		return $result;
	}
}
?>