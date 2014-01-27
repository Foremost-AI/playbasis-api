<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Badge_model extends MY_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->config->load('playbasis');
        $this->load->library('memcached_library');
		$this->load->helper('memcache');
	}
	public function getAllBadges($data)
	{
		//get badge ids
		$this->set_site_mongodb($data['site_id']);
		$this->mongo_db->select(array('badge_id','image','name','description','hint'));
		$this->mongo_db->select(array(),array('_id'));
		$this->mongo_db->where(array(
			'client_id' => $data['client_id'],
			'site_id' => $data['site_id'],
            'deleted' => false
		));
        $badges = $this->mongo_db->get('playbasis_badge_to_client');
        if($badges){
            foreach($badges as &$badge){
                $badge['image'] = $this->config->item('IMG_PATH') . $badge['image'];
            }
        }
		return $badges;
	}
	public function getBadge($data)
	{
		//get badge id
		$this->set_site_mongodb($data['site_id']);
        $this->mongo_db->select(array('badge_id','image','name','description','hint'));
        $this->mongo_db->select(array(),array('_id'));
        $this->mongo_db->where(array(
            'client_id' => $data['client_id'],
            'site_id' => $data['site_id'],
            'badge_id' => $data['badge_id'],
            'deleted' => false
        ));
		$result = $this->mongo_db->get('playbasis_badge_to_client');
        if($result){
            $result[0]['image'] = $this->config->item('IMG_PATH') . $result[0]['image'];
        }
		return $result ? $result[0] : array();
	}
}
?>