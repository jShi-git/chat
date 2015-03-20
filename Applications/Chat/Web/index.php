<?php
  $token = isset($_REQUEST['token']) ? $_REQUEST['token'] : "";
  $openid = isset($_REQUEST['openid']) ? $_REQUEST['openid'] :"";

  // if($openid != '') {
  //   $infoUrl = 'https://api.weixin.qq.com/sns/userinfo?access_token='.$token.'&openid='.$openid.'&lang=zh_CN';
  //   $info = file_get_contents($infoUrl);
  //   $wxinfo = json_decode($info, true);
  // } else {
  //   $wxinfo = array();
  // }
  if(empty($wxinfo)) {
    die("Error!");
  }

  $wxinfo = array("headimgurl"=>'images/avatar.jpg',"nickname"=>"游客".rand(100000));
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
  <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,minimum-scale=1.0,user-scalable=0">
  <title>湖南卫视</title>
  
  <link rel="stylesheet" media="all" href="css/style.css?v=2" />

  <script type="text/javascript" src="/js/swfobject.js"></script>
  <script type="text/javascript" src="/js/web_socket.js"></script>
  <script type="text/javascript" src="/js/json.js"></script>
  <script type="text/javascript" src="/js/jquery.min.js"></script>
</head>
<body>

  <section class="banner" id="banner">
    <img src="images/img_top.png" id="bimg" />
    <span class="count">当前有<span id="count">0</span>人在线</span>
  </section>
  
  <section class="chat_block" id="dialog"></section>

  <section class="input_bar">
    <div class="input_user"><span><img src="<?php echo $wxinfo['headimgurl'];?>" onerror="this.src='images/avatar.jpg'" alt=""></span></div>
    <div class="input">
      <input type="text" id="input-content" maxlength="50" />
      <span id="bq-trigger"></span>
    </div>
    <a class="ico_share say-btn" id="send-trigger"></a>
  </section>

  <div class="thumbnail" style="display:none;">
    <div class="caption" id="userlist"></div>
  </div>

   <select style="margin-bottom:8px; display:none;" id="client_list">
      <option value="all">所有人</option>
    </select>
  
  <div id="restrict_heng"><p><span>请在竖屏下使用！</span></p></div>

  <div id="pop-notification"></div>

  <script>
    //=======================聊天==============================
    if (typeof console == "undefined") {
      this.console = {
        log: function(msg) {}
      };
    }
    WEB_SOCKET_SWF_LOCATION = "/swf/WebSocketMain.swf";
    WEB_SOCKET_DEBUG = true;
    var ws, name, client_list = {},
      timeid, reconnect = false;

    function init() {
      // 创建websocket
      ws = new WebSocket("ws://" + document.domain + ":7272");
      // 当socket连接打开时，输入用户名
      ws.onopen = function() {
        timeid && window.clearInterval(timeid);
        name = "<?php echo $wxinfo['nickname'];?>";
        if (name == "" || !name) {
          return ws.close();
        }
        if (reconnect == false) {
          // 登录
          var login_data = JSON.stringify({
            "type": "login",
            "client_name": name,
            "room_id": <?php echo isset($_GET['room_id']) ? $_GET['room_id'] : 1 ?>
          });
          console.log("websocket握手成功，发送登录数据:" + login_data);
          ws.send(login_data);
          reconnect = true;
        } else {
          // 断线重连
          var relogin_data = JSON.stringify({
            "type": "re_login",
            "client_name": name,
            "room_id": <?php echo isset($_GET['room_id']) ? $_GET['room_id'] : 1 ?>
          });
          console.log("websocket握手成功，发送重连数据:" + relogin_data);
          ws.send(relogin_data);
        }
      };
      // 当有消息时根据消息类型显示不同信息
      ws.onmessage = function(e) {
        console.log(e.data);
        var data = JSON.parse(e.data);
        switch (data['type']) {
          // 服务端ping客户端
          case 'ping':
            ws.send(JSON.stringify({
              "type": "pong"
            }));
            break;;
            // 登录 更新用户列表
          case 'login':
            say(data['client_id'], data['client_name'], '“' +data['client_name'] + '”进来了', data['time'], "login");
            flush_client_list(data['client_list']);
            console.log(data['client_name'] + "登录成功");
            break;
            // 断线重连，只更新用户列表
          case 're_login':
            flush_client_list(data['client_list']);
            console.log(data['client_name'] + "重连成功");
            break;
            // 发言
          case 'say':
            say(data['from_client_id'], data['from_client_name'], data['content'], data['time']);
            break;
            // 用户退出 更新用户列表
          case 'logout':
            say(data['from_client_id'], data['from_client_name'], '“' + data['client_name'] + '”离开了', data['time'], "logout");
            flush_client_list(data['client_list']);
        }
      };
      ws.onclose = function() {
        console.log("连接关闭，定时重连");
        // 定时重连
        window.clearInterval(timeid);
        timeid = window.setInterval(init, 3000);
      };
      ws.onerror = function() {
        console.log("出现错误");
      };
    }

    // 提交对话
    function onSubmit() {
      var input = $("#input-content").val();
      if(input == "") {
        return false;
      }
      var to_client_id = $("#client_list option:selected").attr("value");
      var to_client_name = $("#client_list option:selected").text();
      ws.send(JSON.stringify({
        "type": "say",
        "to_client_id": to_client_id,
        "to_client_name": to_client_name,
        "content": input
      }));
      $("#input-content").val("").blur();
    }

    // 刷新用户列表框
    function flush_client_list(client_list) {
      $("#count").html(client_list.length);
    }

    // 发言
    function say(from_client_id, from_client_name, content, time, type) {
      if (typeof(content) != 'undefined' && content != '') {
        barrage.appendMsg(content, true, type);
      }
    }

    $(function() {
      select_client_id = 'all';
    });
  </script>

  <script src="js/barrage_plus_addclass.js"></script>
  <script src="js/main.js"></script>
</body>
</html>