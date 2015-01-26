<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Quest_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->config->load('playbasis');
    }
    public function getQuest($data, $test=NULL)
    {
        //get quest
        $this->set_site_mongodb($data['site_id']);

        $criteria = array(
            'client_id' => $data['client_id'],
            'site_id' => $data['site_id'],
            '_id' => $data['quest_id'],
        );

        if (!$test)
            $criteria["status"] = true;

        $this->mongo_db->where($criteria);
        $this->mongo_db->where_ne('deleted', true);

        $result = $this->mongo_db->get('playbasis_quest_to_client');

        $result = $result ? $result[0] : array();

        array_walk_recursive($result, array($this, "change_image_path"));
        return $result;
    }
    public function getQuests($data)
    {
        // get all quests related to client
        $this->set_site_mongodb($data['site_id']);

        $this->mongo_db->where(array(
            'client_id' => $data['client_id'],
            'site_id' => $data['site_id'],
            'status' => true
        ));
        $this->mongo_db->where_ne('deleted', true);
        $result = $this->mongo_db->get('playbasis_quest_to_client');

        array_walk_recursive($result, array($this, "change_image_path"));
        return $result;
    }
    public function getMission($data)
    {
        //get mission
        $this->set_site_mongodb($data['site_id']);

        $this->mongo_db->select(array('missions.$'));
        $this->mongo_db->where(array(
            'client_id' => $data['client_id'],
            'site_id' => $data['site_id'],
            '_id' => $data['quest_id'],
            'missions.mission_id' => $data['mission_id'],
            'status' => true
        ));
        $this->mongo_db->where_ne('deleted', true);
        $result = $this->mongo_db->get('playbasis_quest_to_client');

        $result = $result ? $result[0] : array();

        array_walk_recursive($result, array($this, "change_image_path"));
        return $result;
    }

    public function joinQuest($data)
    {
        $this->load->helper('vsort');

        $this->set_site_mongodb($data["site_id"]);

        //log_message('debug', 'MISSION : player = '.$data["pb_player_id"].' quest id = '.$data["quest_id"].' before sort : '.print_r($data["missions"], true));
        file_put_contents('/usr/share/nginx/html/api/application/logs/missions.log', 'player = '.$data["pb_player_id"].' quest id = '.$data["quest_id"].' before sort : '.print_r($data["missions"], true), FILE_APPEND | LOCK_EX);

        $data["missions"] = vsort($data["missions"], "mission_number");

        //log_message('debug', 'MISSION : player = '.$data["pb_player_id"].' quest id = '.$data["quest_id"].' after sort : '.print_r($data["missions"], true));
        file_put_contents('/usr/share/nginx/html/api/application/logs/missions.log', 'player = '.$data["pb_player_id"].' quest id = '.$data["quest_id"].' after sort : '.print_r($data["missions"], true), FILE_APPEND | LOCK_EX);

        $first = true;
        foreach($data["missions"] as &$m){
            if($data["mission_order"]){
                if($first){
                    $m["status"] = "join";
                    $first = false;
                }else{
                    $m["status"] = "unjoin";
                }
            }else{
                $m["status"] = "join";
            }
            $m["date_modified"] = new MongoDate(time());
        }

        //log_message('debug', 'MISSION : player = '.$data["pb_player_id"].' quest id = '.$data["quest_id"].' update status : '.print_r($data["missions"], true));
        file_put_contents('/usr/share/nginx/html/api/application/logs/missions.log', 'player = '.$data["pb_player_id"].' quest id = '.$data["quest_id"].' update status : '.print_r($data["missions"], true), FILE_APPEND | LOCK_EX);

        $this->mongo_db->insert("playbasis_quest_to_player", array(
            "client_id" => $data["client_id"],
            "site_id" => $data["site_id"],
            "date_added" => new MongoDate(time()),
            "date_modified" => new MongoDate(time()),
            "missions" => $data["missions"],
            "pb_player_id" => $data["pb_player_id"],
            "quest_id" => $data["quest_id"],
            "status" => "join"
        ));
    }

    public function getPlayerQuest($data) {
        $this->set_site_mongodb($data["site_id"]);

        $this->mongo_db->where(array(
            "pb_player_id" => $data["pb_player_id"],
            "quest_id" => $data["quest_id"]
        ));
        if(isset($data['status'])){
            $this->mongo_db->where_in('status', $data['status']);
        }
        $this->mongo_db->where_ne('deleted', true);
        $result = $this->mongo_db->get('playbasis_quest_to_player');

        $result = $result ? $result[0] : array();

        array_walk_recursive($result, array($this, "change_image_path"));
        return $result;
    }

    public function getPlayerQuests($data) {
        $this->set_site_mongodb($data["site_id"]);

        $this->mongo_db->where(array(
            "pb_player_id" => $data["pb_player_id"]
        ));
        if(isset($data['status'])){
            $this->mongo_db->where_in('status', $data['status']);
        }
        $this->mongo_db->where_ne('deleted', true);
        $result = $this->mongo_db->get('playbasis_quest_to_player');

        array_walk_recursive($result, array($this, "change_image_path"));

        return $result;
    }

    public function updateQuestStatus($data, $status){
        $this->set_site_mongodb($data['site_id']);

        $this->mongo_db->where(array(
            'site_id' => $data['site_id'],
            'pb_player_id' => $data['pb_player_id'],
            'quest_id' => $data['quest_id']
        ));
        $this->mongo_db->where_ne('deleted', true);
        $this->mongo_db->set(array('status' => $status));
        $this->mongo_db->set(array('date_modified' => new MongoDate(time())));
        $this->mongo_db->update('playbasis_quest_to_player');
    }
    public function updateMissionStatus($data, $status){
        $this->set_site_mongodb($data['site_id']);

        $this->mongo_db->where(array(
            'site_id' => $data['site_id'],
            'pb_player_id' => $data['pb_player_id'],
            'quest_id' => $data['quest_id'],
            'missions.mission_id' => $data['mission_id'],
        ));
        $this->mongo_db->where_ne('deleted', true);
        $qp = $this->mongo_db->get('playbasis_quest_to_player');

        if($qp && isset($qp[0])){
            $this->mongo_db->where(array(
                'site_id' => $data['site_id'],
                'pb_player_id' => $data['pb_player_id'],
                'missions.mission_id' => $data['mission_id'],
            ));
            $this->mongo_db->where_ne('deleted', true);
            $this->mongo_db->set(array('missions.$.status' => $status));
            $this->mongo_db->set(array('missions.$.date_modified' => new MongoDate(time())));
            $this->mongo_db->update('playbasis_quest_to_player');
        }
    }

    public function getQuestName($client_id, $site_id, $quest_id) {
        $this->mongo_db->where(array(
            '_id' => $quest_id,
        ));
        $records = $this->mongo_db->get('playbasis_quest_to_client');
        return $records ? $records[0]['quest_name'] : null;
    }

    public function getRewardHistoryFromPlayerID($client_id, $site_id, $pb_player_id, $offset, $limit) {
        $this->set_site_mongodb($site_id);
        $this->mongo_db->select(array(),array('_id','client_id','site_id','pb_player_id'));
        $this->mongo_db->where(array(
            'client_id' => $client_id,
            'site_id' => $site_id,
            'pb_player_id' => $pb_player_id,
        ));
        $this->mongo_db->limit((int)$limit);
        $this->mongo_db->offset((int)$offset);
        $records = $this->mongo_db->get('playbasis_quest_reward_log');
        if (!$records) $records = array();
        foreach ($records as &$record) {
            $record['quest_name'] = $this->getQuestName($client_id, $site_id, $record['quest_id']);
            $record['type'] = $record['mission_id'] === null ? 'quest' : 'mission';
        }
        return $records;
    }

    public function insertQuestUsage($client_id, $site_id, $quest_id, $mission_id, $pb_player_id) {
        $this->set_site_mongodb($site_id);
        $mongoDate = new MongoDate(time());
        return $this->mongo_db->insert('playbasis_quest_log', array(
            'client_id' => $client_id,
            'site_id' => $site_id,
            'quest_id' => $quest_id,
            'mission_id' => $mission_id,
            'pb_player_id' => $pb_player_id,
            'date_added' => $mongoDate,
            'date_modified' => $mongoDate,
        ), array("w" => 0, "j" => false));
    }

    private function change_image_path(&$item, $key)
    {
        if($key === "image"){
            if(!empty($item)){
                $item = $this->config->item('IMG_PATH').$item;
            }else{
                $item = $this->config->item('IMG_PATH')."no_image.jpg";
            }

        }
    }
}
?>
