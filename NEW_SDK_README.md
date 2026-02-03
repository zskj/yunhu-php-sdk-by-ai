# 新版云湖机器人SDK 使用说明

## 概述

这是云湖机器人SDK的全新重构版本（v5.0），基于参考`Bot`类设计，提供了统一的面向对象接口和向后兼容的函数式接口。

## 文件结构

```
/home/engine/project/
├── Bot.php                     # 核心Bot类（必须包含）
├── yunhu_sdk.php               # 完整SDK，包含函数式接口（推荐）
├── example_bot.php             # 新版示例代码
├── SDK_MIGRATION.md            # 迁移指南
├── test_sdk.php                # SDK测试脚本
├── yunhubot_sdk.php            # 旧版SDK（保留兼容）
└── yunhubot_sdk_optimized.php  # 旧版优化SDK（保留兼容）
```

## 快速开始

### 方式1：面向对象（推荐）

```php
<?php
require_once __DIR__ . '/Bot.php';

// 创建Bot实例
$bot = new Bot('your-token', 'your-bot-id', [
    'debug_mode' => true,
    'log_file' => __DIR__ . '/bot.log',
    'timeout' => 30
]);

// 发送消息
$bot->send(
    'user123',                     // 接收者ID
    Bot::RECV_TYPE_USER,           // 接收者类型
    Bot::CONTENT_TEXT,             // 消息类型
    'Hello World!'                 // 消息内容
);

// 发送Markdown
$bot->send(
    'user123',
    Bot::RECV_TYPE_USER,
    Bot::CONTENT_MARKDOWN,
    '**加粗文本**和*斜体文本*'
);

// 编辑消息
$bot->edit(
    'msgId123',                    // 消息ID
    'user123',
    Bot::RECV_TYPE_USER,
    Bot::CONTENT_TEXT,
    '编辑后的内容'
);

// 撤回消息
$bot->recall('msgId123', 'chatId123', 'user');
```

### 方式2：函数式（兼容旧版）

```php
<?php
require_once __DIR__ . '/yunhu_sdk.php';

// 初始化
$bot = yh_init('your-token', 'your-bot-id', [
    'debug_mode' => true,
    'log_file' => __DIR__ . '/bot.log'
]);

// 解析Webhook事件
if (!yh_parse_event()) {
    return;
}

// 获取事件类型
$eventType = yh_event_type();

// 获取发送者信息
$senderId = yh_sender_id();
$messageContent = yh_message_content();
$back = yh_back_object();  // 自动获取回复目标

// 发送消息（多种方式）
// 方式1：使用Bot实例
$bot->send(
    $back['id'],
    $back['type'],
    Bot::CONTENT_TEXT,
    'Hello!'
);

// 方式2：使用快捷函数
$bot->sendText($back, 'Hello!');
$bot->sendMarkdown($back, '**Hello!**');
$bot->sendHtml($back, '<b>Hello!</b>');

// 方式3：使用全局函数
$bot->sendText($back, 'Hello!');
yh_send_text($back, 'Hello!');
```

## 主要特性

### 1. 完整的常量定义

```php
// 事件类型
Bot::EVENT_MESSAGE_NORMAL      // 普通消息
Bot::EVENT_MESSAGE_INSTRUCTION // 指令消息
Bot::EVENT_BUTTON_INLINE       // 按钮事件
Bot::EVENT_BOT_FOLLOWED        // 关注事件
Bot::EVENT_BOT_UNFOLLOWED      // 取消关注事件
Bot::EVENT_GROUP_JOIN          // 加入群事件
Bot::EVENT_GROUP_LEAVE         // 退出群事件
Bot::EVENT_SHORTCUT_MENU       // 快捷菜单事件
Bot::EVENT_BOT_SETTING         // 机器人设置事件

// 消息类型
Bot::CONTENT_TEXT              // 文本
Bot::CONTENT_MARKDOWN          // Markdown
Bot::CONTENT_HTML              // HTML
Bot::CONTENT_IMAGE             // 图片
Bot::CONTENT_VIDEO             // 视频
Bot::CONTENT_FILE              // 文件
Bot::CONTENT_AUDIO             // 音频
Bot::CONTENT_EXPRESSION        // 表情
Bot::CONTENT_POST              // 帖子
Bot::CONTENT_FORM              // 表单
Bot::CONTENT_AUDIO_CALL        // 语音通话
Bot::CONTENT_VIDEO_CALL        // 视频通话
Bot::CONTENT_UNKNOWN           // 未知类型

// 接收类型
Bot::RECV_TYPE_USER            // 用户
Bot::RECV_TYPE_GROUP           // 群组
Bot::CHAT_TYPE_BOT             // 机器人
Bot::CHAT_TYPE_GROUP           // 群聊

// 用户级别
Bot::USER_LEVEL_OWNER          // 群主
Bot::USER_LEVEL_ADMIN          // 管理员
Bot::USER_LEVEL_MEMBER         // 普通成员
Bot::USER_LEVEL_UNKNOWN        // 未知

// 文件大小限制
Bot::MAX_IMAGE_SIZE            // 10MB
Bot::MAX_VIDEO_SIZE            // 20MB
Bot::MAX_FILE_SIZE             // 20MB
```

### 2. 消息发送API

```php
// 单发消息
$bot->send($recvId, $recvType, $contentType, $content, $buttons, $parentId);

// 批量发送
$bot->batchSend($recvIds, $recvType, $contentType, $content, $buttons, $parentId);

// 流式发送
$generator = function() {
    yield "第一行\n";
    sleep(1);
    yield "第二行\n";
};
$bot->sendStream($recvId, $recvType, Bot::CONTENT_TEXT, $generator(), 1000);

// 快捷方式
$bot->sendStreamText($recvId, $recvType, ['第1条', '第2条', '第3条'], 1000);

// 快捷函数
$bot->sendText($recvId, '文本消息');
$bot->sendMarkdown($recvId, '**Markdown消息**');
$bot->sendHtml($recvId, '<b>HTML消息</b>');
$bot->sendImage($recvId, 'image_key_or_url');
$bot->sendVideo($recvId, 'video_key');
$bot->sendFile($recvId, 'file_key', '文件名', '文件URL');
```

### 3. 文件上传

```php
// 上传图片
$result = $bot->uploadImage('/path/to/image.jpg');
$imageKey = $result['data']['imageKey'];

// 上传视频
$result = $bot->uploadVideo('/path/to/video.mp4');
$videoKey = $result['data']['videoKey'];

// 上传文件
$result = $bot->uploadFile('/path/to/document.pdf');
$fileKey = $result['data']['fileKey'];

// 发送已上传的文件
$bot->sendImage('user123', Bot::RECV_TYPE_USER, Bot::CONTENT_IMAGE, $imageKey);
```

### 4. Webhook事件处理

```php
// 方式1：手动处理
if (!yh_parse_event()) {
    return;
}

$eventType = yh_event_type();

switch ($eventType) {
    case Bot::EVENT_MESSAGE_NORMAL:
        $content = yh_message_content();
        $back = yh_back_object();
        $bot->sendText($back, "你说了: {$content}");
        break;
        
    case Bot::EVENT_MESSAGE_INSTRUCTION:
        $cmd = yh_command_info();
        // 处理指令...
        break;
        
    case Bot::EVENT_BUTTON_INLINE:
        $value = yh_button_value();
        $back = yh_back_object();
        $bot->sendText($back, "按钮值: {$value}");
        break;
}

// 方式2：使用Webhook处理器（推荐）
$webhook = yh_webhook($bot);

$webhook->onMessage(function($event, $bot) {
    $content = yh_message_content();
    $back = yh_back_object();
    $bot->sendText($back, "你说了: {$content}");
});

$webhook->onInstruction(function($event, $bot) {
    $cmd = yh_command_info();
    // 处理指令...
});

$webhook->onButton(function($event, $bot) {
    $value = yh_button_value();
    $back = yh_back_object();
    $bot->sendText($back, "按钮值: {$value}");
});

$webhook->handle();
```

### 5. 按钮创建

```php
// URL按钮
$urlButton = yh_button_url('打开百度', 'https://baidu.com');

// 回调按钮
$callbackButton = yh_button_callback('点击我', 'button_value_123');

// 弹出按钮
$popupButton = yh_button_popup('查看详情', 'detail_info');

// 发送带按钮的消息
$bot->send(
    $back['id'],
    $back['type'],
    Bot::CONTENT_TEXT,
    '请选择操作：',
    [$urlButton, $callbackButton]
);
```

### 6. 看板功能

```php
// 设置个人看板
$bot->setBoard(
    'user123',                     // 聊天ID
    Bot::RECV_TYPE_USER,           // 聊天类型
    Bot::CONTENT_MARKDOWN,         // 内容类型
    '**我的看板**\n这是个人看板内容',  // 内容
    null,                          // 成员ID（群聊时）
    time() + 3600                  // 过期时间（1小时后）
);

// 设置全局看板
$bot->setGlobalBoard(
    Bot::CONTENT_TEXT,
    '这是全局看板内容',
    time() + 86400  // 24小时后过期
);

// 取消看板
$bot->dismissBoard('user123', Bot::RECV_TYPE_USER);
$bot->dismissGlobalBoard();
```

## 向后兼容

新版SDK提供了向后兼容的函数别名，旧代码可以无缝迁移：

```php
// 旧版函数在新版中仍然可用
$bot = yhsdk_init('token', $config);
send($recv, $type, $content, $batch, $buttons, $parentId);
edit($msgId, $recv, $type, $content, $buttons);
recall($msgId, $object);
get_messages($chat, $messageId, $before, $after);
get_back_object();
get_event_type();
get_sender_id();
get_message_id();
get_message_content();
get_command_info();
get_button_value();
yhsdk_write_log($action, $level);
```

## 迁移建议

对于现有项目，建议按以下步骤迁移：

1. **替换包含文件**
   - 旧：`require_once 'yunhubot_sdk.php';`
   - 新：`require_once 'yunhu_sdk.php';`

2. **更新初始化方式**
   - 旧：`yhsdk_init('token', $config)`
   - 新：`yh_init('token', 'botId', $config)` 或直接 `new Bot('token', 'botId', $config)`

3. **使用常量替换硬编码字符串**
   - 旧：`'text'`, `'user'`, `'message.receive.normal'`
   - 新：`Bot::CONTENT_TEXT`, `Bot::RECV_TYPE_USER`, `Bot::EVENT_MESSAGE_NORMAL`

4. **利用新增功能**
   - 使用 `BotManager` 管理多机器人实例
   - 使用 `Webhook` 处理器简化事件处理
   - 使用常量提高代码可读性

## 完整示例

查看 `example_bot.php` 文件获取完整的示例代码，包含：

- 面向对象风格的使用方式
- 函数式风格的使用方式
- 消息发送与接收
- 指令处理
- 按钮交互
- 错误处理
- 日志记录

## 技术支持

如遇到问题，请查看：
- `SDK_MIGRATION.md` - 详细的迁移指南
- `example_bot.php` - 完整的示例代码
- `test_sdk.php` - SDK功能测试脚本

## 版本信息

- **SDK版本**: 5.0
- **发布日期**: 2024
- **主要特性**: 统一OOP接口 + 向后兼容函数式接口
- **核心类**: `Bot`, `BotManager`, `YunhuWebhook`
- **常量完善**: 事件类型、消息类型、聊天类型、用户级别
