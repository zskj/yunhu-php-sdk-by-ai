# 云湖机器人SDK重构文档

## 概述

SDK已从多文件结构重构为统一、规范的面向对象结构。新版SDK（v5.0）提供了更好的代码组织、更清晰的API和更强的类型安全。

## 新版SDK文件结构

```
/home/engine/project/
├── Bot.php              # 核心Bot类（必须包含）
├── yunhu_sdk.php        # 完整SDK，包含函数式接口（推荐）
├── example_bot.php      # 新版示例代码
├── yunhubot_sdk.php     # 旧版SDK（保留兼容）
└── yunhubot_sdk_optimized.php  # 旧版优化SDK（保留兼容）
```

## 核心改进

### 1. 统一的Bot类

新版SDK提供了一个统一的`Bot`类，包含所有核心功能：

```php
class Bot
{
    // 事件常量
    const EVENT_MESSAGE_NORMAL = 'message.receive.normal';
    const EVENT_MESSAGE_INSTRUCTION = 'message.receive.instruction';
    const EVENT_BUTTON_INLINE = 'button.report.inline';
    // ... 更多事件常量
    
    // 消息类型常量
    const CONTENT_TEXT = 'text';
    const CONTENT_MARKDOWN = 'markdown';
    const CONTENT_HTML = 'html';
    const CONTENT_IMAGE = 'image';
    // ... 更多消息类型常量
    
    // 聊天类型常量
    const RECV_TYPE_USER = 'user';
    const RECV_TYPE_GROUP = 'group';
    
    // 主要方法
    public function send(string $recvId, string $recvType, string $contentType, $content, ?array $buttons = null, ?string $parentId = null): array
    public function batchSend(array $recvIds, string $recvType, string $contentType, $content, ?array $buttons = null, ?string $parentId = null): array
    public function sendStream(string $recvId, string $recvType, string $contentType, \Generator $generator, int $delayMs = 1000): array
    public function edit(string $msgId, string $recvId, string $recvType, string $contentType, $content, ?array $buttons = null): array
    public function recall(string $msgId, string $chatId, string $chatType): array
    public function uploadImage(string $imagePath): array
    public function uploadVideo(string $videoPath): array
    public function uploadFile(string $filePath): array
    // ... 更多方法
}
```

### 2. 双接口支持

新版SDK同时支持面向对象和函数式两种编程风格：

#### 面向对象方式（推荐）

```php
// 创建Bot实例
$bot = new Bot('your-token', 'your-bot-id', [
    'debug_mode' => true,
    'log_file' => __DIR__ . '/bot.log'
]);

// 发送消息
$bot->send('user123', Bot::RECV_TYPE_USER, Bot::CONTENT_TEXT, 'Hello');

// 使用快捷方法
$bot->uploadImage('/path/to/image.jpg');
```

#### 函数式方式（兼容旧版）

```php
// 初始化
$bot = yh_init('your-token', 'your-bot-id');

// 发送消息
$back = yh_back_object();  // 自动获取回复目标
$bot->send($back['id'], $back['type'], Bot::CONTENT_TEXT, 'Hello');

// 或使用快捷函数
$bot->sendText($back, 'Hello');
yh_send_text($back, 'Hello');  // 全局函数
```

## 迁移指南

### 从旧版SDK迁移到新版SDK

#### 1. 包含文件变更

**旧版：**
```php
require_once __DIR__ . '/yunhubot_sdk.php';
// 或
require_once __DIR__ . '/yunhubot_sdk_optimized.php';
```

**新版：**
```php
// 方式1：只包含核心类（面向对象风格）
require_once __DIR__ . '/Bot.php';

// 方式2：包含完整SDK（推荐，支持双接口）
require_once __DIR__ . '/yunhu_sdk.php';
```

#### 2. 初始化变更

**旧版：**
```php
$bot = yhsdk_init('your-token', [
    'log_path' => 'bot.log'
]);
```

**新版（面向对象）：**
```php
$bot = new Bot('your-token', 'your-bot-id', [
    'debug_mode' => true,
    'log_file' => __DIR__ . '/bot.log',
    'timeout' => 30
]);
```

**新版（函数式）：**
```php
$bot = yh_init('your-token', 'your-bot-id', [
    'debug_mode' => true,
    'log_file' => __DIR__ . '/bot.log',
    'timeout' => 30
]);
```

#### 3. 常量使用变更

**旧版：**
```php
// 硬编码字符串
$bot->send($recvId, 'user', 'text', 'Hello');
```

**新版：**
```php
// 使用常量
$bot->send($recvId, Bot::RECV_TYPE_USER, Bot::CONTENT_TEXT, 'Hello');

// 或在文件中直接使用常量
use Bot;
$bot->send($recvId, RECV_TYPE_USER, CONTENT_TEXT, 'Hello');
```

可用常量：
- 事件类型：`Bot::EVENT_MESSAGE_NORMAL`, `Bot::EVENT_MESSAGE_INSTRUCTION`, `Bot::EVENT_BUTTON_INLINE` 等
- 消息类型：`Bot::CONTENT_TEXT`, `Bot::CONTENT_MARKDOWN`, `Bot::CONTENT_HTML`, `Bot::CONTENT_IMAGE` 等
- 接收类型：`Bot::RECV_TYPE_USER`, `Bot::RECV_TYPE_GROUP`
- 聊天类型：`Bot::CHAT_TYPE_BOT`, `Bot::CHAT_TYPE_GROUP`

#### 4. 发送消息API变更

**旧版：**
```php
// 函数式
send($object, $type, $content, $batch, $buttons, $parentId);

// $object可以是字符串ID或['id', 'type']数组
```

**新版（面向对象）：**
```php
// 单发
$bot->send($recvId, $recvType, $contentType, $content, $buttons, $parentId);

// 批量发送
$bot->batchSend($recvIds, $recvType, $contentType, $content, $buttons, $parentId);
```

**新版（函数式）：**
```php
// 单发
$back = yh_back_object();  // 自动获取回复目标
$bot->send($back['id'], $back['type'], Bot::CONTENT_TEXT, 'Hello');

// 或使用快捷函数
$bot->sendText($back, 'Hello');

// 或使用全局函数
$back = yh_back_object();
yh_send_text($back, 'Hello');
yyh_send($back, Bot::CONTENT_TEXT, 'Hello');
```

#### 5. 事件解析变更

**旧版：**
```php
$event_type = get_event_type();  // 从$_POST或全局变量获取
$sender_id = get_sender_id();
$message_content = get_message_content();
$back = get_back_object();
```

**新版（面向对象）：**
```php
// 先解析事件
$eventData = yh_parse_event();  // 自动解析php://input

// 然后使用各种获取函数
$eventType = yh_event_type();
$senderId = yh_sender_id();
$messageContent = yh_message_content();
$back = yh_back_object();  // 自动判断回复目标
$cmdInfo = yh_command_info();  // 指令信息
$buttonValue = yh_button_value();  // 按钮值
```

**快捷判断：**
```php
// 判断事件类型
if (yh_is_event(Bot::EVENT_MESSAGE_INSTRUCTION)) {
    // 处理指令
}

if (yh_is_event(Bot::EVENT_BUTTON_INLINE)) {
    // 处理按钮
}
```

#### 6. 按钮创建变更

**旧版：**
```php
create_button($text, $actionType, $url, $value);
```

**新版（函数式）：**
```php
// 通用按钮
$button = yh_button('点击我', 2, 'callback_value');

// URL按钮
$button = yh_button_url('打开链接', 'https://example.com');

// 回调按钮
$button = yh_button_callback('点击回调', 'button_value');

// 弹出按钮
$button = yh_button_popup('弹出提示', 'popup_value');
```

#### 7. 文件上传变更

**旧版：**
```php
$bot->uploadImage($file_path);
$bot->uploadVideo($file_path);
$bot->uploadGeneralFile($file_path);
```

**新版（面向对象）：**
```php
$bot->uploadImage($filePath);
$bot->uploadVideo($filePath);
$bot->uploadFile($filePath);  // 统一命名为uploadFile
```

**新版（函数式）：**
```php
$bot->uploadImage($filePath);
yh_upload_image($filePath);
yh_upload_video($filePath);
yh_upload_file($filePath);
```

#### 8. 流式发送变更

**旧版：**
```php
$bot->sendStream($object, $type, $content);
$bot->sendStreamText($object, $messages, $delay);
```

**新版（面向对象）：**
```php
// 使用Generator
$generator = function() {
    yield "第一行\n";
    sleep(1);
    yield "第二行\n";
};
$bot->sendStream($recvId, $recvType, Bot::CONTENT_TEXT, $generator(), 1000);

// 快捷方式 - 发送文本数组
$bot->sendStreamText($recvId, $recvType, ['第一行', '第二行'], 1000);
```

**新版（函数式）：**
```php
$generator = function() {
    yield "第一行\n";
    sleep(1);
    yield "第二行\n";
};
yh_send_stream($recv, Bot::CONTENT_TEXT, $generator(), 1000);

// 快捷方式 - 发送文本数组
$back = yh_back_object();
yh_send_stream_text($back, ['第一行', '第二行'], 1000);
```

#### 9. Webhook处理器（新增）

新版SDK提供了更优雅的Webhook事件处理方式：

```php
// 创建处理器
$webhook = yh_webhook($bot);

// 注册事件处理器
$webhook->onMessage(function($eventData, $bot) {
    $content = yh_message_content();
    $back = yh_back_object();
    $bot->sendText($back, "你说了: {$content}");
});

$webhook->onInstruction(function($eventData, $bot) {
    $cmd = yh_command_info();
    // 处理指令...
});

$webhook->onButton(function($eventData, $bot) {
    $value = yh_button_value();
    $back = yh_back_object();
    $bot->sendText($back, "按钮值: {$value}");
});

// 运行处理器
$webhook->handle();
```

## 功能对比表

| 功能 | 旧版SDK | 新版SDK (OOP) | 新版SDK (函数式) |
|------|---------|---------------|------------------|
| 初始化 | `yhsdk_init($token, $config)` | `new Bot($token, $botId, $config)` | `yh_init($token, $botId, $config)` |
| 发送消息 | `send($obj, $type, $content, $batch)` | `$bot->send($id, $recvType, $contentType, $content)` | `yh_send($recv, $type, $content)` |
| 批量发送 | `send($obj, $type, $content, true)` | `$bot->batchSend($ids, $recvType, $contentType, $content)` | `yh_batch_send($ids, $recvType, $type, $content)` |
| 编辑消息 | `edit($msgId, $obj, $type, $content)` | `$bot->edit($msgId, $id, $recvType, $contentType, $content)` | `yh_edit($msgId, $recv, $type, $content)` |
| 撤回消息 | `recall($msgId, $obj)` | `$bot->recall($msgId, $chatId, $chatType)` | `yh_recall($msgId, $chat)` |
| 上传图片 | `$bot->uploadImage($path)` | `$bot->uploadImage($path)` | `yh_upload_image($path)` |
| 上传视频 | `$bot->uploadVideo($path)` | `$bot->uploadVideo($path)` | `yh_upload_video($path)` |
| 上传文件 | `$bot->uploadGeneralFile($path)` | `$bot->uploadFile($path)` | `yh_upload_file($path)` |
| 设置看板 | `$bot->setBoard($type, $content, $isAll, $obj)` | `$bot->setBoard($chatId, $chatType, $contentType, $content, $memberId, $expire)` | `yh_set_board($chatId, $chatType, $type, $content, $memberId, $expire)` |
| 取消看板 | `$bot->unsetBoard($isAll, $obj)` | `$bot->dismissBoard($chatId, $chatType, $memberId)` | `yh_dismiss_board($chatId, $chatType, $memberId)` |
| 获取事件类型 | `get_event_type()` | `yh_event_type()` | `yh_event_type()` |
| 获取发送者ID | `get_sender_id()` | `yh_sender_id()` | `yh_sender_id()` |
| 获取消息内容 | `get_message_content()` | `yh_message_content()` | `yh_message_content()` |
| 获取回复目标 | `get_back_object()` | `yh_back_object()` | `yh_back_object()` |
| 创建按钮 | `create_button($text, $type, $url, $value)` | `yh_button($text, $type, $value)` | `yh_button($text, $type, $value)` |
| 流式发送 | `send_stream($recv, $type, $content)` | `$bot->sendStream($id, $type, $generator, $delay)` | `yh_send_stream($recv, $type, $generator, $delay)` |

## 最佳实践

### 1. 使用面向对象方式（推荐）

```php
<?php
require_once __DIR__ . '/yunhu_sdk.php';

class MyBot
{
    private $bot;
    
    public function __construct(string $token, string $botId)
    {
        $this->bot = new Bot($token, $botId, [
            'debug_mode' => true,
            'log_file' => __DIR__ . '/bot.log'
        ]);
    }
    
    public function handleWebhook()
    {
        if (!yh_parse_event()) {
            return;
        }
        
        switch (yh_event_type()) {
            case Bot::EVENT_MESSAGE_INSTRUCTION:
                $this->handleCommand();
                break;
            case Bot::EVENT_MESSAGE_NORMAL:
                $this->handleMessage();
                break;
            case Bot::EVENT_BUTTON_INLINE:
                $this->handleButton();
                break;
        }
    }
    
    private function handleCommand()
    {
        $cmd = yh_command_info();
        $back = yh_back_object();
        
        switch ($cmd['commandId']) {
            case 1001:
                $this->bot->sendText($back, '指令1001已执行');
                break;
        }
    }
    
    private function handleMessage()
    {
        $content = yh_message_content();
        $back = yh_back_object();
        
        // 回复用户
        $this->bot->sendText($back, "你说了: {$content}");
    }
    
    private function handleButton()
    {
        $value = yh_button_value();
        $back = yh_back_object();
        
        $this->bot->sendText($back, "按钮值: {$value}");
    }
}

// 使用
$bot = new MyBot('your-token', 'your-bot-id');
$bot->handleWebhook();
```

### 2. 使用Webhook处理器（最优雅）

```php
<?php
require_once __DIR__ . '/yunhu_sdk.php';

// 初始化
$bot = yh_init('your-token', 'your-bot-id', [
    'debug_mode' => true
]);

// 解析事件
yh_parse_event();

// 创建处理器
$webhook = yh_webhook($bot);

// 注册处理器
$webhook->onMessage(function($event, $bot) {
    $content = yh_message_content();
    $back = yh_back_object();
    $bot->sendText($back, "收到: {$content}");
});

$webhook->onInstruction(function($event, $bot) {
    $cmd = yh_command_info();
    $back = yh_back_object();
    
    switch ($cmd['commandId']) {
        case 1001:
            $bot->sendText($back, '执行指令1001');
            break;
    }
});

$webhook->onButton(function($event, $bot) {
    $value = yh_button_value();
    $back = yh_back_object();
    $bot->sendText($back, "按钮: {$value}");
});

// 运行
$webhook->handle();
```

### 3. 错误处理

```php
try {
    $result = $bot->send($recvId, $recvType, Bot::CONTENT_TEXT, 'Hello');
    
    if ($result['code'] !== 1) {
        throw new \Exception("发送失败: {$result['msg']}");
    }
} catch (\Exception $e) {
    $bot->log("错误: " . $e->getMessage(), [
        'error' => $e->getTraceAsString()
    ]);
    
    // 通知管理员
    $bot->sendText('admin-user-id', 'Bot错误: ' . $e->getMessage());
}
```

### 4. 文件上传

```php
// 检查文件大小
if (filesize($imagePath) > Bot::MAX_IMAGE_SIZE) {
    $bot->sendText($back, '图片太大，请上传小于10MB的图片');
    return;
}

try {
    $result = $bot->uploadImage($imagePath);
    
    if ($result['code'] === 1) {
        $imageKey = $result['data']['imageKey'];
        $bot->sendImage($back, $imageKey);
    }
} catch (\Exception $e) {
    $bot->sendText($back, '上传失败: ' . $e->getMessage());
}
```

## 性能优化

1. **使用BotManager管理多机器人实例：**
```php
// 创建多个机器人
$bot1 = BotManager::getBot('token1', 'botId1');
$bot2 = BotManager::getBot('token2', 'botId2');

// 切换当前机器人
BotManager::setCurrentBot('token1', 'botId1');

// 获取当前机器人
$currentBot = BotManager::getCurrentBot();
```

2. **使用生成器处理大量消息：**
```php
$generator = function() {
    foreach ($largeDataSet as $item) {
        yield processItem($item);
    }
};

$bot->sendStream($recvId, $recvType, Bot::CONTENT_TEXT, $generator());
```

3. **批量发送优化：**
```php
// 批量发送比单个循环更高效
$userIds = ['user1', 'user2', 'user3', 'user4', 'user5'];
$bot->batchSend($userIds, Bot::RECV_TYPE_USER, Bot::CONTENT_TEXT, '群发消息');
```

## 总结

新版SDK提供了：

1. **更清晰的代码结构** - 统一的Bot类，所有功能一目了然
2. **更强的类型安全** - 使用常量和类型提示
3. **双接口支持** - 同时支持OOP和函数式编程
4. **更好的性能** - 支持批量操作和流式处理
5. **更优雅的Webhook处理** - 提供Webhook处理器类
6. **向后兼容** - 保留了旧版函数别名

建议新项目直接使用新版SDK，旧项目可以逐步迁移。
