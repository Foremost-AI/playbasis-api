<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . '/libraries/REST2_Controller.php';

class Insurance extends REST2_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('auth_model');
        $this->load->model('player_model');
        $this->load->model('insurance_model');
        $this->load->model('quiz_model');
        $this->load->model('tool/error', 'error');
        $this->load->model('tool/respond', 'resp');
    }

    /**
     * @SWG\Post(
     *     tags={"Insurance"},
     *     path="/Insurance/getSuggestInsurance",
     *     description="To get suggest insurance cover",
     *     @SWG\Parameter(
     *         name="player_id",
     *         in="query",
     *         type="string",
     *         description="Player ID as used in client's website",
     *         required=true,
     *     ),
     *     @SWG\Parameter(
     *         name="product_type",
     *         in="query",
     *         type="string",
     *         description="Insurance product",
     *         required=true,
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="OK",
     *     )
     * )
     */
    public function getSuggestInsurance_get()
    {
        $required = $this->input->checkParam(array(
            'player_id',
            'product_type'
        ));
        if ($required) {
            $this->response($this->error->setError('PARAMETER_MISSING', $required), 200);
        }

        $client_id = $this->client_id;
        $site_id = $this->site_id;
        $player_id = $this->input->get('player_id');
        $product_type = strtolower($this->input->get('product_type'));
        $quiz = $quiz_player = $swissre_quiz_id = $answer = array();

        $pb_player_id = $this->player_model->getPlaybasisId(array_merge($this->validToken, array(
            'cl_player_id' => $player_id
        )));
        if (!$pb_player_id) {
            $this->response($this->error->setError('USER_NOT_EXIST'), 200);
        }

        //get swissre config
        $swissre_config = $this->insurance_model->getInsuranceConfig($client_id, $site_id);
        foreach ($swissre_config as $key => $value){
            if (strpos($key, 'quiz_id')){
                if(!isset($swissre_quiz_id[$value.""]) && !is_null($value)){
                    $swissre_quiz_id[$value.""]=array();
                }
            }
            elseif (strpos($key, 'question_id')){
                $type = explode('_question_id', $key);
                $quiz_id = is_null($swissre_config[$type[0]."_quiz_id"]) ? null : $swissre_config[$type[0]."_quiz_id"]."";
                if(!is_null($quiz_id)){
                    $swissre_quiz_id[$swissre_config[$type[0]."_quiz_id"].""][$type[0]] = is_null($value) ? null : $value."";
                } else {
                    $answer['non_smoker'] = 'non_smoker';
                }
            }
        }

        foreach($swissre_quiz_id as $quiz_id => $value){
            $quiz[$quiz_id] = $this->quiz_model->find_by_id($client_id, $site_id, new MongoId($quiz_id));
            $quiz_player[$quiz_id] = $this->quiz_model->find_quiz_by_quiz_and_player($client_id, $site_id, new MongoId($quiz_id), $pb_player_id);
            foreach ($value as $key => $val){
                $q_index = array_search(new MongoId($val), $quiz_player[$quiz_id]['questions']);
                if(isset($quiz_player[$quiz_id]['answers'][$q_index]) && $q_index !== false){
                    if(isset($quiz_player[$quiz_id]['answers'][$q_index]['answer'])){
                        $answer[$key] = $quiz_player[$quiz_id]['answers'][$q_index]['answer'];
                    } else {
                        if($key == 'gender'){
                            if($quiz_player[$quiz_id]['answers'][$q_index]['option_id']."" ==  $swissre_config['gender_man_option_id'] ){
                                $answer[$key] = 'male';
                            } elseif($quiz_player[$quiz_id]['answers'][$q_index]['option_id']."" ==  $swissre_config['gender_woman_option_id'] ){
                                $answer[$key] = 'female';
                            } else {
                                $answer[$key] = null;
                            }
                        }
                        elseif($key == 'non_smoker'){
                            $answer[$key] = $quiz_player[$quiz_id]['answers'][$q_index]['option_id']."" ==  $swissre_config['non_smoker_option_id'] ? 'non_smoker' : 'smoker';
                        }
                    }
                } else {
                    $answer[$key] = null;
                }
            }
        }

        $response = array();
        if((isset($answer['age_man']) && !is_null($answer['age_man']) ||
           isset($answer['age_woman']) && !is_null($answer['age_woman'])) &&
           isset($answer['gender']) && !is_null($answer['gender']) && 
           isset($answer['non_smoker']) && !is_null($answer['non_smoker']) &&
           isset($answer['loan']) && !is_null($answer['loan']) &&
           isset($answer['income']) && !is_null($answer['income']))
        {
            $answer['age'] = $answer['gender'] == 'male' ? $answer['age_man'] : $answer['age_woman'];
            unset($answer['age_man']);
            unset($answer['age_woman']);
            if(isset($swissre_config['product'][$product_type])){
                if(isset($swissre_config['product'][$product_type][$answer['gender']][$answer['non_smoker']][intval($answer['age'])]) &&
                   isset($swissre_config['insurance']) && is_array($swissre_config['insurance']))
                {
                    $premium_rate = $swissre_config['product'][$product_type][$answer['gender']][$answer['non_smoker']][intval($answer['age'])];
                    $answer['product'] = $product_type;
                    $answer['premium_rate'] = $premium_rate;
                    foreach($swissre_config['insurance'] as $key => $val){
                        $response[$key]['maxlimit'] = $val['max_limit'];
                        $response[$key]['multiple'] = $val['multiple'];
                        $response[$key]['sum_assure'] = min(($answer['loan'] + ($answer['income'] * $val['multiple'])),$val['max_limit']);
                        $response[$key]['annual_premium'] = round(($response[$key]['sum_assure'] * $premium_rate) / 1000, 1);
                        $response[$key]['monthly_premium'] = round($response[$key]['annual_premium'] / 12, 1);;
                    }
                } else {
                    // error setting
                    $this->response($this->error->setError('INSURANCE_CALCULATOR_NOT_SET'), 200);
                }
            } else {
                // error production type
                $this->response($this->error->setError('INSURANCE_PRODUCT_NOT_FOUND'), 200);
            }
        } else {
            // error answer
            $this->response($this->error->setError('INSURANCE_PLAYER_INFO_MISSING'), 200);
        }

        $this->response($this->resp->setRespond(array('information' => $answer, 'insurance' => $response)), 200);
    }
}

?>