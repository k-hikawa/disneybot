<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>TDLご案内bot</title>
</head>
<body>
  <?php

  $accessToken = '';//ここにアクセストークンを入れる

//ユーザーからのメッセージ取得
$json_string = file_get_contents('php://input');
$jsonObj = json_decode($json_string);


$type = $jsonObj->{"events"}[0]->{"message"}->{"type"};

//メッセージの種類からデータ取得
if($type === "text"){
  $text = $jsonObj->{"events"}[0]->{"message"}->{"text"};
}else if($type === "image"){
  $MessageID = $jsonObj->{"events"}[0]->{"message"}->{"id"};
  $timestamp = $jsonObj->{"events"}[0]->{"timestamp"};
}else if($type === "location"){
  $lat = $jsonObj->{"events"}[0]->{"message"}->{"latitude"};  
  $lng = $jsonObj->{"events"}[0]->{"message"}->{"longitude"}; 
}else if($type === "audio"){
  $MessageID = $jsonObj->{"events"}[0]->{"message"}->{"id"};
  $timestamp = $jsonObj->{"events"}[0]->{"timestamp"};
}else if($type === "video"){
  $MessageID = $jsonObj->{"events"}[0]->{"message"}->{"id"};
  $timestamp = $jsonObj->{"events"}[0]->{"timestamp"};
}

//ReplyToken取得
$replyToken = $jsonObj->{"events"}[0]->{"replyToken"};
//userID取得
$lineID = $jsonObj->{"events"}[0]->{"source"}->{"userId"};
if(!isset($lineID)){
  //groupID取得
  $lineID = $jsonObj->{"events"}[0]->{"source"}->{"groupId"};
}


//データベース連携------------------------------------------------
//相手の名前を取得
$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($ch2, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' .$accessToken));
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_URL, 'https://api.line.me/v2/bot/profile/'.$lineID);
$output = curl_exec($ch2);
curl_close($ch2);
$de_output = json_decode($output);
$lineName = $de_output->{"displayName"};

//名前が取れなかった時は、IDを名前とする
if(!isset($lineName)){
  $lineName = $lineID;
}

$dsn = ''; //SQLのURL
$username = '';//SQLユーザーネーム;
$password = '';//SQLパスワード
$options = array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8');
$pdo = new PDO($dsn, $username, $password, $options);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

foreach($pdo->query("SELECT * FROM disney_table WHERE id='$lineID'") as $row){
  $status = $row['status'];

  if($lineName !== $row['name']){
    $pdo->query("UPDATE disney_table SET name='$lineName' WHERE id='$lineID'");
  }

  $count = $row['count']+1;


  $pdo->query("UPDATE disney_table SET count=$count WHERE id='$lineID'");
  if(!isset($text)){
      $text_t = "";
    }else{
      $text_t = $row['message'];
      $text_t .= "|".$text;
      $pdo->query("UPDATE disney_table SET message='$text_t' WHERE id='$lineID'");
    }
  
 
}

//デバッグは回避
if(isset($type)){

  //登録がなければ新規登録
  if(!isset($status)){
    $table = $pdo->prepare("INSERT INTO disney_table VALUES(?, ?, ?, ?, ?)");
    $status = 0;
    $one = 1;
    if(!isset($text)){
      $text_t = "始めてのメッセージ";
    }else{
      $text_t = $text;
    }
    $table->bindParam(1, $lineID);
    $table->bindParam(2, $lineName);
    $table->bindParam(3, $one);
    $table->bindParam(4, $status);
    $table->bindParam(5, $text_t);
    $table->execute();
  }
}
$pdo = null;


//レスポンス内容-----------------------------------------------

/*
//デバッグ用
if(!isset($type)){
  $type = "text";
  $text = "2017年04月26日";
}
*/


$response = array();

if($type === "text"){
  $land_arealist = get_land_arealist();
  foreach($land_arealist as $key => $content){
    if($text == $key){
      $response = areaName_response($response, $land_arealist, $key);
      break;
    }else if($text == $key." トイレ"){
      $response = areaToilet_response($response, $key);
      break;
    }
  }

  if(count($response) == 0){
    if($text == "クリッターカントリー&ウエスタンランド"){
      $response = doubleArea_response($response, $land_arealist);
    }else if($text == "ポップコーン"){
      $response = popcorn_response($response);
    }else if($text == "トイレ"){
      $response = toilet_response($response);
    }else if(preg_match('/\d{2}月\d{2}日の3時間ごとの天気は？/', $text)){
      $response = detail_weather_response($response, $text);
    }else if(preg_match('/天気/', $text)){
      $response = weather_response($response);
    }
  }


}else if($type === "location"){

  $response = location_response($response, $lat, $lng);
  
}




if(count($response) == 0 && $type === "text"){

  


  $html = get_disney_html("land");

  $attr_area = array();
  $attr_area = get_disney_attr_area($html);

  $area_names = array();
  $attr_names = array();
  $attr_array = array();
  $temp_area_name = "";


  foreach($attr_area as $attr_name => $area_name){
    if($temp_area_name != $area_name){
      array_push($area_names, $temp_area_name);
      array_push($attr_names, $attr_array);
      $attr_array = array();
    }
    array_push($attr_array, $attr_name);
    $temp_area_name = $area_name;
  }

  array_push($area_names, $area_name);
  array_push($attr_names, $attr_array);
  $area_names = array_splice($area_names, 1, count($area_names)-1);
  $attr_names = array_splice($attr_names, 1, count($attr_names)-1);


  $htmlR = get_disney_rest_html_official("land");
  $rest_area = array();
  $rest_area = get_disney_rest_area_official($htmlR);


  $rest_names = array();
  $rest_array = array();
  $temp_area_name = "";

  foreach($rest_area as $rest_name => $area_name){
    if($temp_area_name != $area_name){
      array_push($rest_names, $rest_array);
      $rest_array = array();
    }
    array_push($rest_array, $rest_name);

    $temp_area_name = $area_name;
  }

  array_push($rest_names, $rest_array);
  $rest_names = array_splice($rest_names, 1, count($rest_names)-1);



  //略称のセット
  $attr_shortNames = get_attr_shortNames();
  $short_term = false;
  $short_hits = array();
  foreach($attr_shortNames as $short => $long){
    if($text === $short){
      $short_term = true;
      $attr_term = true;
      if(count($long) == 1){
        array_push($short_hits, $long);
      }else{
        for($i=0; $i<count($long); $i++){
          array_push($short_hits, $long[$i]);
        }
      }
    }
  }


  //レストラン
  if(!$short_term){
    $rest_shortNames = get_rest_shortNames();
    if(preg_match('/\s詳細/', $text)){
      $text = str_replace(" 詳細", "", $text);
      $_rest_info = true;
    }
    foreach($rest_shortNames as $short => $long){
      if($text === $short){
        $short_term = true;
        $rest_term = true;
        if(count($long) == 1){
          array_push($short_hits, $long);
          if($_rest_info){
            $rest_info = true;
            $text = $long;
          }
        }else{
          for($i=0; $i<count($long); $i++){
            array_push($short_hits, $long[$i]);
          }
        }
      }
    }
  }


//レストラン系の返信
  if($rest_term){
    if(!isset($rest_names)){
      array_push($response, response("text", "閉園中です。"));
    }else if(!$rest_info){
      if(count($short_hits) == 1){
        //レストラン名（単品）の時
        $response = rest_one_response($response, $htmlR, $short_hits[0]);

      }else if(count($short_hits) > 1){

        $response = rest_multi_response($response, $htmlR, $short_hits);
      }
        
    }else{
      //レストラン詳細情報
      $rest_id = get_rest_id($text);
      $wait_timeR = get_wait_timeR_official($htmlR, $text);
      if($rest_id != ""){
        $info = get_rest_info($rest_id);
        for($k=0; $k<count($info); $k+=2){
          $info_text .= $info[$k]."\n".$info[$k+1];
          if($k+2 < count($info)){
            $info_text .= "\n";
          }
        }
      }else{
        $info_text = "詳細情報は現地にてご確認ください😥";
      }
      
      if($wait_timeR["status"] == ""){
        if($info_text === "詳細情報は現地にてご確認ください😥"){
          $wait_text = "";
        }else{
          $wait_text = "詳細情報は現地にてご確認ください😥";
        }
      }else{
        if(isset($wait_time["open_time"])){
          $wait_text = "\n営業時間：".$wait_timeR["open_time"];
        }
        $wait_text .= "\n【現在待ち時間：".$wait_timeR["status"]."】";
        
        if(mb_strlen($wait_timeR["reload_time"], "utf-8") > 1){
          $wait_text .= "\n".$wait_timeR["reload_time"];
        }
        if($wait_timeR["pre"]){
          $wait_text .= "\nご利用には事前受付が必要です。";
        }
      }
      $rest_info_text = "🍴".$text."\n";
      $rest_info_text .= $info_text;
      if($wait_text != ""){
        $rest_info_text .= "\n".$wait_text;
      }
      array_push($response, response("text", $rest_info_text));
    }
  }



  if(count($response) == 0){
  
    $once = false;
    while((!$short_term && !$once) || (count($short_hits) != 0 && $short_term)){
      if($short_term){
        $text = $short_hits[0];
        if($attr_term){
          array_splice($short_hits, 0, 1);
        }
      }
      $once = ture;

      for($i=0; $i<count($area_names); $i++){
        if($text == $area_names[$i]." アトラクション"){
          for($j=0; $j<count($attr_names[$i]); $j++){
            $attr_list_text .= "🎢".$attr_names[$i][$j];
            $wait_time = get_wait_time($html, $attr_names[$i][$j]);
            $attr_list_text .= "\n【現在待ち時間：".$wait_time[1]."】 ".$wait_time[2]." ".$wait_time[3];
            if($j != count($attr_names[$i])-1){
              $attr_list_text .= "\n\n";
            }
          }
          array_push($response, response("text", $attr_list_text));
          break;
        }else if($text == $area_names[$i]." レストラン"){
          $area_rest_names = array();
          foreach($rest_area as $key => $area_content){
            if($area_content == $area_names[$i]){
              array_push($area_rest_names, $key);
            }
          }
          if($area_rest_names[0] == ""){
            array_push($response, response("text", "閉園中です。"));
          }

          $response = rest_multi_response($response, $htmlR, $area_rest_names);
          

        }else{
          //アトラクション名の時
          if($attr_term){
            for($j=0; $j<count($attr_names[$i]); $j++){
              if($text == $attr_names[$i][$j]){
                $wait_time = get_wait_time($html, $attr_names[$i][$j]);
                $wait_time[1] .= "】"; 
                $info = get_attr_info($wait_time[0]);
                for($k=0; $k<count($info); $k+=2){
                  $info_text .= $info[$k]."：".$info[$k+1];
                  if($k+2 < count($info)){
                    $info_text .= "\n";
                  }
                }
                $wait_text = $wait_time[1]." ".$wait_time[2];
                if(mb_strlen($wait_time[3], "utf-8") > 1){
                  $wait_text .= "\n".$wait_time[3];
                }
          
                array_push($response, response("text", "🎢".$attr_names[$i][$j]."\n".$info_text."\n\n【現在待ち時間：".$wait_text));
                $info_text = "";
                $wait_text = "";
                break 2;
              }
            }
          }
        }
      }
    }
  }
}


//平文への返信
if(count($response) == 0){
  $response = plane_response($response, $land_arealist);
}


var_dump($response);
//データ送信-----------------------------------------------
if(isset($type)){
  //単品データの場合
  if(count($response['type']) == 1){
    $post_data = array(
      "replyToken" => $replyToken,
      "messages" => array($response)
    );
  //複数データの場合
  }else{
    $post_data = array(
    "replyToken" => $replyToken,
    "messages" => $response
    );
  }


//pushの場合
}else{
  $post_data = array(
    "to" => $userId,
    "messages" => array($response)
  );
}

if(isset($type)){
  $ch = curl_init("https://api.line.me/v2/bot/message/reply");
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json; charser=UTF-8',
    'Authorization: Bearer ' . $accessToken
  ));
  $result = curl_exec($ch);
  curl_close($ch);
}else{
  $ch = curl_init("https://api.line.me/v2/bot/message/push");
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json; charser=UTF-8',
    'Authorization: Bearer ' . $accessToken
  ));
  $result = curl_exec($ch);
  curl_close($ch);

  //var_dump($result);
}
//返信関数--------------------------------------------------
function areaName_response($response, $land_arealist, $key){

  array_push($response, response("text", $key."の何が知りたい？"));
  $menu_buttons = array("アトラクション待ち時間🎢", "レストラン情報🍴", "トイレ位置🚽");
  $menu_responses = array($key." アトラクション", $key." レストラン", $key." トイレ");
  array_push($response, temp_buttons_res($land_arealist[$key]["thumbs"], $key, $land_arealist[$key]["discription"], $menu_buttons, $menu_responses));
  return $response;
}

function areaToilet_response($response, $area){
  $toilet = get_toilet();
      $toilet_list = "";
      $temp_area_nameL = "";
      $toilet_cnt = 0;
      foreach($toilet as $key => $toile){
        if($area == $toile["area"]){
          $toile_lat = $toile["lat"];
          $toile_lng = $toile["lng"];
          $toile_num = $toile["num"];
          array_push($response, response("location", $toile_lat, $toile_lng, "🚽".$key, $toile_num."室"));
      }
    }
  return $response;
}

function doubleArea_response($response, $land_arealist){
  array_push($response, response("text", "何が知りたい？"));
      $areas = array("クリッターカントリー", "ウエスタンランド");
      $labels = array();
      $re_messages = array();
      $label = array("アトラクション待ち時間🎢", "レストラン情報🍴", "トイレ位置🚽");
      for($i=0; $i<count($areas); $i++){
        array_push($labels, $label);
        $re_message = array($areas[$i]." アトラクション", $areas[$i]." レストラン", $areas[$i]." トイレ");
        array_push($re_messages, $re_message);
      }
      $menu_thumbs = array($land_arealist[$areas[0]]["thumbs"], $land_arealist[$areas[1]]["thumbs"]);

      $menu_discriptions = array($land_arealist[$areas[0]]["discription"], $land_arealist[$areas[1]]["discription"]);


    array_push($response, temp_car_mess_res($menu_thumbs, $areas, $menu_discriptions, $labels, $re_messages));

    return $response;
}

function rest_one_response($response, $htmlR, $rest_name){
  $rest_id = get_rest_id($rest_name);
  $wait_timeR = get_wait_timeR_official($htmlR, $rest_name);
  $discription_text = "";
  if($rest_id != ""){
    $info = get_rest_info($rest_id);
  }else{
    unset($info);
  }

  $thumbs = array();
  $discription = array();
  $buttons = array();
  $return = array();
  for($i=0; $i<count($info); $i++){
    if(preg_match('/キーワード：/', $info[$i])){
      $info[$i] = str_replace("キーワード：", "", $info[$i]);
      $keyword = $i;
    }
  }
  if($wait_timeR["pre"]){
    $discription_text = "ご利用には事前受付が必要です。\n【現在待ち時間：".$wait_timeR["status"]."】";
      unset($info);
    }else{
      $discription_text = "【現在待ち時間：".$wait_timeR["status"]."】";
    }
    if($wait_timeR["status"] == ""){
      $discription_text = "閉園中です。";
    }else if(isset($info)){
      $discription_text .= "\n".$info[$keyword];
    }
    $thumb = "https://nkmr.io/linebot/hikawa/disney_img/land_rest2/".$rest_name.".jpg";
    $button = "詳しく調べる🔍";
    $return = $rest_name." 詳細";
                
    array_push($response, temp_buttons_res($thumb, $rest_name, $discription_text, $button, $return));
    return $response;

}

function rest_multi_response($response, $htmlR, $rest_names){
  //レストラン名（複数）の時
  $thumbs = array();
  $discription = array();
  $buttons = array();
  $return = array();

  while(count($rest_names) > 5){
    for($j=0; $j<5; $j++){
      $rest_name = $rest_names[$j];
      $rest_id = get_rest_id($rest_name);
      $wait_timeR = get_wait_timeR_official($htmlR, $rest_name);
      $discription_text = "";
      if($rest_id != ""){
        $info = get_rest_info($rest_id);
      }else{
        unset($info);
      }
      for($i=0; $i<count($info); $i++){
        if(preg_match('/キーワード：/', $info[$i])){
          $info[$i] = str_replace("キーワード：", "", $info[$i]);
          $keyword = $i;
        }
      }
      if($wait_timeR["pre"]){
        $discription_text = "ご利用には事前受付が必要です。\n【現在待ち時間：".$wait_timeR["status"]."】";
        unset($info);
      }else{
        $discription_text = "【現在待ち時間：".$wait_timeR["status"]."】";
      }
      if($wait_timeR["status"] == ""){
        $discription_text = "閉園中です。";
      }else if(isset($info)){
        $discription_text .= "\n".$info[$keyword];
      }
      array_push($discription, $discription_text);
      array_push($thumbs, "https://nkmr.io/linebot/hikawa/disney_img/land_rest2/".$rest_name.".jpg");
      array_push($buttons, array("メニューを調べる🔍"));
      array_push($return, array($rest_name." 詳細"));
    }
    array_push($response, temp_car_mess_res($thumbs, $rest_names, $discription, $buttons, $return));
    $thumbs = array();
    $discription = array();
    $buttons = array();
    $return = array();
    array_splice($rest_names, 0, 5);
  }
  if(count($rest_names) <= 5 && count($rest_names) != 0){
    for($j=0; $j<count($rest_names); $j++){
      $rest_name = $rest_names[$j];
      $rest_id = get_rest_id($rest_name);
      $wait_timeR = get_wait_timeR_official($htmlR, $rest_name);
      $discription_text == "";
      if($rest_id != ""){
        $info = get_rest_info($rest_id);
      }else{
        unset($info);
      }
      for($i=0; $i<count($info); $i++){
        if(preg_match('/キーワード：/', $info[$i])){
          $info[$i] = str_replace("キーワード：", "", $info[$i]);
          $keyword = $i;
        }
      }
      if($wait_timeR["pre"]){
        $discription_text = "ご利用には事前受付が必要です。\n【現在待ち時間：".$wait_timeR["status"]."】";
        unset($info);
      }else{
        $discription_text = "【現在待ち時間：".$wait_timeR["status"]."】";
      }
      if($wait_timeR["status"] == ""){
        $discription_text = "閉園中です。";
      }else if(isset($info)){
        $discription_text .= "\n".$info[$keyword];
      }
      array_push($discription, $discription_text);
      array_push($thumbs, "https://nkmr.io/linebot/hikawa/disney_img/land_rest2/".$rest_name.".jpg");
      array_push($buttons, array("メニューを調べる🔍"));
      array_push($return, array($rest_name." 詳細"));
    }
    array_push($response, temp_car_mess_res($thumbs, $rest_names, $discription, $buttons, $return));
  }
  return $response;
}


function toilet_response($response){
  $toilet = get_toilet();
  array_push($response, response("text", "🚽トイレ一覧🚽"));
      $toilet_list = "";
      $temp_area_nameL = "";
      $toilet_cnt = 0;
      foreach($toilet as $key => $toile){
        if($temp_area_nameL != $toile["area"]){
          if($temp_area_nameL != ""){
            $toilet_list .= "\n";
          }
          $toilet_list .= $toile["area"]."\n";
          $temp_area_nameL = $toile["area"];
        }
        $toilet_list .= "🚽".$key;
        $toilet_cnt++;
        if(count($toilet) != $toilet_cnt){
          $toilet_list .= "\n";
        }
      }
    array_push($response, response("text", $toilet_list));
  return $response;
}

function popcorn_response($response){
  $popcorn = get_popcorn();
  array_push($response, response("text", "🍿ポップコーン一覧🍿"));
        $pop_mess = "";
        $pop_loopCnt = 1;
      foreach($popcorn as $key => $elements){
        $pop_mess .= "🍿".$key."\n";
        $pop_mess .= $elements["taste"]."味";

        //$pop_mess .= $elements["bucket"]."\n";
        if($pop_loopCnt != count($popcorn)){
        $pop_mess .= "\n\n";
        }
        $pop_loopCnt++;
      }
    array_push($response, response("text", $pop_mess));
  return $response;
}

function location_response($response, $lat, $lng){
  $land_arealist = get_land_arealist();
  //各エリアとの直線距離を求める
  $line_distance = array();
  foreach($land_arealist as $key => $area){
    $dis = location_distance($lat, $lng, $area["lat"], $area["lng"]);
    array_push($line_distance, $dis["distance_unit"]);
  }

  //一番近いエリアを返信
  $num = find_min($line_distance);
  
  $i = 0;
  foreach($land_arealist as $key => $member){
    if($i == $num){
      $word = $key;
    }
    $i++;
  }

  $html = get_disney_html("land");

  $attr_area = array();
  $attr_area = get_disney_attr_area($html);

  $area_names = array();
  $attr_names = array();
  $attr_array = array();
  $temp_area_name = "";


  foreach($attr_area as $attr_name => $area_name){
    if($temp_area_name != $area_name){
      array_push($area_names, $temp_area_name);
      array_push($attr_names, $attr_array);
      $attr_array = array();
    }
    array_push($attr_array, $attr_name);
    $temp_area_name = $area_name;
  }

  array_push($area_names, $area_name);
  array_push($attr_names, $attr_array);
  $area_names = array_splice($area_names, 1, count($area_names)-1);
  $attr_names = array_splice($attr_names, 1, count($attr_names)-1);

  for($i=0; $i<count($area_names); $i++){
    if($word == $area_names[$i]){
      for($j=0; $j<count($attr_names[$i]); $j++){
        $attr_list_text .= "🎢".$attr_names[$i][$j];
        $wait_time = get_wait_time($html, $attr_names[$i][$j]);
        $attr_list_text .= "\n【現在待ち時間：".$wait_time[1]."】 ".$wait_time[2];
        if($j != count($attr_names[$i])-1){
          $attr_list_text .= "\n\n";
        }
      }
      array_push($response, response("text", "最寄りエリア：".$word));
      array_push($response, response("text", $attr_list_text));
      break;
    }
  }

  //最寄りトイレを返信
  $toilet = get_toilet();
  $temp_cnt = 0;
  $temp_dis = -1;
  $toilet_cnt = 0;
  $toilet_line_distance = array();
  foreach($toilet as $key => $toilet_name){
    $toilet_dis = location_distance($lat, $lng, $toilet_name["lat"], $toilet_name["lng"]);
    if($toilet_dis["distance_unit"] < $temp_dis || $temp_dis == -1){
      $temp_dis = $toilet_dis["distance_unit"];
      $temp_cnt = $toilet_cnt;
    }
    $toilet_cnt++;
  }
  $toilet_cnt = 0;
  $near_toilet = "";
  $toilet_area;
  foreach ($toilet as $key => $toilet_name){
    if($temp_cnt == $toilet_cnt){
      $near_toilet = $key;
      $toilet_area = $toilet_name["area"];
      $toilet_lat = $toilet_name["lat"];
      $toilet_lng = $toilet_name["lng"];
    }
    $toilet_cnt++;
  }
  //array_push($response, response("text", "最寄りトイレ：\n🚽".$near_toilet."\nエリア：".$toilet_area));
  array_push($response, response("location", $toilet_lat, $toilet_lng, "最寄りトイレ", "🚽".$near_toilet));

   //最寄りポップコーンを返信
  $popcorn = get_popcorn();
  $temp_cnt = 0;
  $temp_dis = -1;
  $pop_cnt = 0;
  $pop_line_distance = array();
  foreach($popcorn as $key => $pop_name){
    $pop_dis = location_distance($lat, $lng, $pop_name["lat"], $pop_name["lng"]);
    if($pop_dis["distance_unit"] < $temp_dis || $temp_dis == -1){
      $temp_dis = $pop_dis["distance_unit"];
      $temp_cnt = $pop_cnt;
    }
    $pop_cnt++;
  }
  $pop_cnt = 0;
  $near_pop = "";
  $pop_area;
  foreach ($popcorn as $key => $pop_name){
    if($temp_cnt == $pop_cnt){
      $near_pop = $key;
      $pop_area = $pop_name["area"];
      $pop_lat = $pop_name["lat"];
      $pop_lng = $pop_name["lng"];
      $pop_taste = $pop_name["taste"];
    }
    $pop_cnt++;
  }
  array_push($response, response("location", $pop_lat, $pop_lng, "最寄りポップコーン", "🍿".$near_pop."（".$pop_taste."味）"));
  return $response;
}

//天気予報を返します
function weather_response($response){
  $filename = "Urayasu_weather.txt";
  
  $file = fopen($filename, "r");
  $weather_info = array();
  while($line = fgets($file)){
    array_push($weather_info, $line);
  }
  fclose($file);

  $file_time = $weather_info[0];
  $now_time = time();

  //現在時刻とtxtファイルの更新時間の差が2時間以上だった場合はapi発動
  if($now_time - $file_time >= 7200){
    $file_w = fopen($filename, "w");
    $url = "http://api.openweathermap.org/data/2.5/forecast";
    $query = "?q=Urayasu,jp&APPID=";//apiIDを入れる
    $weather_json = file_get_contents($url.$query);

    fwrite($file_w, $now_time."\n".$weather_json);
    fclose($file_w);

  }else{
    $weather_json = $weather_info[1];
  }
 

  $weather_array = json_decode($weather_json, true);

  $temp = array();
  $temp_min = array();
  $temp_max = array();
  $main = array();
  $main_des = array();
  $day = array();
  $japan = array();
  date_default_timezone_set('UTC');
  for($i=0; $i<40; $i++){
    if(isset($weather_array["list"][$i]["dt_txt"])){
      
      //時間はUTC
      array_push($day, $weather_array["list"][$i]["dt_txt"]);
      $t = new DateTime($day[$i]);
      $t -> setTimeZone(new DateTimeZone('Asia/Tokyo'));
      array_push($japan, $t->format('Y-m-d H:i:s'));

      //気温の単位はケルビン(K)
      //℃ = K-273.15
      array_push($temp_min, $weather_array["list"][$i]["main"]["temp_min"]-273.15);
      array_push($temp_max, $weather_array["list"][$i]["main"]["temp_max"]-273.15);

      
      array_push($main, $weather_array["list"][$i]["weather"][0]["main"]);
      array_push($main_des, $weather_array["list"][$i]["weather"][0]["description"]);
    }
  }


  $weather_vote[6][4];
  $date_list = array();
  $date_min_temp = array();
  $date_max_temp = array();
  $min_temp_temp = array();
  $max_temp_temp = array();

  for($i=0; $i<count($day); $i++){
    $date = substr($japan[$i], 0, 10);
    if($date_list[count($date_list)-1] != $date){
      array_push($date_list, $date);
      if(count($date_list)-1 != 0){
        array_push($date_min_temp, $min_temp_temp);
        array_push($date_max_temp, $max_temp_temp);
      }
      $min_temp_temp = array();
      $max_temp_temp = array();
    }

    array_push($min_temp_temp, $temp_min[$i]);
    array_push($max_temp_temp, $temp_max[$i]);

  

    //天気の優先度、配列上の天気の番号でもある
    switch($main[$i]){
      case "Clear":
        $weather_point = 0;
      break;
      case "Clouds":
        $weather_point = 1;
      break;
      case "Rain":
        $weather_point = 2;
      break;
      case "Drizzle":
        $weather_point = 3;
      break;
      case "Thunderstorm":
        $weather_point = 4;
      break;
      case "Extreme":
        $weather_point = 5;
      break;
      case "Additional":
        $weather_point = 6;
      break;
      case "Atmosphere":
        $weather_point = 7;
      break;
      case "Snow":
        $weather_point = 8;
      break;
    }

    $time = substr($japan[$i], 11, 2); //時間の部分だけget!
    //時間ごと重みをつけて日付ごとの天気を投票する
    switch((int)$time){
      case 0:
      case 3:
        $weather_vote[count($date_list)-1][$weather_point] += 0;
      break;
      case 6:
        $weather_vote[count($date_list)-1][$weather_point] += 1;
      break;
      case 21:
        $weather_vote[count($date_list)-1][$weather_point] += 2;
      break;
      case 9:
        $weather_vote[count($date_list)-1][$weather_point] += 2.5;
      case 12:
      case 15:
      case 18:
        $weather_vote[count($date_list)-1][$weather_point] += 3;
      break;
    }
  }

  array_push($date_min_temp, $min_temp_temp);
  array_push($date_max_temp, $max_temp_temp);

  $main_weather = array();
  $max_max_temp = array();
  $min_min_temp = array();
  //天気の開票
  for($i=0; $i<count($date_list); $i++){

    array_push($min_min_temp, min($date_min_temp[$i]));
    array_push($max_max_temp, max($date_max_temp[$i]));

    $max_vote = max($weather_vote[$i][0],$weather_vote[$i][1],$weather_vote[$i][2],$weather_vote[$i][3]);


    array_push($main_weather, 0);
    for($j=0; $j<4; $j++){
      if($max_vote == $weather_vote[$i][$j]){
        $main_weather[$i] = $j;
      }
    }
  }


  $day_main_weather = array();
  for($i=0; $i<count($main_weather); $i++){
    switch($main_weather[$i]){
      case 0:
        array_push($day_main_weather, "晴れ");
      break;
      case 1:
        array_push($day_main_weather, "曇り");
      break;
      case 2:
        array_push($day_main_weather, "雨");
      break;
      case 3:
        array_push($day_main_weather, "Drizzle");
      break;
      case 4:
        array_push($day_main_weather, "Thunderstorm");
      break;
      case 5:
        array_push($day_main_weather, "Extreme");
      break;
      case 6:
        array_push($day_main_weather, "Additional");
      break;
      case 7:
        array_push($day_main_weather, "Atmosphere");
      break;
      case 8:
        array_push($day_main_weather, "雪");
      break;
    }
  }
  
  $title = array();
  $text = array();
  $thumb = array();
  $return_text = array();
  $label = array();
  for($i=0; $i<5; $i++){
    $date_list[$i] = substr($date_list[$i], 5, 7);
    $date_list[$i] = str_replace("-", "", $date_list[$i]);
    $date_list[$i] = preg_replace("/^.{0,2}+\K/us", "月", $date_list[$i]);
    $date_list[$i] .= "日";
    array_push($title, $date_list[$i]);

    array_push($text, "天気：".$day_main_weather[$i]."\n最高気温：".floor($max_max_temp[$i])."℃\n最低気温：".floor($min_min_temp[$i])."℃");
    array_push($thumb, "https://nkmr.io/linebot/hikawa/weather_img/".$day_main_weather[$i].".png");
    array_push($return_text, array($date_list[$i]."の3時間ごとの天気は？"));
    array_push($label, array("詳しい天気🔎"));
  }


  array_push($response, temp_car_mess_res($thumb, $title, $text, $label, $return_text));
  return $response;
}

//その日の3時間毎の天気を返します
function detail_weather_response($response, $text){
  $text = str_replace("の3時間ごとの天気は？", "", $text);
  $filename = "Urayasu_weather.txt";
  
  $file = fopen($filename, "r");
  $weather_info = array();
  while($line = fgets($file)){
    array_push($weather_info, $line);
  }
  fclose($file);

  $file_time = $weather_info[0];
  $now_time = time();

  //現在時刻とtxtファイルの更新時間の差が2時間以上だった場合はapi発動
  if($now_time - $file_time >= 7200){
    $file_w = fopen($filename, "w");
    $url = "http://api.openweathermap.org/data/2.5/forecast";
    $query = "?q=Urayasu,jp&APPID="; // apiID入れる
    $weather_json = file_get_contents($url.$query);

    fwrite($file_w, $now_time."\n".$weather_json);
    fclose($file_w);

  }else{
    $weather_json = $weather_info[1];
  }
 

  $weather_array = json_decode($weather_json, true);


  $day = array();
  $japan = array();
  date_default_timezone_set('UTC');
  for($i=0; $i<40; $i++){
    if(isset($weather_array["list"][$i]["dt_txt"])){
      
      //時間はUTC
      array_push($day, $weather_array["list"][$i]["dt_txt"]);
      $t = new DateTime($day[$i]);
      $t -> setTimeZone(new DateTimeZone('Asia/Tokyo'));
      array_push($japan, $t->format('Y-m-d H:i:s'));
      $date = substr($japan[$i], 5, 5);
      $date_time = substr($japan[$i], 11, 5);
      //$text = str_replace("年", "-", $text);
      $text = str_replace("月", "-", $text);
      $text = str_replace("日", "", $text);
      if($text == $date && ($date_time == "09:00" || $date_time == "12:00" || $date_time == "15:00" || $date_time == "18:00" || $date_time == "21:00")){
        //気温の単位はケルビン(K)
        //℃ = K-273.15
        $temp = $weather_array["list"][$i]["main"]["temp"]-273.15;
        $temp = floor($temp);
        $hum = $weather_array["list"][$i]["main"]["humidity"];
        $main = $weather_array["list"][$i]["weather"][0]["main"];
        $main_des = $weather_array["list"][$i]["weather"][0]["description"];
        switch($main){
          case "Clear":
            $main_res = "晴れ";
          break;
          case "Clouds":
            $main_res = "曇り";
          break;
          case "Rain":
            $main_res = "雨";
          break;
          case "Drizzle":
            $main_res = "霧雨";
          break;
          case "Thunderstorm":
            $main_res = "雷";
          break;
          case "Extreme":
            $main_res = "嵐";
          break;
          case "Additional":
            $main_res = "その他";
          break;
          case "Atmosphere":
            $main_res = "嵐";
          break;
          case "Snow":
            $main_res = "雪";
          break;
        }
        array_push($response, response("text", $date_time."\n天気：".$main_res."\n気温：".$temp."℃\n湿度：".$hum."%"));
      }
    }
  }
  if(count($response) == 0){
    array_push($response, response("text", "詳細な情報はありませんでした...😩"));
  }
  return $response;
}


function plane_response($response, $land_arealist){
  
    array_push($response, response("text", "検索ワードがヒットしませんでした...😥"));
    return $response;
}


//関数エリア-----------------------------------------------

function get_disney_html($park){
  $getURL = "http://tokyodisneyresort.info/realtime.php?park=$park&order=area";
  $html = file_get_contents($getURL);
  $html = str_replace("(", "（", $html);
  $html = str_replace(")", "）", $html);
  $html = str_replace("&amp;", "＆", $html);

  return $html;
}


function get_disney_rest_html_official($park){

  // Cookie情報を保存する一時ファイルディレクトリにファイルを作成します
  $tmp_path =  tempnam(sys_get_temp_dir(), "CKI");

  $url = "http://"; //スクレイピング先URL

  $ch = curl_init(); // はじめ

  curl_setopt($ch, CURLOPT_URL, $url); 
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  //Cookie受信
  //cookieオプション
  curl_setopt($ch,CURLOPT_COOKIEFILE,$tmp_path);
  curl_setopt($ch, CURLOPT_COOKIEJAR, $tmp_path);
  curl_exec($ch);//実行
  curl_close($ch); //終了


  $url = "http:"; //スクレイピング先URL
  $ch = curl_init(); // はじめ

  curl_setopt($ch, CURLOPT_URL, $url); 
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  //Cookie送信
  //cookieオプション
  curl_setopt($ch,CURLOPT_COOKIEFILE,$tmp_path);
  curl_setopt($ch, CURLOPT_COOKIEJAR, $tmp_path);

  $html = curl_exec($ch);//実行
  curl_close($ch);



  //一時ファイル削除
  unlink($tmp_path);
  return $html;

}

function get_disney_attr_area($html){
  $area_html = explode("<h3>", $html);
  $area_pattern = '/(.+)<\/h3>\n\t*.+/';
  $attr_pattern = '/<a href=".+">\n\t.+s*p*a*n*>*\s(.+)\n\s+<\/a>/';

  for($i=0; $i<count($area_html); $i++){
    preg_match($area_pattern, $area_html[$i], $area_name);
    preg_match_all($attr_pattern, $area_html[$i], $attr_names);
    if(count($attr_names[1])!=0){
      for($j=0; $j<count($attr_names[1]); $j++){
        $attr_area[$attr_names[1][$j]] = $area_name[1];
      }
    }
  }
  return $attr_area;
}


//レストラン待ち時間（公式HP版！）
function get_disney_rest_area_official($html){


  $html = str_replace("（２Ｆ）", "", $html);

  $area_html = explode("themeName", $html); //htmlをエリアごとに区切る

  $area_pattern = '/<p>(.+)<\/p>\s<\/h2>/'; //エリア名の抽出パターン

  $rest_pattern = '/\s*<*s*p*a*n*\s*c*l*a*s*s*=*"*o*p*e*r*a*t*i*n*g*-*c*h*g*"*>*\s*<*b*>*N*E*W*<*\/*b*>*\s*<*\/*s*p*a*n*>*\s(.+)\s<\/h3>\s<p\sclass="run">/'; //レストラン名の抽出パターン（*はNEWマークを消すための例外処理）
  for($i=1; $i<count($area_html); $i++){
    preg_match($area_pattern, $area_html[$i], $area_name);

    $rest_html = explode("<h3>", $area_html[$i]); //htmlをレストランごとに区切る
    for($j=1; $j<count($rest_html); $j++){
      if(!preg_match('/運営状況は施設でご確認ください。/', $rest_html[$j]) && !preg_match('/イベントブース/', $rest_html[$j])){ //ワゴン系は取り除く
        preg_match($rest_pattern, $rest_html[$j], $match);
      
        if($match[1] != ""){
          $rest_area[$match[1]] = $area_name[1];
        }
      }
    }
  }

  //var_dump($rest_area);
  return $rest_area;
}

//array(id,待ち時間,更新時間)を返します
function get_wait_time($html, $attr_name){
  $wait_pattern = '/\<a href=".+attr_id=(\d{1,3})"\>\n\t+.+s*p*a*n*>*\s'.$attr_name.'\n\t+.+\n\t+.+\n\t\t*(.+)\n\t+.+\>(.+)\<\/span\>\n\t+(.+)\<\/div\>/';


  preg_match($wait_pattern, $html, $wait_time);

  if(count($wait_time) == 0){

    $wait_pattern2 = '/\<a href=".+attr_id=(\d{1,3})"\>\n\t+.+s*p*a*n*>*\s'.$attr_name.'\n\t+.+\n\t+.+\n\t+(.+)\n\t+(.+)\</';

    preg_match($wait_pattern2, $html, $wait_time);
  }

  $wait_time = array_splice($wait_time, 1, count($wait_time)-1);
  $wait_time[2] = str_replace("[", "(", $wait_time[2]);
  $wait_time[2] = str_replace("]", ")", $wait_time[2]);

  return $wait_time;
}


//レストランの基本情報を返します
function get_wait_timeR_official($html, $rest_name){

  $rest_html = explode("<a", $html); 

  for($i=1; $i<count($rest_html); $i++){
    if(preg_match('/'.preg_quote($rest_name, '/').'/', $rest_html[$i])){ //ワゴン系は取り除く)
    


      if(preg_match('/ご利用には事前受付が必要です。/', $rest_html[$i])){
        $pre = true;
      }else{
        $pre = false;
      }

      //待ち時間の抽出
      $wait_pattern = '/<strong>\s(.*)\s<span\sclass="minute">(.*)<\/span>\s(.*)\s<span\sclass="minute">(.*)<\/span>\s<\/strong>\s<\/p>\s<\/div>/';
      preg_match($wait_pattern ,$rest_html[$i], $wait_time);
      if(!isset($wait_time[0])){
        $wait_pattern = '/<strong>\s(.*)\s<span\sclass="minute">(.*)<\/span>\s<\/strong>/';
        preg_match($wait_pattern ,$rest_html[$i], $wait_time);
      }
      for($j=1; $j<count($wait_time); $j++){
        $wait_time_text .= $wait_time[$j];
      }

      //開店時間等の抽出
      $rest_pattern = '/<h3>\s*<*s*p*a*n*\s*c*l*a*s*s*=*"*o*p*e*r*a*t*i*n*g*-*c*h*g*"*>*\s*<*b*>*N*E*W*<*\/*b*>*\s*<*\/*s*p*a*n*>*\s'.preg_quote($rest_name, '/').'\s<\/h3>\s<p\sclass="run">.+<\/p><div\sclass="op-left">\s(.+)\s<\/div>\s<div\sclass="op-right">\s(.+)\s<br>\s<\/div>\s<p/';
      preg_match($rest_pattern, $rest_html[$i], $match);

      //更新時間の抽出
      $update_pattern = '/update">(.+)<\/p>\s<\/a>/';
      preg_match($update_pattern, $rest_html[$i], $update);

    }
  }

  if(!isset($wait_time_text)){
    $wait_time_text = "案内終了";
  }
  $wait_time_array = array(
    "status" => $wait_time_text,
    "open_time" => $match[1],
    "op_time2" => $match[2],
    "reload_time" => $update[1],
    "pre" => $pre
  );
  return $wait_time_array;

}



//アトラクションの詳しい情報
function get_attr_info($attr_id){
  $getURL = "http://".$attr_id; //スクレイピング先URL
  $html2 = file_get_contents($getURL);


  $info_pattern = '/\<table\>\n.+\n\t.+\>(.+)\<.+\n\t.+\>(.+)\<.+\n.+\n.+\n\t.+\>(.+)\<.+\n\t.+\>(.+)\<.+\n.+\n\t.+\>(.+)\<.+\n\t.+\>(.+)\n.+\n.+\n\t.+\>(.+)\<.+\n\t.+\>(.+)\<.+\n.+\n\<\/table\>/';

  preg_match($info_pattern, $html2, $info_match);

  if(count($info_match) == 0){
    $info_pattern2 = '/\<table\>\n.+\n\t.+\>(.+)\<.+\n\t.+\>(.+)\<.+\n.+\n.+\n\t.+\>(.+)\<.+\n\t.+\>(.+)\<.+\n\<\/table\>/';
    
    preg_match($info_pattern2, $html2, $info_match);
  }

  $info_match = array_splice($info_match, 1, count($info_match)-1);


  return $info_match;
}


//アトラクションの詳しい情報
function get_rest_info($rest_id){
    $return_array = array();
    $rest_getURL = = "http://".$rest_id; //スクレイピング先URL;

    $rest_html = file_get_contents($rest_getURL);
    $rest_html = str_replace("\n", "", $rest_html);


    //レストランの基本情報を取ってくる
    $info_pattern = '/<h3>基本情報<\/h3>(.+)<h3>メニュー<\/h3>(.+)\(公式HP/';

    preg_match($info_pattern, $rest_html, $info_match);

    
    //座席数などを取ってくる
    $info_match[1] = str_replace("<li>", "", $info_match[1]);
    $info_match[1] = str_replace("<div>", "", $info_match[1]);
    $info_match[1] = str_replace('<ul data-role="listview">', "", $info_match[1]);
    //echo htmlspecialchars($info_match[1])."<br>";
    $info = explode("</li>", $info_match[1]);
    
    for($i=0; $i<count($info)-1; $i++){
      $info[$i] = str_replace("場所：ディズニーランドの", "エリア：", $info[$i]);
      if(preg_match('/：/', $info[$i])){
        $info[$i] .= "\n";
      }
      
      $info[$i] = str_replace("\t", "", $info[$i]);
      array_push($return_array, $info[$i]);
      
    }
    //echo htmlspecialchars($info_match[2]);


    //レストランのメニューを取ってくる
    $menu = explode('<span style="float:right;">', $info_match[2]);
    $menus = array();
    $plices = array();

    for($i=0; $i<count($menu); $i++){

      $menu2 = explode('<li>', $menu[$i]);

      for($j=1; $j<count($menu2); $j+=2){
        array_push($menus, $menu2[$j]);
      }

      if($i != 0){
        $plice = explode('</span>', $menu[$i]);

        for($j=0; $j<count($plice); $j+=2){
          array_push($plices, $plice[$j]);
        }
      }



      $menu2 = array();
      $plice = array();

    }

    //レストランのメニューを表示
    for($i=0; $i<count($menus); $i++){
      $menus[$i] = str_replace("&nbsp;", " ", $menus[$i]);
      $menus[$i] = str_replace("&amp;amp;", "&&", $menus[$i]);
      array_push($return_array, $menus[$i]);
      $plices[$i] = str_replace("&nbsp;", " ", $plices[$i]);
      $plices[$i] = str_replace("&yen;", "¥", $plices[$i]);
      $plices[$i] = str_replace("&#44;", ",", $plices[$i]);
      array_push($return_array, str_replace("&yen;", "¥", $plices[$i]));
    }

    //レストランの運営状況を表示
    if(count($wait_timeR)!=0){
      array_push($return_array, "現在待ち時間：");
    }
    for($i=0; $i<count($wait_timeR)-2; $i++){

      if(preg_match("/$nbsp;/", $wait_timeR[$i+2])){
        $wait_timeR[$i+2] = str_replace("&nbsp;-&nbsp;", "〜", $wait_timeR[$i+2]);
        array_push($return_array, "営業時間");
      }
      if($i+2 == 3){
        if(preg_match("/$amp;/", $wait_timeR[$i+2])){
          $wait_timeR[$i+2] = "現在状況は施設でご確認ください。";
        }
      }


      array_push($return_array, htmlspecialchars($wait_timeR[$i+2]));
    }

    return $return_array;
}


//返信データ関数 (返すデータの種類, データの内容, データの内容その2)
function response($type, $contents, $contents2, $location_name, $address){
  //$contents2が未定義の場合は$contentsと同じにする
  if(!isset($contents2)){
    $contents2 = $contents;
  }

  //テキストを返信
  if($type === "text"){
    $response_format = array(
      "type" => "text",
      "text" => $contents
    );
  //画像を返信
  }else if($type === "image"){
    $response_format = array(
      "type" => "image",
      "originalContentUrl" => $contents,
      "previewImageUrl" => $contents2
    );
  //位置情報を返信
  }else if($type === "location"){
    $response_format = array(
      "type" => "location",
      "title" => $location_name,
      "address" => $address,
      "latitude" => $contents,
      "longitude" => $contents2
    );
  //スタンプを返信
  }else if($type === "sticker"){
    $response_format = array(
      "type" => "sticker",
      "packageId" => $contents,
      "stickerId" => $contents2
    );
  }

  

  return $response_format;
}


function temp_buttons_res($thumb, $title, $text, $label, $return_text){
  

if(count($label) != 3){
  $action = array(
    "type"=> "message",
    "label"=> $label,
    "text"=> $return_text
  );

  $response_format = array(
  "type" => "template",
  "altText" => "テンプレートメッセージ",
  "template" => array(
    "type" => "buttons",
    "thumbnailImageUrl" => $thumb,
    "title" => $title,
    "text" => $text,
    "actions" => array($action)
    )
  );

}else{
  $actions = array();

      for($i=0; $i<count($label); $i++){
          array_push($actions, 
            array(
              "type"=> "message",
              "label"=> $label[$i],
              "text"=> $return_text[$i]
            )
          );
      }
  $response_format = array(
  "type" => "template",
  "altText" => "テンプレートメッセージ",
  "template" => array(
    "type" => "buttons",
    "thumbnailImageUrl" => $thumb,
    "title" => $title,
    "text" => $text,
    "actions" => array($actions[0], $actions[1], $actions[2])
    )
    );
  }
  return $response_format;
}

/*テンプレートのカルーセルでメッセージを返すフォーマット関数(サムネのURL(1~5), タイトル(1~5), 説明文(1~5), タップするところのボタン(1~3), 返すメッセージ(1~5))
ボタンと返すメッセージは省略するとタイトルが入る
$label = array(1, 2);
$label = array(array(a, b, c), array(a, b, c));
配列の長さは()内の数字を参照*/
function temp_car_mess_res($thumb_url, $title, $text, $label, $return_text){


    $columns = array();

    $actions = array();




      

    for($i=0; $i<count($thumb_url); $i++){
      for($j=0; $j<count($label[0]); $j++){
          array_push($actions, 
            array(
              "type"=> "message",
              "label"=> $label[$i][$j],
              "text"=> $return_text[$i][$j]
            )
          );
        }
        
if(count($label[0]) == 1){
  array_push($columns,
          array(
            "thumbnailImageUrl"=> $thumb_url[$i],
            "title"=> $title[$i],
            "text"=> $text[$i],
            "actions"=> array($actions[$i*count($label[0])])
          )
        );
    
}else{
        array_push($columns,
          array(
            "thumbnailImageUrl"=> $thumb_url[$i],
            "title"=> $title[$i],
            "text"=> $text[$i],
            "actions"=> array($actions[$i*count($label[0])], $actions[$i*count($label[0])+1], $actions[$i*count($label[0])+2])
          )
        );
    }
}

    $response_format = array(
      "type"=> "template",
      "altText"=> "テンプレートメッセージ",
      "template"=> array(
        "type"=> "carousel",
        "columns"=> $columns
        )
      );



    return $response_format;
}




function location_distance($lat1, $lon1, $lat2, $lon2){
  $lat_average = deg2rad( $lat1 + (($lat2 - $lat1) / 2) );//2点の緯度の平均
  $lat_difference = deg2rad( $lat1 - $lat2 );//2点の緯度差
  $lon_difference = deg2rad( $lon1 - $lon2 );//2点の経度差
  $curvature_radius_tmp = 1 - 0.00669438 * pow(sin($lat_average), 2);
  $meridian_curvature_radius = 6335439.327 / sqrt(pow($curvature_radius_tmp, 3));//子午線曲率半径
  $prime_vertical_circle_curvature_radius = 6378137 / sqrt($curvature_radius_tmp);//卯酉線曲率半径
  
  //2点間の距離
  $distance = pow($meridian_curvature_radius * $lat_difference, 2) + pow($prime_vertical_circle_curvature_radius * cos($lat_average) * $lon_difference, 2);
  $distance = sqrt($distance);
  
  $distance_unit = round($distance);
  /*
  //$hoge['distance']で小数点付きの直線距離を返す（メートル）
  //$hoge['distance_unit']で整形された直線距離を返す（1000m以下ならメートルで記述 例:836m ｜ 1000m以下は小数点第一位以上の数をkmで記述 例:2.8km）
  */
  return array("distance" => $distance, "distance_unit" => $distance_unit);
}

function find_min(array $arr){
  $min = min($arr);
  $arrFind = array_keys($arr, $min);
  $key = array_rand($arrFind, 1);
  return $arrFind[$key];
}

//長い配列を返す関数---------------------------------------------------
function get_land_arealist(){
  //エリアごとの中心座標
$land_arealist = array(
 "ワールドバザール" => array(
  "lat" => 35.634255,
  "lng" => 139.879645,
  "discription" => "ヴィクトリア時代の優美な建物が軒を連ねるストリート。",
  "thumbs" => "https://nkmr.io/linebot/hikawa/disney_img/land_area/ワールドバザール.jpg",
  "attr_name" => $attr_names[6],
  "rest_name" => array(
    )
  ),
 "トゥモローランド" => array(
  "lat" => 35.632578,
  "lng" => 139.878449,
  "discription" => "この都市では, はるかなる宇宙への旅や, 感動のミュージカルショーを体験できます。",
  "thumbs" => "https://nkmr.io/linebot/hikawa/disney_img/land_area/トゥモローランド.jpg",
  "attr_name" => $attr_names[0],
  "rest_name" => array(
    )
  ),
 "トゥーンタウン" => array(
  "lat" => 35.630377,
  "lng" => 139.879535,
  "discription" => "ディズニーアニメーションのキャラクター（トゥーン）たちが住む, とびっきりハッピーな街へ。",
  "thumbs" => "https://nkmr.io/linebot/hikawa/disney_img/land_area/トゥーンタウン.jpg",
  "attr_name" => $attr_names[1],
  "rest_name" => array(
    )
  ),
 "ファンタジーランド" => array(
  "lat" => 35.631277,
  "lng" => 139.881335,
  "discription" => "白雪姫やピーターパン,プーさんなど, おなじみのキャラクターたちが魔法の冒険に連れて行ってくれます。",
  "thumbs" => "https://nkmr.io/linebot/hikawa/disney_img/land_area/ファンタジーランド.jpg",
  "attr_name" => $attr_names[2],
  "rest_name" => array(
    )
  ),
 "クリッターカントリー" => array(
  "lat" => 35.632289,
  "lng" => 139.882847,
  "discription" => "アメリカ河のほとりにある水びたしの赤土の山を利用して, 丸太のボートの急流下りを始めました。",
  "thumbs" => "https://nkmr.io/linebot/hikawa/disney_img/land_area/クリッターカントリー.jpg",
  "attr_name" => $attr_names[3],
  "rest_name" => array(
    )
  ),
 "ウエスタンランド" => array(
  "lat" => 35.632711,
  "lng" => 139.884506,
  "discription" => "開拓時代の西部。赤い岩山を背景にアメリカ河は悠々と流れ, 汽笛の音が風にのって届きます。",
  "thumbs" => "https://nkmr.io/linebot/hikawa/disney_img/land_area/ウエスタンランド.jpg",
  "attr_name" => $attr_names[4],
  "rest_name" => array(
    )
  ),
 "アドベンチャーランド" => array(
  "lat" => 35.633810,
  "lng" => 139.882189,
  "discription" => "ジャングルでは猛獣たちが, 暗やみの水路ではカリブの海賊が, スリリングな出会いを待っています。",
  "thumbs" => "https://nkmr.io/linebot/hikawa/disney_img/land_area/アドベンチャーランド.jpg",
  "attr_name" => $attr_names[5],
  "rest_name" => array(
    )
  )
);
  return $land_arealist;
}

function get_toilet(){
$toilet = array(
  "メインエントランス右" => array(
    "lat" => 35.634389,
    "lng" => 139.878791,
    "num" => 15,
    "area" => "ワールドバザール"
    ),
  "メインエントランス左" => array(
    "lat" => 35.634629,
    "lng" => 139.879550,
    "num" => 12,
    "area" => "ワールドバザール"
    ),
  "ゴーカート向かい" => array(
    "lat" => 35.632318,
    "lng" => 139.878898,
    "num" => 24,
    "area" => "トゥモローランド"
    ),
  "モンスターズインク向かい" => array(
    "lat" => 35.633647,
    "lng" => 139.879088,
    "num" => 41,
    "area" => "トゥモローランド"
    ),
  "トゥーンタウン奥" => array(
    "lat" => 35.629571,
    "lng" => 139.880186,
    "num" => 17,
    "area" => "トゥーンタウン"
    ),
  "ピーターパン右裏手" => array(
    "lat" => 35.632175,
    "lng" => 139.882024,
    "num" => 23,
    "area" => "ファンタジーランド"
    ),
  "ホーンテッドマンション右" => array(
    "lat" => 35.631061,
    "lng" => 139.882085,
    "num" => 22,
    "area" => "ファンタジーランド"
    ),
  "ハニハンファストパス横" => array(
    "lat" => 35.630920,
    "lng" => 139.880188,
    "num" => 27,
    "area" => "ファンタジーランド"
    ),
  "スプラッシュ出口横" => array(
    "lat" => 35.630511,
    "lng" => 139.883272,
    "num" => 22,
    "area" => "クリッターカントリー"
    ),
  "ビッグサンダー左奥" => array(
    "lat" => 35.632639,
    "lng" => 139.883528,
    "num" => 13,
    "area" => "ウエスタンランド"
    ),
  "いかだのりば向かい" => array(
    "lat" => 35.631984,
    "lng" => 139.883737,
    "num" => 5,
    "area" => "ウエスタンランド"
    ),
  "プラザバビリオンレストラン右" => array(
    "lat" => 35.632692,
    "lng" => 139.881418,
    "num" => 14,
    "area" => "ウエスタンランド"
    ),
  
  "トムソーヤ島船着場" => array(
    "lat" => 35.631642,
    "lng" => 139.883811,
    "num" => 5,
    "area" => "ウエスタンランド"
    ),
  "トムソーヤ島砦内" => array(
    "lat" => 35.631599,
    "lng" => 139.884743,
    "num" => 1,
    "area" => "ウエスタンランド"
    ),
  "カリブの海賊左奥" => array(
    "lat" => 35.634363,
    "lng" => 139.880687,
    "num" => 20,
    "area" => "アドベンチャーランド"
    ),
  "シアターオーリンズ左" => array(
    "lat" => 35.634344,
    "lng" => 139.881547,
    "num" => 12,
    "area" => "アドベンチャーランド"
    ),
  "ジャングルクルーズ左" => array(
    "lat" => 35.633687,
    "lng" => 139.882331,
    "num" => 7,
    "area" => "アドベンチャーランド"
    )
);
  return $toilet;
}

function get_popcorn(){
$popcorn = array(
  "スウィートハート・カフェ前" => array(
    "lat" => 35.633588,
    "lng" => 139.879691,
    "taste" => "キャラメル",
    "bucket" => "R2-D2"
    ),
  "ザ・ガゼーボ横" => array(
    "lat" => 35.633974,
    "lng" => 139.880705,
    "taste" => "ミルクチョコレート",
    "bucket" => "Mr.ポテトヘッド(クリスマスver.)"
    ),
  "カフェ・オーリンズ前" => array(
    "lat" => 35.634020,
    "lng" => 139.881292,
    "taste" => "しょうゆバター",
    "bucket" => "Mr.ポテトヘッド(クリスマスver.)"
    ),
  "ポリネシアンテラス・レストラン前" => array(
    "lat" => 35.633409,
    "lng" => 139.882272,
    "taste" => "キャラメル",
    "bucket" => "ミニーリボン"
    ),
  "トレーディングポスト横" => array(
    "lat" => 35.632643,
    "lng" => 139.882112,
    "taste" => "カレー",
    "bucket" => "Mr.ポテトヘッド(クリスマスver.)"
    ),
  "チャックワゴン横" => array(
    "lat" => 35.632200,
    "lng" => 139.882543,
    "taste" => "ソルト",
    "bucket" => "Mr.ポテトヘッド(クリスマスver.)"
    ),
  "蒸気船マークトウェイン号乗り場前" => array(
    "lat" => 35.631817,
    "lng" => 139.882856,
    "taste" => "カレー",
    "bucket" => "ミッキー"
    ),
  "キャッスルカルーセル横" => array(
    "lat" => 35.631523,
    "lng" => 139.881366,
    "taste" => "ミルクチョコレート",
    "bucket" => "ダンボ"
    ),
  "プーさんのハニーハント前" => array(
    "lat" => 35.630926,
    "lng" => 139.880092,
    "taste" => "ハニー",
    "bucket" => "Mr.ポテトヘッド(クリスマスver.)"
    ),
  /*"トゥーンポップ" => array(
    "lat" => 35.630377,
    "lng" => 139.879684,
    "taste" => "キャラメル",
    "bucket" => "ダンボ"
    ),
  "ポップ・ア・ロット・ポップコーン" => array(
    "lat" => 35.630014,
    "lng" => 139.879709,
    "taste" => "キャラメル",
    "bucket" => "Mr.ポテトヘッド(クリスマスver.)"
    ),*/
  "トレジャーコメット横" => array(
    "lat" => 35.632100,
    "lng" => 139.878713,
    "taste" => "しょうゆバター",
    "bucket" => "Mr.ポテトヘッド(クリスマスver.)"
    ),
  "ポッピングポッド" => array(
    "lat" => 35.632919,
    "lng" => 139.878377,
    "taste" => "キャラメル",
    "bucket" => "BB-8"
    )
);
  return $popcorn;
}

function get_attr_shortNames(){
$attr_shortNames = array(
  "オムニバス" => "オムニバス",
  "オムニ" => "オムニバス",
  "スターツアーズ" => "スターツアーズ",
  "スタツア" => "スターツアーズ",
  "スター・ツアーズ" => "スターツアーズ",
  "スペース・マウンテン" => "スペース・マウンテン",
  "スペースマウンテン" => "スペース・マウンテン",
  "マウンテン" => array(
   "スペース・マウンテン",
   "スプラッシュ・マウンテン",
   "ビッグサンダー・マウンテン"
   ),
  "山" => array(
   "スペース・マウンテン",
   "スプラッシュ・マウンテン",
   "ビッグサンダー・マウンテン"
   ),
  "スペマン" => "スペース・マウンテン",
  "バズ・ライトイヤー" => "バズ・ライトイヤー",
  "バズライトイヤー" => "バズ・ライトイヤー",
  "バズ" => "バズ・ライトイヤー",
  "バズ・ライトイヤーのアストロブラスター" => "バズ・ライトイヤー",
  "モンスターズ・インク" => "モンスターズ・インク",
  "モン社" => "モンスターズ・インク",
  "モンイン" => "モンスターズ・インク",
  "モンスターズ・インク“ライド＆ゴーシーク！”" => "モンスターズ・インク",
  "モンスターズインク" => "モンスターズ・インク",
  "スティッチ・エンカウンター" => "スティッチ・エンカウンター",
  "エンカ" => "スティッチ・エンカウンター",
  "スティッチエンカウンター" => "スティッチ・エンカウンター",
  "スティッチ" => array(
   "スティッチ・エンカウンター",
   "魅惑のチキルーム"
   ),
  "ガジェットのゴーコースター" => "ガジェットのゴーコースター",
  "ゴーコースター" => "ガジェットのゴーコースター",
  "どんぐり" => "ガジェットのゴーコースター",
  "ガジェット" => "ガジェットのゴーコースター",
  "グーフィーのペイント＆プレイハウス" => "グーフィーのペイント＆プレイハウス",
  "グーフィー" => "グーフィーのペイント＆プレイハウス",
  "グーフィーの家" => "グーフィーのペイント＆プレイハウス",
  "グ家" => "グーフィーのペイント＆プレイハウス",
  "PPH" => "グーフィーのペイント＆プレイハウス",
  "チップとデールのツリーハウス" => "チップとデールのツリーハウス",
  "チップ" => "チップとデールのツリーハウス",
  "デール" => "チップとデールのツリーハウス",
  "チップとデール" => "チップとデールのツリーハウス",
  "チデ家" => "チップとデールのツリーハウス",
  "ツリーハウス" => "チップとデールのツリーハウス",
  "ドナルドのボート" => "ドナルドのボート",
  "ドナルド" => "ドナルドのボート",
  "ボート" => "ドナルドのボート",
  "ミッキーの家とミート・ミッキー" => "ミッキーの家とミート・ミッキー",
  "ミッキーの家" => "ミッキーの家とミート・ミッキー",
  "ミッキー" => array(
   "ミッキーの家とミート・ミッキー",
   "ミッキーのフィルハーマジック"
   ),
  "ミート・ミッキー" => "ミッキーの家とミート・ミッキー",
  "ミートミッキー" => "ミッキーの家とミート・ミッキー",
  "ミトミキ" => "ミッキーの家とミート・ミッキー",
  "ミトミ" => "ミッキーの家とミート・ミッキー",
  "MM" => "ミッキーの家とミート・ミッキー",
  "MMM" => "ミッキーの家とミート・ミッキー",
  "ミニーの家" => "ミニーの家",
  "ミニー" => "ミニーの家",
  "ロジャーラビットのカートゥーンスピン" => "ロジャーラビットのカートゥーンスピン",
  "ロジャーラビット" => "ロジャーラビットのカートゥーンスピン",
  "カートゥーンスピン" => "ロジャーラビットのカートゥーンスピン",
  "アリスのティーパーティー" => "アリスのティーパーティー",
  "アリス" => "アリスのティーパーティー",
  "ティーパーティー" => "アリスのティーパーティー",
  "ティーカップ" => "アリスのティーパーティー",
  "コーヒーカップ" => "アリスのティーパーティー",
  "イッツ・ア・スモールワールド" => "イッツ・ア・スモールワールド",
  "イッツアスモールワールド" => "イッツ・ア・スモールワールド",
  "スモールワールド" => "イッツ・ア・スモールワールド",
  "小さな世界" => "イッツ・ア・スモールワールド",
  "人間なんてちっぽけな存在" => "イッツ・ア・スモールワールド",
  "スモワ" => "イッツ・ア・スモールワールド",
  "世界は一つ" => "イッツ・ア・スモールワールド",
  "世界は丸い" => "イッツ・ア・スモールワールド",
  "キャッスルカルーセル" => "キャッスルカルーセル",
  "カルーセル" => "キャッスルカルーセル",
  "ランカル" => "キャッスルカルーセル",
  "白雪姫と七人のこびと" => "白雪姫と七人のこびと",
  "白雪姫" => "白雪姫と七人のこびと",
  "スノーホワイト" => "白雪姫と七人のこびと",
  "七人のこびと" => "白雪姫と七人のこびと",
  "こびと" => "白雪姫と七人のこびと",
  "小人" => "白雪姫と七人のこびと",
  "白雪姫と七人の小人" => "白雪姫と七人のこびと",
  "白雪姫と７人の小人" => "白雪姫と七人のこびと",
  "白雪姫と７人のこびと" => "白雪姫と七人のこびと",
  "白雪姫と7人の小人" => "白雪姫と七人のこびと",
  "白雪姫と7人のこびと" => "白雪姫と七人のこびと",
  "シンデレラのフェアリーテイル･ホール" => "シンデレラのフェアリーテイル･ホール",
  "シンデレラのフェアリーテイルホール" => "シンデレラのフェアリーテイル･ホール",
  "シンデレラ" => "シンデレラのフェアリーテイル･ホール",
  "シンデレラのフェアリーテイル" => "シンデレラのフェアリーテイル･ホール",
  "フェアリーテイル･ホール" => "シンデレラのフェアリーテイル･ホール",
  "フェアリーテイルホール" => "シンデレラのフェアリーテイル･ホール",
  "シンデレラ城" => "シンデレラのフェアリーテイル･ホール",
  "城" => "シンデレラのフェアリーテイル･ホール",
  "お城" => "シンデレラのフェアリーテイル･ホール",
  "FTH" => "シンデレラのフェアリーテイル･ホール",
  "空飛ぶダンボ" => "空飛ぶダンボ",
  "ダンボ" => "空飛ぶダンボ",
  "ピノキオの冒険旅行" => "ピノキオの冒険旅行",
  "ピノキオ" => "ピノキオの冒険旅行",
  "キノピオの冒険旅行" => "ピノキオの冒険旅行",
  "キノピオ" => "ピノキオの冒険旅行",
  "ピーターパン空の旅" => "ピーターパン空の旅",
  "ピーターパン" => "ピーターパン空の旅",
  "ピーター" => "ピーターパン空の旅",
  "ピタパン" => "ピーターパン空の旅",
  "プーさんのハニーハント" => "プーさんのハニーハント",
  "プーさん" => "プーさんのハニーハント",
  "プー" => "プーさんのハニーハント",
  "ハニーハント" => "プーさんのハニーハント",
  "ハニハン" => "プーさんのハニーハント",
  "ホーンテッドマンション" => "ホーンテッドマンション",
  "ホンテ" => "ホーンテッドマンション",
  "HM" => "ホーンテッドマンション",
  "ミッキーのフィルハーマジック" => "ミッキーのフィルハーマジック",
  "フィルハー" => "ミッキーのフィルハーマジック",
  "フィルハ" => "ミッキーのフィルハーマジック",
  "スプラッシュ・マウンテン" => "スプラッシュ・マウンテン",
  "スプラッシュマウンテン" => "スプラッシュ・マウンテン",
  "スプラッシュ" => "スプラッシュ・マウンテン",
  "カヌー探検" => "カヌー探検",
  "ビーバーブラザーズのカヌー探検" => "カヌー探検",
  "カヌー" => "カヌー探検",
  "ビーバー" => "カヌー探検",
  "ウエスタンランド・シューティングギャラリー" => "ウエスタンランド・シューティングギャラリー",
  "ウエスタンランドシューティングギャラリー" => "ウエスタンランド・シューティングギャラリー",
  "射的" => "ウエスタンランド・シューティングギャラリー",
  "WSG" => "ウエスタンランド・シューティングギャラリー",
  "シューティング" => "ウエスタンランド・シューティングギャラリー",
  "ウエハース" => array(
   "ウエスタンランド・シューティングギャラリー",
   "ウエスタンリバー鉄道"
   ),
  "カントリーベア・シアター" => "カントリーベア・シアター",
  "カントリーベアシアター" => "カントリーベア・シアター",
  "カントリーベア" => "カントリーベア・シアター",
  "カンベア" => "カントリーベア・シアター",
  "CBT" => "カントリーベア・シアター",
  "蒸気船マークトウェイン号" => "蒸気船マークトウェイン号",
  "蒸気船" => "蒸気船マークトウェイン号",
  "船" => "蒸気船マークトウェイン号",
  "マークトウェイン" => "蒸気船マークトウェイン号",
  "マーク" => "蒸気船マークトウェイン号",
  "トムソーヤ島いかだ" => "トムソーヤ島いかだ",
  "トムソーヤ島" => "トムソーヤ島いかだ",
  "トムソーヤ" => "トムソーヤ島いかだ",
  "トム" => "トムソーヤ島いかだ",
  "いかだ" => "トムソーヤ島いかだ",
  "ビッグサンダー・マウンテン" => "ビッグサンダー・マウンテン",
  "ビッグサンダーマウンテン" => "ビッグサンダー・マウンテン",
  "ビッグサンダー" => "ビッグサンダー・マウンテン",
  "ウエスタンリバー鉄道" => "ウエスタンリバー鉄道",
  "リバー鉄道" => "ウエスタンリバー鉄道",
  "リバ鉄" => "ウエスタンリバー鉄道",
  "カリブの海賊" => "カリブの海賊",
  "カリブ" => "カリブの海賊",
  "海賊" => "カリブの海賊",
  "POC" => "カリブの海賊",
  "パイレーツ" => "カリブの海賊",
  "スイスファミリーツリーハウス" => "スイスファミリー・ツリーハウス",
  "スイスファミリー・ツリーハウス" => "スイスファミリー・ツリーハウス",
  "ツリーハウス" => "スイスファミリー・ツリーハウス",
  "ロビンソン" => "スイスファミリー・ツリーハウス",
  "ロビンソン家" => "スイスファミリー・ツリーハウス",
  "ロビ家" => "スイスファミリー・ツリーハウス",
  "魅惑のチキルーム" => "魅惑のチキルーム",
  "チキルーム" => "魅惑のチキルーム",
  "チキ" => "魅惑のチキルーム",
  "ジャングルクルーズ：ワイルドライフ・エクスペディション" => "ジャングルクルーズ：ワイルドライフ・エクスペディション",
  "ジャングル" => "ジャングルクルーズ：ワイルドライフ・エクスペディション",
  "ジャングルクルーズ" => "ジャングルクルーズ：ワイルドライフ・エクスペディション",
  "ドンキーコング" => "ジャングルクルーズ：ワイルドライフ・エクスペディション",
  "DK" => "ジャングルクルーズ：ワイルドライフ・エクスペディション"
);
  return $attr_shortNames;
}

function get_rest_shortNames(){
$rest_shortNames = array(
 "アイスクリームコーン" => "アイスクリームコーン",
 "アイスクリーム" => array(
  "アイスクリームコーン",
  "ラケッティのラクーンサルーン",
  "トルバドールタバン",
  "ソフトランディング"
  ),
 "アイス" => array(
  "アイスクリームコーン",
  "ラケッティのラクーンサルーン",
  "トルバドールタバン",
  "ソフトランディング"
  ),
 "イーストサイド・カフェ" => "イーストサイド・カフェ",
 "イーストサイドカフェ" => "イーストサイド・カフェ",
 "イーストサイド" => "イーストサイド・カフェ",
 "カフェ" => array(
  "イーストサイド・カフェ",
  "スウィートハート・カフェ",
  "ヒューイ・デューイ・ルーイのグッドタイム・カフェ",
  "ペコスビル・カフェ",
  "カフェ・オーリンズ"
  ),
 "グレートアメリカン・ワッフルカンパニー" => "グレートアメリカン・ワッフルカンパニー",
 "グレートアメリカン" => "グレートアメリカン・ワッフルカンパニー",
 "グレートアメリカンワッフルカンパニー" => "グレートアメリカン・ワッフルカンパニー",
 "ワッフルカンパニー" => "グレートアメリカン・ワッフルカンパニー",
 "ワッフル" => "グレートアメリカン・ワッフルカンパニー",
 "スウィートハート・カフェ" => "スウィートハート・カフェ",
 "スウィートハートカフェ" => "スウィートハート・カフェ",
 "スウィートハート" => "スウィートハート・カフェ",
 "スウィート" => "スウィートハート・カフェ",
 "スイート" => "スウィートハート・カフェ",
 "スイーツ" => "スウィートハート・カフェ",
 "センターストリート・コーヒーハウス" => "センターストリート・コーヒーハウス",
 "センターストリートコーヒーハウス" => "センターストリート・コーヒーハウス",
 "センターストリート" => "センターストリート・コーヒーハウス",
 "コーヒーハウス" => "センターストリート・コーヒーハウス",
 "コーヒー" => "センターストリート・コーヒーハウス",
 "リフレッシュメントコーナー" => "リフレッシュメントコーナー",
 "リフレッシュメント" => array(
  "リフレッシュメントコーナー",
  "キャリッジハウス・リフレッシュメント"
  ),
 "リフレッシュ" => array(
  "リフレッシュメントコーナー",
  "キャリッジハウス・リフレッシュメント"
  ),
 "れすとらん北齋" => "れすとらん北齋",
 "れすとらん" => "れすとらん北齋",
 "レストラン北斎" => "れすとらん北齋",
 "レストラン" => array(
  "れすとらん北齋",
  "ハングリーベア・レストラン",
  "プラザパビリオン・レストラン",
  "クリスタルパレス・レストラン",
  "ブルーバイユー・レストラン"
  ),
 "スペースプレース・フードポート" => "スペースプレース・フードポート",
 "スペースプレースフードポート" => "スペースプレース・フードポート",
 "スペースプレース" => "スペースプレース・フードポート",
 "フードポート" => "スペースプレース・フードポート",
 "ソフトランディング" => "ソフトランディング",
 "ソフト" => "ソフトランディング",
 "ソフトクリーム" => "ソフトランディング",
 "トゥモローランド・テラス" => "トゥモローランド・テラス",
 "トゥモローランドテラス" => "トゥモローランド・テラス",
 "テラス" => "トゥモローランド・テラス",
 "パン・ギャラクティック・ピザ・ポート" => "パン・ギャラクティック・ピザ・ポート",
 "パンギャラクティックピザポート" => "パン・ギャラクティック・ピザ・ポート",
 "パンギャラクティック" => "パン・ギャラクティック・ピザ・ポート",
 "ピザポート" => "パン・ギャラクティック・ピザ・ポート",
 "パン" => array(
  "パン・ギャラクティック・ピザ・ポート",
  "スウィートハート・カフェ"
  ),
 "ピザ" => array(
  "パン・ギャラクティック・ピザ・ポート",
  "キャプテンフックス・ギャレー",
  "ヒューイ・デューイ・ルーイのグッドタイム・カフェ"
  ),
 "プラザ" => "プラザパビリオン・レストラン",
 "ポッピングポッド" => "ポッピングポッド",
 "ポッピング" => "ポッピングポッド",
 "ポッド" => "ポッピングポッド",
 "ライトバイト・サテライト" => "ライトバイト・サテライト",
 "ライトバイトサテライト" => "ライトバイト・サテライト",
 "ライトバイト" => "ライトバイト・サテライト",
 "サテライト" => "ライトバイト・サテライト",
 "ディンギードリンク" => "ディンギードリンク",
 "ディンギー" => "ディンギードリンク",
 "ドリンク" => array(
  "ディンギードリンク",
  "スクウィーザーズ・トロピカル・ジュースバー"
  ),
 "トゥーントーン・トリート" => "トゥーントーン・トリート",
 "トゥーントーントリート" => "トゥーントーン・トリート",
 "トゥーントーン" => "トゥーントーン・トリート",
 "トリート" => "トゥーントーン・トリート",
 "トゥーンポップ" => "トゥーンポップ",
 "トゥーン" => "トゥーンポップ",
 "ヒューイ・デューイ・ルーイのグッドタイム・カフェ" => "ヒューイ・デューイ・ルーイのグッドタイム・カフェ",
 "ヒューイデューイルーイのグッドタイムカフェ" => "ヒューイ・デューイ・ルーイのグッドタイム・カフェ",
 "ヒューイ・デューイ・ルーイ" => "ヒューイ・デューイ・ルーイのグッドタイム・カフェ",
 "ヒューイデューイルーイ" => "ヒューイ・デューイ・ルーイのグッドタイム・カフェ",
 "グッドタイム・カフェ" => "ヒューイ・デューイ・ルーイのグッドタイム・カフェ",
 "グッドタイムカフェ" => "ヒューイ・デューイ・ルーイのグッドタイム・カフェ",
 "ポップ・ア・ロット・ポップコーン" => "ポップ・ア・ロット・ポップコーン",
 "ポップアロットポップコーン" => "ポップ・ア・ロット・ポップコーン",
 "ポップアロット" => "ポップ・ア・ロット・ポップコーン",
 "ミッキーのトレーラー" => "ミッキーのトレーラー",
 "ミッキー" => "ミッキーのトレーラー",
 "トレーラー" => "ミッキーのトレーラー",
 "キャプテンフックス・ギャレー" => "キャプテンフックス・ギャレー",
 "キャプテンフックスギャレー" => "キャプテンフックス・ギャレー",
 "キャプテンフックス" => "キャプテンフックス・ギャレー",
 "ギャレー" => "キャプテンフックス・ギャレー",
 "クイーン・オブ・ハートのバンケットホール" => "クイーン・オブ・ハートのバンケットホール",
 "クイーンオブハートのバンケットホール" => "クイーン・オブ・ハートのバンケットホール",
 "クイーン・オブ・ハート" => "クイーン・オブ・ハートのバンケットホール",
 "クイーンオブハート" => "クイーン・オブ・ハートのバンケットホール",
 "クイーン" => "クイーン・オブ・ハートのバンケットホール",
 "クイーンオブ" => "クイーン・オブ・ハートのバンケットホール",
 "バンケットホール" => "クイーン・オブ・ハートのバンケットホール",
 "バンケット" => "クイーン・オブ・ハートのバンケットホール",
 "クレオズ" => "クレオズ",
 "トルバドールタバン" => "トルバドールタバン",
 "トルバドール" => "トルバドールタバン",
 "トルバ" => "トルバドールタバン",
 "タバン" => "トルバドールタバン",
 "ビレッジペイストリー" => "ビレッジペイストリー",
 "ビレッジ" => "ビレッジペイストリー",
 "ペイストリー" => "ビレッジペイストリー",
 "グランマ・サラのキッチン" => "グランマ・サラのキッチン",
 "グランマ・サラ" => "グランマ・サラのキッチン",
 "グランマサラのキッチン" => "グランマ・サラのキッチン",
 "グランマ" => "グランマ・サラのキッチン",
 "サラ" => "グランマ・サラのキッチン",
 "グランマサラ" => "グランマ・サラのキッチン",
 "キッチン" => array(
  "グランマ・サラのキッチン",
  "キャンプ・ウッドチャック・キッチン"
  ),
 "ラケッティのラクーンサルーン" => "ラケッティのラクーンサルーン",
 "ラケッティ" => "ラケッティのラクーンサルーン",
 "ラクーン" => "ラケッティのラクーンサルーン",
 "サルーン" => "ラケッティのラクーンサルーン",
 "キャンティーン" => "キャンティーン",
 "チャックワゴン" => "チャックワゴン",
 "チャック" => "チャックワゴン",
 "ワゴン" => array(
  "チャックワゴン",
  "パークサイドワゴン"
  ),
 "ハングリーベア・レストラン" => "ハングリーベア・レストラン",
 "ハングリーベア" => "ハングリーベア・レストラン",
 "ハングリーベアレストラン" => "ハングリーベア・レストラン",
 "プラザパビリオン・レストラン" => "プラザパビリオン・レストラン",
 "プラザパビリオンレストラン" => "プラザパビリオン・レストラン",
 "プラザパビリオン" => "プラザパビリオン・レストラン",
 "ペコスビル・カフェ" => "ペコスビル・カフェ",
 "ペコスビルカフェ" => "ペコスビル・カフェ",
 "ペコスビル" => "ペコスビル・カフェ",
 "カフェ・オーリンズ" => "カフェ・オーリンズ",
 "カフェオーリンズ" => "カフェ・オーリンズ",
 "オーリンズ" => "カフェ・オーリンズ",
 "クリスタルパレス・レストラン" => "クリスタルパレス・レストラン",
 "クリスタルパレスレストラン" => "クリスタルパレス・レストラン",
 "クリスタルパレス" => "クリスタルパレス・レストラン",
 "ザ・ガゼーボ" => "ザ・ガゼーボ",
 "ザガゼーボ" => "ザ・ガゼーボ",
 "ガゼーボ" => "ザ・ガゼーボ",
 "スキッパーズ・ギャレー" => "スキッパーズ・ギャレー",
 "スキッパーズギャレー" => "スキッパーズ・ギャレー",
 "スキッパーズ" => "スキッパーズ・ギャレー",
 "ギャレー" => "スキッパーズ・ギャレー",
 "スクウィーザーズ・トロピカル・ジュースバー" => "スクウィーザーズ・トロピカル・ジュースバー",
 "スクウィーザーズトロピカルジュースバー" => "スクウィーザーズ・トロピカル・ジュースバー",
 "スクウィーザーズ" => "スクウィーザーズ・トロピカル・ジュースバー",
 "トロピカル" => "スクウィーザーズ・トロピカル・ジュースバー",
 "ジュースバー" => "スクウィーザーズ・トロピカル・ジュースバー",
 "スクウィーザーズトロピカル" => "スクウィーザーズ・トロピカル・ジュースバー",
 "トロピカルジュースバー" => "スクウィーザーズ・トロピカル・ジュースバー",
 "ジュース" => "スクウィーザーズ・トロピカル・ジュースバー",
 "バー" => "スクウィーザーズ・トロピカル・ジュースバー",
 "トロピカルジュース" => "スクウィーザーズ・トロピカル・ジュースバー",
 "チャイナボイジャー" => "チャイナボイジャー",
 "チャイナ" => "チャイナボイジャー",
 "ボイジャー" => "チャイナボイジャー",
 "パークサイドワゴン" => "パークサイドワゴン",
 "パークサイド" => "パークサイドワゴン",
 "フレッシュフルーツオアシス" => "フレッシュフルーツオアシス",
 "フレッシュフルーツ" => "フレッシュフルーツオアシス",
 "フルーツオアシス" => "フレッシュフルーツオアシス",
 "フレッシュ" => "フレッシュフルーツオアシス",
 "フルーツ" => "フレッシュフルーツオアシス",
 "オアシス" => "フレッシュフルーツオアシス",
 "ブルーバイユー・レストラン" => "ブルーバイユー・レストラン",
 "ブルーバイユーレストラン" => "ブルーバイユー・レストラン",
 "ブルーバイユー" => "ブルーバイユー・レストラン",
 "ボイラールーム・バイツ" => "ボイラールーム・バイツ",
 "ボイラールームバイツ" => "ボイラールーム・バイツ",
 "ボイラールーム" => "ボイラールーム・バイツ",
 "バイツ" => "ボイラールーム・バイツ",
 "ロイヤルストリート・ベランダ" => "ロイヤルストリート・ベランダ",
 "ロイヤルストリートベランダ" => "ロイヤルストリート・ベランダ",
 "ロイヤルストリート" => "ロイヤルストリート・ベランダ",
 "ベランダ" => "ロイヤルストリート・ベランダ",
 "キャンプ・ウッドチャック・キッチン" => "キャンプ・ウッドチャック・キッチン",
 "キャンプウッドチャックキッチン" => "キャンプ・ウッドチャック・キッチン",
 "キャンプ" => "キャンプ・ウッドチャック・キッチン",
 "ウッドチャック" => "キャンプ・ウッドチャック・キッチン",
 "キャンプウッドチャック" => "キャンプ・ウッドチャック・キッチン",
 "ウッドチャックキッチン" => "キャンプ・ウッドチャック・キッチン",
 "ホットドッグ" => "リフレッシュメントコーナー",
 "和食" => array(
  "れすとらん北齋",
  "クリスタルパレス・レストラン"
  ),
 "チュロス" => array(
  "パークサイドワゴン",
  "ペコスビル・カフェ",
  "ラケッティのラクーンサルーン",
  "ビレッジペイストリー",
  "ライトバイト・サテライト"
  ),
 "洋食" => array(
  "クリスタルパレス・レストラン",
  "ブルーバイユー・レストラン",
  "プラザパビリオン・レストラン",
  "グランマ・サラのキッチン",
  "クイーン・オブ・ハートのバンケットホール"
  ),
 "カレー" => "ハングリーベア・レストラン",
 "ハンバーグ" => array(
  "グランマ・サラのキッチン",
  "クイーン・オブ・ハートのバンケットホール"
  ),
 "パスタ" => "イーストサイド・カフェ",
 "イタリアン" => array(
  "イーストサイド・カフェ",
  "パン・ギャラクティック・ピザ・ポート",
  "キャプテンフックス・ギャレー",
  "ヒューイ・デューイ・ルーイのグッドタイム・カフェ"
  ),
 "キャリッジハウス・リフレッシュメント" => "キャリッジハウス・リフレッシュメント",
 "キャリッジハウスリフレッシュメント" => "キャリッジハウス・リフレッシュメント",
 "キャリッジハウス" => "キャリッジハウス・リフレッシュメント",
 "食べ歩き"  => array(
  "アイスクリームコーン",
  "グレートアメリカン・ワッフルカンパニー",
  "スウィートハート・カフェ",
  "リフレッシュメントコーナー",
  "ザ・ガゼーボ",
  "スキッパーズ・ギャレー",
  "ボイラールーム・バイツ",
  "キャンティーン",
  "チャックワゴン",
  "ペコスビル・カフェ",
  "ラケッティのラクーンサルーン",
  "トルバドールタバン",
  "ビレッジペイストリー",
  "トゥーントーン・トリート",
  "ヒューイ・デューイ・ルーイのグッドタイム・カフェ",
  "ミッキーのトレーラー",
  "スペースプレース・フードポート",
  "ソフトランディング",
  "パン・ギャラクティック・ピザ・ポート",
  "ライトバイト・サテライト"
  ),
 "クレープ" => "カフェ・オーリンズ",
 "ステーキ" => "クイーン・オブ・ハートのバンケットホール"
);
  return $rest_shortNames;
}

function get_rest_id($rest_name){
  $rest_id_array = array(
    "れすとらん北齋" => "79",
    "アイスクリームコーン" => "1",
    "イーストサイド・カフェ" => "3",
    "キャリッジハウス・リフレッシュメント" => "",
    "グレートアメリカン・ワッフルカンパニー" => "17",
    "スウィートハート・カフェ" => "26",
    "センターストリート・コーヒーハウス" => "32",
    "リフレッシュメントコーナー" => "77",
    "カフェ・オーリンズ" => "9",
    "クリスタルパレス・レストラン" => "14",
    "ザ・ガゼーボ" => "23",
    "スキッパーズ・ギャレー" => "27",
    "スクウィーザーズ・トロピカル・ジュースバー" => "28",
    "チャイナボイジャー" => "34",
    "ブルーバイユー・レストラン" => "56",
    "ボイラールーム・バイツ" => "61",
    "ロイヤルストリート・ベランダ" => "81",
    "パークサイドワゴン" => "51",
    "フレッシュフルーツオアシス" => "54",
    "キャンティーン" => "12",
    "キャンプ・ウッドチャック・キッチン" => "",
    "ハングリーベア・レストラン" => "47",
    "プラザパビリオン・レストラン" => "57",
    "ペコスビル・カフェ" => "59",
    "チャックワゴン" => "35",
    "グランマ・サラのキッチン" => "16",
    "ラケッティのラクーンサルーン" => "72",
    "キャプテンフックス・ギャレー" => "11",
    "クイーン・オブ・ハートのバンケットホール" => "13",
    "クレオズ" => "15",
    "トルバドールタバン" => "42",
    "ビレッジペイストリー" => "53",
    "ヒューイ・デューイ・ルーイのグッドタイム・カフェ" => "52",
    "ミッキーのトレーラー" => "69",
    "トゥーントーン・トリート" => "40",
    "スペースプレース・フードポート" => "29",
    "ソフトランディング" => "33",
    "トゥモローランド・テラス" => "39",
    "パン・ギャラクティック・ピザ・ポート" => "50",
    "ライトバイト・サテライト" => "71"
    );

  if(isset($rest_id_array[$rest_name])){
    $rest_id = $rest_id_array[$rest_name];
  }else{
    $rest_id = "";
  }
  return $rest_id;
}
?>

</body>
</html>