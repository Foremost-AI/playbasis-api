<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . '/libraries/REST2_Controller.php';

class Campaign extends REST2_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('campaign_model');
        $this->load->model('player_model');
        $this->load->model('tool/error', 'error');
        $this->load->model('tool/respond', 'resp');
    }

    /**
     * @SWG\Get(
     *     tags={"Campaign"},
     *     path="/Campaign",
     *     description="Get campaign",
     *     @SWG\Parameter(
     *         name="campaign_name",
     *         in="query",
     *         type="string",
     *         description="Name of campaign to retrieve campaign details",
     *         required=false,
     *     ),
     *     @SWG\Parameter(
     *         name="tags",
     *         in="query",
     *         type="string",
     *         description="Specific tag(s) to find",
     *         required=false,
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="OK",
     *     )
     * )
     */
    public function index_get()
    {
        $campaign_name = $this->input->get('campaign_name');
        $tags = $this->input->get('tags') ? explode(',', $this->input->get('tags')) : null;
        $result = $this->campaign_model->getCampaign($this->client_id, $this->site_id, $campaign_name ? $campaign_name: false, $tags);
        foreach ($result as $index => $res){
            unset($result[$index]['_id']);
        }

        array_walk_recursive($result, array($this, "convert_mongo_object_and_optional"));
        $this->response($this->resp->setRespond($result), 200);
    }

    /**
     * @SWG\Get(
     *     tags={"Campaign"},
     *     path="/Campaign/active",
     *     description="Retrieve active campaign",
     *     @SWG\Parameter(
     *         name="tags",
     *         in="query",
     *         type="string",
     *         description="Specific tag(s) to find",
     *         required=false,
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="OK",
     *     )
     * )
     */
    public function activeCampaign_get()
    {
        $tags = $this->input->get('tags') ? explode(',', $this->input->get('tags')) : null;
        $result = $this->campaign_model->getActiveCampaign($this->client_id, $this->site_id, $tags);
        if($result){
            unset($result['_id']);
            array_walk_recursive($result, array($this, "convert_mongo_object_and_optional"));
        }

        $this->response($this->resp->setRespond($result), 200);
    }

    /**
     * Use with array_walk and array_walk_recursive.
     * Recursive iterable items to modify array's value
     * from MongoId to string and MongoDate to readable date
     * @param mixed $item this is reference
     * @param string $key
     */
    private function convert_mongo_object_and_optional(&$item, $key)
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