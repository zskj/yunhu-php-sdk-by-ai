<?php
/**
 * 🤖 云湖信息查询机器人
 * 🎯 指令ID:
 *   - 帮助菜单 (2215)｜版本信息查询 (2247)｜用户信息查询 (2248) | 群组信息查询 (2249) | 机器人信息查询 (2250)
 */

require_once __DIR__ . '/yunhubot_sdk.php';

$BOT_VERSION = "1.0.21";
$bot = yhsdk_init('这里填写你的token', [
    'log_path' => 'yunhu_functional_bot.log'
]);

// 支持的指令ID数组（新增2249和2250）
$SUPPORTED_COMMANDS = [2215, 2247, 2248, 2249, 2250]; // 请自行修改指令信息id

// 🔧 SDK 事件兼容层
if (!function_exists('get_event_type')) {
    function get_event_type() {
        global $event_type;
        return $event_type ?? ($_POST['header']['eventType'] ?? '');
    }
}

if (!function_exists('get_command_info')) {
    function get_command_info() {
        $event_type = get_event_type();
        if ($event_type === 'message.receive.instruction') {
            return [
                'commandId' => $_POST['event']['message']['commandId'] ?? 0,
                'commandName' => $_POST['event']['message']['commandName'] ?? ''
            ];
        }
        return null;
    }
}

if (!function_exists('get_message_content')) {
    function get_message_content() {
        $event_type = get_event_type();
        if (in_array($event_type, ['message.receive.normal', 'message.receive.instruction'])) {
            return $_POST['event']['message']['content']['text'] ?? '';
        }
        return '';
    }
}

if (!function_exists('get_back_object')) {
    function get_back_object() {
        global $back;
        if (isset($back) && !empty($back)) {
            return $back;
        }
        
        if (isset($_POST['event']['chat'])) {
            $chat = $_POST['event']['chat'];
            return [
                'id' => $chat['chatId'] ?? '',
                'type' => $chat['chatType'] ?? 'user'
            ];
        }
        
        return ['id' => '', 'type' => 'user'];
    }
}

// 🔧 核心API函数
function yunhuApiRequest($endpoint, $method = 'GET', $params = []) {
    $base_url = 'https://chat-web-go.jwzhd.com/v1/';
    $url = $base_url . $endpoint;
    
    if ($method === 'GET' && !empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    $ch = curl_init();
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_ENCODING => 'gzip, deflate'
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        if (!empty($params)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($params);
        }
    }

    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $decoded = json_decode($response, true);
        if (isset($decoded['code']) && $decoded['code'] == 1) {
            return ['success' => true, 'data' => $decoded['data'] ?? []];
        } else {
            return [
                'success' => false,
                'code' => $decoded['code'] ?? $httpCode,
                'message' => $decoded['msg'] ?? 'API返回错误'
            ];
        }
    }

    return [
        'success' => false,
        'code' => $httpCode,
        'message' => $error ?: "HTTP {$httpCode}"
    ];
}

// 📦 业务逻辑函数
function getVersionInfo() {
    return yunhuApiRequest("common/get-version");
}

function getUserInfo($userId) {
    if (empty($userId) || !is_numeric($userId)) {
        return ['success' => false, 'code' => 400, 'message' => '用户ID必须是数字'];
    }
    return yunhuApiRequest("user/homepage", 'GET', ['userId' => $userId]);
}

function getGroupInfo($groupId) {
    if (empty($groupId) || !is_numeric($groupId)) {
        return ['success' => false, 'code' => 400, 'message' => '群组ID必须是数字'];
    }
    return yunhuApiRequest("group/group-info", 'POST', ['groupId' => $groupId]);
}

function getBotInfo($botId) {
    if (empty($botId) || !is_numeric($botId)) {
        return ['success' => false, 'code' => 400, 'message' => '机器人ID必须是数字'];
    }
    return yunhuApiRequest("bot/bot-info", 'POST', ['botId' => $botId]);
}

// 🖼️ HTML 卡片生成函数
function getVersionCard($versionData) {
    if (empty($versionData)) {
        return '';
    }
    
    $platforms = [
        'android' => ['name' => 'Android', 'version' => 'androidVersion', 'date' => 'androidVersionDate'],
        'harmony' => ['name' => 'HarmonyOS', 'version' => 'harmonyVersion', 'date' => 'harmonyVersionDate'],
        'ios' => ['name' => 'iOS', 'version' => 'iosVersion', 'date' => 'iosVersionDate'],
        'linux' => ['name' => 'Linux', 'version' => 'linuxVersion', 'date' => 'linuxVersionDate'],
        'macos' => ['name' => 'macOS', 'version' => 'macosVersion', 'date' => 'macosVersionDate'],
        'windows' => ['name' => 'Windows', 'version' => 'windowsVersion', 'date' => 'windowsVersionDate']
    ];
    
    $platformCount = count($platforms);
    $platformsHtml = '';
    $time = date('Y-m-d H:i:s');
    $botVersion = $GLOBALS['BOT_VERSION'];
    
    foreach ($platforms as $key => $platform) {
        $platformName = $platform['name'];
        $versionKey = $platform['version'];
        $dateKey = $platform['date'];
        
        $version = htmlspecialchars($versionData[$versionKey] ?? '未知');
        $versionDate = htmlspecialchars($versionData[$dateKey] ?? '未知');
        
        $platformsHtml .= <<<HTML
      <div style="margin:0 0 8px 0; padding:8px; background:#f5f5f4; border-radius:4px;">
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">系统名称:</span> {$platformName}</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">版本号:</span> v{$version}</p>
        <p style="margin:0;"><span style="color:#333; font-weight:500;">更新时间:</span> {$versionDate}</p>
      </div>
HTML;
    }
    
    // 查找最新版本
    $latestVersion = '';
    $latestPlatform = '';
    $latestDate = '';
    
    foreach ($platforms as $key => $platform) {
        $versionKey = $platform['version'];
        $dateKey = $platform['date'];
        
        if (isset($versionData[$versionKey]) && isset($versionData[$dateKey])) {
            if (empty($latestDate) || strtotime($versionData[$dateKey]) > strtotime($latestDate)) {
                $latestDate = $versionData[$dateKey];
                $latestVersion = $versionData[$versionKey];
                $latestPlatform = $platform['name'];
            }
        }
    }
    
    $latestInfo = '';
    if ($latestVersion && $latestPlatform && $latestDate) {
        $latestInfo = <<<HTML
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">最新版本:</span> v{$latestVersion}</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">最新平台:</span> {$latestPlatform}</p>
  <p style="margin:0 0 12px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">更新时间:</span> {$latestDate}</p>
HTML;
    }

    return <<<HTML
<div style="padding:15px; border-radius:10px; max-width:350px; background:#ffffff; border:1px solid #e0e0e0; font-family:Arial, sans-serif;">
  <h2 style="margin:0 0 15px 0; color:#333; font-size:18px; font-weight:bold; text-align:center;">云湖｜版本信息</h2>
  <p style="margin:0 0 8px 0; color:#333; font-size:15px; font-weight:bold; padding-left:8px; border-left:3px solid #4285f4;">最新应用程序版本</p>
  {$latestInfo}
  <details style="margin:0 0 12px 0; color:#555; font-size:14px;">
    <summary style="cursor: pointer; color: #0066cc; font-weight: bold;">点击展开全平台版本（{$platformCount}个）</summary>
    <div style="margin-top:10px;">
      {$platformsHtml}
    </div>
  </details>
  <p style="margin:0 0 15px 0; color:#555; font-size:14px;"><span style="color:#4285f4;">⏰</span> [查询时间] {$time}</p>
  <div style="text-align:right; font-size:10px; color:#888;">Powered by 云湖API｜Bot Version {$botVersion}</div>
</div>
HTML;
}

function getUserCard($userData) {
    if (empty($userData['user'])) {
        return '';
    }
    
    $user = $userData['user'];
    $userId = htmlspecialchars($user['userId'] ?? '未知');
    $nickname = htmlspecialchars($user['nickname'] ?? '未知');
    $avatarUrl = htmlspecialchars($user['avatarUrl'] ?? '');
    $registerTime = htmlspecialchars($user['registerTimeText'] ?? '未知');
    $registerTimestamp = htmlspecialchars($user['registerTime'] ?? '未知');
    $onLineDay = htmlspecialchars($user['onLineDay'] ?? '0');
    $continuousOnLineDay = htmlspecialchars($user['continuousOnLineDay'] ?? '0');
    $isVip = isset($user['isVip']) && $user['isVip'] == 1 ? '✅' : '☑️';
    $medals = $user['medals'] ?? [];
    $medalCount = count($medals);
    $time = date('Y-m-d H:i:s');
    $botVersion = $GLOBALS['BOT_VERSION'];
    
    // 处理奖章HTML
    $medalsHtml = '';
    foreach ($medals as $medal) {
        $medalId = htmlspecialchars($medal['id'] ?? '');
        $medalName = htmlspecialchars($medal['name'] ?? '未知');
        $medalDesc = htmlspecialchars($medal['desc'] ?? '无');
        $medalSort = htmlspecialchars($medal['sort'] ?? '0');
        $medalImageUrl = htmlspecialchars($medal['imageUrl'] ?? '');
        
        $medalsHtml .= <<<HTML
      <div style="margin:0 0 8px 0; padding:8px; background:#f5f5f4; border-radius:4px; display:flex; align-items:center; gap:10px;">
        <div style="flex:1;">
          <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">奖章名称:</span> {$medalName}｜(ID: {$medalId})</p>
          <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">描述:</span> {$medalDesc}</p>
          <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">排序:</span> {$medalSort}</p>
        </div>
        <div style="width:60px; height:60px; display:flex; align-items:center; justify-content:center; background:#fff; border-radius:4px; border:1px solid #eee; overflow:hidden;">
          <img src="{$medalImageUrl}" style="width:100%; height:100%; object-fit:cover;" alt="{$medalName}">
        </div>
      </div>
HTML;
    }
    
    $medalsSection = '';
    if ($medalCount > 0) {
        $medalsSection = <<<HTML
  <p style="margin:0 0 8px 0; color:#333; font-size:15px; font-weight:bold; padding-left:8px; border-left:3px solid #4285f4;">用户奖章</p>
  <details style="margin:0 0 12px 0; color:#555; font-size:14px;">
    <summary style="cursor: pointer; color: #0066cc; font-weight: bold;">点击展开奖章列表（{$medalCount}个）</summary>
    <div style="margin-top:10px;">
      {$medalsHtml}
    </div>
  </details>
HTML;
    } else {
        $medalsSection = <<<HTML
  <p style="margin:0 0 8px 0; color:#333; font-size:15px; font-weight:bold; padding-left:8px; border-left:3px solid #4285f4;">用户奖章</p>
  <p style="margin:0 0 12px 0; color:#555; font-size:14px;">该用户暂无奖章</p>
HTML;
    }
    
    // 头像显示
    $avatarHtml = '';
    if (!empty($avatarUrl)) {
        $avatarHtml = <<<HTML
  <a href="https://www.yhchat.com/user/homepage/{$userId}" target="_blank" style="display:block; text-align:center; text-decoration:none;">
    <img src="{$avatarUrl}" style="width:256px; height:256px; margin:0 auto; object-fit:cover; border-radius:50%;">
  </a>
HTML;
    } else {
        $avatarHtml = <<<HTML
  <div style="text-align:center; margin:10px 0;">
    <div style="width:256px; height:256px; margin:0 auto; background:#f0f0f0; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#888;">
      暂无头像
    </div>
  </div>
HTML;
    }

    return <<<HTML
<div style="padding:15px; border-radius:10px; max-width:300px; background:#ffffff; border:1px solid #e0e0e0; font-family:Arial, sans-serif;">
  <h2 style="margin:0 0 15px 0; color:#333; font-size:18px; font-weight:bold; text-align:center;">云湖｜用户信息</h2>
  {$avatarHtml}
  <p style="margin:15px 0 8px 0; color:#333; font-size:15px; font-weight:bold; padding-left:8px; border-left:3px solid #4285f4;">基础资料</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">昵称:</span> {$nickname}</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">用户ID:</span> {$userId}</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">注册时间:</span> {$registerTime}</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">注册时间戳:</span> {$registerTimestamp}</p>
  <p style="margin:0 0 12px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">是否会员:</span> {$isVip}</p>
  <p style="margin:0 0 8px 0; color:#333; font-size:15px; font-weight:bold; padding-left:8px; border-left:3px solid #4285f4;">在线数据</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">累计在线天数:</span> {$onLineDay}天</p>
  <p style="margin:0 0 12px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">连续在线天数:</span> {$continuousOnLineDay}天</p>
  {$medalsSection}
  <p style="margin:0 0 15px 0; color:#555; font-size:14px;"><span style="color:#4285f4;">⏰</span> [查询时间] {$time}</p>
  <div style="text-align:right; font-size:10px; color:#888;">Powered by 云湖API｜Bot Version {$botVersion}</div>
</div>
HTML;
}


// 新增：群组信息卡片
function getGroupCard($groupData) {
    if (empty($groupData['group'])) {
        return '';
    }
    
    $group = $groupData['group'];
    $groupId = htmlspecialchars($group['groupId'] ?? '未知');
    $groupIdInternal = htmlspecialchars($group['id'] ?? '0');
    $groupName = htmlspecialchars($group['name'] ?? '未知');
    $introduction = htmlspecialchars($group['introduction'] ?? '无');
    $createBy = htmlspecialchars($group['createBy'] ?? '未知');
    $createTime = htmlspecialchars($group['createTime'] ?? '0');
    $createTimeText = !empty($createTime) && is_numeric($createTime) ? date('Y-m-d H:i:s', $createTime) : '未知';
    $avatarId = htmlspecialchars($group['avatarId'] ?? '0');
    $avatarUrl = htmlspecialchars($group['avatarUrl'] ?? '');
    $headcount = htmlspecialchars($group['headcount'] ?? '0');
    $readHistory = htmlspecialchars($group['readHistory'] ?? '0');
    $category = htmlspecialchars($group['category'] ?? '未知');
    $uri = htmlspecialchars($group['uri'] ?? '未知');
    
    $checkRecord = $group['checkChatInfoRecord'] ?? [];
    $botRel = $group['groupBotRel'] ?? ['bot' => []];
    
    $time = date('Y-m-d H:i:s');
    $botVersion = $GLOBALS['BOT_VERSION'];
    
    // 群组关联机器人信息
    $botRelHtml = '';
    $bot = $botRel['bot'] ?? [];
    
    if (!empty($bot['botId'])) {
        $botRelHtml .= <<<HTML
        <p style="margin:0 0 5px 0; padding-top:5px; border-top:1px dashed #ddd;"><span style="color:#333; font-weight:500;">群组关联机器人详情:</span></p>
        <p style="margin:0 0 2px 0; font-size:13px;">- ID: {$bot['id']} | 机器人ID: {$bot['botId']}</p>
        <p style="margin:0 0 2px 0; font-size:13px;">- 机器人昵称: {$bot['nickname']} | 昵称ID: {$bot['nicknameId']}</p>
        <p style="margin:0 0 2px 0; font-size:13px;">- 机器人头像ID: {$bot['avatarId']} | 头像链接: {$bot['avatarUrl']}</p>
        <p style="margin:0 0 2px 0; font-size:13px;">- 机器人类型: {$bot['type']} | 简介: {$bot['introduction']}</p>
        <p style="margin:0 0 2px 0; font-size:13px;">- 创建者: {$bot['createBy']} | 创建时间: {$bot['createTime']}</p>
        <p style="margin:0 0 2px 0; font-size:13px;">- 成员数量: {$bot['headcount']} | 是否私有: {$bot['private']}</p>
        <p style="margin:0 0 2px 0; font-size:13px;">- 是否停止: {$bot['isStop']} | 设置JSON: {$bot['settingJson']}</p>
        <p style="margin:0 0 2px 0; font-size:13px;">- 删除标志: {$bot['del_flag']} | 是否总是同意: {$bot['alwaysAgree']}</p>
        <p style="margin:0 0 2px 0; font-size:13px;">- 群组限制: {$bot['groupLimit']} | 封禁ID: {$bot['banId']}</p>
        <p style="margin:0 0 2px 0; font-size:13px;">- 是否链接停止: {$bot['linkStop']} | 是否大模型: {$bot['isBigModel']}</p>
        <p style="margin:0 0 2px 0; font-size:13px;">- 机器人接口: {$bot['uri']}</p>
HTML;
    } else {
        $botRelHtml = <<<HTML
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">关联群组ID:</span> {$botRel['groupId']}｜(ID: {$botRel['id']})</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">关联机器人ID:</span> {$botRel['botId']}</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">关联删除标志:</span> {$botRel['delFlag']}</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">关联创建时间:</span> {$botRel['createTime']}</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">关联更新时间:</span> {$botRel['updateTime']}</p>
HTML;
    }
    
    // 审核记录信息
    $checkRecordHtml = '';
    if (!empty($checkRecord)) {
        $updateTimeText = !empty($checkRecord['updateTime']) && is_numeric($checkRecord['updateTime']) ? date('Y-m-d H:i:s', $checkRecord['updateTime']) : '未知';
        
        $checkRecordHtml = <<<HTML
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">审核记录ID:</span> {$checkRecord['id']}</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">关联群组ID:</span> {$checkRecord['chatId']}</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">聊天类型:</span> {$checkRecord['chatType']}</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">审核方式:</span> {$checkRecord['checkWay']}</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">审核原因:</span> {$checkRecord['reason']}</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">审核状态:</span> {$checkRecord['status']}</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">审核创建时间戳:</span> {$checkRecord['createTime']}</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">审核创建时间:</span> {$createTimeText}</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">审核更新时间戳:</span> {$checkRecord['updateTime']}</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">审核更新时间:</span> {$updateTimeText}</p>
        <p style="margin:0;"><span style="color:#333; font-weight:500;">审核删除标志:</span> {$checkRecord['delFlag']}</p>
HTML;
    }
    
    // 头像显示
    $avatarHtml = '';
    if (!empty($avatarUrl)) {
        $avatarHtml = <<<HTML
  <a href="https://www.yhchat.com/group/homepage/{$groupId}?userId=7058262" target="_blank" style="display:block; text-align:center; text-decoration:none;">
    <img src="{$avatarUrl}" style="width:256px; height:256px; margin:0 auto; object-fit:cover; border-radius:8px;">
  </a>
HTML;
    } else {
        $avatarHtml = <<<HTML
  <div style="text-align:center; margin:10px 0;">
    <div style="width:256px; height:256px; margin:0 auto; background:#f0f0f0; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#888;">
      暂无群组头像
    </div>
  </div>
HTML;
    }
    
    // 历史消息状态显示
    $historyStatus = ($readHistory == '1') ? '✅' : '☑️';
    $deletedStatus = (isset($group['delFlag']) && $group['delFlag'] == '1') ? '✅' : '☑️';

    return <<<HTML
<div style="padding:15px; border-radius:10px; max-width:400px; background:#ffffff; border:1px solid #e0e0e0; font-family:Arial, sans-serif;">
  <h2 style="margin:0 0 15px 0; color:#333; font-size:18px; font-weight:bold; text-align:center;">云湖｜群组信息</h2>
  {$avatarHtml}
  <p style="margin:15px 0 8px 0; color:#333; font-size:15px; font-weight:bold; padding-left:8px; border-left:3px solid #4285f4;">基础信息</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">群组名称:</span> {$groupName}</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">群组ID:</span> {$groupId}｜(ID: {$groupIdInternal})</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">创建者ID:</span> {$createBy}</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">创建时间:</span> {$createTimeText}</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">创建时间戳:</span> {$createTime}</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">群组分类:</span> {$category}</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">成员数量:</span> {$headcount}</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">群组头像ID:</span> {$avatarId}</p>
  <p style="margin:0 0 12px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">群组接口:</span> {$uri}</p>

  <p style="margin:0 0 8px 0; color:#333; font-size:15px; font-weight:bold; padding-left:8px; border-left:3px solid #4285f4;">群组状态</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">开启历史消息:</span> {$historyStatus}</p>
  <p style="margin:0 0 12px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">群组是否删除:</span> {$deletedStatus}</p>

  <p style="margin:0 0 8px 0; color:#333; font-size:15px; font-weight:bold; padding-left:8px; border-left:3px solid #4285f4;">群组简介</p>
  <details style="margin:0 0 12px 0; color:#555; font-size:14px;">
    <summary style="cursor: pointer; color: #0066cc; font-weight: bold;">点击展开群组简介</summary>
    <div style="margin-top:5px; padding:8px; background:#f5f5f4; border-radius:4px; white-space:pre-line;">{$introduction}</div>
  </details>

  <p style="margin:0 0 8px 0; color:#333; font-size:15px; font-weight:bold; padding-left:8px; border-left:3px solid #4285f4;">群组关联机器人信息</p>
  <details style="margin:0 0 12px 0; color:#555; font-size:14px;">
    <summary style="cursor: pointer; color: #0066cc; font-weight: bold;">点击展开群组关联机器人信息</summary>
    <div style="margin-top:5px; padding:8px; background:#f5f5f4; border-radius:4px;">
      {$botRelHtml}
    </div>
  </details>

  <p style="margin:0 0 8px 0; color:#333; font-size:15px; font-weight:bold; padding-left:8px; border-left:3px solid #4285f4;">群组审核记录信息</p>
  <details style="margin:0 0 12px 0; color:#555; font-size:14px;">
    <summary style="cursor: pointer; color: #0066cc; font-weight: bold;">点击展开群组审核记录信息</summary>
    <div style="margin-top:5px; padding:8px; background:#f5f5f4; border-radius:4px;">
      {$checkRecordHtml}
    </div>
  </details>

  <p style="margin:0 0 15px 0; color:#555; font-size:14px;"><span style="color:#4285f4;">⏰</span> [查询时间] {$time}</p>
  <div style="text-align:right; font-size:10px; color:#888;">Powered by 云湖API｜Bot Version {$botVersion}</div>
</div>
HTML;
}

// 新增：机器人信息卡片
function getBotCard($botData) {
    if (empty($botData['bot'])) {
        return '';
    }
    
    $bot = $botData['bot'];
    $botId = htmlspecialchars($bot['botId'] ?? '未知');
    $botIdInternal = htmlspecialchars($bot['id'] ?? '0');
    $nickname = htmlspecialchars($bot['nickname'] ?? '未知');
    $nicknameId = htmlspecialchars($bot['nicknameId'] ?? '0');
    $avatarId = htmlspecialchars($bot['avatarId'] ?? '0');
    $avatarUrl = htmlspecialchars($bot['avatarUrl'] ?? '');
    $createBy = htmlspecialchars($bot['createBy'] ?? '未知');
    $createTime = htmlspecialchars($bot['createTime'] ?? '0');
    $createTimeText = !empty($createTime) && is_numeric($createTime) ? date('Y-m-d H:i:s', $createTime) : '未知';
    $headcount = htmlspecialchars($bot['headcount'] ?? '0');
    $uri = htmlspecialchars($bot['uri'] ?? '未知');
    $introduction = htmlspecialchars($bot['introduction'] ?? '无');
    $type = htmlspecialchars($bot['type'] ?? '0');
    $private = htmlspecialchars($bot['private'] ?? '0');
    $isStop = htmlspecialchars($bot['isStop'] ?? '0');
    $settingJson = htmlspecialchars($bot['settingJson'] ?? '无');
    $delFlag = htmlspecialchars($bot['del_flag'] ?? '0');
    $alwaysAgree = htmlspecialchars($bot['alwaysAgree'] ?? '0');
    $groupLimit = htmlspecialchars($bot['groupLimit'] ?? '0');
    $banId = htmlspecialchars($bot['banId'] ?? '0');
    $linkStop = htmlspecialchars($bot['linkStop'] ?? '0');
    $isBigModel = htmlspecialchars($bot['isBigModel'] ?? '0');
    $token = htmlspecialchars($bot['token'] ?? '无');
    $link = htmlspecialchars($bot['link'] ?? '无');
    
    $checkRecord = $bot['checkChatInfoRecord'] ?? [];
    
    $time = date('Y-m-d H:i:s');
    $botVersion = $GLOBALS['BOT_VERSION'];
    
    // 状态显示
    $privateStatus = ($private == '1') ? '✅' : '☑️';
    $stopStatus = ($isStop == '1') ? '✅' : '☑️';
    $agreeStatus = ($alwaysAgree == '1') ? '✅' : '☑️';
    $groupLimitStatus = ($groupLimit == '1') ? '✅' : '☑️';
    $linkStopStatus = ($linkStop == '1') ? '✅' : '☑️';
    $bigModelStatus = ($isBigModel == '1') ? '✅' : '☑️';
    $deleteStatus = ($delFlag == '1') ? '✅' : '☑️';
    
    // 审核记录信息
    $checkRecordHtml = '';
    if (!empty($checkRecord)) {
        $checkRecordHtml = <<<HTML
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">审核记录ID:</span> {$checkRecord['id']}</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">聊天ID:</span> {$checkRecord['chatId']}</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">聊天类型:</span> {$checkRecord['chatType']}</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">审核方式:</span> {$checkRecord['checkWay']}</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">审核原因:</span> {$checkRecord['reason']}</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">审核状态:</span> {$checkRecord['status']}</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">审核创建时间戳:</span> {$checkRecord['createTime']}</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">审核创建时间:</span> {$createTimeText}</p>
        <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">审核更新时间:</span> {$checkRecord['updateTime']}</p>
        <p style="margin:0;"><span style="color:#333; font-weight:500;">审核删除标志:</span> {$checkRecord['delFlag']}</p>
HTML;
    }
    
    // 头像显示
    $avatarHtml = '';
    if (!empty($avatarUrl)) {
        $avatarHtml = <<<HTML
  <a href="https://www.yhchat.com/bot/homepage/{$botId}?userId=7058262" target="_blank" style="display:block; text-align:center; text-decoration:none;">
    <img src="{$avatarUrl}" style="width:256px; height:256px; margin:0 auto; object-fit:cover; border-radius:8px;">
  </a>
HTML;
    } else {
        $avatarHtml = <<<HTML
  <div style="text-align:center; margin:10px 0;">
    <div style="width:256px; height:256px; margin:0 auto; background:#f0f0f0; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#888;">
      暂无机器人头像
    </div>
  </div>
HTML;
    }

    return <<<HTML
<div style="padding:15px; border-radius:10px; max-width:400px; background:#ffffff; border:1px solid #e0e0e0; font-family:Arial, sans-serif;">
  <h2 style="margin:0 0 15px 0; color:#333; font-size:18px; font-weight:bold; text-align:center;">云湖｜机器人信息</h2>
  {$avatarHtml}
  <p style="margin:15px 0 8px 0; color:#333; font-size:15px; font-weight:bold; padding-left:8px; border-left:3px solid #4285f4;">基础核心信息</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">机器人昵称:</span> {$nickname}</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">机器人ID:</span> {$botId}｜(ID: {$botIdInternal})</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">昵称ID:</span> {$nicknameId}</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">头像ID:</span> {$avatarId}</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">创建者ID:</span> {$createBy}</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">创建时间戳:</span> {$createTime}</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">创建时间:</span> {$createTimeText}</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">使用数量:</span> {$headcount}</p>
  <p style="margin:0 0 12px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">接口地址:</span> {$uri}</p>

  <p style="margin:0 0 8px 0; color:#333; font-size:15px; font-weight:bold; padding-left:8px; border-left:3px solid #4285f4;">机器人简介</p>
  <details style="margin:0 0 12px 0; color:#555; font-size:14px;">
    <summary style="cursor: pointer; color: #0066cc; font-weight: bold;">点击展开机器人简介</summary>
    <div style="margin-top:5px; padding:8px; background:#f5f5f4; border-radius:4px; color:#555; font-size:14px;">{$introduction}</div>
  </details>

  <p style="margin:0 0 8px 0; color:#333; font-size:15px; font-weight:bold; padding-left:8px; border-left:3px solid #4285f4;">功能配置信息</p>
  <details style="margin:0 0 12px 0; color:#555; font-size:14px;">
    <summary style="cursor: pointer; color: #0066cc; font-weight: bold;">点击展开配置详情</summary>
    <div style="margin-top:5px; padding:8px; background:#f5f5f4; border-radius:4px;">
      <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">机器人类型:</span> {$type}</p>
      <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">是否私有:</span> {$privateStatus}</p>
      <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">是否停止:</span> {$stopStatus}</p>
      <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">是否总是同意:</span> {$agreeStatus}</p>
      <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">是否群组限制:</span> {$groupLimitStatus}</p>
      <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">封禁ID:</span> {$banId}</p>
      <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">是否链接停止:</span> {$linkStopStatus}</p>
      <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">是否大模型:</span> {$bigModelStatus}</p>
      <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">是否删除:</span> {$deleteStatus}</p>
      <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">设置JSON:</span> {$settingJson}</p>
      <p style="margin:0 0 3px 0;"><span style="color:#333; font-weight:500;">机器人令牌:</span> {$token}</p>
      <p style="margin:0;"><span style="color:#333; font-weight:500;">机器人链接:</span> {$link}</p>
    </div>
  </details>

  <p style="margin:0 0 8px 0; color:#333; font-size:15px; font-weight:bold; padding-left:8px; border-left:3px solid #4285f4;">机器人审核记录信息</p>
  <details style="margin:0 0 12px 0; color:#555; font-size:14px;">
    <summary style="cursor: pointer; color: #0066cc; font-weight: bold;">点击展开机器人审核记录信息</summary>
    <div style="margin-top:5px; padding:8px; background:#f5f5f4; border-radius:4px;">
      {$checkRecordHtml}
    </div>
  </details>

  <p style="margin:0 0 15px 0; color:#555; font-size:14px;"><span style="color:#4285f4;">⏰</span> [查询时间] {$time}</p>
  <div style="text-align:right; font-size:10px; color:#888;">Powered by 云湖API｜Bot Version {$botVersion}</div>
</div>
HTML;
}

function getHelpCard() {
    $time = date('Y-m-d H:i:s');
    $botVersion = $GLOBALS['BOT_VERSION'];
    
    return <<<HTML
<div style="padding:15px; border-radius:10px; max-width:300px; background:#ffffff; border:1px solid #e0e0e0; font-family:Arial, sans-serif;">
  <h2 style="margin:0 0 12px 0; color:#333; font-size:18px; font-weight:bold; text-align:center;">云湖查询机器人｜帮助菜单</h2>
  <p style="margin:0 0 10px 0; color:#333; font-size:15px; font-weight:bold; padding-left:8px; border-left:3px solid #4285f4;">可用指令</p>
  <div style="margin:0 0 10px 0; padding:10px; background:#f5f5f4; border-radius:6px;">
    <p style="margin:0 0 3px 0; color:#333; font-size:14px; font-weight:500;">版本信息查询</p>
    <p style="margin:0 0 3px 0; color:#555; font-size:13px;">[指令ID] 2247</p>
    <p style="margin:0 0 3px 0; color:#555; font-size:13px;">[格式] 无需输入内容</p>
    <p style="margin:0; color:#555; font-size:13px;">[示例] 直接发送指令</p>
  </div>
  <div style="margin:0 0 10px 0; padding:10px; background:#f5f5f4; border-radius:6px;">
    <p style="margin:0 0 3px 0; color:#333; font-size:14px; font-weight:500;">用户信息查询</p>
    <p style="margin:0 0 3px 0; color:#555; font-size:13px;">[指令ID] 2248</p>
    <p style="margin:0 0 3px 0; color:#555; font-size:13px;">[格式] 直接输入用户ID</p>
    <p style="margin:0; color:#555; font-size:13px;">[示例] <code style="background:#f0f0f0; padding:2px 4px; border-radius:2px;">7058262</code></p>
  </div>
  <div style="margin:0 0 10px 0; padding:10px; background:#f5f5f4; border-radius:6px;">
    <p style="margin:0 0 3px 0; color:#333; font-size:14px; font-weight:500;">群组信息查询</p>
    <p style="margin:0 0 3px 0; color:#555; font-size:13px;">[指令ID] 2249</p>
    <p style="margin:0 0 3px 0; color:#555; font-size:13px;">[格式] 直接输入群组ID</p>
    <p style="margin:0; color:#555; font-size:13px;">[示例] <code style="background:#f0f0f0; padding:2px 4px; border-radius:2px;">730197213</code></p>
  </div>
  <div style="margin:0 0 10px 0; padding:10px; background:#f5f5f4; border-radius:6px;">
    <p style="margin:0 0 3px 0; color:#333; font-size:14px; font-weight:500;">机器人信息查询</p>
    <p style="margin:0 0 3px 0; color:#555; font-size:13px;">[指令ID] 2250</p>
    <p style="margin:0 0 3px 0; color:#555; font-size:13px;">[格式] 直接输入机器人ID</p>
    <p style="margin:0; color:#555; font-size:13px;">[示例] <code style="background:#f0f0f0; padding:2px 4px; border-radius:2px;">43272366</code></p>
  </div>
  <div style="margin:0 0 10px 0; padding:10px; background:#f5f5f4; border-radius:6px;">
    <p style="margin:0 0 3px 0; color:#333; font-size:14px; font-weight:500;">帮助信息查询</p>
    <p style="margin:0 0 3px 0; color:#555; font-size:13px;">[指令ID] 2215</p>
    <p style="margin:0 0 3px 0; color:#555; font-size:13px;">[格式] 无需输入内容</p>
    <p style="margin:0; color:#555; font-size:13px;">[示例] 直接发送指令</p>
  </div>
  <p style="margin:0 0 15px 0; color:#555; font-size:14px;"><span style="color:#4285f4;">⏰</span> [查询时间] {$time}</p>
  <div style="text-align:right; font-size:10px; color:#888;">Powered by 云湖API｜Bot Version {$botVersion}</div>
</div>
HTML;
}

function getErrorCard($message, $code = 'ERR') {
    $time = date('Y-m-d H:i:s');
    $message = htmlspecialchars($message);
    $code = htmlspecialchars($code);
    
    return <<<HTML
<div style="padding:15px; border-radius:10px; max-width:300px; background:#fff5f5; border:1px solid #ffcccc; font-family:Arial, sans-serif; color:#d32f2f;">
  <h2 style="margin:0 0 12px 0; font-size:18px; font-weight:bold; text-align:center;">❌ 查询失败</h2>
  <p style="margin:0 0 8px 0; font-size:14px;"><strong>错误代码:</strong> {$code}</p>
  <p style="margin:0 0 12px 0; font-size:14px;"><strong>错误详情:</strong><br>{$message}</p>
  <p style="margin:0; font-size:12px; color:#888;"><span style="color:#d32f2f;">⏰</span> [发生时间] {$time}</p>
  <div style="text-align:right; font-size:10px; color:#888; margin-top:10px;">云湖 API Error</div>
</div>
HTML;
}

/* ================== 🎯 主事件处理 ================== */
$event_type = get_event_type();

if ($event_type === 'message.receive.instruction') {
    $cmd = get_command_info();
    $content = trim(get_message_content());
    $back = get_back_object();

    if (!$cmd || !isset($cmd['commandId'])) {
        exit;
    }

    global $SUPPORTED_COMMANDS;
    $commandId = intval($cmd['commandId']);
    
    yhsdk_write_log("收到指令: ID={$commandId}, 内容='{$content}'");
    
    if (!in_array($commandId, $SUPPORTED_COMMANDS)) {
        yhsdk_write_log("忽略不支持的指令ID: {$commandId}");
        exit;
    }

    $loading = send($back, 'html',
        '<div style="padding:10px;text-align:center;">⏳ 正在查询，请稍候...</div>'
    );

    $msgId = $loading['data']['messageInfo']['msgId'] ?? $loading['data']['msgId'] ?? null;

    try {
        switch ($commandId) {
            case 2215:
                yhsdk_write_log("处理帮助指令");
                if ($msgId) {
                    edit($msgId, $back, 'html', getHelpCard());
                } else {
                    send($back, 'html', getHelpCard());
                }
                break;

                case 2247:
                yhsdk_write_log("处理版本查询");
                $versionInfo = getVersionInfo();
                if (!$versionInfo['success']) {
                    $errorMsg = $versionInfo['message'] ?? '未知错误';
                    $errorCode = $versionInfo['code'] ?? 'API_ERROR';
                    
                    $error = getErrorCard($errorMsg, $errorCode);
                    if ($msgId) {
                        edit($msgId, $back, 'html', $error);
                    } else {
                        send($back, 'html', $error);
                    }
                    break;
                }
                
                if (empty($versionInfo['data'])) {
                    $error = getErrorCard('未找到版本信息', 'NO_DATA');
                    if ($msgId) {
                        edit($msgId, $back, 'html', $error);
                    } else {
                        send($back, 'html', $error);
                    }
                    break;
                }
                
                $card = getVersionCard($versionInfo['data']);
                
                if (empty($card)) {
                    $error = getErrorCard('版本数据解析失败', 'PARSE_ERROR');
                    if ($msgId) {
                        edit($msgId, $back, 'html', $error);
                    } else {
                        send($back, 'html', $error);
                    }
                    break;
                }
                
                if ($msgId) {
                    edit($msgId, $back, 'html', $card);
                } else {
                    send($back, 'html', $card);
                }
                break;

            case 2248:
                yhsdk_write_log("处理用户查询: ID={$content}");
                if (empty($content) || !is_numeric($content)) {
                    $error = getErrorCard('请输入有效的数字用户ID', 'INVALID_INPUT');
                    if ($msgId) {
                        edit($msgId, $back, 'html', $error);
                    } else {
                        send($back, 'html', $error);
                    }
                    break;
                }
                
                $userInfo = getUserInfo($content);
                if (!$userInfo['success']) {
                    $errorMsg = $userInfo['message'] ?? '未知错误';
                    $errorCode = $userInfo['code'] ?? 'API_ERROR';
                    
                    if ($userInfo['code'] == 0) {
                        $errorMsg = '用户不存在或ID无效';
                    }
                    
                    $error = getErrorCard($errorMsg, $errorCode);
                    if ($msgId) {
                        edit($msgId, $back, 'html', $error);
                    } else {
                        send($back, 'html', $error);
                    }
                    break;
                }
                
                if (empty($userInfo['data'])) {
                    $error = getErrorCard('未找到用户信息', 'NO_DATA');
                    if ($msgId) {
                        edit($msgId, $back, 'html', $error);
                    } else {
                        send($back, 'html', $error);
                    }
                    break;
                }
                
                $card = getUserCard($userInfo['data']);
                
                if (empty($card)) {
                    $error = getErrorCard('用户数据解析失败', 'PARSE_ERROR');
                    if ($msgId) {
                        edit($msgId, $back, 'html', $error);
                    } else {
                        send($back, 'html', $error);
                    }
                    break;
                }
                
                if ($msgId) {
                    edit($msgId, $back, 'html', $card);
                } else {
                    send($back, 'html', $card);
                }
                break;

            case 2249:
                // 新增：群组信息查询
                yhsdk_write_log("处理群组查询: ID={$content}");
                if (empty($content) || !is_numeric($content)) {
                    $error = getErrorCard('请输入有效的数字群组ID', 'INVALID_INPUT');
                    if ($msgId) {
                        edit($msgId, $back, 'html', $error);
                    } else {
                        send($back, 'html', $error);
                    }
                    break;
                }
                
                $groupInfo = getGroupInfo($content);
                if (!$groupInfo['success']) {
                    $errorMsg = $groupInfo['message'] ?? '未知错误';
                    $errorCode = $groupInfo['code'] ?? 'API_ERROR';
                    
                    if ($groupInfo['code'] == 0) {
                        $errorMsg = '群组不存在或ID无效';
                    }
                    
                    $error = getErrorCard($errorMsg, $errorCode);
                    if ($msgId) {
                        edit($msgId, $back, 'html', $error);
                    } else {
                        send($back, 'html', $error);
                    }
                    break;
                }
                
                if (empty($groupInfo['data'])) {
                    $error = getErrorCard('未找到群组信息', 'NO_DATA');
                    if ($msgId) {
                        edit($msgId, $back, 'html', $error);
                    } else {
                        send($back, 'html', $error);
                    }
                    break;
                }
                
                $card = getGroupCard($groupInfo['data']);
                
                if (empty($card)) {
                    $error = getErrorCard('群组数据解析失败', 'PARSE_ERROR');
                    if ($msgId) {
                        edit($msgId, $back, 'html', $error);
                    } else {
                        send($back, 'html', $error);
                    }
                    break;
                }
                
                if ($msgId) {
                    edit($msgId, $back, 'html', $card);
                } else {
                    send($back, 'html', $card);
                }
                break;

            case 2250:
                // 新增：机器人信息查询
                yhsdk_write_log("处理机器人查询: ID={$content}");
                if (empty($content) || !is_numeric($content)) {
                    $error = getErrorCard('请输入有效的数字机器人ID', 'INVALID_INPUT');
                    if ($msgId) {
                        edit($msgId, $back, 'html', $error);
                    } else {
                        send($back, 'html', $error);
                    }
                    break;
                }
                
                $botInfo = getBotInfo($content);
                if (!$botInfo['success']) {
                    $errorMsg = $botInfo['message'] ?? '未知错误';
                    $errorCode = $botInfo['code'] ?? 'API_ERROR';
                    
                    if ($botInfo['code'] == 0) {
                        $errorMsg = '机器人不存在或ID无效';
                    }
                    
                    $error = getErrorCard($errorMsg, $errorCode);
                    if ($msgId) {
                        edit($msgId, $back, 'html', $error);
                    } else {
                        send($back, 'html', $error);
                    }
                    break;
                }
                
                if (empty($botInfo['data'])) {
                    $error = getErrorCard('未找到机器人信息', 'NO_DATA');
                    if ($msgId) {
                        edit($msgId, $back, 'html', $error);
                    } else {
                        send($back, 'html', $error);
                    }
                    break;
                }
                
                $card = getBotCard($botInfo['data']);
                
                if (empty($card)) {
                    $error = getErrorCard('机器人数据解析失败', 'PARSE_ERROR');
                    if ($msgId) {
                        edit($msgId, $back, 'html', $error);
                    } else {
                        send($back, 'html', $error);
                    }
                    break;
                }
                
                if ($msgId) {
                    edit($msgId, $back, 'html', $card);
                } else {
                    send($back, 'html', $card);
                }
                break;

            default:
                // yhsdk_write_log("未知指令ID: {$commandId}");
                exit;
        }
    } catch (Throwable $e) {
        error_log('云湖 Bot Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        yhsdk_write_log("异常: " . $e->getMessage());
        $error = getErrorCard('服务器内部错误，请稍后重试', 'INTERNAL_ERROR');
        if ($msgId) {
            edit($msgId, $back, 'html', $error);
        } else {
            send($back, 'html', $error);
        }
    }
} else {
    // yhsdk_write_log("非指令消息事件: {$event_type}");
    exit;
}

// 记录启动日志
yhsdk_write_log("云湖功能机器人启动 - 版本 {$BOT_VERSION}");
?>
