<?php 

$start = microtime(true);

	$apikey = $_GET['apikey'];
	if (hash('sha512',$apikey) == '8194f848c6b6d235567a55c39b2b9415714bd346906e6b13f3fb139834fbeac92a803d6f98ae05cd6f9e7f16ba9d22691b833c464f50632f0b81afbe257d3b2f') {
		define('API_KEY',$apikey);
		$botsplit = explode(':',$apikey);
		define('BOT_ID',$botsplit[0]);
		define('BOT_KEY',$botsplit[1]);
	} else {
		sendMessageEcho('none','@groupagreebotdebug',$apikey);
		exit('We\'re done here Mr. '.$_SERVER['REMOTE_ADDR'].' '.$_SERVER['HTTP_X_FORWARDED_FOR']);
	}
	$content = file_get_contents('php://input');

sendMessageEcho('none','@groupagreebotdebug',$content);

$update = json_decode($content, true);

$db = new mysqli('localhost',BOT_ID,BOT_KEY,BOT_ID);
if ($db->connect_errno) {
	sendMessage('none',$update['message']['chat']['id'],"Failed to connect to Database!\nPlease contact @wjclub and forward this message...\n".$db->connect_error());
}

if (isset($update['callback_query'])) {
//     _____      _ _ _                _      _    _                 _ _           
//    / ____|    | | | |              | |    | |  | |               | | |          
//   | |     __ _| | | |__   __ _  ___| | __ | |__| | __ _ _ __   __| | | ___ _ __ 
//   | |    / _` | | | '_ \ / _` |/ __| |/ / |  __  |/ _` | '_ \ / _` | |/ _ \ '__|
//   | |___| (_| | | | |_) | (_| | (__|   <  | |  | | (_| | | | | (_| | |  __/ |   
//    \_____\__,_|_|_|_.__/ \__,_|\___|_|\_\ |_|  |_|\__,_|_| |_|\__,_|_|\___|_|   
//                                                                                 
	if (substr($update['callback_query']['data'],0,5) == 'comm:') {
		$request = $db->query('SELECT * from pointer WHERE chat_id = \''.$update['message']['chat']['id'].'\'');
		$answer = $request->fetch_assoc();
		$pointer = $answer['pointer'];
		$pointer_type = $answer['type'];
		if ($pointer_type == 'v') {
			$pointer_type = 'vote';
		} else if ($pointer_type == 'd') {
			$pointer_type = 'doodle';
		}
		$pointer_anony = $answer['anony'];
		$request->close();
		$insert_content = str_replace('comm:','',$update['callback_query']['data']);
		$callback_response = 'An error occured...';
		if ($insert_content == 'anony') {
			$request = $db->query('UPDATE pointer SET anony=\'y\' WHERE chat_id='.$update['callback_query']['message']['chat']['id'].';');
			$callback_response = 'Set poll to anonymous...';
			$pointer_anony = 'y';
		} else if ($insert_content == 'noanony') {
			$request = $db->query('UPDATE pointer SET anony=\'n\' WHERE chat_id='.$update['callback_query']['message']['chat']['id'].';');
			$callback_response = 'Set poll to personal...';
			$pointer_anony = 'n';
        } else if ($insert_content == 'vote' || $insert_content == 'doodle') {
			$request = $db->query('UPDATE pointer SET type=\''.$insert_content.'\' WHERE chat_id='.$update['callback_query']['message']['chat']['id'].';');
			$callback_response = 'Set poll to '.$insert_content;
			$pointer_type = $insert_content;
		}
		editMessageReplyMarkup($update['callback_query']['message']['message_id'],generate_markup($pointer_type,$pointer_anony),$update['callback_query']['message']['chat']['id']);
		answerCallbackQuery($update['callback_query']['id'],$callback_response);
	} else {
		$incoming_parameters = explode(':',$update['callback_query']['data']);
		$request = $db->query('SELECT * FROM polls WHERE chat_id='.$incoming_parameters[0].' AND poll_id='.$incoming_parameters[1].';');
		$answer = $request->fetch_assoc();
		$current_votes = json_decode($answer['poll_votes'],true);
		
		$type = array_keys($current_votes);
		if (!array_key_exists($update['callback_query']['from']['id'],$current_votes[$type[0]][$incoming_parameters[2]])) {
			$current_votes[$type[0]][$incoming_parameters[2]][$update['callback_query']['from']['id']] = $update['callback_query']['from']['first_name'];
		} else {
			array_splice($current_votes[$type[0]][$incoming_parameters[2]], array_search($update['callback_query']['from']['id'], $current_votes[$type[0]][$incoming_parameters[2]]), 1);
		}
		$insert_json = json_encode($current_votes);
		$request = $db->query('UPDATE polls SET poll_votes=\''.$insert_json.'\' WHERE chat_id='.$incoming_parameters[0].' AND poll_id='.$incoming_parameters[1].';');
		$request = $db->query('SELECT * FROM polls WHERE chat_id = '.$incoming_parameters[0].' AND poll_id='.$incoming_parameters[1].';');
		$message = generate_poll_message($request->fetch_assoc());
		if (isset($update['callback_query']['inline_message_id'])) {
			editMessageText($update['callback_query']['inline_message_id'],$message['text'],$message['inline_keyboard']);
		} else {
			editMessageText($update['callback_query']['message']['message_id'],$message['text'],$message['inline_keyboard'],$update['callback_query']['message']['chat']['id']);
		}
		answerCallbackQuery($update['callback_query']['id'],'You voted for '.$incoming_parameters[2]);
	}
} else if (isset($update['inline_query'])){
//    _____ _   _ _      _____ _   _ ______ 
//   |_   _| \ | | |    |_   _| \ | |  ____|
//     | | |  \| | |      | | |  \| | |__   
//     | | | . ` | |      | | | . ` |  __|  
//    _| |_| |\  | |____ _| |_| |\  | |____ 
//   |_____|_| \_|______|_____|_| \_|______|
//                                          
	$request = $db->query('SELECT * FROM polls WHERE chat_id = '.$update['inline_query']['from']['id'].' ORDER BY poll_id DESC LIMIT 1;');
	$rows = array();
	while($answer = $request->fetch_assoc()) $rows[] = $answer;
	$request = $db->query('SELECT * FROM polls ORDER BY chat_id ASC, poll_id ASC;');
	answerInlineQuery($update['inline_query']['id'],$rows);
} else if (isset($update['message'])) { 
//    __  __                                  _    _                 _ _           
//   |  \/  |                                | |  | |               | | |          
//   | \  / | ___  ___ ___  __ _  __ _  ___  | |__| | __ _ _ __   __| | | ___ _ __ 
//   | |\/| |/ _ \/ __/ __|/ _` |/ _` |/ _ \ |  __  |/ _` | '_ \ / _` | |/ _ \ '__|
//   | |  | |  __/\__ \__ \ (_| | (_| |  __/ | |  | | (_| | | | | (_| | |  __/ |   
//   |_|  |_|\___||___/___/\__,_|\__, |\___| |_|  |_|\__,_|_| |_|\__,_|_|\___|_|   
//                                __/ |                                            
//                               |___/                                         
//
	$request = $db->query('SELECT * from pointer WHERE chat_id = \''.$update['message']['chat']['id'].'\'');
	$answer = $request->fetch_assoc();
	$pointer = $answer['pointer'];
	$pointer_type = $answer['type'];
	if ($pointer_type == 'v') {
		$pointer_type = 'vote';
	} else if ($pointer_type == 'd') {
		$pointer_type = 'doodle';
	}
	$pointer_anony = $answer['anony'];
	$request->close();
	if (substr($update['message']['text'],0,1) == '/') {
	$command = str_replace('/','',str_replace('@groupagreebot','',explode(' ',$update['message']['text'])[0]));
		if ($command == 'start') {
		$request = $db->query('REPLACE INTO pointer (chat_id,pointer) VALUES(\''.$update['message']['chat']['id'].'\',\'0\') ');
		if ($request === TRUE){
			$request = $db->query('SELECT * from pointer WHERE chat_id = \''.$update['message']['chat']['id'].'\'');
            $answer = $request->fetch_assoc();
            $pointer = $answer['pointer'];
            $pointer_type = $answer['type'];
            if ($pointer_type == 'v') {
            	$pointer_type = 'vote';
            } else if ($pointer_type == 'd') {
            	$pointer_type = 'doodle';
            }
            $pointer_anony = $answer['anony'];
            $request->close();
            sendMessage('start',$update['message']['chat']['id'],['type' => $pointer_type,'anony' => $pointer_anony]);
			exit();
		} else {
			sendMessage('none',$update['message']['chat']['id'],"The database ran into an error...\nContact @wjclub and forward this message\n<code>POINTER_ERROR\n".mysql_error()."</code>");
			exit();
		}
		$request->close();
	}
}


if ($command != null) {
 if ($command == 'done') {
		$request = $db->query('SELECT COUNT(*) from polls WHERE chat_id = '.$update['message']['chat']['id']);
		$answer = $request->fetch_row();
		$current_poll = (($answer[0])-1);
		$request = $db->query('UPDATE pointer SET pointer=3 WHERE chat_id='.$update['message']['chat']['id'].';');
		if ($request === TRUE){
			$request = $db->query('SELECT * FROM polls WHERE chat_id = '.$update['message']['chat']['id'].' AND poll_id = '.$current_poll.';');
			$poll = $request->fetch_assoc();
			sendMessage('done',$update['message']['chat']['id'],$poll);
			exit();
		} else {
			sendMessage('none',$update['message']['chat']['id'],"The database ran into an error...\nContact @wjclub and forward this message\n<code>".mysql_error()."</code>");
			exit();
		}
	} else {
		sendMessage('wrong',$update['message']['chat']['id']);
	}
}

switch ($pointer) {
	case 0:
		$request = $db->query('SELECT COUNT(*) from polls WHERE chat_id = '.$update['message']['chat']['id']);
		$answer = $request->fetch_row();
		$request = $db->query('INSERT INTO polls (chat_id,poll_id,poll_text,anony) VALUES (\''.$update['message']['chat']['id'].'\','.$answer[0].',\''.$update['message']['text'].'\',\''.$pointer_anony.'\')');
		if ($request === TRUE) {
			$request = $db->query('UPDATE pointer SET pointer=1 WHERE chat_id='.$update['message']['chat']['id'].';');
			if ($request === TRUE){
				sendMessage('enter_first',$update['message']['chat']['id']);
			} else {
				sendMessage('none',$update['message']['chat']['id'],"The database ran into an error...\nContact @wjclub and forward this message\n<code>ERROR_AT_POINTER_REPLACE_0_1\n".mysql_error()."</code>");
				exit();
			}
		} else {
			sendMessage('none',$update['message']['chat']['id'],"The database ran into an error...\nContact @wjclub and forward this message\n<code>ERROR_POLL_FIRST_INSERT".mysql_error()."</code>");
			exit();
		}
		break;
	case 1:
		$request = $db->query('SELECT COUNT(*) from polls WHERE chat_id = '.$update['message']['chat']['id']);
		$answer = $request->fetch_row();
		$current_poll = (($answer[0])-1);
		$text_array = array();
		$raw_insert = [
			$pointer_type => [
				$update['message']['text'] => [
					//Here are the users
					],
				],
			];
		$insert_json = json_encode($raw_insert);
		$request = $db->query('UPDATE polls SET poll_votes=\''.$insert_json.'\' WHERE chat_id='.$update['message']['chat']['id'].' AND poll_id='.$current_poll.';');
		if ($request === TRUE) {
			$request = $db->query('UPDATE pointer SET pointer=2 WHERE chat_id='.$update['message']['chat']['id'].';');
			if ($request === TRUE){
				sendMessage('enter_more',$update['message']['chat']['id']);
			} else {
				sendMessage('none',$update['message']['chat']['id'],"The database ran into an error...\nContact @wjclub and forward this message\n<code>ERROR_AT_POINTER_REPLACE_1_2\n".mysql_error()."</code>");
				exit();
			}
		} else {
			sendMessage('none',$update['message']['chat']['id'],"The database ran into an error...\nContact @wjclub and forward this message\n<code>ERROR_POLL_SECOND_INSERT".mysql_error()."</code>");
			exit();
		}
		break;
	case 2:
		$request = $db->query('SELECT COUNT(*) from polls WHERE chat_id = '.$update['message']['chat']['id']);
		$answer = $request->fetch_row();
		$current_poll = (($answer[0])-1);
		$request = $db->query('SELECT * FROM polls WHERE chat_id='.$update['message']['chat']['id'].' AND poll_id='.$current_poll.';');
		$answer = $request->fetch_assoc();
		$current_poll_votes = json_decode($answer['poll_votes'],true);
		$type_raw = array_keys($current_poll_votes);
		$type = $type_raw[0];
		$current_poll_votes[$type][$update['message']['text']] = array();
		$insert_json = json_encode($current_poll_votes);
		$request = $db->query('UPDATE polls SET poll_votes=\''.$insert_json.'\' WHERE chat_id='.$update['message']['chat']['id'].' AND poll_id='.$current_poll.';');
		if ($request === TRUE) {
			$request = $db->query('REPLACE INTO pointer (chat_id,pointer) VALUES(\''.$update['message']['chat']['id'].'\',\'2\')');
			if ($request === TRUE){
				sendMessage('enter_more',$update['message']['chat']['id']);
			} else {
				sendMessage('none',$update['message']['chat']['id'],"The database ran into an error...\nContact @wjclub and forward this message\n<code>ERROR_AT_POINTER_REPLACE_1_2\n".mysql_error()."</code>");
				exit();
			}
		} else {
			sendMessage('none',$update['message']['chat']['id'],"The database ran into an error...\nContact @wjclub and forward this message\n<code>ERROR_POLL_SECOND_INSERT".mysql_error()."</code>");
			exit();
		}
		break;
	}
	sendMessage('none',$update['message']['chat']['id'],'<b>Pointer:</b> '.$pointer);
}

function generate_poll_message($val,$inline){
	$text = $val['poll_text'];
	$inline_keyboard = array();
	$current_working_array = array();
	$current_column_working_array = array();
	$poll_votes = json_decode($val['poll_votes'],true);
	$type = array_keys($poll_votes)[0];
	$anony = $val['anony'];
	if ($anony == 'n') {
		$text .= "\n";
		foreach ($poll_votes[$type] as $key => $value) {
			$user_count = count($value);
			if($user_count>1000000000000) $current_number = round(($user_count/1000000000000),1).' T';
        	else if($user_count>1000000000) $current_number = round(($user_count/1000000000),1).' B';
        	else if($user_count>1000000) $current_number = round(($user_count/1000000),1).' M';
        	else if($user_count>1000) $current_number = round(($user_count/1000),1).' K';
        	else $current_number = $user_count;
			$inline_keyboard[][] = [
			'text' => $key.' - '.$current_number,
			'callback_data' => $val['chat_id'].':'.$val['poll_id'].':'.$key,
			];
			
			$text .= "\n<b>$key</b> [$current_number]\n";
			foreach ($value as $id => $name) $text .= $name."\n";
		}
	} else if ($anony == 'y') {
		$text .= "\n";
		foreach ($poll_votes[$type] as $key => $value) {
			$user_count = count($value);
			if($user_count>1000000000000) $current_number = round(($user_count/1000000000000),1).' T';
        	else if($user_count>1000000000) $current_number = round(($user_count/1000000000),1).' B';
        	else if($user_count>1000000) $current_number = round(($user_count/1000000),1).' M';
        	else if($user_count>1000) $current_number = round(($user_count/1000),1).' K';
        	else $current_number = $user_count;
			$inline_keyboard[][] = [
			'text' => $key.' - '.$current_number,
			'callback_data' => $val['chat_id'].':'.$val['poll_id'].':'.$key,
			];
			
			$text .= "\n<b>$key</b> [$current_number]\n";
		}
	}
	return ['text' => $text,'inline_keyboard' => $inline_keyboard];
}

function generate_markup($type,$anony) {
    $inline_keyboard = [
				[
					[
						'text' => 'Vote'.$type == 'vote' ? ' ✅':'',
						'callback_data' => 'comm:vote',
						],
					[
						'text' => 'Doodle'.$type == 'doodle' ? ' ✅':'',
						'callback_data' => 'comm:doodle',
						],
					],
				[
					[
						'text' => 'Anonymous'.$anony == 'y' ? ' ✅':'',
						'callback_data' => 'comm:anony',
						],
					[
						'text' => 'Identified Users'.$anony == 'n' ? ' ✅':'',
						'callback_data' => 'comm:anony',
						]
					],
				];
	return $inline_keyboard;
}

function sendMessage($sampletext,$chat_id,$val) {
	switch ($sampletext) {
		case 'start':
			$text = "Hello\nI can help you organize stuff in group chats\nFirst, send me the question and select the poll type with the buttons below.\nNeed help? Get /help!";
			$inline_keyboard = generate_markup($val['type'],$val['anony']);
			break;
		case 'enter_first':
			$text = "Okay\nNow send me the first vote option";
			break;
		case 'enter_more':
			$text = "Got it\nKeep sending more vote options or hit /done to publish the poll";
			break;
		case 'done':
			$message = generate_poll_message($val,false);
			$text = $message['text'];
			$inline_keyboard = $message['inline_keyboard'];
			break;
		case 'wrong':
			$text = "Unrecognized command\nYou may now correct that or make a new poll using /start ...";
		case 'none':
			$text = $val;
			break;
	}
	$reply_content = [
	'method' => 'sendMessage',
	'chat_id' => $chat_id,
	'parse_mode' => 'HTML',
	'text' => $text,
	];
	
	if (isset($inline_keyboard)) {
		$reply_content['reply_markup'] = ['inline_keyboard' => $inline_keyboard];
	}

	$reply_json = json_encode($reply_content);
	header('Content-Type: application/json');
	curl_json_request($reply_json);
}


function sendMessageEcho($sampletext,$chat_id,$val) {
	$text = $val;
	
	$reply_content = [
	'method' => 'sendMessage',
	'chat_id' => $chat_id,
	'parse_mode' => 'HTML',
	'text' => $text,
	];

	$reply_json = json_encode($reply_content);
	header('Content-Type: application/json');
	echo($reply_json);
}



function answerCallbackQuery($query_id,$val) {
	$text = $val;
	$response = [
		'method' => 'answerCallbackQuery',
		'callback_query_id' => $query_id,
		'text' => $text,
	];
	$json_response = json_encode($response);
	curl_json_request($json_response);
}

function answerInlineQuery($query_id,$contents) {
	$results = array();
	foreach ($contents as $key => $row) {
		$message = generate_poll_message($row,true);
		$poll = json_decode($row['poll_votes'],true);
		$desc = array_keys($poll)[0].' '.$row['anony'] == 'y' ? 'Anonymous ' : 'Personal ';
		foreach ($poll[array_keys($poll)[0]] as $name => $users) $desc.=$name.', ';
		$text = $message['text'];
		$inline_keyboard = $message['inline_keyboard'];
		$input_message_content = [
			'parse_mode' => 'HTML',
			'message_text' => $text,
			'disable_web_page_preview' => true,
		];
		$results[] = [
			'type' => 'article',
			'id' => $query_id.$key,
			'title' => $row['poll_text'],
			'description' => $desc,
			'input_message_content' => $input_message_content,
			'reply_markup' => ['inline_keyboard' => $inline_keyboard],
		];  
	}
	$reply_content = [
    	'method' => 'answerInlineQuery',
    	'inline_query_id' => $query_id,
    	'results' => $results,
  	];
	
  	curl_json_request(json_encode($reply_content));
}

function editMessageText($id_val,$text_val,$markup_val,$chat_id) {
	$response = [
		'method' => 'editMessageText',
		'text' => $text_val,
	    'parse_mode' => 'HTML',
		'reply_markup' => ['inline_keyboard' => $markup_val],
	];
	
	if ($chat_id != null) {
		$response['chat_id'] = $chat_id;
		$response['message_id'] = $id_val;
	} else {
		$response['inline_message_id'] = $id_val;
	}
	
	$json_response = json_encode($response);
	curl_json_request($json_response);
}

function editMessageReplyMarkup($id_val,$markup_val,$chat_id) {
	$response = [
		'method' => 'editMessageReplyMarkup',
		'reply_markup' => ['inline_keyboard' => $markup_val],
	];
	
	if (chat_id != null) {
		$response['chat_id'] = $chat_id;
		$response['message_id'] = $id_val;
	} else {
		$response['inline_message_id'] = $id_val;
	}
	
	$json_response = json_encode($response);
	curl_json_request($json_response);
}

function typing($chat_id) {
	$response = [
		'method' => 'sendChatAction',
		'chat_id' => $chat_id,
		'action' => 'typing',
	];
	$json_response = json_encode($response);
	curl_json_request($json_response);
}

function curl_json_request($json) {
	$curl = curl_init('https://api.telegram.org/bot'.API_KEY.'/');

	curl_setopt($curl, CURLOPT_HEADER, false);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HTTPHEADER,
		array("Content-type: application/json"));
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $json);

	$json_response = curl_exec($curl);
	$response = json_decode($json_response,true);
	if ($response['ok']!=true || isset($response['update_id'])) debugMessage('@groupagreebotdebug',$json_response);
	
}

sendMessage('none','@groupagreebotdebug','Time elapsed: '.(microtime(true) - $start).' Seconds');

?>
