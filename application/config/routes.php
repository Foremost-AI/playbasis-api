<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	http://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There area two reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router what URI segments to use if those provided
| in the URL cannot be matched to a valid route.
|
*/

define('ANY_STRING','([a-zA-Z0-9-%_:\.]+)');
define('ANY_NUMBER','([0-9]+)');

$route['default_controller'] = "welcome/playbasis";

//global player API

//$route['Universe/'.ANY_STRING.'/test'] = 'universe/test/$1';
$route['Universe/test'] = 'universe/test';
$route['Universe/mau'] = 'universe/mau';
$route['Universe/mau_per_day'] = 'universe/mauDay';
$route['Universe/mytest'] = 'universe/mytest';

$route['Universe/'.ANY_STRING.'/register'] = 'universe/register/$1';
$route['Universe/register'] = 'universe/register';

$route['Universe/'.ANY_STRING.'/login'] = 'universe/login/$1';
$route['Universe/login'] = 'universe/login';

$route['Universe/'.ANY_STRING.'/join'] = 'universe/join/$1';
$route['Universe/join'] = 'universe/join';

$route['Universe/'.ANY_STRING.'/searchClientSite'] = 'universe/searchClientSite/$1';
$route['Universe/searchClientSite'] = 'universe/searchClientSite';

$route['Universe/'.ANY_STRING.'/feature'] = 'universe/feature/$1';
$route['Universe/feature'] = 'universe/feature';

$route['Universe/'.ANY_STRING.'/feature'] = 'universe/service/$1';
$route['Universe/service'] = 'universe/service';

$route['Universe/'.ANY_STRING.'/deviceRegistration'] = 'universe/deviceRegistration/$1';
$route['Universe/deviceRegistration'] = 'universe/deviceRegistration';

$route['Universe/'.ANY_STRING.'/directMsg'] = 'universe/directMsg/$1';
$route['Universe/directMsg'] = 'universe/directMsg';



//auth API
$route['Auth'] = 'auth';
$route['Auth/renew'] = 'auth/renew';

//player API
$route['Player/'.ANY_STRING] = 'player/index/$1';
$route['Player/'.ANY_STRING.'/data/all'] = 'player/details/$1';
$route['Player'] = 'player/index/';
$route['Player/list'] = 'player/list';
$route['Player/'.ANY_STRING.'/status'] = 'player/status/$1';
$route['Player/'.ANY_STRING.'/custom'] = 'player/custom/$1';

$route['Player/'.ANY_STRING.'/register'] = 'player/register/$1';
$route['Player/register'] = 'player/register';

$route['Player/'.ANY_STRING.'/update'] = 'player/update/$1';
$route['Player/update'] = 'player/update';

$route['Player/'.ANY_STRING.'/delete'] = 'player/delete/$1';
$route['Player/delete'] = 'player/delete';

$route['Player/'.ANY_STRING.'/login'] = 'player/login/$1';
$route['Player/login'] = 'player/login';
$route['Player/'.ANY_STRING.'/logout'] = 'player/logout/$1';
$route['Player/logout'] = 'player/logout';
$route['Player/'.ANY_STRING.'/sessions'] = 'player/sessions/$1';
$route['Player/sessions'] = 'player/sessions';
$route['Player/session/'.ANY_STRING] = 'player/session/$1';
$route['Player/session'] = 'player/session';

$route['Player/rank/'.ANY_STRING.'/'.ANY_NUMBER] = 'player/rank/$1/$2';
$route['Player/rank/'.ANY_STRING] = 'player/rank/$1/20';
$route['Player/rank'] = 'player/rank/0/0';

$route['Player/ranks/'.ANY_NUMBER] = 'player/ranks/$1';
$route['Player/ranks'] = 'player/ranks/20';

$route['Player/level/'.ANY_NUMBER] = 'player/level/$1';
$route['Player/level'] = 'player/level/0';
$route['Player/levels'] = 'player/levels';

$route['Player/'.ANY_STRING.'/points'] = 'player/points/$1';
$route['Player/points'] = 'player/points';
$route['Player/'.ANY_STRING.'/point/'.ANY_STRING] = 'player/point/$1/$2';
$route['Player/'.ANY_STRING.'/point/'.ANY_STRING.'/lastUsed'] = 'player/pointLastUsed/$1/$2';
$route['Player/'.ANY_STRING.'/point/'.ANY_STRING.'/lastFilled'] = 'player/pointLastCronModified/$1/$2';
$route['Player/point/'.ANY_STRING] = 'player/point/0/$1';
$route['Player/'.ANY_STRING.'/point'] = 'player/points/$1';
$route['Player/point'] = 'player/point/';
$route['Player/'.ANY_STRING.'/point_history'] = 'player/point_history/$1';
$route['Player/'.ANY_STRING.'/quest_reward_history'] = 'player/quest_reward_history/$1';

$route['Player/'.ANY_STRING.'/action/'.ANY_STRING.'/(time|count)'] = 'player/action/$1/$2/$3';
$route['Player/action/'.ANY_STRING.'/(time|count)'] = 'player/action/0/$1/$2';
$route['Player/'.ANY_STRING.'/action/(time|count)'] = 'player/action/$1/0/$2';
$route['Player/action'] = 'player/action/';

$route['Player/'.ANY_STRING.'/badge'] = 'player/badge/$1';
$route['Player/badge'] = 'player/badge/0';
$route['Player/'.ANY_STRING.'/badgeAll'] = 'player/badgeAll/$1';
$route['Player/badgeAll'] = 'player/badgeAll';
$route['Player/'.ANY_STRING.'/badge/'.ANY_STRING.'/claim'] = 'player/claimBadge/$1/$2';
$route['Player/'.ANY_STRING.'/badge/'.ANY_STRING.'/redeem'] = 'player/redeemBadge/$1/$2';

$route['Player/'.ANY_STRING.'/goods'] = 'player/goods/$1';

$route['Player/quest'] = 'quest/questOfPlayer';
$route['Player/quest/'.ANY_STRING] = 'quest/questOfPlayer/$1';

$route['Player/questAll'] = 'quest/questAll';
$route['Player/questAll/'.ANY_STRING] = 'quest/questAll/$1';

$route['Player/'.ANY_STRING.'/deduct'] = 'player/deduct_reward/$1';

$route['Player/rankuser/'.ANY_STRING.'/'.ANY_STRING] = 'player/rankuser/$1/$2';

$route['Player/'.ANY_STRING.'/contact'] = 'player/contact/$1/10';
$route['Player/'.ANY_STRING.'/contact/'.ANY_NUMBER] = 'player/contact/$1/$2';
$route['Player/contact'] = 'player/contact/0/10';
$route['Player/contact/'.ANY_NUMBER] = 'player/contact/0/$1';

//badge API
$route['Badge/'.ANY_STRING] = 'badge/index/$1';
$route['Badge']  = 'badge/index';
$route['Badges'] = 'badge/index';

//Goods API
$route['Goods/'.ANY_STRING] = 'goods/index/$1';
$route['Goods'] = 'goods/index';
$route['Goods/sponsor/'.ANY_STRING] = 'goods/sponsor/$1';
$route['Goods/sponsor'] = 'goods/sponsor';
$route['Goods/ad'] = 'goods/personalizedSponsor';

//engine API
$route['Engine/actionConfig']	= 'engine/getActionConfig';
$route['Engine/rule/'.ANY_STRING] = 'engine/rule/$1';
$route['Engine/rule']	= 'engine/rule/0';
$route['Engine/facebook']	= 'engine/rule/facebook';
$route['Engine/quest']	= 'engine/quest';

//redeem API
$route['Redeem/goods/'.ANY_STRING] = 'redeem/goods/$1';
$route['Redeem/goods'] = 'redeem/goods/0';
$route['Redeem/goodsGroup'] = 'redeem/goodsGroup';
$route['Redeem/sponsor/'.ANY_STRING] = 'redeem/sponsor/$1';
$route['Redeem/sponsor'] = 'redeem/sponsor/0';
$route['Redeem/sponsorGroup'] = 'redeem/sponsorGroup';

// api-carlos
$route['Action'] = 'action/index';
$route['Action/log'] = 'action/log';
$route['Action/usedonly'] = 'action/usedonly';
$route['Reward'] = 'reward/index';
$route['Reward/badge'] = 'badge/index'; // reuse
$route['Reward/'.ANY_STRING.'/log'] = 'reward/$1Log';
$route['Player/total'] = 'player/total';
$route['Player/new'] = 'player/new';
$route['Player/dau'] = 'player/dau';
$route['Player/mau'] = 'player/mau';
$route['Player/dau_per_day'] = 'player/dauDay';
$route['Player/mau_per_day'] = 'player/mauDay';
$route['Player/mau_per_week'] = 'player/mauWeek';
$route['Player/mau_per_month'] = 'player/mauMonth';

//service API
$route['Service/recent_point/'.ANY_STRING] = 'service/recent_point/$1';
$route['Service/recent_point'] = 'service/recent_point';
$route['Service/recentActivities'] = 'service/recent_activities';
$route['Service/detailActivityFeed/'.ANY_STRING] = 'service/detail_activity/$1';
$route['Service/detailActivityFeed'] = 'service/detail_activity';
$route['Service/likeActivityFeed/'.ANY_STRING] = 'service/like_activity/$1';
$route['Service/likeActivityFeed'] = 'service/like_activity';
$route['Service/commentActivityFeed/'.ANY_STRING] = 'service/comment_activity/$1';
$route['Service/commentActivityFeed'] = 'service/comment_activity';
$route['Service/reset_point'] = 'service/reset_point';

//Quest
//$route['quest/testquest'] = 'quest/testQuest';
$route['Quest/'.ANY_STRING] = 'quest/index/$1';
$route['Quest'] = 'quest/index';
$route['Quests'] = 'quest/index';
$route['Quest/'.ANY_STRING.'/join'] = 'quest/join/$1';
$route['Quest/'.ANY_STRING.'/cancel'] = 'quest/cancel/$1';
$route['Quest/available'] = 'quest/available';
$route['Quest/'.ANY_STRING.'/available'] = 'quest/available/$1';
$route['Quest/'.ANY_STRING.'/mission/'.ANY_STRING] = 'quest/mission/$1/$2';
$route['Quest/joinAll'] = 'quest/joinAll';
$route['Quest/reset'] = 'quest/reset';

//quiz API
$route['Quiz/list'] = 'quiz/list';
$route['Quiz/'.ANY_STRING.'/detail'] = 'quiz/detail/$1'; // ANY_STRING = quiz_id
$route['Quiz/random'] = 'quiz/random';
$route['Quiz/random/'.ANY_STRING] = 'quiz/random/$1'; // ANY_STRING = quiz_id
$route['Quiz/player/'.ANY_STRING.'/'.ANY_NUMBER] = 'quiz/player_recent/$1/$2'; // ANY_STRING = player_id
$route['Quiz/player/'.ANY_STRING] = 'quiz/player_recent/$1/5'; // ANY_STRING = player_id
$route['Quiz/player/'.ANY_STRING.'/pending/'.ANY_NUMBER] = 'quiz/player_pending/$1/$2'; // ANY_STRING = player_id
$route['Quiz/player/'.ANY_STRING.'/pending'] = 'quiz/player_pending/$1/-1'; // ANY_STRING = player_id
$route['Quiz/'.ANY_STRING.'/question'] = 'quiz/question/$1'; // ANY_STRING = quiz_id
$route['Quiz/'.ANY_STRING.'/answer'] = 'quiz/answer/$1'; // ANY_STRING = quiz_id
$route['Quiz/'.ANY_STRING.'/rank/'.ANY_NUMBER] = 'quiz/rank/$1/$2'; // ANY_STRING = quiz_id
$route['Quiz/'.ANY_STRING.'/rank'] = 'quiz/rank/$1/5'; // ANY_STRING = quiz_id
$route['Quiz/'.ANY_STRING.'/stat'] = 'quiz/stat/$1'; // ANY_STRING = quiz_id
$route['Quiz/reset'] = 'quiz/reset';

//email API
$route['Email/sendTo'] = 'email/send';
$route['Email/send'] = 'email/send_player';
$route['Email/isBlackList'] = 'email/isBlackList';
$route['Email/addBlackList'] = 'email/addBlackList';
$route['Email/removeBlackList'] = 'email/removeBlackList';
$route['Email/goods'] = 'email/send_goods';
$route['Email/recent'] = 'email/recent';

//sms API
$route['Sms/sendTo'] = 'pb_sms/sendTo';
$route['Sms/send'] = 'pb_sms/send';
$route['Sms/goods'] = 'pb_sms/send_goods';
$route['Sms/recent'] = 'pb_sms/recent';

//notification API
$route['notification/'.ANY_STRING] = 'notification/index/$1';
$route['notification'] = 'notification/index';

//promo API
$route['Promo'] = 'promo/list';
$route['Promo/list'] = 'promo/list';
$route['Promo/name'] = 'promo/detailByName';
$route['Promo/name/' . ANY_STRING] = 'promo/detailByName/$1';

//misc
//$route['test']	= 'playbasis/test';
$route['test']	= 'notification/index';
$route['fb'] = 'playbasis/fb';
$route['login'] = 'playbasis/login';
$route['memtest'] = 'playbasis/memtest';

//dummy 
//$route['dummy/dummyPlayer/'.ANY_NUMBER.'/'.ANY_NUMBER]	= 'dummy/dummyPlayer/$1/$2/$3';
//$route['dummy/'.ANY_NUMBER.'/'.ANY_NUMBER.'/'.ANY_NUMBER]	= 'dummy/index/$1/$2/$3';
//$route['dummy/:any']	= 'dummy/error';

$route['404_override'] = '';
/* End of file routes.php */
/* Location: ./application/config/routes.php */
