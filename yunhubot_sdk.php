<?php
/**
 * 云湖机器人SDK - 函数式接口版本
 * 版本: 3.0
 */

// 全局配置
define('YHSDK_API_BASE', 'https://chat-go.jwzhd.com/open-apis/v1/');
define('YHSDK_DEFAULT_LOG_PATH', 'yunhu_bot.log');

// 核心SDK类
class YunhuBotSDK {
    private $bot_token;
    private $log_path;
    private $debug;
    private $post_data;
    private $event_type;
    private $back = [];
    
    /**
     * 初始化SDK实例
     */
    public function __construct($token, $config = []) {
        $this->bot_token = $token;
        $this->log_path = $config['log_path'] ?? YHSDK_DEFAULT_LOG_PATH;
        $this->debug = $config['debug'] ?? false;
        
        $this->initPostData();
    }
    
    /**
     * 解析POST请求数据
     */
    public function initPostData() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return false;
        }
        
        $raw_data = file_get_contents('php://input');
        
        if (empty($raw_data)) {
            $this->writeLog("POST数据为空", 'WARNING');
            return false;
        }
        
        $this->post_data = json_decode($raw_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->writeLog("JSON解析失败: " . json_last_error_msg(), 'ERROR');
            $this->post_data = null;
            return false;
        }

        $this->event_type = $this->post_data['header']['eventType'] ?? '';
        $this->initBackObject();
        
        if ($this->debug) {
            $this->writeLog("事件类型: {$this->event_type}");
        }
        
        return true;
    }
    
    /**
     * 初始化回复目标对象
     */
    private function initBackObject() {
        if (empty($this->post_data)) return;
        
        // 按钮事件
        if ($this->event_type == 'button.report.inline') {
            $this->back['id'] = $this->post_data['recvId'] ?? '';
            $this->back['type'] = $this->post_data['recvType'] ?? '';
            return;
        }
        
        // 消息事件
        if (isset($this->post_data['event']['chat'])) {
            $chat_type = $this->post_data['event']['chat']['chatType'] ?? '';
            if ($chat_type == 'bot') {
                $this->back['id'] = $this->post_data['event']['sender']['senderId'] ?? '';
                $this->back['type'] = 'user';
            } else {
                $this->back['id'] = $this->post_data['event']['chat']['chatId'] ?? '';
                $this->back['type'] = $chat_type;
            }
        }
    }
    
    /**
     * 发送消息
     */
    public function send($object, $type, $content, $batch = false, $buttons = null, $parentId = null) {
        if (is_string($object)) {
            $object = [
                'id' => $object,
                'type' => 'user'
            ];
        }
        
        if ($batch) {
            $data = [
                'recvIds' => $object['ids'],
                'recvType' => $object['type'],
                'contentType' => $type,
                'content' => $this->makeContent($type, $content, $buttons)
            ];
            $endpoint = 'bot/batch_send';
        } else {
            $data = [
                'recvId' => $object['id'],
                'recvType' => $object['type'],
                'contentType' => $type
            ];
            
            if ($parentId) {
                $data['parentId'] = $parentId;
            }
            
            $data['content'] = $this->makeContent($type, $content, $buttons);
            $endpoint = 'bot/send';
        }
        
        return $this->sendRequest($data, $endpoint);
    }
    
    /**
     * 流式发送消息
     */
    public function sendStream($object, $type, $content) {
        if (!in_array($type, ['text', 'markdown'])) {
            return ['code' => 1002, 'msg' => '流式消息仅支持text和markdown类型'];
        }
        
        $url = YHSDK_API_BASE . 'bot/send-stream?' . http_build_query([
            'token' => $this->bot_token,
            'recvId' => $object['id'],
            'recvType' => $object['type'],
            'contentType' => $type
        ]);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Transfer-Encoding: chunked',
                'Content-Type: text/plain'
            ]
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['code' => 9999, 'msg' => "CURL错误: {$error}", 'http_code' => $http_code];
        }
        curl_close($ch);
        
        $result = json_decode($response, true);
        return $result ?: ['code' => 9998, 'msg' => '响应解析失败', 'raw_response' => $response];
    }
    
    /**
     * 编辑消息
     */
    public function edit($msg_id, $object, $type, $content, $buttons = null) {
        $data = [
            'msgId' => $msg_id,
            'recvId' => $object['id'],
            'recvType' => $object['type'],
            'contentType' => $type,
            'content' => $this->makeContent($type, $content, $buttons)
        ];
        
        return $this->sendRequest($data, 'bot/edit');
    }
    
    /**
     * 撤回消息
     */
    public function recall($msg_id, $object) {
        $data = [
            'msgId' => $msg_id,
            'chatId' => $object['id'],
            'chatType' => $object['type']
        ];
        
        return $this->sendRequest($data, 'bot/recall');
    }
    
    /**
     * 获取消息列表
     */
    public function getMessages($chat, $messageId = null, $before = 0, $after = 0) {
        $params = [
            'chat-id' => $chat['id'],
            'chat-type' => $chat['type']
        ];
        
        if ($messageId) $params['message-id'] = $messageId;
        if ($before > 0) $params['before'] = $before;
        if ($after > 0) $params['after'] = $after;
        
        $url = YHSDK_API_BASE . 'bot/messages?' . http_build_query($params);
        return $this->sendRequest([], 'bot/messages', 'GET', $url);
    }
    
    /**
     * 上传文件
     */
    public function uploadFile($file_path, $endpoint, $field_name) {
        if (!file_exists($file_path)) {
            return ['code' => 1002, 'msg' => '文件不存在'];
        }
        
        $url = YHSDK_API_BASE . $endpoint . '?token=' . $this->bot_token;
        
        $ch = curl_init();
        $file = new CURLFile($file_path);
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [$field_name => $file],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['code' => 9999, 'msg' => "CURL错误: {$error}"];
        }
        curl_close($ch);
        
        $this->writeLog("上传文件: {$field_name}");
        return json_decode($response, true);
    }
    
    /**
     * 上传图片
     */
    public function uploadImage($file_path) {
        return $this->uploadFile($file_path, 'image/upload', 'image');
    }
    
    /**
     * 上传视频
     */
    public function uploadVideo($file_path) {
        return $this->uploadFile($file_path, 'video/upload', 'video');
    }
    
    /**
     * 上传普通文件
     */
    public function uploadGeneralFile($file_path) {
        return $this->uploadFile($file_path, 'file/upload', 'file');
    }
    
    /**
     * 设置看板
     */
    public function setBoard($type, $content, $is_all = true, $object = null, $memberId = null, $expireTime = 0) {
        $data = [
            'contentType' => $type,
            'content' => $content
        ];
        
        if ($expireTime > 0) {
            $data['expireTime'] = $expireTime;
        }
        
        if ($is_all) {
            $endpoint = 'bot/board-all';
        } else {
            $data['chatId'] = $object['id'];
            $data['chatType'] = $object['type'];
            
            if ($object['type'] == 'group' && $memberId) {
                $data['memberId'] = $memberId;
            }
            
            $endpoint = 'bot/board';
        }
        
        return $this->sendRequest($data, $endpoint);
    }
    
    /**
     * 取消看板
     */
    public function unsetBoard($is_all = true, $object = null, $memberId = null) {
        if ($is_all) {
            return $this->sendRequest([], 'bot/board-all-dismiss');
        }
        
        $data = [
            'chatId' => $object['id'],
            'chatType' => $object['type']
        ];
        
        if ($object['type'] == 'group' && $memberId) {
            $data['memberId'] = $memberId;
        }
        
        return $this->sendRequest($data, 'bot/board-dismiss');
    }
    
    /**
     * 构造消息内容
     */
    private function makeContent($type, $content, $buttons = null) {
        $content_data = [];

        switch ($type) {
            case 'text':
            case 'markdown':
            case 'html':
                $content_data['text'] = strval($content);
                break;
                
            case 'image':
                if (is_string($content)) {
                    $content_data['imageKey'] = $content;
                } elseif (is_array($content) && isset($content['imageKey'])) {
                    $content_data['imageKey'] = $content['imageKey'];
                } elseif (filter_var($content, FILTER_VALIDATE_URL)) {
                    $temp_file = tempnam(sys_get_temp_dir(), 'yh_image_');
                    file_put_contents($temp_file, file_get_contents($content));
                    $upload_result = $this->uploadImage($temp_file);
                    unlink($temp_file);
                    
                    if ($upload_result['code'] == 1) {
                        $content_data['imageKey'] = $upload_result['data']['imageKey'];
                    } else {
                        throw new Exception("图片上传失败: " . ($upload_result['msg'] ?? '未知错误'));
                    }
                }
                break;
                
            case 'file':
                if (is_array($content) && isset($content['fileName']) && isset($content['fileUrl'])) {
                    $content_data['fileName'] = $content['name'];
                    $content_data['fileUrl'] = $content['url'];
                } elseif (is_string($content) && file_exists($content)) {
                    $upload_result = $this->uploadGeneralFile($content);
                    if ($upload_result['code'] == 1) {
                        $content_data['fileKey'] = $upload_result['data']['fileKey'];
                    } else {
                        throw new Exception("文件上传失败: " . ($upload_result['msg'] ?? '未知错误'));
                    }
                } elseif (is_string($content)) {
                    $content_data['fileKey'] = $content;
                }
                break;
                
            case 'video':
                if (is_string($content)) {
                    $content_data['videoKey'] = $content;
                }
                break;
        }

        if (!empty($buttons)) {
            $content_data['buttons'] = $buttons;
        }
        
        return $content_data;
    }
    
    /**
     * 发送API请求
     */
    private function sendRequest($data, $endpoint, $method = 'POST', $custom_url = null) {
        $url = $custom_url ?: YHSDK_API_BASE . $endpoint . '?token=' . $this->bot_token;
        
        $ch = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ];
        
        if ($method == 'POST') {
            $options[CURLOPT_POST] = true;
            if (!empty($data)) {
                $json_data = json_encode($data, JSON_UNESCAPED_UNICODE);
                $options[CURLOPT_POSTFIELDS] = $json_data;
                $options[CURLOPT_HTTPHEADER] = ['Content-Type: application/json; charset=utf-8'];
            }
        }
        
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['code' => 9999, 'msg' => "CURL错误: {$error}", 'http_code' => $http_code];
        }
        curl_close($ch);
        
        $result = json_decode($response, true);
        if (!$result) {
            $result = ['code' => 9998, 'msg' => '响应解析失败', 'raw_response' => $response];
        }
        
        if ($result['code'] != 1) {
            $this->writeLog("API请求失败: {$endpoint}, 错误码: {$result['code']}, 错误信息: {$result['msg']}", 'ERROR');
        }
        
        return $result;
    }
    
    /**
     * 写入日志
     */
    public function writeLog($action, $level = 'INFO') {
        $event_map = [
            "message.receive.normal" => "普通消息",
            "message.receive.instruction" => "指令消息",
            "bot.followed" => "关注机器人",
            "bot.unfollowed" => "取消关注",
            "group.join" => "加入群",
            "group.leave" => "退出群",
            "button.report.inline" => "按钮事件",
            "bot.shortcut.menu" => "快捷菜单"
        ];
        
        $event_name = $event_map[$this->event_type] ?? $this->event_type;
        $time = date('Y-m-d H:i:s');
        $target_id = $this->back['id'] ?? 'unknown';
        $sender_id = $this->getSenderId();
        
        $log_content = "[{$time}] [{$level}] [{$event_name}] [Sender: {$sender_id}] [Target: {$target_id}] {$action}\n";
        
        file_put_contents($this->log_path, $log_content, FILE_APPEND | LOCK_EX);
        
        if ($this->debug) {
            error_log($log_content);
        }
    }
    
    // 事件数据获取方法
    
    /**
     * 获取发送者ID
     */
    public function getSenderId() {
        if ($this->event_type == 'button.report.inline') {
            return $this->post_data['userId'] ?? '';
        }
        return $this->post_data['event']['sender']['senderId'] ?? '';
    }
    
    /**
     * 获取消息内容（支持指令消息）
     */
    public function getMessageContent() {
        if (!in_array($this->event_type, ['message.receive.normal', 'message.receive.instruction'])) {
            return '';
        }
        
        $content = $this->post_data['event']['message']['content'] ?? [];
        $contentType = $this->post_data['event']['message']['contentType'] ?? '';
        
        if (in_array($contentType, ['text', 'markdown', 'html'])) {
            return $content['text'] ?? '';
        }
        
        return $content;
    }
    
    /**
     * 获取消息ID
     */
    public function getMessageId() {
        if (in_array($this->event_type, ['message.receive.normal', 'message.receive.instruction'])) {
            return $this->post_data['event']['message']['msgId'] ?? '';
        } elseif ($this->event_type == 'button.report.inline') {
            return $this->post_data['msgId'] ?? '';
        }
        return '';
    }
    
    /**
     * 获取指令信息
     */
    public function getCommandInfo() {
        if ($this->event_type !== 'message.receive.instruction') {
            return null;
        }
        
        return [
            'commandId' => $this->post_data['event']['message']['commandId'] ?? 0,
            'commandName' => $this->post_data['event']['message']['commandName'] ?? ''
        ];
    }
    
    /**
     * 获取按钮事件值
     */
    public function getButtonValue() {
        if ($this->event_type !== 'button.report.inline') {
            return '';
        }
        return $this->post_data['value'] ?? '';
    }
    
    /**
     * 获取事件类型
     */
    public function getEventType() {
        return $this->event_type;
    }
    
    /**
     * 获取回复目标对象
     */
    public function getBackObject() {
        return $this->back;
    }
    
    /**
     * 获取原始POST数据
     */
    public function getPostData() {
        return $this->post_data;
    }
    
    /**
     * 获取机器人token
     */
    public function getBotToken() {
        return $this->bot_token;
    }
}

// 多机器人管理器
class YunhuBotManager {
    private static $instances = [];
    private static $current_bot = null;
    
    /**
     * 创建或获取机器人实例
     */
    public static function getBot($token, $config = []) {
        if (!isset(self::$instances[$token])) {
            self::$instances[$token] = new YunhuBotSDK($token, $config);
        }
        
        self::$current_bot = self::$instances[$token];
        
        return self::$instances[$token];
    }
    
    /**
     * 获取所有机器人实例
     */
    public static function getAllBots() {
        return self::$instances;
    }
    
    /**
     * 设置当前活跃机器人
     */
    public static function setCurrentBot($token) {
        if (isset(self::$instances[$token])) {
            self::$current_bot = self::$instances[$token];
            return true;
        }
        return false;
    }
    
    /**
     * 获取当前活跃机器人
     */
    public static function getCurrentBot() {
        return self::$current_bot;
    }
    
    /**
     * 根据请求自动识别机器人（需配置token识别逻辑）
     */
    public static function autoDetectBot($token_key = 'HTTP_X_BOT_TOKEN', $config = []) {
        $token = $_SERVER[$token_key] ?? '';
        
        if (empty($token)) {
            $raw_data = file_get_contents('php://input');
            $post_data = json_decode($raw_data, true);
            
            // 这里可以根据实际业务逻辑识别token
            // 例如：从消息内容、header或其他字段提取
        }
        
        if (!empty($token)) {
            return self::getBot($token, $config);
        }
        
        return null;
    }
}

// 全局函数接口

/**
 * 初始化SDK（单机器人，向后兼容）
 */
function yhsdk_init($token = '', $config = []) {
    return YunhuBotManager::getBot($token, $config);
}

/**
 * 发送消息（使用当前活跃机器人）
 */
function send($object, $type, $content, $batch = false, $buttons = null, $parentId = null) {
    $bot = YunhuBotManager::getCurrentBot();
    if (!$bot) {
        return ['code' => 1003, 'msg' => '未设置活跃机器人'];
    }
    
    return $bot->send($object, $type, $content, $batch, $buttons, $parentId);
}

/**
 * 流式发送消息
 */
function send_stream($object, $type, $content) {
    $bot = YunhuBotManager::getCurrentBot();
    if (!$bot) {
        return ['code' => 1003, 'msg' => '未设置活跃机器人'];
    }
    
    return $bot->sendStream($object, $type, $content);
}

/**
 * 编辑消息
 */
function edit($msg_id, $object, $type, $content, $buttons = null) {
    $bot = YunhuBotManager::getCurrentBot();
    if (!$bot) {
        return ['code' => 1003, 'msg' => '未设置活跃机器人'];
    }
    
    return $bot->edit($msg_id, $object, $type, $content, $buttons);
}

/**
 * 撤回消息
 */
function recall($msg_id, $object) {
    $bot = YunhuBotManager::getCurrentBot();
    if (!$bot) {
        return ['code' => 1003, 'msg' => '未设置活跃机器人'];
    }
    
    return $bot->recall($msg_id, $object);
}

/**
 * 设置看板
 */
function set_board($type, $content, $is_all = true, $object = null, $memberId = null, $expireTime = 0) {
    $bot = YunhuBotManager::getCurrentBot();
    if (!$bot) {
        return ['code' => 1003, 'msg' => '未设置活跃机器人'];
    }
    
    return $bot->setBoard($type, $content, $is_all, $object, $memberId, $expireTime);
}

/**
 * 取消看板
 */
function unset_board($is_all = true, $object = null, $memberId = null) {
    $bot = YunhuBotManager::getCurrentBot();
    if (!$bot) {
        return ['code' => 1003, 'msg' => '未设置活跃机器人'];
    }
    
    return $bot->unsetBoard($is_all, $object, $memberId);
}

/**
 * 上传文件
 */
function upload_file($file_path, $file_type = 'image') {
    $bot = YunhuBotManager::getCurrentBot();
    if (!$bot) {
        return ['code' => 1003, 'msg' => '未设置活跃机器人'];
    }
    
    switch ($file_type) {
        case 'image':
            return $bot->uploadImage($file_path);
        case 'video':
            return $bot->uploadVideo($file_path);
        case 'file':
            return $bot->uploadGeneralFile($file_path);
        default:
            return ['code' => 1002, 'msg' => '不支持的文件类型'];
    }
}

/**
 * 获取消息列表
 */
function get_messages($chat, $messageId = null, $before = 0, $after = 0) {
    $bot = YunhuBotManager::getCurrentBot();
    if (!$bot) {
        return ['code' => 1003, 'msg' => '未设置活跃机器人'];
    }
    
    return $bot->getMessages($chat, $messageId, $before, $after);
}

/**
 * 创建按钮
 */
function create_button($text, $actionType, $url = '', $value = '') {
    $button = [
        'text' => $text,
        'actionType' => $actionType
    ];
    
    if ($actionType == 1 && $url) {
        $button['url'] = $url;
    } elseif (($actionType == 2 || $actionType == 3) && $value) {
        $button['value'] = $value;
    }
    
    return $button;
}

/**
 * 获取发送者ID（当前活跃机器人）
 */
function get_sender_id() {
    $bot = YunhuBotManager::getCurrentBot();
    return $bot ? $bot->getSenderId() : '';
}

/**
 * 获取消息ID（当前活跃机器人）
 */
function get_message_id() {
    $bot = YunhuBotManager::getCurrentBot();
    return $bot ? $bot->getMessageId() : '';
}

/**
 * 获取消息内容（当前活跃机器人）
 */
function get_message_content() {
    $bot = YunhuBotManager::getCurrentBot();
    return $bot ? $bot->getMessageContent() : '';
}

/**
 * 获取指令信息（当前活跃机器人）
 */
function get_command_info() {
    $bot = YunhuBotManager::getCurrentBot();
    return $bot ? $bot->getCommandInfo() : null;
}

/**
 * 获取按钮事件值（当前活跃机器人）
 */
function get_button_value() {
    $bot = YunhuBotManager::getCurrentBot();
    return $bot ? $bot->getButtonValue() : '';
}

/**
 * 获取事件类型（当前活跃机器人）
 */
function get_event_type() {
    $bot = YunhuBotManager::getCurrentBot();
    return $bot ? $bot->getEventType() : '';
}

/**
 * 获取回复目标对象（当前活跃机器人）
 */
function get_back_object() {
    $bot = YunhuBotManager::getCurrentBot();
    return $bot ? $bot->getBackObject() : [];
}

/**
 * 写入日志
 */
function yhsdk_write_log($action, $level = 'INFO') {
    $bot = YunhuBotManager::getCurrentBot();
    if ($bot) {
        $bot->writeLog($action, $level);
    } else {
        $log_path = YHSDK_DEFAULT_LOG_PATH;
        $time = date('Y-m-d H:i:s');
        $log_content = "[{$time}] [{$level}] [SDK未初始化] {$action}\n";
        file_put_contents($log_path, $log_content, FILE_APPEND | LOCK_EX);
    }
}
