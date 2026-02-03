<?php
/**
 * 云湖机器人SDK - 优化版（面向对象设计）
 * 版本: 4.0
 * 不使用namespace，纯面向对象设计
 */

// 异常类定义
class YunhuBotException extends Exception {}

class YunhuBotConfigurationException extends YunhuBotException {}

class YunhuBotNetworkException extends YunhuBotException {}

class YunhuBotValidationException extends YunhuBotException {}

// 配置类
class YunhuBotConfig {
    private $config = [];
    
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'log_path' => 'yunhu_bot.log',
            'debug' => false,
            'timeout' => 10,
            'retry_times' => 3,
            'retry_delay' => 1000000, // 微秒
            'api_base' => 'https://chat-go.jwzhd.com/open-apis/v1/',
            'web_base' => 'https://chat-web-go.jwzhd.com/v1/'
        ], $config);
    }
    
    public function get($key, $default = null) {
        return $this->config[$key] ?? $default;
    }
    
    public function set($key, $value) {
        $this->config[$key] = $value;
    }
    
    public function all() {
        return $this->config;
    }
}

// 日志记录器
class YunhuBotLogger {
    private $logPath;
    private $debug;
    
    public function __construct($logPath, $debug = false) {
        $this->logPath = $logPath;
        $this->debug = $debug;
    }
    
    public function log($message, $level = 'INFO', $context = []) {
        $time = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        $logContent = "[{$time}] [{$level}] {$message}{$contextStr}\n";
        
        file_put_contents($this->logPath, $logContent, FILE_APPEND | LOCK_EX);
        
        if ($this->debug) {
            error_log($logContent);
        }
    }
    
    public function info($message, $context = []) {
        $this->log($message, 'INFO', $context);
    }
    
    public function warning($message, $context = []) {
        $this->log($message, 'WARNING', $context);
    }
    
    public function error($message, $context = []) {
        $this->log($message, 'ERROR', $context);
    }
}

// HTTP客户端
class YunhuBotHttpClient {
    private $timeout;
    private $retryTimes;
    private $retryDelay;
    private $logger;
    
    public function __construct($timeout = 10, $retryTimes = 3, $retryDelay = 1000000, $logger = null) {
        $this->timeout = $timeout;
        $this->retryTimes = $retryTimes;
        $this->retryDelay = $retryDelay;
        $this->logger = $logger;
    }
    
    public function request($url, $method = 'GET', $data = null, $headers = []) {
        $attempt = 0;
        
        while ($attempt < $this->retryTimes) {
            $attempt++;
            
            try {
                $result = $this->performRequest($url, $method, $data, $headers);
                
                if ($this->logger) {
                    $this->logger->info("HTTP请求成功", [
                        'url' => $url,
                        'method' => $method,
                        'attempt' => $attempt
                    ]);
                }
                
                return $result;
            } catch (Exception $e) {
                if ($this->logger) {
                    $this->logger->error("HTTP请求失败", [
                        'url' => $url,
                        'method' => $method,
                        'attempt' => $attempt,
                        'error' => $e->getMessage()
                    ]);
                }
                
                if ($attempt >= $this->retryTimes) {
                    throw new YunhuBotNetworkException("HTTP请求失败: " . $e->getMessage(), 0, $e);
                }
                
                usleep($this->retryDelay);
            }
        }
    }
    
    private function performRequest($url, $method, $data, $headers) {
        $ch = curl_init();
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ];
        
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            if (!empty($data)) {
                if (is_array($data)) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($data, JSON_UNESCAPED_UNICODE);
                    $options[CURLOPT_HTTPHEADER] = array_merge($headers, ['Content-Type: application/json; charset=utf-8']);
                } else {
                    $options[CURLOPT_POSTFIELDS] = $data;
                    $options[CURLOPT_HTTPHEADER] = array_merge($headers, ['Content-Type: text/plain']);
                }
            }
        } else {
            $options[CURLOPT_HTTPHEADER] = $headers;
        }
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new YunhuBotNetworkException("CURL错误: {$error}");
        }
        
        $result = json_decode($response, true);
        if (!$result) {
            throw new YunhuBotNetworkException("响应解析失败: {$response}");
        }
        
        return [
            'data' => $result,
            'http_code' => $httpCode,
            'raw_response' => $response
        ];
    }
}

// 事件数据模型
class YunhuBotEvent {
    private $eventType;
    private $postData;
    private $senderId;
    private $messageId;
    private $messageContent;
    private $commandInfo;
    private $buttonValue;
    private $backObject;
    
    public function __construct($postData) {
        $this->postData = $postData;
        $this->eventType = $postData['header']['eventType'] ?? '';
        $this->parseEventData();
    }
    
    private function parseEventData() {
        if (empty($this->postData)) return;
        
        // 解析发送者ID
        if ($this->eventType == 'button.report.inline') {
            $this->senderId = $this->postData['userId'] ?? '';
        } else {
            $this->senderId = $this->postData['event']['sender']['senderId'] ?? '';
        }
        
        // 解析消息ID
        if (in_array($this->eventType, ['message.receive.normal', 'message.receive.instruction'])) {
            $this->messageId = $this->postData['event']['message']['msgId'] ?? '';
        } elseif ($this->eventType == 'button.report.inline') {
            $this->messageId = $this->postData['msgId'] ?? '';
        }
        
        // 解析消息内容
        if (in_array($this->eventType, ['message.receive.normal', 'message.receive.instruction'])) {
            $content = $this->postData['event']['message']['content'] ?? [];
            $contentType = $this->postData['event']['message']['contentType'] ?? '';
            
            if (in_array($contentType, ['text', 'markdown', 'html'])) {
                $this->messageContent = $content['text'] ?? '';
            } else {
                $this->messageContent = $content;
            }
        }
        
        // 解析指令信息
        if ($this->eventType === 'message.receive.instruction') {
            $this->commandInfo = [
                'commandId' => $this->postData['event']['message']['commandId'] ?? 0,
                'commandName' => $this->postData['event']['message']['commandName'] ?? ''
            ];
        }
        
        // 解析按钮值
        if ($this->eventType === 'button.report.inline') {
            $this->buttonValue = $this->postData['value'] ?? '';
        }
        
        // 解析回复目标
        $this->parseBackObject();
    }
    
    private function parseBackObject() {
        if ($this->eventType == 'button.report.inline') {
            $this->backObject = [
                'id' => $this->postData['recvId'] ?? '',
                'type' => $this->postData['recvType'] ?? ''
            ];
            return;
        }
        
        if (isset($this->postData['event']['chat'])) {
            $chatType = $this->postData['event']['chat']['chatType'] ?? '';
            if ($chatType == 'bot') {
                $this->backObject = [
                    'id' => $this->postData['event']['sender']['senderId'] ?? '',
                    'type' => 'user'
                ];
            } else {
                $this->backObject = [
                    'id' => $this->postData['event']['chat']['chatId'] ?? '',
                    'type' => $chatType
                ];
            }
        }
    }
    
    // Getter方法
    public function getEventType() { return $this->eventType; }
    public function getSenderId() { return $this->senderId; }
    public function getMessageId() { return $this->messageId; }
    public function getMessageContent() { return $this->messageContent; }
    public function getCommandInfo() { return $this->commandInfo; }
    public function getButtonValue() { return $this->buttonValue; }
    public function getBackObject() { return $this->backObject; }
    public function getPostData() { return $this->postData; }
    
    public function isMessageEvent() {
        return in_array($this->eventType, ['message.receive.normal', 'message.receive.instruction']);
    }
    
    public function isButtonEvent() {
        return $this->eventType === 'button.report.inline';
    }
    
    public function isInstructionEvent() {
        return $this->eventType === 'message.receive.instruction';
    }
}

// 消息构建器
class YunhuBotMessageBuilder {
    private $contentType;
    private $content = [];
    private $buttons = [];
    
    public static function create($contentType) {
        return new self($contentType);
    }
    
    public function __construct($contentType) {
        $this->contentType = $contentType;
    }
    
    public function setText($text) {
        $this->content['text'] = strval($text);
        return $this;
    }
    
    public function setImage($imageKey, $imageUrl = null) {
        $this->content['imageKey'] = $imageKey;
        if ($imageUrl) {
            $this->content['imageUrl'] = $imageUrl;
        }
        return $this;
    }
    
    public function setVideo($videoKey) {
        $this->content['videoKey'] = $videoKey;
        return $this;
    }
    
    public function setFile($fileKey, $fileName = null, $fileUrl = null) {
        $this->content['fileKey'] = $fileKey;
        if ($fileName) $this->content['fileName'] = $fileName;
        if ($fileUrl) $this->content['fileUrl'] = $fileUrl;
        return $this;
    }
    
    public function addButton($text, $actionType, $url = '', $value = '') {
        $button = [
            'text' => $text,
            'actionType' => $actionType
        ];
        
        if ($actionType == 1 && $url) {
            $button['url'] = $url;
        } elseif (($actionType == 2 || $actionType == 3) && $value) {
            $button['value'] = $value;
        }
        
        $this->buttons[] = $button;
        return $this;
    }
    
    public function build() {
        $content = $this->content;
        if (!empty($this->buttons)) {
            $content['buttons'] = $this->buttons;
        }
        return $content;
    }
}

// 文件上传器
class YunhuBotFileUploader {
    private $httpClient;
    private $token;
    private $apiBase;
    private $logger;
    
    public function __construct($httpClient, $token, $apiBase, $logger = null) {
        $this->httpClient = $httpClient;
        $this->token = $token;
        $this->apiBase = $apiBase;
        $this->logger = $logger;
    }
    
    public function upload($filePath, $endpoint, $fieldName = 'file') {
        if (!file_exists($filePath)) {
            throw new YunhuBotValidationException("文件不存在: {$filePath}");
        }
        
        $url = $this->apiBase . $endpoint . '?token=' . $this->token;
        
        $ch = curl_init();
        $file = new CURLFile($filePath);
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [$fieldName => $file],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new YunhuBotNetworkException("文件上传CURL错误: {$error}");
        }
        curl_close($ch);
        
        $result = json_decode($response, true);
        if (!$result) {
            throw new YunhuBotNetworkException("文件上传响应解析失败: {$response}");
        }
        
        if ($this->logger) {
            $this->logger->info("文件上传成功", [
                'file' => $filePath,
                'endpoint' => $endpoint,
                'field' => $fieldName
            ]);
        }
        
        return $result;
    }
    
    public function uploadImage($filePath) {
        return $this->upload($filePath, 'image/upload', 'image');
    }
    
    public function uploadVideo($filePath) {
        return $this->upload($filePath, 'video/upload', 'video');
    }
    
    public function uploadFile($filePath) {
        return $this->upload($filePath, 'file/upload', 'file');
    }
}

// 核心SDK类 - 完全重构
class YunhuBotSDK {
    private $token;
    private $config;
    private $logger;
    private $httpClient;
    private $event;
    private $fileUploader;
    
    public function __construct($token, YunhuBotConfig $config = null) {
        $this->token = $token;
        $this->config = $config ?: new YunhuBotConfig();
        $this->logger = new YunhuBotLogger(
            $this->config->get('log_path'),
            $this->config->get('debug')
        );
        $this->httpClient = new YunhuBotHttpClient(
            $this->config->get('timeout'),
            $this->config->get('retry_times'),
            $this->config->get('retry_delay'),
            $this->logger
        );
        $this->fileUploader = new YunhuBotFileUploader(
            $this->httpClient,
            $this->token,
            $this->config->get('api_base'),
            $this->logger
        );
        
        $this->initEvent();
    }
    
    private function initEvent() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $rawData = file_get_contents('php://input');
            if (!empty($rawData)) {
                $postData = json_decode($rawData, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->event = new YunhuBotEvent($postData);
                }
            }
        }
    }
    
    // 事件相关方法
    public function getEvent() {
        return $this->event;
    }
    
    public function isValidWebhook() {
        return $this->event !== null;
    }
    
    // 消息发送相关方法
    public function send($object, $contentType, $content, $buttons = null, $parentId = null) {
        if (is_string($object)) {
            $object = ['id' => $object, 'type' => 'user'];
        }
        
        if (is_array($content)) {
            $contentData = $content;
        } else {
            $builder = YunhuBotMessageBuilder::create($contentType);
            $contentData = $builder->setText($content)->build();
        }
        
        if ($buttons) {
            $contentData['buttons'] = $buttons;
        }
        
        $data = [
            'recvId' => $object['id'],
            'recvType' => $object['type'],
            'contentType' => $contentType,
            'content' => $contentData
        ];
        
        if ($parentId) {
            $data['parentId'] = $parentId;
        }
        
        return $this->sendRequest($data, 'bot/send');
    }
    
    public function sendBatch($object, $contentType, $content, $buttons = null) {
        if (is_string($object)) {
            $object = ['ids' => [$object], 'type' => 'user'];
        }
        
        if (is_array($content)) {
            $contentData = $content;
        } else {
            $builder = YunhuBotMessageBuilder::create($contentType);
            $contentData = $builder->setText($content)->build();
        }
        
        if ($buttons) {
            $contentData['buttons'] = $buttons;
        }
        
        $data = [
            'recvIds' => $object['ids'],
            'recvType' => $object['type'],
            'contentType' => $contentType,
            'content' => $contentData
        ];
        
        return $this->sendRequest($data, 'bot/batch_send');
    }
    
    public function sendStream($object, $contentType, $content) {
        if (!in_array($contentType, ['text', 'markdown'])) {
            throw new YunhuBotValidationException('流式消息仅支持text和markdown类型');
        }
        
        if (is_string($object)) {
            $object = ['id' => $object, 'type' => 'user'];
        }
        
        $url = $this->config->get('api_base') . 'bot/send-stream?' . http_build_query([
            'token' => $this->token,
            'recvId' => $object['id'],
            'recvType' => $object['type'],
            'contentType' => $contentType
        ]);
        
        $headers = [
            'Transfer-Encoding: chunked',
            'Content-Type: text/plain'
        ];
        
        return $this->httpClient->request($url, 'POST', $content, $headers);
    }
    
    public function edit($msgId, $object, $contentType, $content, $buttons = null) {
        if (is_string($object)) {
            $object = ['id' => $object, 'type' => 'user'];
        }
        
        if (is_array($content)) {
            $contentData = $content;
        } else {
            $builder = YunhuBotMessageBuilder::create($contentType);
            $contentData = $builder->setText($content)->build();
        }
        
        if ($buttons) {
            $contentData['buttons'] = $buttons;
        }
        
        $data = [
            'msgId' => $msgId,
            'recvId' => $object['id'],
            'recvType' => $object['type'],
            'contentType' => $contentType,
            'content' => $contentData
        ];
        
        return $this->sendRequest($data, 'bot/edit');
    }
    
    public function recall($msgId, $object) {
        if (is_string($object)) {
            $object = ['id' => $object, 'type' => 'user'];
        }
        
        $data = [
            'msgId' => $msgId,
            'chatId' => $object['id'],
            'chatType' => $object['type']
        ];
        
        return $this->sendRequest($data, 'bot/recall');
    }
    
    // 文件上传方法
    public function getFileUploader() {
        return $this->fileUploader;
    }
    
    public function uploadImage($filePath) {
        return $this->fileUploader->uploadImage($filePath);
    }
    
    public function uploadVideo($filePath) {
        return $this->fileUploader->uploadVideo($filePath);
    }
    
    public function uploadFile($filePath) {
        return $this->fileUploader->uploadFile($filePath);
    }
    
    // 看板方法
    public function setBoard($contentType, $content, $isAll = true, $object = null, $memberId = null, $expireTime = 0) {
        if (is_array($content)) {
            $contentData = $content;
        } else {
            $builder = YunhuBotMessageBuilder::create($contentType);
            $contentData = $builder->setText($content)->build();
        }
        
        $data = [
            'contentType' => $contentType,
            'content' => $contentData
        ];
        
        if ($expireTime > 0) {
            $data['expireTime'] = $expireTime;
        }
        
        if ($isAll) {
            $endpoint = 'bot/board-all';
        } else {
            if (!$object) {
                throw new YunhuBotValidationException('设置个人看板时必须指定对象');
            }
            
            if (is_string($object)) {
                $object = ['id' => $object, 'type' => 'user'];
            }
            
            $data['chatId'] = $object['id'];
            $data['chatType'] = $object['type'];
            
            if ($object['type'] == 'group' && $memberId) {
                $data['memberId'] = $memberId;
            }
            
            $endpoint = 'bot/board';
        }
        
        return $this->sendRequest($data, $endpoint);
    }
    
    public function unsetBoard($isAll = true, $object = null, $memberId = null) {
        if ($isAll) {
            return $this->sendRequest([], 'bot/board-all-dismiss');
        }
        
        if (!$object) {
            throw new YunhuBotValidationException('取消个人看板时必须指定对象');
        }
        
        if (is_string($object)) {
            $object = ['id' => $object, 'type' => 'user'];
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
    
    // 消息获取
    public function getMessages($chat, $messageId = null, $before = 0, $after = 0) {
        if (is_string($chat)) {
            $chat = ['id' => $chat, 'type' => 'user'];
        }
        
        $params = [
            'chat-id' => $chat['id'],
            'chat-type' => $chat['type']
        ];
        
        if ($messageId) $params['message-id'] = $messageId;
        if ($before > 0) $params['before'] = $before;
        if ($after > 0) $params['after'] = $after;
        
        $url = $this->config->get('api_base') . 'bot/messages?' . http_build_query($params);
        return $this->httpClient->request($url, 'GET');
    }
    
    // 内部方法
    private function sendRequest($data, $endpoint) {
        $url = $this->config->get('api_base') . $endpoint . '?token=' . $this->token;
        $result = $this->httpClient->request($url, 'POST', $data);
        
        if ($result['data']['code'] != 1) {
            $this->logger->error("API请求失败", [
                'endpoint' => $endpoint,
                'code' => $result['data']['code'],
                'msg' => $result['data']['msg']
            ]);
        }
        
        return $result['data'];
    }
    
    // 便捷方法
    public function sendText($object, $text, $buttons = null) {
        return $this->send($object, 'text', $text, $buttons);
    }
    
    public function sendMarkdown($object, $markdown, $buttons = null) {
        return $this->send($object, 'markdown', $markdown, $buttons);
    }
    
    public function sendHtml($object, $html, $buttons = null) {
        return $this->send($object, 'html', $html, $buttons);
    }
    
    public function sendImage($object, $imageKey, $imageUrl = null) {
        return $this->send($object, 'image', ['imageKey' => $imageKey, 'imageUrl' => $imageUrl]);
    }
    
    public function sendVideo($object, $videoKey) {
        return $this->send($object, 'video', ['videoKey' => $videoKey]);
    }
    
    public function sendFile($object, $fileKey, $fileName = null, $fileUrl = null) {
        return $this->send($object, 'file', ['fileKey' => $fileKey, 'fileName' => $fileName, 'fileUrl' => $fileUrl]);
    }
    
    public function createButton($text, $actionType, $url = '', $value = '') {
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
    
    // 日志方法
    public function log($message, $level = 'INFO', $context = []) {
        if ($this->logger) {
            $this->logger->log($message, $level, $context);
        }
    }
    
    public function getConfig() {
        return $this->config;
    }
    
    public function getLogger() {
        return $this->logger;
    }
}

// 机器人管理器
class YunhuBotManager {
    private static $instances = [];
    private static $currentBot = null;
    
    public static function createBot($token, YunhuBotConfig $config = null) {
        $bot = new YunhuBotSDK($token, $config);
        self::$instances[$token] = $bot;
        self::$currentBot = $bot;
        return $bot;
    }
    
    public static function getBot($token) {
        return self::$instances[$token] ?? null;
    }
    
    public static function setCurrentBot($token) {
        if (isset(self::$instances[$token])) {
            self::$currentBot = self::$instances[$token];
            return true;
        }
        return false;
    }
    
    public static function getCurrentBot() {
        return self::$currentBot;
    }
    
    public static function getAllBots() {
        return self::$instances;
    }
    
    public static function autoDetectBot($tokenKey = 'HTTP_X_BOT_TOKEN', YunhuBotConfig $config = null) {
        $token = $_SERVER[$tokenKey] ?? '';
        
        if (empty($token)) {
            $rawData = file_get_contents('php://input');
            $postData = json_decode($rawData, true);
            // 这里可以根据实际业务逻辑识别token
        }
        
        if (!empty($token)) {
            return self::createBot($token, $config);
        }
        
        return null;
    }
}

// Webhook处理器
class YunhuBotWebhook {
    private $bot;
    private $handlers = [];
    
    public function __construct(YunhuBotSDK $bot) {
        $this->bot = $bot;
    }
    
    public function onMessage($callback) {
        $this->handlers['message'] = $callback;
        return $this;
    }
    
    public function onInstruction($callback) {
        $this->handlers['instruction'] = $callback;
        return $this;
    }
    
    public function onButton($callback) {
        $this->handlers['button'] = $callback;
        return $this;
    }
    
    public function onEvent($eventType, $callback) {
        $this->handlers[$eventType] = $callback;
        return $this;
    }
    
    public function handle() {
        if (!$this->bot->isValidWebhook()) {
            $this->bot->log('无效的webhook请求', 'WARNING');
            return false;
        }
        
        $event = $this->bot->getEvent();
        $eventType = $event->getEventType();
        
        $this->bot->log('收到事件', 'INFO', ['event_type' => $eventType]);
        
        try {
            if (isset($this->handlers[$eventType])) {
                return call_user_func($this->handlers[$eventType], $event, $this->bot);
            }
            
            if ($event->isInstructionEvent() && isset($this->handlers['instruction'])) {
                return call_user_func($this->handlers['instruction'], $event, $this->bot);
            }
            
            if ($event->isButtonEvent() && isset($this->handlers['button'])) {
                return call_user_func($this->handlers['button'], $event, $this->bot);
            }
            
            if ($event->isMessageEvent() && isset($this->handlers['message'])) {
                return call_user_func($this->handlers['message'], $event, $this->bot);
            }
            
            $this->bot->log('未处理的事件类型', 'WARNING', ['event_type' => $eventType]);
            return false;
            
        } catch (Exception $e) {
            $this->bot->log('事件处理异常', 'ERROR', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    public function run() {
        return $this->handle();
    }
}