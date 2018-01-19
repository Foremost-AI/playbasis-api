<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . '/libraries/REST2_Controller.php';

class Language extends REST2_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('language_model');
        $this->load->model('tool/error', 'error');
        $this->load->model('tool/respond', 'resp');
    }

    public function list_get()
    {
        $location_info = $this->language_model->getLanguage($this->client_id, $this->site_id);
        
        array_walk_recursive($location_info, array($this, "convert_mongo_object"));

        $this->response($this->resp->setRespond($location_info), 200);
    }



    private function convert_mongo_object(&$item, $key)
    {
        if (is_object($item)) {
            if (get_class($item) === 'MongoId') {
                $item = $item->{'$id'};
            } else {
                if (get_class($item) === 'MongoDate') {
                    $item = datetimeMongotoReadable($item);
                }
            }
        }
    }
}
