<?php
/**
 * 云湖机器人 Webhook 入口文件
 * 
 * 使用方法：
 * 1. 配置机器人token和参数
 * 2. 设置事件处理器
 * 3. 启动webhook服务
 */

// 引入优化后的SDK
require_once __DIR__ . '/yunhubot_sdk_optimized.php';

// 配置区域 - 请根据实际情况修改
define('BOT_TOKEN', '这里填写你的机器人token'); // 必须修改为你的机器人token
define('BOT_NAME', '云湖查询机器人'); // 机器人显示名称
define('BOT_VERSION', '2.0.0'); // 机器人版本

// 支持的指令ID数组
$SUPPORTED_COMMANDS = [2215, 2247, 2248, 2249, 2250];

// 初始化机器人
$config = new YunhuBotConfig([
    'log_path' => 'yunhu_bot_webhook.log',
    'debug' => false
]);

$bot = YunhuBotManager::createBot(BOT_TOKEN, $config);

// API请求封装类
class YunhuApiClient {
    private $baseUrl = 'https://chat-web-go.jwzhd.com/v1/';
    private $logger;
    
    public function __construct($logger) {
        $this->logger = $logger;
    }
    
    public function request($endpoint, $method = 'GET', $params = []) {
        $url = $this->baseUrl . $endpoint;
        
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; YunhuBot/2.0)',
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
}

// API客户端实例
$apiClient = new YunhuApiClient($bot->getLogger());

// 业务逻辑函数
class BotBusinessLogic {
    private $apiClient;
    private $logger;
    private $botVersion;
    
    public function __construct($apiClient, $logger, $botVersion) {
        $this->apiClient = $apiClient;
        $this->logger = $logger;
        $this->botVersion = $botVersion;
    }
    
    public function getVersionInfo() {
        return $this->apiClient->request("common/get-version");
    }
    
    public function getUserInfo($userId) {
        if (empty($userId) || !is_numeric($userId)) {
            return ['success' => false, 'code' => 400, 'message' => '用户ID必须是数字'];
        }
        return $this->apiClient->request("user/homepage", 'GET', ['userId' => $userId]);
    }
    
    public function getGroupInfo($groupId) {
        if (empty($groupId) || !is_numeric($groupId)) {
            return ['success' => false, 'code' => 400, 'message' => '群组ID必须是数字'];
        }
        return $this->apiClient->request("group/group-info", 'POST', ['groupId' => $groupId]);
    }
    
    public function getBotInfo($botId) {
        if (empty($botId) || !is_numeric($botId)) {
            return ['success' => false, 'code' => 400, 'message' => '机器人ID必须是数字'];
        }
        return $this->apiClient->request("bot/bot-info", 'POST', ['botId' => $botId]);
    }
    
    public function generateCard($type, $data) {
        switch ($type) {
            case 'version':
                return $this->generateVersionCard($data);
            case 'user':
                return $this->generateUserCard($data);
            case 'group':
                return $this->generateGroupCard($data);
            case 'bot':
                return $this->generateBotCard($data);
            case 'help':
                return $this->generateHelpCard();
            case 'error':
                return $this->generateErrorCard($data['message'], $data['code'] ?? 'ERR');
            default:
                return '';
        }
    }
    
    private function generateVersionCard($versionData) {
        if (empty($versionData)) return '';
        
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
  <div style="text-align:right; font-size:10px; color:#888;">Powered by 云湖API｜Bot Version {$this->botVersion}</div>
</div>
HTML;
    }
    
    private function generateUserCard($userData) {
        if (empty($userData['user'])) return '';
        
        $user = $userData['user'];
        $userId = htmlspecialchars($user['userId'] ?? '未知');
        $nickname = htmlspecialchars($user['nickname'] ?? '未知');
        $avatarUrl = htmlspecialchars($user['avatarUrl'] ?? '');
        $registerTime = htmlspecialchars($user['registerTimeText'] ?? '未知');
        $isVip = isset($user['isVip']) && $user['isVip'] == 1 ? '✅' : '☑️';
        $onLineDay = htmlspecialchars($user['onLineDay'] ?? '0');
        $continuousOnLineDay = htmlspecialchars($user['continuousOnLineDay'] ?? '0');
        $time = date('Y-m-d H:i:s');
        
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
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">是否会员:</span> {$isVip}</p>
  <p style="margin:0 0 8px 0; color:#333; font-size:15px; font-weight:bold; padding-left:8px; border-left:3px solid #4285f4;">在线数据</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">累计在线天数:</span> {$onLineDay}天</p>
  <p style="margin:0 0 12px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">连续在线天数:</span> {$continuousOnLineDay}天</p>
  <p style="margin:0 0 15px 0; color:#555; font-size:14px;"><span style="color:#4285f4;">⏰</span> [查询时间] {$time}</p>
  <div style="text-align:right; font-size:10px; color:#888;">Powered by 云湖API｜Bot Version {$this->botVersion}</div>
</div>
HTML;
    }
    
    private function generateGroupCard($groupData) {
        if (empty($groupData['group'])) return '';
        
        $group = $groupData['group'];
        $groupId = htmlspecialchars($group['groupId'] ?? '未知');
        $groupName = htmlspecialchars($group['name'] ?? '未知');
        $createBy = htmlspecialchars($group['createBy'] ?? '未知');
        $headcount = htmlspecialchars($group['headcount'] ?? '0');
        $time = date('Y-m-d H:i:s');
        
        return <<<HTML
<div style="padding:15px; border-radius:10px; max-width:300px; background:#ffffff; border:1px solid #e0e0e0; font-family:Arial, sans-serif;">
  <h2 style="margin:0 0 15px 0; color:#333; font-size:18px; font-weight:bold; text-align:center;">云湖｜群组信息</h2>
  <p style="margin:0 0 8px 0; color:#333; font-size:15px; font-weight:bold; padding-left:8px; border-left:3px solid #4285f4;">基础信息</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">群组名称:</span> {$groupName}</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">群组ID:</span> {$groupId}</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">创建者ID:</span> {$createBy}</p>
  <p style="margin:0 0 12px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">成员数量:</span> {$headcount}</p>
  <p style="margin:0 0 15px 0; color:#555; font-size:14px;"><span style="color:#4285f4;">⏰</span> [查询时间] {$time}</p>
  <div style="text-align:right; font-size:10px; color:#888;">Powered by 云湖API｜Bot Version {$this->botVersion}</div>
</div>
HTML;
    }
    
    private function generateBotCard($botData) {
        if (empty($botData['bot'])) return '';
        
        $bot = $botData['bot'];
        $botId = htmlspecialchars($bot['botId'] ?? '未知');
        $nickname = htmlspecialchars($bot['nickname'] ?? '未知');
        $headcount = htmlspecialchars($bot['headcount'] ?? '0');
        $time = date('Y-m-d H:i:s');
        
        return <<<HTML
<div style="padding:15px; border-radius:10px; max-width:300px; background:#ffffff; border:1px solid #e0e0e0; font-family:Arial, sans-serif;">
  <h2 style="margin:0 0 15px 0; color:#333; font-size:18px; font-weight:bold; text-align:center;">云湖｜机器人信息</h2>
  <p style="margin:0 0 8px 0; color:#333; font-size:15px; font-weight:bold; padding-left:8px; border-left:3px solid #4285f4;">基础信息</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">机器人昵称:</span> {$nickname}</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">机器人ID:</span> {$botId}</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><span style="color:#333; font-weight:500;">使用数量:</span> {$headcount}</p>
  <p style="margin:0 0 15px 0; color:#555; font-size:14px;"><span style="color:#4285f4;">⏰</span> [查询时间] {$time}</p>
  <div style="text-align:right; font-size:10px; color:#888;">Powered by 云湖API｜Bot Version {$this->botVersion}</div>
</div>
HTML;
    }
    
    private function generateHelpCard() {
        $time = date('Y-m-d H:i:s');
        
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
    <p style="margin:0; color:#555; font-size:13px;">[示例] 7058262</p>
  </div>
  <div style="margin:0 0 10px 0; padding:10px; background:#f5f5f4; border-radius:6px;">
    <p style="margin:0 0 3px 0; color:#333; font-size:14px; font-weight:500;">群组信息查询</p>
    <p style="margin:0 0 3px 0; color:#555; font-size:13px;">[指令ID] 2249</p>
    <p style="margin:0 0 3px 0; color:#555; font-size:13px;">[格式] 直接输入群组ID</p>
    <p style="margin:0; color:#555; font-size:13px;">[示例] 730197213</p>
  </div>
  <div style="margin:0 0 10px 0; padding:10px; background:#f5f5f4; border-radius:6px;">
    <p style="margin:0 0 3px 0; color:#333; font-size:14px; font-weight:500;">机器人信息查询</p>
    <p style="margin:0 0 3px 0; color:#555; font-size:13px;">[指令ID] 2250</p>
    <p style="margin:0 0 3px 0; color:#555; font-size:13px;">[格式] 直接输入机器人ID</p>
    <p style="margin:0; color:#555; font-size:13px;">[示例] 43272366</p>
  </div>
  <div style="margin:0 0 15px 0; padding:10px; background:#f5f5f4; border-radius:6px;">
    <p style="margin:0 0 3px 0; color:#333; font-size:14px; font-weight:500;">帮助信息查询</p>
    <p style="margin:0 0 3px 0; color:#555; font-size:13px;">[指令ID] 2215</p>
    <p style="margin:0 0 3px 0; color:#555; font-size:13px;">[格式] 无需输入内容</p>
    <p style="margin:0; color:#555; font-size:13px;">[示例] 直接发送指令</p>
  </div>
  <p style="margin:0 0 15px 0; color:#555; font-size:14px;"><span style="color:#4285f4;">⏰</span> [查询时间] {$time}</p>
  <div style="text-align:right; font-size:10px; color:#888;">Powered by 云湖API｜Bot Version {$this->botVersion}</div>
</div>
HTML;
    }
    
    private function generateErrorCard($message, $code = 'ERR') {
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
}

// 业务逻辑实例
$businessLogic = new BotBusinessLogic($apiClient, $bot->getLogger(), BOT_VERSION);

// 创建Webhook处理器
$webhook = new YunhuBotWebhook($bot);

// 设置指令事件处理器
$webhook->onInstruction(function($event, $bot) use ($businessLogic, $SUPPORTED_COMMANDS) {
    $commandInfo = $event->getCommandInfo();
    $content = trim($event->getMessageContent());
    $backObject = $event->getBackObject();
    
    if (!$commandInfo || !isset($commandInfo['commandId'])) {
        return false;
    }
    
    $commandId = intval($commandInfo['commandId']);
    $bot->log("收到指令", 'INFO', [
        'command_id' => $commandId,
        'content' => $content,
        'supported_commands' => $SUPPORTED_COMMANDS
    ]);
    
    if (!in_array($commandId, $SUPPORTED_COMMANDS)) {
        $bot->log("不支持的指令ID", 'WARNING', ['command_id' => $commandId]);
        return false;
    }
    
    // 发送加载中消息
    $loadingResult = $bot->sendText($backObject, '⏳ 正在查询，请稍候...');
    $msgId = null;
    if ($loadingResult && isset($loadingResult['data'])) {
        $msgId = $loadingResult['data']['messageInfo']['msgId'] ?? $loadingResult['data']['msgId'] ?? null;
    }
    
    try {
        switch ($commandId) {
            case 2215:
                $bot->log("处理帮助指令", 'INFO');
                $card = $businessLogic->generateCard('help', []);
                if ($msgId) {
                    $bot->edit($msgId, $backObject, 'html', $card);
                } else {
                    $bot->sendHtml($backObject, $card);
                }
                break;
                
            case 2247:
                $bot->log("处理版本查询", 'INFO');
                $versionInfo = $businessLogic->getVersionInfo();
                if (!$versionInfo['success']) {
                    $errorMsg = $versionInfo['message'] ?? '未知错误';
                    $errorCode = $versionInfo['code'] ?? 'API_ERROR';
                    $card = $businessLogic->generateCard('error', ['message' => $errorMsg, 'code' => $errorCode]);
                    if ($msgId) {
                        $bot->edit($msgId, $backObject, 'html', $card);
                    } else {
                        $bot->sendHtml($backObject, $card);
                    }
                    break;
                }
                
                if (empty($versionInfo['data'])) {
                    $card = $businessLogic->generateCard('error', ['message' => '未找到版本信息', 'code' => 'NO_DATA']);
                    if ($msgId) {
                        $bot->edit($msgId, $backObject, 'html', $card);
                    } else {
                        $bot->sendHtml($backObject, $card);
                    }
                    break;
                }
                
                $card = $businessLogic->generateCard('version', $versionInfo['data']);
                if ($msgId) {
                    $bot->edit($msgId, $backObject, 'html', $card);
                } else {
                    $bot->sendHtml($backObject, $card);
                }
                break;
                
            case 2248:
                $bot->log("处理用户查询", 'INFO', ['user_id' => $content]);
                if (empty($content) || !is_numeric($content)) {
                    $card = $businessLogic->generateCard('error', ['message' => '请输入有效的数字用户ID', 'code' => 'INVALID_INPUT']);
                    if ($msgId) {
                        $bot->edit($msgId, $backObject, 'html', $card);
                    } else {
                        $bot->sendHtml($backObject, $card);
                    }
                    break;
                }
                
                $userInfo = $businessLogic->getUserInfo($content);
                if (!$userInfo['success']) {
                    $errorMsg = $userInfo['message'] ?? '未知错误';
                    $errorCode = $userInfo['code'] ?? 'API_ERROR';
                    if ($userInfo['code'] == 0) {
                        $errorMsg = '用户不存在或ID无效';
                    }
                    $card = $businessLogic->generateCard('error', ['message' => $errorMsg, 'code' => $errorCode]);
                    if ($msgId) {
                        $bot->edit($msgId, $backObject, 'html', $card);
                    } else {
                        $bot->sendHtml($backObject, $card);
                    }
                    break;
                }
                
                if (empty($userInfo['data'])) {
                    $card = $businessLogic->generateCard('error', ['message' => '未找到用户信息', 'code' => 'NO_DATA']);
                    if ($msgId) {
                        $bot->edit($msgId, $backObject, 'html', $card);
                    } else {
                        $bot->sendHtml($backObject, $card);
                    }
                    break;
                }
                
                $card = $businessLogic->generateCard('user', $userInfo['data']);
                if ($msgId) {
                    $bot->edit($msgId, $backObject, 'html', $card);
                } else {
                    $bot->sendHtml($backObject, $card);
                }
                break;
                
            case 2249:
                $bot->log("处理群组查询", 'INFO', ['group_id' => $content]);
                if (empty($content) || !is_numeric($content)) {
                    $card = $businessLogic->generateCard('error', ['message' => '请输入有效的数字群组ID', 'code' => 'INVALID_INPUT']);
                    if ($msgId) {
                        $bot->edit($msgId, $backObject, 'html', $card);
                    } else {
                        $bot->sendHtml($backObject, $card);
                    }
                    break;
                }
                
                $groupInfo = $businessLogic->getGroupInfo($content);
                if (!$groupInfo['success']) {
                    $errorMsg = $groupInfo['message'] ?? '未知错误';
                    $errorCode = $groupInfo['code'] ?? 'API_ERROR';
                    if ($groupInfo['code'] == 0) {
                        $errorMsg = '群组不存在或ID无效';
                    }
                    $card = $businessLogic->generateCard('error', ['message' => $errorMsg, 'code' => $errorCode]);
                    if ($msgId) {
                        $bot->edit($msgId, $backObject, 'html', $card);
                    } else {
                        $bot->sendHtml($backObject, $card);
                    }
                    break;
                }
                
                if (empty($groupInfo['data'])) {
                    $card = $businessLogic->generateCard('error', ['message' => '未找到群组信息', 'code' => 'NO_DATA']);
                    if ($msgId) {
                        $bot->edit($msgId, $backObject, 'html', $card);
                    } else {
                        $bot->sendHtml($backObject, $card);
                    }
                    break;
                }
                
                $card = $businessLogic->generateCard('group', $groupInfo['data']);
                if ($msgId) {
                    $bot->edit($msgId, $backObject, 'html', $card);
                } else {
                    $bot->sendHtml($backObject, $card);
                }
                break;
                
            case 2250:
                $bot->log("处理机器人查询", 'INFO', ['bot_id' => $content]);
                if (empty($content) || !is_numeric($content)) {
                    $card = $businessLogic->generateCard('error', ['message' => '请输入有效的数字机器人ID', 'code' => 'INVALID_INPUT']);
                    if ($msgId) {
                        $bot->edit($msgId, $backObject, 'html', $card);
                    } else {
                        $bot->sendHtml($backObject, $card);
                    }
                    break;
                }
                
                $botInfo = $businessLogic->getBotInfo($content);
                if (!$botInfo['success']) {
                    $errorMsg = $botInfo['message'] ?? '未知错误';
                    $errorCode = $botInfo['code'] ?? 'API_ERROR';
                    if ($botInfo['code'] == 0) {
                        $errorMsg = '机器人不存在或ID无效';
                    }
                    $card = $businessLogic->generateCard('error', ['message' => $errorMsg, 'code' => $errorCode]);
                    if ($msgId) {
                        $bot->edit($msgId, $backObject, 'html', $card);
                    } else {
                        $bot->sendHtml($backObject, $card);
                    }
                    break;
                }
                
                if (empty($botInfo['data'])) {
                    $card = $businessLogic->generateCard('error', ['message' => '未找到机器人信息', 'code' => 'NO_DATA']);
                    if ($msgId) {
                        $bot->edit($msgId, $backObject, 'html', $card);
                    } else {
                        $bot->sendHtml($backObject, $card);
                    }
                    break;
                }
                
                $card = $businessLogic->generateCard('bot', $botInfo['data']);
                if ($msgId) {
                    $bot->edit($msgId, $backObject, 'html', $card);
                } else {
                    $bot->sendHtml($backObject, $card);
                }
                break;
                
            default:
                $bot->log("未知指令ID", 'WARNING', ['command_id' => $commandId]);
                return false;
        }
        
        return true;
        
    } catch (Exception $e) {
        $bot->log("事件处理异常", 'ERROR', [
            'command_id' => $commandId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        $card = $businessLogic->generateCard('error', ['message' => '服务器内部错误，请稍后重试', 'code' => 'INTERNAL_ERROR']);
        if ($msgId) {
            $bot->edit($msgId, $backObject, 'html', $card);
        } else {
            $bot->sendHtml($backObject, $card);
        }
        return false;
    }
});

// 处理webhook请求
try {
    $bot->log("Webhook服务启动", 'INFO', ['bot_name' => BOT_NAME, 'version' => BOT_VERSION]);
    
    $result = $webhook->run();
    
    if ($result) {
        $bot->log("Webhook处理成功", 'INFO');
    } else {
        $bot->log("Webhook处理失败或无需处理", 'WARNING');
    }
    
} catch (Exception $e) {
    $bot->log("Webhook处理异常", 'ERROR', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // 返回错误响应（如果需要）
    http_response_code(500);
    echo 'Internal Server Error';
}

// 记录关闭日志
$bot->log("Webhook服务关闭", 'INFO');
?>