<?php
defined('BASEPATH') OR exit('No direct script access allowed');
define("CUSTOM_POINT_START_ID", 10000);
class Client_model extends MY_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->config->load('playbasis');
        $this->load->library('memcached_library');
		$this->load->helper('memcache');
	}
	public function getRuleSet($clientData)
	{
		assert($clientData);
		assert(is_array($clientData));
		assert(isset($clientData['client_id']));
		assert(isset($clientData['site_id']));
		$this->set_site_mongodb($clientData['site_id']);
		$this->mongo_db->select(array('jigsaw_set'));
		$this->mongo_db->where($clientData);
		return $this->mongo_db->get('playbasis_rule');
	}
    public function getAction($clientData)
    {
        assert($clientData);
        assert(is_array($clientData));
        assert(isset($clientData['client_id']));
        assert(isset($clientData['site_id']));
        assert(isset($clientData['action_name']));
        $this->set_site_mongodb($clientData['site_id']);
        $this->mongo_db->select(array('action_id'));
        $this->mongo_db->where(array(
            'client_id' => $clientData['client_id'],
            'site_id' => $clientData['site_id'],
            'name' => $clientData['action_name']
        ));
        $id = $this->mongo_db->get('playbasis_action_to_client');
        return ($id && $id[0]) ? $id[0] : array();
    }
	public function getActionId($clientData)
	{
		assert($clientData);
		assert(is_array($clientData));
		assert(isset($clientData['client_id']));
		assert(isset($clientData['site_id']));
		assert(isset($clientData['action_name']));
		$this->set_site_mongodb($clientData['site_id']);
		$this->mongo_db->select(array('action_id'));
		$this->mongo_db->where(array(
			'client_id' => $clientData['client_id'],
			'site_id' => $clientData['site_id'],
			'name' => $clientData['action_name']
		));
		$id = $this->mongo_db->get('playbasis_action_to_client');
		return ($id && $id[0]) ? $id[0]['action_id'] : 0;
	}
    /*
     * get RuleSet by _id from plybasis_rule
     * @param string | MongoId $id
     * @return array
     */
    public function getRuleSetById($id)
    {
        try {
            $id = new MongoId($id);
        } catch (Exception $e) {
            return array();
        }

        $this->mongo_db->select(array("_id", "name", "jigsaw_set"));
        $rules = $this->mongo_db->get_where(
            "playbasis_rule",
            array("_id" => $id));

        if ($rules) {
            foreach ($rules as &$rule) {
                $rule["rule_id"] = $rule["_id"];
                unset($rule["_id"]);
            }
        }
        return $rules;
    }
	public function getRuleSetByActionId($clientData)
	{
		assert($clientData);
		assert(is_array($clientData));
		assert(isset($clientData['client_id']));
		assert(isset($clientData['site_id']));
		assert(isset($clientData['action_id']));
		$clientData['active_status'] = true;
		$this->set_site_mongodb($clientData['site_id']);
		$this->mongo_db->select(array(
			'_id',
			'name',
			'jigsaw_set'
		));
		$this->mongo_db->where($clientData);
		$rules = $this->mongo_db->get('playbasis_rule');
		foreach($rules as &$rule)
		{
			$rule['rule_id'] = $rule['_id'];
			unset($rule['_id']);
		}
		return $rules;
	}
	public function getJigsawProcessor($jigsawId, $site_id)
	{
		assert($jigsawId);
		$this->set_site_mongodb($site_id);
		$this->mongo_db->select(array('class_path'));
		$this->mongo_db->where(array(
			'jigsaw_id' => $jigsawId
		));
		$jigsawProcessor = $this->mongo_db->get('playbasis_game_jigsaw_to_client');
        if($jigsawProcessor){
            assert($jigsawProcessor);
            return $jigsawProcessor[0]['class_path'];
        }else{
            return null;
        }
	}
	public function updatePlayerPointReward($rewardId, $quantity, $pbPlayerId, $clPlayerId, $clientId, $siteId, $overrideOldValue = FALSE)
	{
		assert(isset($rewardId));
		assert(isset($siteId));
		assert(isset($quantity));
		assert(isset($pbPlayerId));
		$this->set_site_mongodb($siteId);
		$this->mongo_db->where(array(
			'pb_player_id' => $pbPlayerId,
			'reward_id' => $rewardId
		));
		$hasReward = $this->mongo_db->count('playbasis_reward_to_player');
		if($hasReward)
		{
			$this->mongo_db->where(array(
				'pb_player_id' => $pbPlayerId,
				'reward_id' => $rewardId
			));
			$this->mongo_db->set('date_modified', new MongoDate(time()));
			if($overrideOldValue)
				$this->mongo_db->set('value', intval($quantity));
			else
				$this->mongo_db->inc('value', intval($quantity));
			$this->mongo_db->update('playbasis_reward_to_player');
		}
		else
		{
			$mongoDate = new MongoDate(time());
			$this->mongo_db->insert('playbasis_reward_to_player', array(
				'pb_player_id' => $pbPlayerId,
				'cl_player_id' => $clPlayerId,
				'client_id' => $clientId,
				'site_id' => $siteId,
				'reward_id' => $rewardId,
				'value' => intval($quantity),
				'date_added' => $mongoDate,
				'date_modified' => $mongoDate
			));
		}
		//upadte client reward limit
		$this->mongo_db->select(array('limit'));
		$this->mongo_db->where(array(
			'reward_id' => $rewardId,
			'site_id' => $siteId
		));
		$result = $this->mongo_db->get('playbasis_reward_to_client');
		assert($result);
		$result = $result[0];
		if(is_null($result['limit']))
			return;
		$this->mongo_db->where(array(
			'reward_id' => $rewardId,
			'site_id' => $siteId
		));
		$this->mongo_db->dec('limit', intval($quantity));
		$this->mongo_db->update('playbasis_reward_to_client');
	}
	public function updateCustomReward($rewardName, $quantity, $input, &$jigsawConfig)
	{
		//get reward id
		$this->set_site_mongodb($input['site_id']);
		$this->mongo_db->select(array('reward_id'));
		$this->mongo_db->where(array(
			'client_id' => $input['client_id'],
			'site_id' => $input['site_id'],
			'name' => strtolower($rewardName)
		));
		$result = $this->mongo_db->get('playbasis_reward_to_client');
		$customRewardId = null;
		if($result && $result[0])
		{
			$result = $result[0];
			$customRewardId = isset($result['reward_id']) ? $result['reward_id'] : null;
		}
		if(!$customRewardId)
		{
            return 0;
			//reward does not exist, add new custom point where id is max(reward_id)+1
			//$this->mongo_db->select(array('reward_id'));
			//$this->mongo_db->where(array(
			//	'client_id' => $input['client_id'],
			//	'site_id' => $input['site_id']
			//));
			//$this->mongo_db->order_by(array('reward_id' => 'desc'));
			//$result = $this->mongo_db->get('playbasis_reward_to_client');
			//$result = $result[0];
			//$customRewardId = $result['reward_id'] + 1;
			//if($customRewardId < CUSTOM_POINT_START_ID)
			//	$customRewardId = CUSTOM_POINT_START_ID;

            /*$field1 = array(
                'field_type' => 'read_only',
                'label' => 'Name',
                'param_name' => 'reward_name',
                'placeholder' => '',
                'sortOrder' => '0',
                'value' => strtolower($rewardName)
            );

            $field2 = array(
                'field_type' => 'hidden',
                'label' => '',
                'param_name' => 'item_id',
                'placeholder' => '',
                'sortOrder' => '0',
                'value' => ''
            );

            $field3 = array(
                'field_type' => 'number',
                'label' => strtolower($rewardName),
                'param_name' => 'quantity',
                'placeholder' => 'How many ...',
                'sortOrder' => '0',
                'value' => '0'
            );

            $init_dataset = array($field1,$field2,$field3);

			$customRewardId = new MongoId();
			$mongoDate = new MongoDate(time());
			$this->mongo_db->insert('playbasis_reward_to_client', array(
				'reward_id' => $customRewardId,
				'client_id' => $input['client_id'],
				'site_id' => $input['site_id'],
				'group' => 'POINT',
				'name' => strtolower($rewardName),
				'limit' => null,
				'description' => null,
                'init_dataset' => $init_dataset,
				'sort_order' => 1,
				'status' => true,
				'is_custom' => true,
				'date_added' => $mongoDate,
				'date_modified' => $mongoDate
			));*/
		}
		$level = 0;
		if($rewardName == 'exp')
		{
			$level = $this->updateExpAndLevel($quantity, $input['pb_player_id'], $input['player_id'], array(
				'client_id' => $input['client_id'],
				'site_id' => $input['site_id']
			));
		}
		else
		{
			//update player reward
			$this->updatePlayerPointReward($customRewardId, $quantity, $input['pb_player_id'], $input['player_id'], $input['client_id'], $input['site_id']);
		}
		$jigsawConfig['reward_id'] = $customRewardId;
		$jigsawConfig['reward_name'] = $rewardName;
		$jigsawConfig['quantity'] = $quantity;
		return $level;
	}
	public function updateplayerBadge($badgeId, $quantity, $pbPlayerId, $clPlayerId, $client_id, $site_id)
	{
		assert(isset($badgeId));
		assert(isset($quantity));
		assert(isset($pbPlayerId));
		//update badge master table
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
			'badge_id' => $badgeId,
			'deleted' => false
		));
		$result = $this->mongo_db->get('playbasis_badge_to_client');
		if(!$result)
			return;
		$badgeInfo = $result[0];
		$mongoDate = new MongoDate(time());
		if(isset($badgeInfo['substract']) && $badgeInfo['substract'])
		{
			$remainingQuantity = (int)$badgeInfo['quantity'] - (int)$quantity;
			if($remainingQuantity < 0)
			{
				$remainingQuantity = 0;
				$quantity = $badgeInfo['quantity'];
			}
			$this->mongo_db->set('quantity', $remainingQuantity);
			$this->mongo_db->set('date_modified', $mongoDate);
            $this->mongo_db->where('client_id', $client_id);
            $this->mongo_db->where('site_id', $site_id);
			$this->mongo_db->where('badge_id', $badgeId);
			$this->mongo_db->update('playbasis_badge_to_client');
		}
		//update player badge table
		$this->mongo_db->where(array(
			'pb_player_id' => $pbPlayerId,
			'badge_id' => $badgeId
		));
		$hasBadge = $this->mongo_db->count('playbasis_reward_to_player');
		if($hasBadge)
		{
			$this->mongo_db->where(array(
				'pb_player_id' => $pbPlayerId,
				'badge_id' => $badgeId
			));
			$this->mongo_db->set('date_modified', $mongoDate);
            if(isset($badgeInfo['claim']) && $badgeInfo['claim'])
            {
                $this->mongo_db->inc('claimed', intval($quantity));
            }else{
                $this->mongo_db->inc('value', intval($quantity));
            }
			$this->mongo_db->update('playbasis_reward_to_player');
		}
		else
		{
            $data = array(
                'pb_player_id' => $pbPlayerId,
                'cl_player_id' => $clPlayerId,
                'client_id' => $client_id,
                'site_id' => $site_id,
                'badge_id' => $badgeId,
                'redeemed' => 0,
                'date_added' => $mongoDate,
                'date_modified' => $mongoDate
            );
            if(isset($badgeInfo['claim']) && $badgeInfo['claim'])
            {
                $data['value'] = 0;
                $data['claimed'] = intval($quantity);
            }else{
                $data['value'] = intval($quantity);
                $data['claimed'] = 0;
            }
			$this->mongo_db->insert('playbasis_reward_to_player', $data);
		}
	}
	public function updateExpAndLevel($exp, $pb_player_id, $cl_player_id, $clientData)
	{
		assert($exp);
		assert($pb_player_id);
		assert($clientData);
		assert(is_array($clientData));
		assert(isset($clientData['client_id']));
		assert(isset($clientData['site_id']));
		//get player exp
		$this->set_site_mongodb($clientData['site_id']);
		$this->mongo_db->select(array(
			'exp',
			'level'
		));
		$this->mongo_db->where('_id', $pb_player_id);
		$result = $this->mongo_db->get('playbasis_player');
		$playerExp = $result[0]['exp'];
		$playerLevel = $result[0]['level'];
		$newExp = $exp + $playerExp;
		//check if client have their own exp table setup
		$this->mongo_db->select(array('level'));
		$this->mongo_db->where($clientData);
		$this->mongo_db->where_lte('exp', intval($newExp));
		$this->mongo_db->order_by(array('level' => 'desc'));
		$level = $this->mongo_db->get('playbasis_client_exp_table');
		if(!$level || !$level[0] || !$level[0]['level'])
		{
			//get level from default exp table instead
			$this->mongo_db->select(array('level'));
			$this->mongo_db->where_lte('exp', intval($newExp));
			$this->mongo_db->order_by(array('level' => 'desc'));
			$level = $this->mongo_db->get('playbasis_exp_table');
			assert($level);
		}
		$level = $level[0];
		if($level['level'] && $level['level'] > $playerLevel)
			$level = $level['level'];
		else
			$level = -1;
		$this->mongo_db->where('_id', $pb_player_id);
		$this->mongo_db->set('date_modified', new MongoDate(time()));
		$this->mongo_db->inc('exp', intval($exp));
		if($level > 0)
			$this->mongo_db->set('level', intval($level));
		$this->mongo_db->update('playbasis_player');
		//get reward id to update the reward to player table
		$this->mongo_db->select(array('_id'));
		$this->mongo_db->where('name', 'exp');
		$result = $this->mongo_db->get('playbasis_reward');
		assert($result);
		$result = $result[0];
		$this->updatePlayerPointReward($result['_id'], $newExp, $pb_player_id, $cl_player_id, $clientData['client_id'], $clientData['site_id'], TRUE);
		return $level;
	}
	public function log($logData, $jigsawOptionData = array())
	{
		assert($logData);
		assert(is_array($logData));
		assert($logData['pb_player_id']);
		assert($logData['action_id']);
		assert(is_string($logData['action_name']));
		assert($logData['client_id']);
		assert($logData['site_id']);
		assert(is_string($logData['domain_name']));
		if(isset($logData['input']))
//			$logData['input'] = serialize(array_merge($logData['input'], $jigsawOptionData));
			$logData['input'] = array_merge($logData['input'], $jigsawOptionData);
		else
			$logData['input'] = 'NO-INPUT';
		$this->set_site_mongodb($logData['site_id']);
		$mongoDate = new MongoDate(time());
		$this->mongo_db->insert('jigsaw_log', array(
			'pb_player_id'	  =>		$logData['pb_player_id'],
			'input'			  =>		$logData['input'],
			'client_id'		  =>		$logData['client_id'],
			'site_id'		  =>		$logData['site_id'],
			'domain_name'	  =>		$logData['domain_name'],
			'action_id'		  => (isset($logData['action_id']))		  ? $logData['action_id']		: 0,
			'action_name'	  => (isset($logData['action_name']))	  ? $logData['action_name']		: '',
			'rule_id'		  => (isset($logData['rule_id']))		  ? $logData['rule_id']			: 0,
			'rule_name'		  => (isset($logData['rule_name']))		  ? $logData['rule_name']		: '',
			'jigsaw_id'		  => (isset($logData['jigsaw_id']))		  ? $logData['jigsaw_id']		: 0,
			'jigsaw_name'	  => (isset($logData['jigsaw_name']))	  ? $logData['jigsaw_name']	    : '',
			'jigsaw_category' => (isset($logData['jigsaw_category'])) ? $logData['jigsaw_category'] : '',
			'jigsaw_index'	  => (isset($logData['jigsaw_index']))	  ? $logData['jigsaw_index']	: '',
			'site_name'		  => (isset($logData['site_name']))		  ? $logData['site_name']		: '',
			'date_added'	  => $mongoDate,
			'date_modified'	  => $mongoDate
		));
	}
	public function getBadgeById($badgeId, $site_id)
	{
		$this->set_site_mongodb($site_id);
		$this->mongo_db->select(array(
            'badge_id',
			'name',
			'description',
			'image',
			'hint',
            'claim',
            'redeem'
		));
        $this->mongo_db->select(array(),array('_id'));
		$this->mongo_db->where(array(
            'site_id' => $site_id,
            'badge_id' => $badgeId,
			'deleted' => false
		));
		$badge = $this->mongo_db->get('playbasis_badge_to_client');
		if(!$badge)
			return null;
		$badge = $badge[0];
        $badge['badge_id'] = $badge['badge_id']."";
		$badge['image'] = $this->config->item('IMG_PATH') . $badge['image'];
		return $badge;
	}
    public function updateplayerGoods($goodsId, $quantity, $pbPlayerId, $clPlayerId, $client_id, $site_id)
    {
        assert(isset($goodsId));
        assert(isset($quantity));
        assert(isset($pbPlayerId));
        //update badge master table
        $this->set_site_mongodb($site_id);
        $this->mongo_db->select(array(
            'quantity'
        ));
        $this->mongo_db->where(array(
            'client_id' => $client_id,
            'site_id' => $site_id,
            'goods_id' => $goodsId,
            'deleted' => false
        ));
        $result = $this->mongo_db->get('playbasis_goods_to_client');
        if(!$result)
            return;
        $goodsInfo = $result[0];
        $mongoDate = new MongoDate(time());

        if (!is_null($goodsInfo['quantity'])){
        	$remainingQuantity = $goodsInfo['quantity'] - $quantity;
        }else{
        	$remainingQuantity = null;
        }

        /*
        if($remainingQuantity < 0)
        {
            $remainingQuantity = 0;
            $quantity = $goodsInfo['quantity'];
        }
        */

        // NEW -->
        if(is_null($remainingQuantity)){
        	$remainingQuantity = null;
        	// $quantity = $goodsInfo['quantity'];
        }elseif($remainingQuantity < 0){
            $remainingQuantity = 0;
            $quantity = $goodsInfo['quantity'];
        }
        // END NEW -->
        $this->mongo_db->set('quantity', $remainingQuantity);
        $this->mongo_db->set('date_modified', $mongoDate);
        $this->mongo_db->where('client_id', $client_id);
        $this->mongo_db->where('site_id', $site_id);
        $this->mongo_db->where('goods_id', $goodsId);
        $this->mongo_db->update('playbasis_goods_to_client');

        //update player badge table
        $this->mongo_db->where(array(
            'pb_player_id' => $pbPlayerId,
            'goods_id' => $goodsId
        ));
        $hasBadge = $this->mongo_db->count('playbasis_goods_to_player');
        if($hasBadge)
        {
            $this->mongo_db->where(array(
                'pb_player_id' => $pbPlayerId,
                'goods_id' => $goodsId
            ));
            $this->mongo_db->set('date_modified', $mongoDate);
            $this->mongo_db->inc('value', intval($quantity));
            $this->mongo_db->update('playbasis_goods_to_player');
        }
        else
        {
            $this->mongo_db->insert('playbasis_goods_to_player', array(
                'pb_player_id' => $pbPlayerId,
                'cl_player_id' => $clPlayerId,
                'client_id' => $client_id,
                'site_id' => $site_id,
                'goods_id' => $goodsId,
                'value' => intval($quantity),
                'date_added' => $mongoDate,
                'date_modified' => $mongoDate
            ));
        }
    }

    public function getRewardName($input)
    {
        $this->set_site_mongodb($input['site_id']);
        $this->mongo_db->select(array('name'));
        $this->mongo_db->where(array(
            'client_id' => $input['client_id'],
            'site_id' => $input['site_id'],
            'reward_id' => new MongoId($input['reward_id'])
        ));
        $result = $this->mongo_db->get('playbasis_reward_to_client');

        return $result ? $result[0]['name'] : null;
    }

	public function listSites($client_id = null)
	{
		$this->set_site_mongodb(0);
		$where = array(
			'status' => true,
			'deleted' => false
		);
		if (!empty($client_id)) $where['client_id'] = $client_id;
		$this->mongo_db->where($where);
		return $this->mongo_db->get('playbasis_client_site');
	}

	public function getById($client_id) {
		$this->set_site_mongodb(0);
		$this->mongo_db->where(array(
			'_id' => $client_id,
		));
		$ret = $this->mongo_db->get('playbasis_client');
		return is_array($ret) && count($ret) == 1 ? $ret[0] : $ret;
	}

    public function getPermissionBySiteId($site_id) {
        $this->set_site_mongodb($site_id);

        $this->mongo_db->where('site_id', new MongoID($site_id));

        $this->mongo_db->limit(1);

        $result = $this->mongo_db->get("playbasis_permission");

        return $result ? $result[0]['plan_id'] : null;
    }

    /**
     * Return Permission limitation by Plan ID
     * in particular type and field
     * e.g. notifications email
     * @param site_id string
     * @param plan_id string
     * @param type notifications | requests
     * @param field string
     * @return integer | null
     */
    private function getPlanLimitById($site_id, $plan_id, $type, $field)
    {
        $this->set_site_mongodb($site_id);
        $this->mongo_db->where(array(
            '_id' => $plan_id,
        ));
        $res = $this->mongo_db->get('playbasis_plan');
        if ($res) {
            $res = $res[0];
            $limit = 'limit_'.$type;
            if (isset($res[$limit]) &&
                isset($res[$limit][$field])) {
                    return $res[$limit][$field];
                }
            else { // this plan does not set this limitation
                return null;
            }
        }
        else {
            throw new Exception("PLANID_NOTFOUND");
        }
    }

    /**
     * Return usage of service from client-site
     * in particular type and field
     * e.g. notifications email
     * @param client_id string
     * @param site_id string
     * @param type notifications | requests
     * @param field string
     * @return array('plan_id' => string, 'value' => integer) | null
     */
    private function getPermissionUsage($client_id, $site_id, $type, $field)
    {
        // wrong type
        if ($type != "notifications" && $type != "requests")
            throw new Exception("WRONG_TYPE");

        $this->set_site_mongodb($site_id);

        // Sync current bill usage with Client bill
        // TODO If not sync, Sync date and Reset usage

        // Get current bill usage
        $this->mongo_db->select(
            array(
                "plan_id",
                "usage.". $type. ".". $field)
        );
        $this->mongo_db->where(array(
            'client_id' => $client_id,
            'site_id' => $site_id
        ));
        $res = $this->mongo_db->get('playbasis_permission');

        $result = array();
        if ($res) {
            $res = $res[0];
            $result["plan_id"] = $res["plan_id"];

            // check this limitation on this client-site
            if (isset($res[$type]) &&
                isset($res[$type][$field]))
                    $result["value"] = $res[$type][$field];

            else // this limitation is not found in database
                $result["value"] = 0;

            return $result;
        }
        else { // client-site is not found
            throw new Exception("CLIENTSITE_NOTFOUND");
        }
    }

    /**
     * Update Permission service usage
     * in particular type and field
     * e.g. notifications email
     * @param client_id string
     * @param site_id string
     * @param type notifications | requests
     * @param field string
     */
    private function updatePermission(
        $client_id, $site_id, $type, $field, $inc=1)
    {
        $this->set_site_mongodb($site_id);
        $this->mongo_db->where(array(
            'client_id' => $client_id,
            'site_id' => $site_id
        ));
        $this->mongo_db->inc("usage.". $type. ".". $field, $inc);
        $this->mongo_db->update('playbasis_permission');
    }

    /*
     * Sync Permission billing date with Client billing date
     * @param string @client_id
     * @param string @site_id
     * @param datetime @start_date
     * @param datetime @end_date
     */
    private function syncPermissionDate($client_id, $site_id)
    {
        // TODO
    }

    /*
     * Get plan price if price not set default is 0
     * @param string $plan_id
     * @return int
     */
    private function getPlanPrice($site_id, $plan_id)
    {
        $this->set_site_mongodb($site_id);
        $this->mongo_db->select(array("price"));
        $this->mongo_db->where(array(
            '_id' => $plan_id,
        ));
        $res = $this->mongo_db->get('playbasis_plan');

        if ($res) {
            $res = $res[0];
            if (isset($res["price"]))
                return intval($res["price"]);
            else
                return 0;
        } else {
            return 0;
        }
    }

    /*
     * Check & Update permission usage
     * @param string $client_id
     * @param string $site_id
     * @param (notifications | requests) $type
     * @particular string $field
     */
    public function permissionProcess($client_id, $site_id, $type, $field) {
        // TODO
        // get current usage
        $usage = $this->getPermissionUsage(
            $client_id, $site_id, $type, $field);

        // get limit by plan
        $limit = $this->getPlanLimitById(
            $site_id, $usage["plan_id"], $type, $field);

        // compare
        if ($limit && $usage["value"] >= $limit) {
            // no permission to use this service
            throw new Exception("LIMIT_EXCEED");
        } else {  // increase service usage
            $this->updatePermission($client_id, $site_id, $type, $field);
        }
    }
}
?>
