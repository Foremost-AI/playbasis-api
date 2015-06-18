<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
require_once APPPATH . '/libraries/REST2_Controller.php';
require_once(APPPATH.'controllers/engine.php');

define('LITHIUM_EPSILON', 2);
define('EVENT_CANCELLED', 'cancelled');

/**
 * Notification Endpoint for (1) Amazon Simple Notification Service (SNS), (2) PayPal, (3) FullContact, (4) Jive
 */
//class Notification extends REST2_Controller
class Notification extends Engine
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('notification_model');
		$this->load->model('client_model');
		$this->load->model('player_model');
		$this->load->model('payment_model');
		$this->load->model('email_model');
		$this->load->model('jive_model');
		$this->load->model('lithium_model');
		$this->load->model('googles_model');
		$this->load->model('tool/error', 'error');
		$this->load->model('tool/utility', 'utility');
		$this->load->model('tool/respond', 'resp');
		$this->load->model('tool/node_stream', 'node');
		$this->load->library('curl');
	}

	public function index_get()
	{
		$messages = $this->notification_model->list_messages($this->site_id);
		$this->response($this->resp->setRespond($messages), 200);
	}

	public function index_post($arg = null)
	{
		// headers = HTTP_X_AMZ_SNS_MESSAGE_TYPE, HTTP_X_AMZ_SNS_MESSAGE_ID, HTTP_X_AMZ_SNS_TOPIC_ARN, HTTP_X_AMZ_SNS_SUBSCRIPTION_ARN
		// body = $this->request->body
		$message = !empty($this->request->body) ? $this->request->body : $_POST;
		log_message('debug', '_SERVER = '.print_r($_SERVER, true));
		log_message('debug', 'message = '.print_r($message, true));
		$log_id = $this->notification_model->log($this->site_id, $message);
		if (array_key_exists('HTTP_X_AMZ_SNS_MESSAGE_TYPE', $_SERVER)) { // Amazon SNS: http://docs.aws.amazon.com/sns/latest/dg/json-formats.html#http-header
			log_message('error', 'type = '.print_r($_SERVER['HTTP_X_AMZ_SNS_MESSAGE_TYPE'], true));
			switch ($_SERVER['HTTP_X_AMZ_SNS_MESSAGE_TYPE']) {
				case 'SubscriptionConfirmation': // http://docs.aws.amazon.com/sns/latest/dg/json-formats.html#http-subscription-confirmation-json
					// fields: Type, MessageId, Token, TopicArn, Message, SubscribeURL, Timestamp, SignatureVersion, Signature, SigningCertURL
					log_message('debug', 'SubscribeURL = '.print_r($message['SubscribeURL'], true));
					$response = $this->curl->simple_get($message['SubscribeURL']); // http://philsturgeon.co.uk/code/codeigniter-curl
					log_message('debug', 'response = '.$response);
					break;
				case 'Notification': // http://docs.aws.amazon.com/sns/latest/dg/json-formats.html#http-notification-json
					// fields: Type, MessageId, TopicArn, Subject, Message, Timestamp, SignatureVersion, Signature, SigningCertURL, UnsubscribeURL
					log_message('debug', 'message = '.$message['Message']);
					$response = $this->handleNotification($this->convertToJson($message['Message']));
					log_message('debug', 'response = '.$response);
					break;
				case 'UnsubscribeConfirmation': // http://docs.aws.amazon.com/sns/latest/dg/json-formats.html#http-unsubscribe-confirmation-json
					// fields: Type, MessageId, Token, TopicArn, Message, SubscribeURL, Timestamp, SignatureVersion, Signature, SigningCertURL
					break;
				default:
					$this->response($this->error->setError('UNKNOWN_SNS_MESSAGE_TYPE', $_SERVER['HTTP_X_AMZ_SNS_MESSAGE_TYPE']), 200);
					break;
			}
			$this->response($this->resp->setRespond('Handle notification message successfully'), 200);
		} else if (strpos($_SERVER['HTTP_USER_AGENT'], PAYMENT_CHANNEL_PAYPAL) === false ? false : true) { // PayPal IPN: https://developer.paypal.com/docs/classic/ipn/ht_ipn/
			// STEP 1: read POST data

			// Reading POSTed data directly from $_POST causes serialization issues with array data in the POST.
			// Instead, read raw POST data from the input stream.
			$myPost = array();
			$raw_post_array = explode('&', $this->request->raw);
			foreach ($raw_post_array as $keyval) {
				$keyval = explode ('=', $keyval);
				if (count($keyval) == 2)
					$myPost[$keyval[0]] = urldecode($keyval[1]);
			}
			// read the IPN message sent from PayPal and prepend 'cmd=_notify-validate'
			$req = 'cmd=_notify-validate';
			$get_magic_quotes_exists = false;
			if(function_exists('get_magic_quotes_gpc')) {
				$get_magic_quotes_exists = true;
			}
			foreach ($myPost as $key => $value) {
				if($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
					$value = urlencode(stripslashes($value));
				} else {
					$value = urlencode($value);
				}
				$req .= "&$key=$value";
			}

			// Step 2: POST IPN data back to PayPal to validate

			$ch = curl_init('https://www.'.(PAYPAL_ENV == 'sandbox' ? PAYPAL_ENV.'.' : '').'paypal.com/cgi-bin/webscr');
			curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));
			// In wamp-like environments that do not come bundled with root authority certificates,
			// please download 'cacert.pem' from "http://curl.haxx.se/docs/caextract.html" and set
			// the directory path of the certificate as shown below:
			curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__).'/../certs/cacert.pem');
			if( !($res = curl_exec($ch)) ) {
				$msg = 'Getting a problem when trying to verify PayPal IPN, response: '.curl_error($ch);
				log_message('error', $msg);
				curl_close($ch);
				$this->response($this->error->setError('CANNOT_VERIFY_PAYPAL_IPN', $msg), 200);
			}
			curl_close($ch);

			// inspect IPN validation result and act accordingly
			if (strcmp($res, PAYPAL_IPN_VERIFIED) == 0) { // The IPN is verified
				// extract 'client_id' and 'plan_id' from 'custom' field in IPN message
				$custom = $_POST['custom'];
				$pieces = explode(',', $custom);
				$client_id = new MongoId($pieces[0]);
				$plan_id = new MongoId($pieces[1]);

				log_message('debug', 'process: _POST = '.print_r($_POST, true));
				$result = $this->payment_model->processVerifiedIPN($client_id, $plan_id, $_POST, $log_id);
				log_message('debug', 'process: result = '.$result);

				$this->response($this->resp->setRespond('Handle notification message successfully'), 200);
			} else if (strcmp($res, PAYPAL_IPN_INVALID) == 0) { // IPN invalid, log for further investigation
				log_message('error', 'Invalid PayPal IPN message, response: '.$res);
				$this->response($this->error->setError('INVALID_PAYPAL_IPN', $res), 200);
			} else {
				log_message('error', 'Unknown return status from PayPal, response: '.$res);
				$this->response($this->error->setError('INVALID_PAYPAL_IPN', $res), 200);
			}
		} else if (strpos($_SERVER['HTTP_USER_AGENT'], FULLCONTACT_USER_AGENT) === false ? false : true) {
			log_message('debug', 'arg = '.print_r($arg, true));
			$email = urlsafe_b64decode($arg);
			log_message('debug', 'email = '.print_r($email, true));
			$this->player_model->insertOrUpdateFullContact($email, $message);
			$this->response($this->resp->setRespond('Handle notification message successfully'), 200);
		} else if (strpos($_SERVER['HTTP_USER_AGENT'], JIVE_USER_AGENT) === false ? false : true) {
			if (array_key_exists('HTTP_X_TENANT_ID', $_SERVER)) {
				/* process Jive webhook */
				$tenent_id = $_SERVER['HTTP_X_TENANT_ID'];
				log_message('debug', 'tenent_id = '.$tenent_id);
				$jive = $this->jive_model->findByTenantId($tenent_id);
				if (!$jive) {
					log_message('error', 'Unknown tenant ID: '.$tenent_id);
					$this->response($this->error->setError('INVALID_JIVE_TENANT_ID', $tenent_id), 200);
				}
				if (!array_key_exists('activity', $message)) {
					log_message('error', 'Invalid Jive message');
					$this->response($this->error->setError('INVALID_JIVE_MESSAGE'), 200);
				}
				$activity = $message['activity'];
				if (!array_key_exists('verb', $activity)) {
					log_message('error', 'Missing jive:verb');
					$this->response($this->error->setError('PARAMETER_MISSING', array('verb')), 200);
				}
				$site_id = new MongoId($jive['site_id']);
				$actionName = $activity['verb'];
				$url = isset($activity['object']['summary']) ? $activity['object']['summary'] : null;
				$names = explode(' ', $activity['actor']['displayName']);
				$arr = explode('/', $activity['actor']['id']);
				$cl_player_id = $arr[count($arr)-1].'@jive';
				$player = array(
					'cl_player_id' => $cl_player_id,
					'username' => $activity['actor']['jive']['username'],
					'image' => isset($activity['actor']['image']['url']) ? $activity['actor']['image']['url'] : $this->config->item('DEFAULT_PROFILE_IMAGE'),
					'first_name' => isset($names[0]) ? $names[0] : '[first_name]',
					'last_name' => isset($names[1]) ? $names[1] : '[last_name]',
				);
				$apiResult = $this->rule($site_id, $actionName, $url, $player);
				$this->response($this->resp->setRespond($apiResult), 200);
			} else {
				/* register/unregister */
				log_message('debug', 'arg = '.print_r($arg, true));
				$site_id = new MongoId($arg);
				log_message('debug', 'site_id = '.print_r($site_id, true));
				$this->handleJive($site_id, $message);
			}
			$this->response($this->resp->setRespond('Handle notification message successfully'), 200);
		} else if (strpos($_SERVER['HTTP_USER_AGENT'], LITHIUM_USER_AGENT) === false ? false : true) {
			$this->load->library('RestClient');
			$this->load->library('LithiumApi');

			/* init: look into event subscription record and map client-site */
			$record = $this->lithium_model->findSubscription($message['token']);
			if (!$record) $this->response($this->error->setError('LITHIUM_SUBSCRIPTION_RECORD_NOT_FOUND'), 200);
			$validToken = array('client_id' => $record['client_id'], 'site_id' => $record['site_id']);

			/* init: find lithium setting record */
			$lithium = $this->lithium_model->getRegistration($validToken['site_id']);
			if (!$lithium) $this->response($this->error->setError('LITHIUM_RECORD_NOT_FOUND'), 200);

			/* init: initialize LithiumApi */
			$this->lithiumapi->initialize($lithium['lithium_url']);
			if (!empty($lithium['http_auth_username'])) $this->lithiumapi->setHttpAuth('basic', $lithium['http_auth_username'], $lithium['http_auth_password']);
			$this->lithiumapi->login($lithium['lithium_username'], $lithium['lithium_password']);

			/* process Lithium events */
			$event_type = $message['event_type'];
			switch ($event_type) {
			case 'UserRegistered':
				/* parse payload */
				$user = simplexml_load_string($message['user']);
				/* map Lithium ID to player ID */
				$player_id = $this->mapPlayer($user->id, 'lithium');
				/* create a new player */
				$pb_player_id = $this->player_model->getPlaybasisId(array_merge($validToken, array('cl_player_id' => $player_id)));
				if (!$pb_player_id) {
					$info = $this->lithiumapi->user($user->id);
					$avatar = $this->getLithiumUserProfile($info, 'url_icon');
					$path = $this->resolveLithiumUserProfileAvatar($avatar);
					$image = $lithium['lithium_url'].'/'.$path;
					$email = $info->email->{'$'};
					$username = $info->login->{'$'};
					$pb_player_id = $this->player_model->createPlayer(array_merge($validToken, array(
						'player_id' => $player_id,
						'image' => $image,
						'email' => !empty($email) ? $email : 'no-reply@playbasis.com',
						'username' => $username
					)));
				} else {
					$this->response($this->error->setError('USER_ALREADY_EXIST'), 200);
				}
				$this->response($this->resp->setRespond($pb_player_id), 200);
				break;
			case 'UserSignOn': // lithium:login
			case 'UserUpdate': // lithium:updateprofile
				/* parse payload */
				$user = simplexml_load_string($message['user']);
				/* map Lithium ID to player ID */
				$player_id = $this->mapPlayer($user->id, 'lithium');
				/* read player info */
				$pb_player_id = $this->player_model->getPlaybasisId(array_merge($validToken, array('cl_player_id' => $player_id)));
				if (!$pb_player_id) {
					$info = $this->lithiumapi->user($user->id);
					$avatar = $this->getLithiumUserProfile($info, 'url_icon');
					$path = $this->resolveLithiumUserProfileAvatar($avatar);
					$image = $lithium['lithium_url'].'/'.$path;
					$email = $info->email->{'$'};
					$username = $info->login->{'$'};
					$pb_player_id = $this->player_model->createPlayer(array_merge($validToken, array(
						'player_id' => $player_id,
						'image' => $image,
						'email' => !empty($email) ? $email : 'no-reply@playbasis.com',
						'username' => $username
					)));
				}
				$player = $this->player_model->readPlayer($pb_player_id, $validToken['site_id'], array(
					'cl_player_id',
					'username',
					'first_name',
					'last_name',
					'email',
					'image'
				));
				$actionName = 'lithium:updateprofile';
				if ($event_type == 'UserSignOn') {
					/* track event */
					$eventMessage = $this->utility->getEventMessage('login');
					$this->tracker_model->trackEvent('LOGIN', $eventMessage, array(
						'client_id' => $validToken['client_id'],
						'site_id' => $validToken['site_id'],
						'pb_player_id' => $pb_player_id,
						'action_log_id' => null
					));
					$actionName = 'lithium:login';
				}
				/* process rule */
				$apiResult = $this->rule($validToken['site_id'], $actionName, null, $player);
				$this->response($this->resp->setRespond($apiResult), 200);
				break;
			case 'UserSignOff': // lithium:logout
				/* Lithium always send us anonymous user <user type="user" href="/users/id/-1"> */
				$this->response($this->resp->setRespond(), 200);
				break;
			case 'MessageCreate': // lithium:createmessage, lithium:reply
				/* parse payload */
				$msg = simplexml_load_string($message['message']);
				/* store this new message into our database */
				$this->lithium_model->insertMessage($validToken, $msg);
				/* map Lithium ID to player ID */
				$id = $this->getLithiumId($this->getAttribute($msg->last_edit_author, 'href'));
				/* determine action */
				$actionName = 'lithium:createmessage';
				if ($this->getAttribute($msg->parent, 'href')) {
					$actionName = 'lithium:reply';
				}
				/* read player info */
				$player_id = $this->mapPlayer($id, 'lithium');
				$pb_player_id = $this->player_model->getPlaybasisId(array_merge($validToken, array('cl_player_id' => $player_id)));
				if (!$pb_player_id) {
					$info = $this->lithiumapi->user($id);
					$avatar = $this->getLithiumUserProfile($info, 'url_icon');
					$path = $this->resolveLithiumUserProfileAvatar($avatar);
					$image = $lithium['lithium_url'].'/'.$path;
					$email = $info->email->{'$'};
					$username = $info->login->{'$'};
					$pb_player_id = $this->player_model->createPlayer(array_merge($validToken, array(
						'player_id' => $player_id,
						'image' => $image,
						'email' => !empty($email) ? $email : 'no-reply@playbasis.com',
						'username' => $username
					)));
				}
				$player = $this->player_model->readPlayer($pb_player_id, $validToken['site_id'], array(
					'cl_player_id',
					'username',
					'first_name',
					'last_name',
					'email',
					'image'
				));
				/* process rule */
				$apiResult = $this->rule($validToken['site_id'], $actionName, $msg->body.'', $player);
				$this->response($this->resp->setRespond($apiResult), 200);
				break;
			case 'MessageUpdate': // lithium:editmessage, lithium:like
				/* parse payload */
				$msg = simplexml_load_string($message['message']);
				$msgId = $msg->id;
				/* check if we've already stored this message */
				$m = $this->lithium_model->getMessage(array_merge($validToken, array('message_id' => $msgId)));
				if (!$m) {
					$this->lithium_model->insertMessage($validToken, $msg);
					/* because we don't have previous info, it is best to skip processing */
					$this->response($this->resp->setRespond(), 200);
				} else {
					$this->lithium_model->updateMessage($validToken, $msgId, $msg);
				}
				/* determine action */
				$count = intval($msg->kudos->count.'');
				if ($count != $m['kudos']) { // "like", "unlike"
					if ($count < $m['kudos']) {
						/* then we know that this is "unlike" */
						$this->response($this->resp->setRespond(), 200); /* and because we have no clue who unlikes, we simply skip processing */
					}
					$givers = $this->lithiumapi->kudosGivers($msgId);
					$info = $this->findLatest($givers->user);
					$id = $info->id->{'$'};
					$actionName = 'lithium:like';
				} else { // "edit", "tag"
					/* map Lithium ID to player ID */
					$id = $this->getLithiumId($this->getAttribute($msg->last_edit_author, 'href'));
					$info = $this->lithiumapi->user($id);
					/* check if it is "edit" */
					$last_visit = $info->last_visit_time->{'$'};
					// Lithium does not guarantee that $msg->last_edit_time and $user->last_visit will be the same
					if (abs(strtotime($msg->last_edit_time.'') - strtotime($last_visit)) > LITHIUM_EPSILON) { // so we have to use abs(diff) here
						/* then we know that this it not "edit", but "tag" action */
						$this->response($this->resp->setRespond(), 200); /* and because we have no clue who tags it, we simply skip processing */
					}
					$actionName = 'lithium:editmessage';
				}
				/* read player info */
				$player_id = $this->mapPlayer($id, 'lithium');
				$pb_player_id = $this->player_model->getPlaybasisId(array_merge($validToken, array('cl_player_id' => $player_id)));
				if (!$pb_player_id) {
					$avatar = $this->getLithiumUserProfile($info, 'url_icon');
					$path = $this->resolveLithiumUserProfileAvatar($avatar);
					$image = $lithium['lithium_url'].'/'.$path;
					$email = $info->email->{'$'};
					$username = $info->login->{'$'};
					$pb_player_id = $this->player_model->createPlayer(array_merge($validToken, array(
						'player_id' => $player_id,
						'image' => $image,
						'email' => !empty($email) ? $email : 'no-reply@playbasis.com',
						'username' => $username
					)));
				}
				$player = $this->player_model->readPlayer($pb_player_id, $validToken['site_id'], array(
					'cl_player_id',
					'username',
					'first_name',
					'last_name',
					'email',
					'image'
				));
				/* process rule */
				$apiResult = $this->rule($validToken['site_id'], $actionName, null, $player);
				$this->response($this->resp->setRespond($apiResult), 200);
				break;
			case 'MessageDelete': // lithium:removemessage
				/* parse payload */
				$msg = simplexml_load_string($message['message']);
				/* map Lithium ID to player ID */
				$id = $this->getLithiumId($this->getAttribute($msg->last_edit_author, 'href'));
				/* determine action */
				$actionName = 'lithium:removemessage';
				/* read player info */
				$player_id = $this->mapPlayer($id, 'lithium');
				$pb_player_id = $this->player_model->getPlaybasisId(array_merge($validToken, array('cl_player_id' => $player_id)));
				if (!$pb_player_id) {
					$info = $this->lithiumapi->user($id);
					$avatar = $this->getLithiumUserProfile($info, 'url_icon');
					$path = $this->resolveLithiumUserProfileAvatar($avatar);
					$image = $lithium['lithium_url'].'/'.$path;
					$email = $info->email->{'$'};
					$username = $info->login->{'$'};
					$pb_player_id = $this->player_model->createPlayer(array_merge($validToken, array(
						'player_id' => $player_id,
						'image' => $image,
						'email' => !empty($email) ? $email : 'no-reply@playbasis.com',
						'username' => $username
					)));
				}
				$player = $this->player_model->readPlayer($pb_player_id, $validToken['site_id'], array(
					'cl_player_id',
					'username',
					'first_name',
					'last_name',
					'email',
					'image'
				));
				/* process rule */
				$apiResult = $this->rule($validToken['site_id'], $actionName, $msg->body.'', $player);
				$this->response($this->resp->setRespond($apiResult), 200);
				break;
			case 'MessageMove':
			case 'MessageRootPublished':
			case 'UserCreate':
			case 'ImageCreated':
			case 'ImageUpdated':
			case 'EscalateThread':
			case 'SendPrivateMessage':
			default:
				$this->response($this->error->setError('NOT_IMPLEMENTED'), 200);
				break;
			}
		} else if (strpos($_SERVER['HTTP_USER_AGENT'], GOOGLE_USER_AGENT) === false ? false : true) {
			$this->load->library('GoogleApi');
			$channel_id = $_SERVER['HTTP_X_GOOG_CHANNEL_ID'];
			$site_id = new MongoId($_SERVER['HTTP_X_GOOG_CHANNEL_TOKEN']);
			$subscription = $this->googles_model->getSubscription($site_id, $channel_id);
			if (!$subscription) $this->response($this->error->setError('NOT_SETUP_GOOGLE'), 200);
			$calendar_id = $subscription['calendar_id'];
			$resource_id = $_SERVER['HTTP_X_GOOG_RESOURCE_ID'];
			$resource_uri = $_SERVER['HTTP_X_GOOG_RESOURCE_URI'];
			$date_expire = isset($_SERVER['HTTP_X_GOOG_CHANNEL_EXPIRATION']) ? new MongoDate(strtotime($_SERVER['HTTP_X_GOOG_CHANNEL_EXPIRATION'])) : null;
			$service = null;
			$record = $this->googles_model->getRegistration($site_id);
			if ($record) {
				$client = $this->googleapi->initialize($record['google_client_id'], $record['google_client_secret']);
				if (isset($record['token'])) {
					$service = $client->setAccessToken($record['token'])->calendar();
				}
			}
			if (!$service) $this->response($this->error->setError('NOT_SETUP_GOOGLE'), 200);
			switch ($_SERVER['HTTP_X_GOOG_RESOURCE_STATE']) {
			case 'sync':
				/* set resource_id, resource_uri and date_expire for this channel_id */
				$this->googles_model->updateWebhook($site_id, $channel_id, $resource_id, $resource_uri, $date_expire);
				$syncToken = $this->googles_model->getSyncToken($site_id, $calendar_id);
				if (!$syncToken) { // never sync
					/* for the 1st time, we do a full sync on that calendar */
					$events = array();
					$nextSyncToken = $this->googleapi->listEvents($service, $calendar_id, $events, array('timeMin' => date('c', strtotime('previous month')))); // only on recent events though
					$this->googles_model->insertEvents($site_id, $calendar_id, $this->extractEvents($events));
					$this->googles_model->storeSyncToken($site_id, $calendar_id, $nextSyncToken);
				}
				break;
			case 'exists':
				/* TODO: there can be multiple messages at the same time, transaction is needed here */
				/* transaction begins */
				$changes = array();
				$events = array();
				$syncToken = $this->googles_model->getSyncToken($site_id, $calendar_id);
				$nextSyncToken = $this->googleapi->listEvents($service, $calendar_id, $events, $syncToken ? array('syncToken' => $syncToken) : array());
				foreach ($events as $event) {
					$newEvent = $this->extractEvent($event);
					$event_id = $newEvent['event_id'];
					$oldEvent = $this->googles_model->getEvent($site_id, $calendar_id, $event_id);
					if ($newEvent['event']['status'] != EVENT_CANCELLED) {
						$this->googles_model->insertOrUpdateEvent($site_id, $calendar_id, $newEvent);
					} else {
						$this->googles_model->removeEvent($site_id, $calendar_id, $event_id);
					}
					$change = $this->diffEvent(isset($oldEvent['event']) ? $oldEvent['event'] : null, $newEvent['event']);
					array_push($changes, $change);
				}
				$this->googles_model->storeSyncToken($site_id, $calendar_id, $nextSyncToken);
				/* TODO: processing the changes and submit actions to rule engine */
				/* transaction ends */
				break;
			case 'not_exists':
				$this->response($this->error->setError('NOT_IMPLEMENTED'), 200);
				break;
			default:
				$this->response($this->error->setError('UNSUPPORTED_RESOURCE_STATE'), 200);
				break;
			}
			$this->response($this->resp->setRespond('Handle notification message successfully'), 200);
		}
		$this->response($this->error->setError('UNKNOWN_NOTIFICATION_MESSAGE'), 200);
	}

	private function extractEvents($events) {
		$_events = array();
		foreach ($events as $event) {
			array_push($_events, $this->extractEvent($event));
		}
		return $_events;
	}

	private function extractEvent($event) {
		$_creator = null;
		$_attendees = null;
		if ($event->getStatus() != EVENT_CANCELLED) {
			$creator = $event->getCreator();
			$_creator = $creator->getEmail();
			$_attendees = array();
			$attendees = $event->getAttendees();
			if ($attendees) foreach ($attendees as $attendee) {
				array_push($_attendees, array(
					'email' => $attendee->getEmail(),
					'responseStatus' => $attendee->getResponseStatus(), // needsAction, accepted, tentative, declined
				));
			}
		}
		$entry = array(
			'creator' => $_creator,
			'attendees' => $_attendees,
			'summary' => $event->getSummary(),
			'status' => $event->getStatus(), // confirmed, cancelled
		);
		return array(
			'event_id' => $event->getId(),
			'event' => $entry,
		);
	}

	private function diffEvent($oldEvent, $newEvent) {
		return null;
	}

	private function mapPlayer($id, $service) {
		$player_id = $id;
		switch ($service) {
		case 'jive':
		case 'lithium':
		default:
			$player_id = $id.'@'.$service;
			break;
		}
		return $player_id;
	}

	private function getLithiumId($href) {
		$arr = explode('/', $href);
		return $arr ? intval($arr[count($arr)-1]) : $href;
	}

	private function getLithiumUserProfile($user, $key) {
		foreach ($user->profiles->profile as $each) {
			if ($each->name == $key) {
				return $each->{'$'};
			}
		}
		return null;
	}

	private function findLatest($users) {
		$max = null;
		foreach ($users as $user) {
			if (!$max || $max->last_visit_time < $user->last_visit_time) {
				$max = $user;
			}
		}
		return $max;
	}

	private function getAttribute($xml, $key) {
		foreach($xml->attributes() as $a => $b) {
			if ($key == $a) return $b;
		}
		return null;
	}

	private function resolveLithiumUserProfileAvatar($avatar) {
		list($theme, $collection, $name) = explode('/', str_replace('avatar:', '', $avatar));
		return '/t5/image/serverpage/avatar-name/'.$name.'/avatar-theme/'.$theme.'/avatar-collection/'.$collection.'/avatar-display-size/profile';
	}

	private function convertToJson($str)
	{
		$str = trim($str);
		if ($str[0] == '{' && $str[strlen($str)-1] == '}') {
			return $this->format->factory($str, 'json')->to_array();
		}
		return $str;
	}

	private function handleNotification($message)
	{
		$ret = false;
		if (!empty($message)) {
			if (array_key_exists('notificationType', $message)) { // http://docs.aws.amazon.com/ses/latest/DeveloperGuide/notification-examples.html
				switch ($message['notificationType']) {
				// example: http://sesblog.amazon.com/post/TxJE1JNZ6T9JXK/Handling-Bounces-and-Complaints
				case 'Bounce': // http://docs.aws.amazon.com/ses/latest/DeveloperGuide/notification-contents.html#bounce-object
					switch ($message['bounce']['bounceType']) {
					case 'Transient': // soft bounce
						switch ($message['bounce']['bounceSubType']) {
							case 'MailboxFull':
							case 'MessageTooLarge':
							case 'ContentRejected':
							case 'AttachmentRejected':
								$this->handleBounce($message['bounce']);
								break;
							case 'General':
							default:
								break;
						}
						break;
					case 'Permanent': // hard bounce
					case 'Undetermined':
					default:
						$this->handleBounce($message['bounce']);
						break;
					}
					$ret = true;
					break;
				case 'Complaint': // http://docs.aws.amazon.com/ses/latest/DeveloperGuide/notification-contents.html#complaint-object
					$this->handleComplaint($message['complaint']);
					$ret = true;
					break;
				default:
					break;
				}
			}
		}
		return $ret;
	}

	private function handleBounce($bounce)
	{
		foreach ($bounce['bouncedRecipients'] as $each) {
			$email = $each['emailAddress'];
			if ($this->email_model->isEmailInBlackList($email, $this->site_id)) continue;
			$this->email_model->addIntoBlackList($this->site_id, $email, 'Bounce', $bounce['bounceType'], $bounce['bounceSubType'], $bounce['feedbackId']);
		}
	}

	private function handleComplaint($complaint)
	{
		foreach ($complaint['complainedRecipients'] as $each) {
			$email = $each['emailAddress'];
			if ($this->email_model->isEmailInBlackList($email, $this->site_id)) continue;
			$this->email_model->addIntoBlackList($this->site_id, $email, 'Complaint', $complaint['userAgent'], $complaint['complaintFeedbackType'], $complaint['feedbackId']);
		}
	}

	private function handleJive($site_id, $message)
	{
		$ret = false;
		if (!empty($message) && array_key_exists('tenantId', $message)) {
			$this->jive_model->delete($site_id);
			if (!array_key_exists('uninstalled', $message)) {
				$this->jive_model->insert($site_id, $message);
			}
			$ret = true;
		}
		return $ret;
	}

	protected function rule($site_id, $actionName, $url, $player) {
		$site = $this->client_model->findBySiteId($site_id);
		$validToken = array('client_id' => $site['client_id'], 'site_id' => $site_id, 'domain_name' => $site['domain_name']);
		$cl_player_id = $player['cl_player_id'];
		$pb_player_id = $this->player_model->getPlaybasisId(array_merge($validToken, array('cl_player_id' => $cl_player_id)));
		if (!$pb_player_id) {
			$pb_player_id = $this->player_model->createPlayer(array_merge($validToken, array(
				'player_id' => $cl_player_id,
				'image' => isset($player['image']) ? $player['image'] : $this->config->item('DEFAULT_PROFILE_IMAGE'),
				'email' => isset($player['email']) ? $player['email'] : 'no-reply@playbasis.com',
				'username' => isset($player['username']) ? $player['username'] : $cl_player_id,
				'first_name' => isset($player['first_name']) ? $player['first_name'] : null,
				'last_name' => isset($player['last_name']) ? $player['last_name'] : null,
			)));
		}
		$action = $this->client_model->getAction(array(
			'client_id' => $validToken['client_id'],
			'site_id' => $validToken['site_id'],
			'action_name' => $actionName
		));
		if (!$action) {
			$this->response($this->error->setError('ACTION_NOT_FOUND'), 200);
		}
		$actionId = $action['action_id'];
		$actionIcon = $action['icon'];
		$input = array_merge($validToken, array(
			'player_id' => $cl_player_id,
			'pb_player_id' => $pb_player_id,
			'action_id' => $actionId,
			'action_name' => $actionName,
			'action_icon' => $actionIcon,
			'url' => $url,
			'test' => false
		));
		$apiResult = $this->processRule($input, $validToken, null, null);
		$apiQuestResult = $this->QuestProcess($pb_player_id, $validToken);
		return array_merge($apiResult, $apiQuestResult);
	}
}

function urlsafe_b64decode($string) {
	$data = str_replace(array('-','_'), array('+','/'), $string);
	$mod4 = strlen($data) % 4;
	if ($mod4) {
		$data .= substr('====', $mod4);
	}
	return base64_decode($data);
}
?>