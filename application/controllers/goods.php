<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . '/libraries/REST2_Controller.php';

class Goods extends REST2_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('auth_model');
        $this->load->model('goods_model');
        $this->load->model('player_model');
        $this->load->model('store_org_model');
        $this->load->model('tool/utility', 'utility');
        $this->load->model('tool/error', 'error');
        $this->load->model('tool/respond', 'resp');
    }

    public function index_get($goodsId = 0)
    {
        /* process group */

        $org_id_list = array();
        /* find my goods */
        $player_id = $this->input->get('player_id');
        if ($player_id !== false) {
            $pb_player_id = $this->player_model->getPlaybasisId(array(
                'client_id' => $this->client_id,
                'site_id' => $this->site_id,
                'cl_player_id' => $player_id,
            ));
            if (!$pb_player_id) {
                $this->response($this->error->setError('USER_NOT_EXIST'), 200);
            }
            $myGoods = $this->player_model->getGoods($pb_player_id, $this->site_id);
            $m = $this->mapByGoodsId($myGoods);

            $org_list = $this->store_org_model->retrieveNodeByPBPlayerID($this->client_id, $this->site_id,
                $pb_player_id);

            if (is_array($org_list)) {
                foreach ($org_list as $node) {
                    $org_info = $this->store_org_model->getOrgInfoOfNode($this->client_id, $this->site_id,
                        $node['node_id']);
                    $a = array((string)$org_info[0]['organize'] => isset($node['roles']) ? $node['roles'] : array());
                    $org_id_list = array_merge($org_id_list, $a);
                }
            }

        }

        /* main */
        if ($goodsId) // given specified goods_id
        {
            $goods['goods'] = $this->goods_model->getGoods(array_merge($this->validToken, array('goods_id' => new MongoId($goodsId))));

            // return an error if
            // 1. good id is set organize and player_id is not in that organize
            // Or 2. organize role is set and player role is not matched
            if (isset($goods['goods']['organize_id'])) {
                if ((!array_key_exists((string)$goods['goods']['organize_id'], $org_id_list)
                    || ((isset($goods['goods']['organize_role']) && $goods['goods']['organize_role'] != "")
                        && !array_key_exists($goods['goods']['organize_role'],
                            $org_id_list[(string)$goods['goods']['organize_id']])))
                ) {
                    $this->response($this->error->setError('GOODS_NOT_FOUND'), 200);
                }
                $org = $this->store_org_model->retrieveOrganizeById($this->client_id, $this->site_id,
                    $goods['goods']['organize_id']);
                $goods['goods']['organize'] = $org['name'];
                unset($goods['goods']['organize_id']);
            }

            $goods['goods']['is_group'] = array_key_exists('group', $goods['goods']);
            unset($goods['goods']['code']);
            if ($goods['goods']['is_group']) {
                $group = $goods['goods']['group'];
                $goods['goods']['name'] = $group;
                $goods['goods']['quantity'] = $this->goods_model->checkGoodsGroupQuantity($this->client_id, $this->site_id, $group);

                if ($player_id !== false) {
                    $goods['amount'] = isset($m[$group]) ? $m[$group]['amount'] : 0;
                    if(isset($m[$group]['code']) && $goods['amount'] > 0) $goods['goods']['code'] = $m[$group]['code'];
                }
            } else {
                if ($player_id !== false) {
                    $goods['amount'] = isset($m[$goodsId]) ? $m[$goodsId]['amount'] : 0;
                    if(isset($m[$goodsId]['code'])  && $goods['amount'] > 0) $goods['goods']['code'] = $m[$goodsId]['code'];
                }
            }

            $this->response($this->resp->setRespond($goods), 200);
        } else // list all
        {
            $data = $this->validToken;

            if ($this->input->get('tags')) {
                $data['tags'] = explode(',', $this->input->get('tags'));
            }

            if ($this->input->get('selected_field')) {
                $data['selected_field'] = explode(',', $this->input->get('selected_field'));
                foreach ($data['selected_field'] as $index => $field) {
                    if (!$field) {
                        unset($data['selected_field'][$index]);
                    }
                }
                $data['selected_field'] = array_values($data['selected_field']);
            }

            $active_filter = $this->input->get('active_filter') == "true" ? true : false;
            if ($active_filter) {
                if (!$this->input->get('date_start') && !$this->input->get('date_end')) {
                    $data['date_start'] = new MongoDate();
                }
            }

            if ($this->input->get('date_start')) {
                $data['date_start'] = new MongoDate(strtotime($this->input->get('date_start')));
            }

            if ($this->input->get('date_end')) {
                if (strpos($this->input->get('date_end'), ':') !== false) {
                    $data['date_end'] = new MongoDate(strtotime($this->input->get('date_end')));
                } else {
                    $data['date_end'] = new MongoDate(strtotime($this->input->get('date_end') . " 23:59:59"));
                }
            }

            $data['offset'] = ($this->input->get('offset')) ? $this->input->get('offset') : 0;
            $data['limit'] = ($this->input->get('limit')) ? $this->input->get('limit') : null;
            if ($data['limit'] > 500) {
                $data['limit'] = 500;
            }

            $filter_goods_name = $this->input->get('name') ? $this->input->get('name') : null;
            if ($this->input->get('custom_param') || $this->input->get('not_custom_param')) {
                $custom_array = array();
                $not_custom_array = array();

                if($this->input->get('custom_param')) {
                    $custom_param = explode(',', $this->input->get('custom_param'));
                    foreach ($custom_param as $param) {
                        $param_data = explode('|', $param);
                        if (isset($param_data[0]) && isset($param_data[1]) && isset($param_data[2])) {
                            $param_data[0] = is_numeric($param_data[2]) ? $param_data[0] . '_numeric' : $param_data[0];
                            if (isset($custom_array[$param_data[0]])) {
                                if ($param_data[1] == '!=') {
                                    $custom_array[$param_data[0]]['$ne'] = is_numeric($param_data[2]) ? floatval($param_data[2]) : $param_data[2];
                                } elseif (($param_data[1] == '>') && is_numeric($param_data[2])) {
                                    $custom_array[$param_data[0]]['$gt'] = floatval($param_data[2]);
                                } elseif (($param_data[1] == '>=') && is_numeric($param_data[2])) {
                                    $custom_array[$param_data[0]]['$gte'] = floatval($param_data[2]);
                                } elseif (($param_data[1] == '<') && is_numeric($param_data[2])) {
                                    $custom_array[$param_data[0]]['$lt'] = floatval($param_data[2]);
                                } elseif (($param_data[1] == '<=') && is_numeric($param_data[2])) {
                                    $custom_array[$param_data[0]]['$lte'] = floatval($param_data[2]);
                                } else {
                                    $custom_array[$param_data[0]]['$eq'] = is_numeric($param_data[2]) ? floatval($param_data[2]) : $param_data[2];
                                }
                            } else {
                                if ($param_data[1] == '!=') {
                                    $custom_array[$param_data[0]] = array('$ne' => is_numeric($param_data[2]) ? floatval($param_data[2]) : $param_data[2]);
                                } elseif (($param_data[1] == '>') && is_numeric($param_data[2])) {
                                    $custom_array[$param_data[0]] = array('$gt' => floatval($param_data[2]));
                                } elseif (($param_data[1] == '>=') && is_numeric($param_data[2])) {
                                    $custom_array[$param_data[0]] = array('$gte' => floatval($param_data[2]));
                                } elseif (($param_data[1] == '<') && is_numeric($param_data[2])) {
                                    $custom_array[$param_data[0]] = array('$lt' => floatval($param_data[2]));
                                } elseif (($param_data[1] == '<=') && is_numeric($param_data[2])) {
                                    $custom_array[$param_data[0]] = array('$lte' => floatval($param_data[2]));
                                } else {
                                    $custom_array[$param_data[0]] = array('$eq' => is_numeric($param_data[2]) ? floatval($param_data[2]) : $param_data[2]);
                                }
                            }
                        } else {
                            if (isset($custom_array[$param_data[0]])) {
                                $custom_array[$param_data[0]] = $custom_array[$param_data[0]];
                            } else {
                                $custom_array[$param_data[0]] = null;
                            }
                        }
                    }
                }
                if($this->input->get('not_custom_param')) {
                    $not_custom_param = explode(',', $this->input->get('not_custom_param'));
                    foreach ($not_custom_param as $not_param) {
                        $not_param_data = explode('|', $not_param);
                        if (isset($not_param_data[0]) && isset($not_param_data[1]) && isset($not_param_data[2])) {
                            $not_param_data[0] = is_numeric($not_param_data[2]) ? $not_param_data[0] . '_numeric' : $not_param_data[0];
                            if (isset($not_custom_array[$not_param_data[0]])) {
                                if ($not_param_data[1] == '!=') {
                                    $not_custom_array[$not_param_data[0]]['$ne'] = is_numeric($not_param_data[2]) ? floatval($not_param_data[2]) : $not_param_data[2];
                                } elseif (($not_param_data[1] == '>') && is_numeric($not_param_data[2])) {
                                    $not_custom_array[$not_param_data[0]]['$gt'] = floatval($not_param_data[2]);
                                } elseif (($not_param_data[1] == '>=') && is_numeric($not_param_data[2])) {
                                    $not_custom_array[$not_param_data[0]]['$gte'] = floatval($not_param_data[2]);
                                } elseif (($not_param_data[1] == '<') && is_numeric($not_param_data[2])) {
                                    $not_custom_array[$not_param_data[0]]['$lt'] = floatval($not_param_data[2]);
                                } elseif (($not_param_data[1] == '<=') && is_numeric($not_param_data[2])) {
                                    $not_custom_array[$not_param_data[0]]['$lte'] = floatval($not_param_data[2]);
                                } else {
                                    $not_custom_array[$not_param_data[0]]['$eq'] = is_numeric($not_param_data[2]) ? floatval($not_param_data[2]) : $not_param_data[2];
                                }
                            } else {
                                if ($not_param_data[1] == '!=') {
                                    $not_custom_array[$not_param_data[0]] = array('$ne' => is_numeric($not_param_data[2]) ? floatval($not_param_data[2]) : $not_param_data[2]);
                                } elseif (($not_param_data[1] == '>') && is_numeric($not_param_data[2])) {
                                    $not_custom_array[$not_param_data[0]] = array('$gt' => floatval($not_param_data[2]));
                                } elseif (($not_param_data[1] == '>=') && is_numeric($not_param_data[2])) {
                                    $not_custom_array[$not_param_data[0]] = array('$gte' => floatval($not_param_data[2]));
                                } elseif (($not_param_data[1] == '<') && is_numeric($not_param_data[2])) {
                                    $not_custom_array[$not_param_data[0]] = array('$lt' => floatval($not_param_data[2]));
                                } elseif (($not_param_data[1] == '<=') && is_numeric($not_param_data[2])) {
                                    $not_custom_array[$not_param_data[0]] = array('$lte' => floatval($not_param_data[2]));
                                } else {
                                    $not_custom_array[$not_param_data[0]] = array('$eq' => is_numeric($not_param_data[2]) ? floatval($not_param_data[2]) : $not_param_data[2]);
                                }
                            }
                        } else {
                            if (isset($not_custom_array[$not_param_data[0]])) {
                                $not_custom_array[$not_param_data[0]] = $not_custom_array[$not_param_data[0]];
                            } else {
                                $not_custom_array[$not_param_data[0]] = null;
                            }
                        }
                    }
                }
                $custom_goods = $this->goods_model->getGroupsCustomParam($this->site_id,$active_filter, $custom_array, $not_custom_array);
                $in_group = array();
                $goods_param_id = array();
                foreach ($custom_goods as $c_goods){
                    if($c_goods['is_group']){
                        array_push($in_group, $c_goods['name']);
                    } else {
                        $custom_goods_id =$this->goods_model->getGoodsIDByName($this->client_id, $this->site_id, $c_goods['name']);
                        array_push($goods_param_id, new MongoId($custom_goods_id));
                    }
                }
                $group_list = $in_group ? $this->goods_model->getGroupsList($this->site_id, $active_filter, $filter_goods_name, $in_group) : array() ;
            } else {
                $group_list = $this->goods_model->getGroupsList($this->site_id, $active_filter, $filter_goods_name);
            }
            
            $in_goods = array();
            foreach ($group_list as $group_name) {
                $check = $active_filter;
                if($active_filter && isset($group_name['batch_name']) && sizeof($group_name['batch_name']) <= 1){
                    $check = false;
                }
                $goods_group_detail = $this->goods_model->getGoodsIDByName($this->client_id, $this->site_id, null, $group_name['name'], $check);
                if(!is_null($goods_group_detail)){
                    array_push($in_goods, new MongoId($goods_group_detail));
                }
            }
            if ($filter_goods_name) {
                $data['specific'] = $this->input->get('custom_param') || $this->input->get('not_custom_param') ?
                    array('$or' => array(array("group" => array('$exists' => false) , 'goods_id'=> array('$in' => $goods_param_id), 'name'=> array('$regex' => new MongoRegex("/" . preg_quote(mb_strtolower($filter_goods_name)) . "/i"))), array("goods_id" => array('$in' => $in_goods)))) :
                    array('$or' => array(array("group" => array('$exists' => false) , 'name'=> array('$regex' => new MongoRegex("/" . preg_quote(mb_strtolower($filter_goods_name)) . "/i"))), array("goods_id" => array('$in' => $in_goods))));
            } else {
                $data['specific'] = $this->input->get('custom_param') || $this->input->get('not_custom_param') ?
                    array('$or' => array(array("group" => array('$exists' => false) , 'goods_id'=> array('$in' => $goods_param_id)), array("goods_id" => array('$in' => $in_goods)))) :
                    array('$or' => array(array("group" => array('$exists' => false)), array("goods_id" => array('$in' => $in_goods))));
            }
            $goodsList['goods_list'] = $this->input->get('custom_param') && !$custom_goods ? array() : $this->goods_model->getAllGoods($data);

            if (is_array($goodsList['goods_list'])) {
                foreach ($goodsList['goods_list'] as $key => &$goods) {

                    if($player_id){
                        $goods_distinct_info = $this->goods_model->getGoodsDistinctByID($this->client_id, $this->site_id, $goods['distinct_id']);
                        if(isset($goods_distinct_info['whitelist_enable']) && $goods_distinct_info['whitelist_enable'] == true){
                            $is_in_whitelist = $this->goods_model->checkIfUserInWhitelist($this->client_id, $this->site_id, $goods['distinct_id'], $player_id);
                            if(!$is_in_whitelist){
                                unset($goodsList['goods_list'][$key]);
                                continue;
                            }
                        }
                    }

                    $is_group = array_key_exists('group', $goods);
                    unset($goods['code']);
                    unset($goods['distinct_id']);
                    if ($is_group) {
                        $goods['is_group'] = true;
                        $goods['name'] = $goods['group'];
                        $goods['quantity'] = $this->goods_model->checkGoodsGroupQuantity($this->client_id, $this->site_id, $goods['group']);
                        if ($player_id !== false) {
                            $goods['amount'] = isset($m[$goods['name']]) ? $m[$goods['name']]['amount'] : 0;
                            if(isset($m[$goods['name']]['code'])  && $goods['amount'] > 0) $goods['code'] = $m[$goods['name']]['code'];
                        }
                    } else {
                        $goods['is_group'] = false;
                        if ($player_id !== false) {
                            $goods['amount'] = isset($m[$goods['goods_id']]) ? $m[$goods['goods_id']]['amount'] : 0;
                            if(isset($m[$goods['name']]['code'])  && $goods['amount'] > 0) $goods['code'] = $m[$goods['name']]['code'];
                        }
                    }
                    unset($goods['_id']);
                    
                    // unset the result if
                    // 1. good id is set organize and player_id is not in that organize
                    // Or 2. organize role is set and player role is not matched
                    if (isset($goods['organize_id'])) {
                        if ((!array_key_exists((string)$goods['organize_id'], $org_id_list)
                            || ((isset($goods['organize_role']) && $goods['organize_role'] != "")
                                && !array_key_exists($goods['organize_role'],
                                    $org_id_list[(string)$goods['organize_id']]))
                        )
                        ) {
                            unset($goodsList['goods_list'][$key]);
                        } else {
                            $org = $this->store_org_model->retrieveOrganizeById($this->client_id, $this->site_id,
                                $goods['organize_id']);
                            $goods['organize'] = $org['name'];
                            unset($goods['organize_id']);
                        }
                    }
                }
            }
            $goodsList['goods_list'] = array_values($goodsList['goods_list']); // sort array just in case there were unset

            if ($this->input->get('sort')) {
                // Sorting
                $sort_data = array('name', 'quantity', 'description', 'date_start', 'date_expire', 'sort_order');

                if ($this->input->get('order') && (mb_strtolower($this->input->get('order')) == 'desc')) {
                    $order = SORT_DESC;
                } else {
                    $order = SORT_ASC;
                }

                if ($this->input->get('sort') && in_array($this->input->get('sort'), $sort_data)) {
                    $sort = $this->input->get('sort');
                } else {
                    $sort = "sort_order";
                }

                foreach ($goodsList['goods_list'] as $key => $row) {
                    $sorter[$key] = $row[$sort];
                }

                array_multisort($sorter, $order, $goodsList['goods_list']);
            }

            $this->response($this->resp->setRespond($goodsList), 200);
        }
    }

    public function sponsor_get($goodsId = 0)
    {
        $validToken_ad = array('client_id' => null, 'site_id' => null);
        /* process group */
        /* find my goods */
        $player_id = $this->input->get('player_id');
        if ($player_id !== false) {
            $pb_player_id = $this->player_model->getPlaybasisId(array(
                'client_id' => $this->client_id,
                'site_id' => $this->site_id,
                'cl_player_id' => $player_id,
            ));
            if (!$pb_player_id) {
                $this->response($this->error->setError('USER_NOT_EXIST'), 200);
            }
            $myGoods = $this->player_model->getGoods($pb_player_id, $this->site_id);
            $m = $this->mapByGoodsId($myGoods);
        }
        /* main */
        if ($goodsId) // given specified goods_id
        {
            $goods['goods'] = $this->goods_model->getGoods(array_merge($validToken_ad, array(
                'goods_id' => new MongoId($goodsId)
            )));
            $goods['goods']['is_group'] = array_key_exists('group', $goods['goods']);
            if ($goods['goods']['is_group']) {
                $group = $goods['goods']['group'];
                $goods['goods']['quantity'] = $this->goods_model->checkGoodsGroupQuantity($this->client_id, $this->site_id, $group);
                if ($player_id !== false) {
                    $goods['amount'] = isset($m[$group]) ? $m[$group]['amount'] : 0;
                }
            } else {
                if ($player_id !== false) {
                    $goods['amount'] = isset($m[$goods['goods_id']]) ? $m[$goods['goods_id']]['amount'] : 0;
                }
            }
            $this->response($this->resp->setRespond($goods), 200);
        } else // list all
        {
            $group_list = $this->goods_model->getGroupsList($this->site_id);
            $in_goods = array();
            foreach ($group_list as $group_name){
                $goods_group_detail =  $this->goods_model->getGoodsIDByName($this->client_id, $this->site_id, "", $group_name['name']);
                array_push($in_goods, new MongoId($goods_group_detail));
            }
            $validToken_ad['specific'] = array('$or' => array(array("group" => array('$exists' => false ) ), array("goods_id" => array('$in' => $in_goods ) ) ));
            $goodsList['goods_list'] = $this->goods_model->getAllGoods($validToken_ad);
            if (is_array($goodsList['goods_list'])) {
                foreach ($goodsList['goods_list'] as &$goods) {
                    $goods_id = $goods['_id'];
                    $is_group = array_key_exists('group', $goods);
                    if ($is_group) {
                        $goods['is_group'] = true;
                        $goods['name'] = $goods['group'];
                        $goods['quantity'] = $this->goods_model->checkGoodsGroupQuantity($this->client_id, $this->site_id, $goods['group']);
                        if ($player_id !== false) {
                            $goods['amount'] = isset($m[$goods['name']]) ? $m[$goods['name']]['amount'] : 0;
                        }
                    } else {
                        if ($player_id !== false) {
                            $goods['amount'] = isset($m[$goods['goods_id']]) ? $m[$goods['goods_id']]['amount'] : 0;
                        }
                    }
                    unset($goods['_id']);
                    $goods['code'] = null;
                }
            }
            $this->response($this->resp->setRespond($goodsList), 200);
        }
    }

    public function personalizedSponsor_get()
    {
        $validToken_ad = array('client_id' => null, 'site_id' => null);
        /* check required 'player_id' */
        $required = $this->input->checkParam(array(
            'player_id',
        ));
        if ($required) {
            $this->response($this->error->setError('PARAMETER_MISSING', $required), 200);
        }
        $cl_player_id = $this->input->get('player_id');
        $validToken = array_merge($this->validToken, array(
            'cl_player_id' => $cl_player_id
        ));
        $pb_player_id = $this->player_model->getPlaybasisId($validToken);
        if (!$pb_player_id) {
            $this->response($this->error->setError('USER_NOT_EXIST'), 200);
        }
        /* process group */
        $group_list = $this->goods_model->getGroupsList($this->site_id);
        $in_goods = array();
        foreach ($group_list as $group_name){
            $goods_group_detail =  $this->goods_model->getGoodsIDByName($this->client_id, $this->site_id, "", $group_name['name']);
            array_push($in_goods, new MongoId($goods_group_detail));
        }
        $validToken_ad['specific'] = array('$or' => array(array("group" => array('$exists' => false ) ), array("goods_id" => array('$in' => $in_goods ) ) ));
        /* goods list */
        $goodsList = $this->goods_model->getAllGoods($validToken_ad);
        $goods['goods'] = $this->recommend($pb_player_id, $goodsList);
        $goods['goods']['is_group'] = array_key_exists('group', $goods['goods']);
        if ($goods['goods']['is_group']) {
            $goods['goods']['quantity'] = $this->goods_model->checkGoodsGroupQuantity($this->client_id, $this->site_id, $goods['goods']['group']);
        }
        $this->response($this->resp->setRespond($goods), 200);
    }

    private function recommend($pb_player_id, $goodsList)
    {
        if (!$goodsList) {
            return array();
        }
        /* TODO: integrate machine learning algorithm instead of randomly picking a goods */
        $idx = rand(0, count($goodsList) - 1);
        return $this->goods_model->getGoods(array_merge(array('client_id' => null, 'site_id' => null), array(
            'goods_id' => new MongoId($goodsList[$idx]['goods_id'])
        )));
    }

    private function mapByGoodsId($goodsList)
    {
        $ret = array();
        foreach ($goodsList as $goods) {
            $key = isset($goods['group']) ? $goods['group'] : $goods['goods_id'];
            if (!isset($ret[$key])) {
                $ret[$key] = $goods;
                if(isset($goods['group'])){
                    if($goods['amount'] > 0){
                        $ret[$key]['code'] = array($goods['code']);
                    } else {
                        $ret[$key]['code'] = array();
                    }
                } else {
                    if($goods['amount'] > 0){
                        $ret[$key]['code'] = $goods['code'];
                    } else {
                        $ret[$key]['code'] = "";
                    }
                }
            } else {
                $ret[$key]['amount'] += $goods['amount'];
                if((isset($goods['group'])) && ($goods['amount'] > 0)) $ret[$key]['code'][] = $goods['code'];
            }
        }
        return $ret;
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

?>