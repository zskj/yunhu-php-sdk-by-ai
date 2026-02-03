<?php
/**
 * 云湖机器人SDK - 统一接口版本
 * 版本: 5.0
 * 
 * 提供两种使用方式：
 * 1. 面向对象方式：直接使用 Bot 类
 * 2. 函数式方式：使用全局辅助函数
 * 
 * @example 面向对象方式
 * $bot = new Bot('your-token', 'your-bot-id');
 * $bot->send('user123', Bot::RECV_TYPE_USER, Bot::CONTENT_TEXT, 'Hello World');
 * 
 * @example 函数式方式
 * yh_init('your-token', 'your-bot-id');
 * yh_send('user123', 'text', 'Hello World');
 */

// 加载核心Bot类
require_once __DIR__ . '/Bot.php';

/**
 * ==================== 全局配置与事件解析 ====================
 */

// 全局存储当前机器人和事件数据
$_yh_globals = [
    'current_bot' => null,
    'event_data'  => null,
    'event_type'  => ''
];

/**
 * 初始化全局机器人实例（函数式接口）
 * 
 * @param string $token 机器人Token
 * @param string $botId 机器人ID
 * @param array $config 配置选项
 * @return Bot 机器人实例
 */
function yh_init(string $token, string $botId = '', array $config = []): Bot
{
    global $_yh_globals;
    $_yh_globals['current_bot'] = BotManager::getBot($token, $botId, $config);
    return $_yh_globals['current_bot'];
}

/**
 * 解析Webhook事件数据
 * 
 * @return array|null 事件数据或null
 */
function yh_parse_event(): ?array
{
    global $_yh_globals;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return null;
    }
    
    $raw_data = file_get_contents('php://input');
    if (empty($raw_data)) {
        return null;
    }
    
    $_yh_globals['event_data'] = json_decode($raw_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $_yh_globals['event_data'] = null;
        return null;
    }
    
    $_yh_globals['event_type'] = $_yh_globals['event_data']['header']['eventType'] ?? '';
    
    // 自动初始化Bot实例（如果header中有token）
    if (!$_yh_globals['current_bot'] && isset($_yh_globals['event_data']['token'])) {
        yh_init($_yh_globals['event_data']['token']);
    }
    
    return $_yh_globals['event_data'];
}

/**
 * 获取当前事件类型
 * 
 * @return string 事件类型
 */
function yh_event_type(): string
{
    global $_yh_globals;
    return $_yh_globals['event_type'] ?? '';
}

/**
 * 获取事件数据
 * 
 * @return array|null 事件数据
 */
function yh_event_data(): ?array
{
    global $_yh_globals;
    return $_yh_globals['event_data'];
}

/**
 * 判断是否为指定事件类型
 * 
 * @param string $type 事件类型常量
 * @return bool
 */
function yh_is_event(string $type): bool
{
    return yh_event_type() === $type;
}

/**
 * 获取发送者ID
 * 
 * @return string 发送者ID
 */
function yh_sender_id(): string
{
    $event_data = yh_event_data();
    if (empty($event_data)) {
        return '';
    }
    
    if (yh_event_type() === Bot::EVENT_BUTTON_INLINE) {
        return $event_data['userId'] ?? '';
    }
    
    return $event_data['event']['sender']['senderId'] ?? '';
}

/**
 * 获取消息ID
 * 
 * @return string 消息ID
 */
function yh_message_id(): string
{
    $event_data = yh_event_data();
    if (empty($event_data)) {
        return '';
    }
    
    if (in_array(yh_event_type(), [Bot::EVENT_MESSAGE_NORMAL, Bot::EVENT_MESSAGE_INSTRUCTION])) {
        return $event_data['event']['message']['msgId'] ?? '';
    } elseif (yh_event_type() === Bot::EVENT_BUTTON_INLINE) {
        return $event_data['msgId'] ?? '';
    }
    
    return '';
}

/**
 * 获取消息内容
 * 
 * @return string|array 消息内容
 */
function yh_message_content()
{
    $event_data = yh_event_data();
    if (empty($event_data) || !in_array(yh_event_type(), [Bot::EVENT_MESSAGE_NORMAL, Bot::EVENT_MESSAGE_INSTRUCTION])) {
        return '';
    }
    
    $content = $event_data['event']['message']['content'] ?? [];
    $contentType = $event_data['event']['message']['contentType'] ?? '';
    
    if (in_array($contentType, ['text', 'markdown', 'html'])) {
        return $content['text'] ?? '';
    }
    
    return $content;
}

/**
 * 获取指令信息
 * 
 * @return array|null 指令信息
 */
function yh_command_info(): ?array
{
    if (yh_event_type() !== Bot::EVENT_MESSAGE_INSTRUCTION) {
        return null;
    }
    
    $event_data = yh_event_data();
    if (empty($event_data)) {
        return null;
    }
    
    return [
        'commandId' => $event_data['event']['message']['commandId'] ?? 0,
        'commandName' => $event_data['event']['message']['commandName'] ?? ''
    ];
}

/**
 * 获取按钮事件值
 * 
 * @return string 按钮值
 */
function yh_button_value(): string
{
    if (yh_event_type() !== Bot::EVENT_BUTTON_INLINE) {
        return '';
    }
    
    $event_data = yh_event_data();
    return $event_data['value'] ?? '';
}

/**
 * 获取回复目标对象
 * 
 * @return array 回复目标 ['id' => '', 'type' => '']
 */
function yh_back_object(): array
{
    $event_data = yh_event_data();
    if (empty($event_data)) {
        return ['id' => '', 'type' => 'user'];
    }
    
    // 按钮事件
    if (yh_event_type() === Bot::EVENT_BUTTON_INLINE) {
        return [
            'id' => $event_data['recvId'] ?? '',
            'type' => $event_data['recvType'] ?? 'user'
        ];
    }
    
    // 消息事件
    if (isset($event_data['event']['chat'])) {
        $chat_type = $event_data['event']['chat']['chatType'] ?? '';
        if ($chat_type === 'bot') {
            return [
                'id' => $event_data['event']['sender']['senderId'] ?? '',
                'type' => 'user'
            ];
        } else {
            return [
                'id' => $event_data['event']['chat']['chatId'] ?? '',
                'type' => $chat_type
            ];
        }
    }
    
    return ['id' => '', 'type' => 'user'];
}

/**
 * ==================== 消息发送函数 ====================
 */

/**
 * 发送消息（函数式接口）
 * 
 * @param string|array $recv 接收者ID或['id', 'type']数组
 * @param string $contentType 内容类型
 * @param mixed $content 消息内容
 * @param array|null $buttons 按钮数组
 * @param string|null $parentId 父消息ID
 * @return array API响应
 */
function yh_send($recv, string $contentType, $content, ?array $buttons = null, ?string $parentId = null): array
{
    global $_yh_globals;
    $bot = $_yh_globals['current_bot'] ?? BotManager::getCurrentBot();
    
    if (!$bot) {
        throw new \RuntimeException('机器人未初始化，请先调用 yh_init()');
    }
    
    if (is_array($recv)) {
        $recvId = $recv['id'] ?? '';
        $recvType = $recv['type'] ?? 'user';
    } else {
        $recvId = $recv;
        $recvType = 'user';
    }
    
    return $bot->send($recvId, $recvType, $contentType, $content, $buttons, $parentId);
}

/**
 * 批量发送消息
 * 
 * @param array $recvIds 接收者ID数组
 * @param string $recvType 接收者类型
 * @param string $contentType 内容类型
 * @param mixed $content 消息内容
 * @param array|null $buttons 按钮数组
 * @param string|null $parentId 父消息ID
 * @return array API响应
 */
function yh_batch_send(array $recvIds, string $recvType, string $contentType, $content, ?array $buttons = null, ?string $parentId = null): array
{
    global $_yh_globals;
    $bot = $_yh_globals['current_bot'] ?? BotManager::getCurrentBot();
    
    if (!$bot) {
        throw new \RuntimeException('机器人未初始化，请先调用 yh_init()');
    }
    
    return $bot->batchSend($recvIds, $recvType, $contentType, $content, $buttons, $parentId);
}

/**
 * 发送文本消息（快捷函数）
 * 
 * @param string|array $recv 接收者
 * @param string $text 文本内容
 * @param array|null $buttons 按钮数组
 * @return array API响应
 */
function yh_send_text($recv, string $text, ?array $buttons = null): array
{
    return yh_send($recv, Bot::CONTENT_TEXT, $text, $buttons);
}

/**
 * 发送Markdown消息（快捷函数）
 * 
 * @param string|array $recv 接收者
 * @param string $markdown Markdown内容
 * @param array|null $buttons 按钮数组
 * @return array API响应
 */
function yh_send_markdown($recv, string $markdown, ?array $buttons = null): array
{
    return yh_send($recv, Bot::CONTENT_MARKDOWN, $markdown, $buttons);
}

/**
 * 发送HTML消息（快捷函数）
 * 
 * @param string|array $recv 接收者
 * @param string $html HTML内容
 * @param array|null $buttons 按钮数组
 * @return array API响应
 */
function yh_send_html($recv, string $html, ?array $buttons = null): array
{
    return yh_send($recv, Bot::CONTENT_HTML, $html, $buttons);
}

/**
 * 发送图片消息（快捷函数）
 * 
 * @param string|array $recv 接收者
 * @param string $imageKey 图片key或URL
 * @return array API响应
 */
function yh_send_image($recv, string $imageKey): array
{
    return yh_send($recv, Bot::CONTENT_IMAGE, $imageKey);
}

/**
 * 编辑消息
 * 
 * @param string $msgId 消息ID
 * @param string|array $recv 接收者
 * @param string $contentType 内容类型
 * @param mixed $content 消息内容
 * @param array|null $buttons 按钮数组
 * @return array API响应
 */
function yh_edit(string $msgId, $recv, string $contentType, $content, ?array $buttons = null): array
{
    global $_yh_globals;
    $bot = $_yh_globals['current_bot'] ?? BotManager::getCurrentBot();
    
    if (!$bot) {
        throw new \RuntimeException('机器人未初始化，请先调用 yh_init()');
    }
    
    if (is_array($recv)) {
        $recvId = $recv['id'] ?? '';
        $recvType = $recv['type'] ?? 'user';
    } else {
        $recvId = $recv;
        $recvType = 'user';
    }
    
    return $bot->edit($msgId, $recvId, $recvType, $contentType, $content, $buttons);
}

/**
 * 撤回消息
 * 
 * @param string $msgId 消息ID
 * @param string|array $chat 聊天对象
 * @return array API响应
 */
function yh_recall(string $msgId, $chat): array
{
    global $_yh_globals;
    $bot = $_yh_globals['current_bot'] ?? BotManager::getCurrentBot();
    
    if (!$bot) {
        throw new \RuntimeException('机器人未初始化，请先调用 yh_init()');
    }
    
    if (is_array($chat)) {
        $chatId = $chat['id'] ?? '';
        $chatType = $chat['type'] ?? 'user';
    } else {
        $chatId = $chat;
        $chatType = 'user';
    }
    
    return $bot->recall($msgId, $chatId, $chatType);
}

/**
 * 流式发送消息
 * 
 * @param string|array $recv 接收者
 * @param string $contentType 内容类型
 * @param \Generator $generator 数据生成器
 * @param int $delayMs 延迟毫秒数
 * @return array API响应
 */
function yh_send_stream($recv, string $contentType, \Generator $generator, int $delayMs = 1000): array
{
    global $_yh_globals;
    $bot = $_yh_globals['current_bot'] ?? BotManager::getCurrentBot();
    
    if (!$bot) {
        throw new \RuntimeException('机器人未初始化，请先调用 yh_init()');
    }
    
    if (is_array($recv)) {
        $recvId = $recv['id'] ?? '';
        $recvType = $recv['type'] ?? 'user';
    } else {
        $recvId = $recv;
        $recvType = 'user';
    }
    
    return $bot->sendStream($recvId, $recvType, $contentType, $generator, $delayMs);
}

/**
 * 流式发送文本数组
 * 
 * @param string|array $recv 接收者
 * @param array $messages 消息数组
 * @param int $delayMs 延迟毫秒数
 * @return array API响应
 */
function yh_send_stream_text($recv, array $messages, int $delayMs = 1000): array
{
    global $_yh_globals;
    $bot = $_yh_globals['current_bot'] ?? BotManager::getCurrentBot();
    
    if (!$bot) {
        throw new \RuntimeException('机器人未初始化，请先调用 yh_init()');
    }
    
    if (is_array($recv)) {
        $recvId = $recv['id'] ?? '';
        $recvType = $recv['type'] ?? 'user';
    } else {
        $recvId = $recv;
        $recvType = 'user';
    }
    
    return $bot->sendStreamText($recvId, $recvType, $messages, $delayMs);
}

/**
 * ==================== 文件上传函数 ====================
 */

/**
 * 上传图片
 * 
 * @param string $filePath 文件路径
 * @return array API响应
 */
function yh_upload_image(string $filePath): array
{
    global $_yh_globals;
    $bot = $_yh_globals['current_bot'] ?? BotManager::getCurrentBot();
    
    if (!$bot) {
        throw new \RuntimeException('机器人未初始化，请先调用 yh_init()');
    }
    
    return $bot->uploadImage($filePath);
}

/**
 * 上传视频
 * 
 * @param string $filePath 文件路径
 * @return array API响应
 */
function yh_upload_video(string $filePath): array
{
    global $_yh_globals;
    $bot = $_yh_globals['current_bot'] ?? BotManager::getCurrentBot();
    
    if (!$bot) {
        throw new \RuntimeException('机器人未初始化，请先调用 yh_init()');
    }
    
    return $bot->uploadVideo($filePath);
}

/**
 * 上传文件
 * 
 * @param string $filePath 文件路径
 * @return array API响应
 */
function yh_upload_file(string $filePath): array
{
    global $_yh_globals;
    $bot = $_yh_globals['current_bot'] ?? BotManager::getCurrentBot();
    
    if (!$bot) {
        throw new \RuntimeException('机器人未初始化，请先调用 yh_init()');
    }
    
    return $bot->uploadFile($filePath);
}

/**
 * ==================== 看板设置函数 ====================
 */

/**
 * 设置看板
 * 
 * @param string $chatId 聊天ID
 * @param string $chatType 聊天类型
 * @param string $contentType 内容类型
 * @param string $content 内容
 * @param string|null $memberId 成员ID（群聊时）
 * @param int|null $expireTime 过期时间戳
 * @return array API响应
 */
function yh_set_board(string $chatId, string $chatType, string $contentType, string $content, ?string $memberId = null, ?int $expireTime = null): array
{
    global $_yh_globals;
    $bot = $_yh_globals['current_bot'] ?? BotManager::getCurrentBot();
    
    if (!$bot) {
        throw new \RuntimeException('机器人未初始化，请先调用 yh_init()');
    }
    
    return $bot->setBoard($chatId, $chatType, $contentType, $content, $memberId, $expireTime);
}

/**
 * 设置全局看板
 * 
 * @param string $contentType 内容类型
 * @param string $content 内容
 * @param int|null $expireTime 过期时间戳
 * @return array API响应
 */
function yh_set_global_board(string $contentType, string $content, ?int $expireTime = null): array
{
    global $_yh_globals;
    $bot = $_yh_globals['current_bot'] ?? BotManager::getCurrentBot();
    
    if (!$bot) {
        throw new \RuntimeException('机器人未初始化，请先调用 yh_init()');
    }
    
    return $bot->setGlobalBoard($contentType, $content, $expireTime);
}

/**
 * 取消看板
 * 
 * @param string $chatId 聊天ID
 * @param string $chatType 聊天类型
 * @param string|null $memberId 成员ID（群聊时）
 * @return array API响应
 */
function yh_dismiss_board(string $chatId, string $chatType, ?string $memberId = null): array
{
    global $_yh_globals;
    $bot = $_yh_globals['current_bot'] ?? BotManager::getCurrentBot();
    
    if (!$bot) {
        throw new \RuntimeException('机器人未初始化，请先调用 yh_init()');
    }
    
    return $bot->dismissBoard($chatId, $chatType, $memberId);
}

/**
 * 取消全局看板
 * 
 * @return array API响应
 */
function yh_dismiss_global_board(): array
{
    global $_yh_globals;
    $bot = $_yh_globals['current_bot'] ?? BotManager::getCurrentBot();
    
    if (!$bot) {
        throw new \RuntimeException('机器人未初始化，请先调用 yh_init()');
    }
    
    return $bot->dismissGlobalBoard();
}

/**
 * ==================== 获取消息列表函数 ====================
 */

/**
 * 获取消息列表
 * 
 * @param string $chatId 聊天ID
 * @param string $chatType 聊天类型
 * @param string|null $messageId 消息ID
 * @param int|null $before 之前消息数量
 * @param int|null $after 之后消息数量
 * @return array API响应
 */
function yh_get_messages(string $chatId, string $chatType, ?string $messageId = null, ?int $before = null, ?int $after = null): array
{
    global $_yh_globals;
    $bot = $_yh_globals['current_bot'] ?? BotManager::getCurrentBot();
    
    if (!$bot) {
        throw new \RuntimeException('机器人未初始化，请先调用 yh_init()');
    }
    
    return $bot->getMessages($chatId, $chatType, $messageId, $before, $after);
}

/**
 * ==================== 日志函数 ====================
 */

/**
 * 写入日志
 * 
 * @param string $message 日志消息
 * @param array $context 上下文数据
 */
function yh_log(string $message, array $context = []): void
{
    global $_yh_globals;
    $bot = $_yh_globals['current_bot'] ?? BotManager::getCurrentBot();
    
    if ($bot) {
        $bot->log($message, $context);
    }
}

/**
 * ==================== 按钮构造函数 ====================
 */

/**
 * 创建按钮
 * 
 * @param string $text 按钮文本
 * @param int $actionType 动作类型 (1=跳转URL, 2=回调, 3=弹出)
 * @param string|null $url_or_value URL或值
 * @return array 按钮配置
 */
function yh_button(string $text, int $actionType, ?string $url_or_value = null): array
{
    $button = [
        'text' => $text,
        'actionType' => $actionType
    ];
    
    if ($actionType === 1 && $url_or_value) {
        $button['url'] = $url_or_value;
    } elseif (($actionType === 2 || $actionType === 3) && $url_or_value) {
        $button['value'] = $url_or_value;
    }
    
    return $button;
}

/**
 * 创建URL按钮
 * 
 * @param string $text 按钮文本
 * @param string $url URL地址
 * @return array 按钮配置
 */
function yh_button_url(string $text, string $url): array
{
    return yh_button($text, 1, $url);
}

/**
 * 创建回调按钮
 * 
 * @param string $text 按钮文本
 * @param string $value 回调值
 * @return array 按钮配置
 */
function yh_button_callback(string $text, string $value): array
{
    return yh_button($text, 2, $value);
}

/**
 * 创建弹出按钮
 * 
 * @param string $text 按钮文本
 * @param string $value 弹出值
 * @return array 按钮配置
 */
function yh_button_popup(string $text, string $value): array
{
    return yh_button($text, 3, $value);
}

/**
 * ==================== Webhook处理器 ====================
 */

/**
 * Webhook处理器类
 * 
 * 用于处理不同的Webhook事件
 */
class YunhuWebhook
{
    private $handlers = [];
    private $bot;
    
    public function __construct(?Bot $bot = null)
    {
        $this->bot = $bot ?: BotManager::getCurrentBot();
    }
    
    /**
     * 注册事件处理器
     * 
     * @param string $eventType 事件类型
     * @param callable $callback 回调函数
     * @return $this
     */
    public function on(string $eventType, callable $callback): self
    {
        $this->handlers[$eventType] = $callback;
        return $this;
    }
    
    /**
     * 注册消息事件处理器
     * 
     * @param callable $callback 回调函数
     * @return $this
     */
    public function onMessage(callable $callback): self
    {
        $this->handlers[Bot::EVENT_MESSAGE_NORMAL] = $callback;
        return $this;
    }
    
    /**
     * 注册指令事件处理器
     * 
     * @param callable $callback 回调函数
     * @return $this
     */
    public function onInstruction(callable $callback): self
    {
        $this->handlers[Bot::EVENT_MESSAGE_INSTRUCTION] = $callback;
        return $this;
    }
    
    /**
     * 注册按钮事件处理器
     * 
     * @param callable $callback 回调函数
     * @return $this
     */
    public function onButton(callable $callback): self
    {
        $this->handlers[Bot::EVENT_BUTTON_INLINE] = $callback;
        return $this;
    }
    
    /**
     * 处理当前事件
     * 
     * @return bool 是否成功处理
     */
    public function handle(): bool
    {
        if (empty(yh_event_type())) {
            yh_log('无效的Webhook请求', ['level' => 'WARNING']);
            return false;
        }
        
        $eventType = yh_event_type();
        $handler = $this->handlers[$eventType] ?? null;
        
        if (!$handler && in_array($eventType, [Bot::EVENT_MESSAGE_NORMAL, Bot::EVENT_MESSAGE_INSTRUCTION])) {
            $handler = $this->handlers['message'] ?? null;
        }
        
        if (!$handler) {
            yh_log('未找到事件处理器', ['event_type' => $eventType]);
            return false;
        }
        
        try {
            $eventData = yh_event_data();
            $bot = $this->bot ?: BotManager::getCurrentBot();
            
            if ($bot) {
                $bot->log('处理事件', ['event_type' => $eventType]);
            }
            
            return call_user_func($handler, $eventData, $bot);
        } catch (\Exception $e) {
            yh_log('事件处理异常', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    /**
     * 运行处理器
     * 
     * @return bool
     */
    public function run(): bool
    {
        return $this->handle();
    }
}

/**
 * 创建Webhook处理器
 * 
 * @param Bot|null $bot 机器人实例
 * @return YunhuWebhook
 */
function yh_webhook(?Bot $bot = null): YunhuWebhook
{
    return new YunhuWebhook($bot);
}

/**
 * 快速响应函数 - 自动判断并回复
 * 
 * @param mixed $content 消息内容
 * @param string $contentType 内容类型
 * @param array|null $buttons 按钮
 * @return array|null API响应
 */
function yh_reply($content, string $contentType = Bot::CONTENT_TEXT, ?array $buttons = null): ?array
{
    $back = yh_back_object();
    if (empty($back['id'])) {
        return null;
    }
    
    return yh_send($back, $contentType, $content, $buttons);
}

/**
 * 快速文本回复
 * 
 * @param string $text 文本内容
 * @return array|null API响应
 */
function yh_reply_text(string $text): ?array
{
    return yh_reply($text, Bot::CONTENT_TEXT);
}

/**
 * 快速Markdown回复
 * 
 * @param string $markdown Markdown内容
 * @return array|null API响应
 */
function yh_reply_markdown(string $markdown): ?array
{
    return yh_reply($markdown, Bot::CONTENT_MARKDOWN);
}

/**
 * 快速HTML回复
 * 
 * @param string $html HTML内容
 * @return array|null API响应
 */
function yh_reply_html(string $html): ?array
{
    return yh_reply($html, Bot::CONTENT_HTML);
}

/**
 * 获取当前Bot实例
 * 
 * @return Bot|null
 */
function yh_bot(): ?Bot
{
    global $_yh_globals;
    return $_yh_globals['current_bot'] ?? BotManager::getCurrentBot();
}

// ==================== 向后兼容的别名 ====================

// 兼容旧的函数命名
if (!function_exists('yhsdk_init')) {
    function yhsdk_init(string $token, array $config = []) {
        return yh_init($token, '', $config);
    }
}

if (!function_exists('send')) {
    function send($recv, $type, $content, $batch = false, $buttons = null, $parentId = null) {
        if ($batch) {
            return yh_batch_send(is_array($recv) ? $recv : [$recv], 'user', $type, $content, $buttons, $parentId);
        }
        return yh_send($recv, $type, $content, $buttons, $parentId);
    }
}

if (!function_exists('edit')) {
    function edit($msg_id, $recv, $type, $content, $buttons = null) {
        return yh_edit($msg_id, $recv, $type, $content, $buttons);
    }
}

if (!function_exists('recall')) {
    function recall($msg_id, $object) {
        return yh_recall($msg_id, $object);
    }
}

if (!function_exists('get_messages')) {
    function get_messages($chat, $messageId = null, $before = 0, $after = 0) {
        $chatId = is_array($chat) ? ($chat['id'] ?? '') : $chat;
        $chatType = is_array($chat) ? ($chat['type'] ?? 'user') : 'user';
        return yh_get_messages($chatId, $chatType, $messageId, $before ?: null, $after ?: null);
    }
}

if (!function_exists('get_back_object')) {
    function get_back_object() {
        return yh_back_object();
    }
}

if (!function_exists('get_event_type')) {
    function get_event_type() {
        return yh_event_type();
    }
}

if (!function_exists('get_sender_id')) {
    function get_sender_id() {
        return yh_sender_id();
    }
}

if (!function_exists('get_message_id')) {
    function get_message_id() {
        return yh_message_id();
    }
}

if (!function_exists('get_message_content')) {
    function get_message_content() {
        return yh_message_content();
    }
}

if (!function_exists('get_command_info')) {
    function get_command_info() {
        return yh_command_info();
    }
}

if (!function_exists('get_button_value')) {
    function get_button_value() {
        return yh_button_value();
    }
}

if (!function_exists('yhsdk_write_log')) {
    function yhsdk_write_log($action, $level = 'INFO') {
        yh_log($action, ['level' => $level]);
    }
}
