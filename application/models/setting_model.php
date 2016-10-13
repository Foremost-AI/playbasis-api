<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Setting_model extends MY_Model
{
    public function retrieveSetting($client_id, $site_id)
    {
        $this->set_site_mongodb($site_id);

        $this->mongo_db->where('client_id', new MongoId($client_id));
        $this->mongo_db->where('site_id', new MongoId($site_id));
        $results = $this->mongo_db->get("playbasis_setting");
        $results = $results ? $results[0] : null;

        if ($results['password_policy_enable'] == false) {
            unset($results['password_policy']);
        }
        return $results;
    }

    public function appStatus($client_id, $site_id)
    {
        $this->set_site_mongodb($site_id);

        $this->mongo_db->where('client_id', new MongoId($client_id));
        $this->mongo_db->where('site_id', new MongoId($site_id));
        $this->mongo_db->where('app_enable', true);
        $this->mongo_db->where(array('$or' => array(array('app_period' => null) , array('$and' => array(array('app_period.date_start' => array('$lt' => new MongoDate())), array('app_period.date_end' => array('$gt' => new MongoDate())))))));
        $results = $this->mongo_db->get("playbasis_setting");

        return $results ? true : false;
    }
}