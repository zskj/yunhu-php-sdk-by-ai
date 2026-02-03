# 云湖机器人优化版SDK

## 项目概述

本项目是一个完全面向对象的云湖机器人开发框架，不使用namespace，提供简洁的API接口和强大的功能。

## 文件结构

```
├── index.php                      # Webhook入口文件（推荐使用）
├── yunhubot_sdk_optimized.php     # 优化版SDK（面向对象设计）
├── yunhu_functional_bot.php       # 原有功能机器人文件（保留作为参考）
├── yunhubot_sdk.php              # 原版SDK文件（保留作为参考）
├── .gitignore                    # Git忽略文件
└── README.md                     # 项目说明文档
```

## 主要特性

### 1. 完全面向对象设计
- 不使用namespace，纯面向对象架构
- 模块化设计，各组件职责清晰
- 支持依赖注入和配置管理

### 2. 异常处理
- 自定义异常类体系
- 完善的错误处理和日志记录
- 网络请求重试机制

### 3. 事件驱动架构
- 事件模型封装
- 灵活的处理器注册机制
- 支持多种事件类型

### 4. 消息构建器
- 链式调用的消息构建器
- 支持多种消息类型
- 灵活的按钮和附件支持

### 5. 完善的日志系统
- 分级日志记录
- 调试模式支持
- 自动日志轮转

## 使用方法

### 1. 基础配置

在 `index.php` 中修改配置：

```php
// 配置机器人token和参数
define('BOT_TOKEN', '你的机器人token'); // 必须修改
define('BOT_NAME', '云湖查询机器人');
define('BOT_VERSION', '2.0.0');
```

### 2. Webhook使用

推荐的Webhook入口文件，支持：
- 自动事件解析
- 指令处理
- 错误处理
- 日志记录

### 3. SDK使用示例

```php
require_once __DIR__ . '/yunhubot_sdk_optimized.php';

// 创建配置
$config = new YunhuBotConfig([
    'log_path' => 'bot.log',
    'debug' => true
]);

// 创建机器人实例
$bot = new YunhuBotSDK('你的token', $config);

// 发送文本消息
$bot->sendText('user_id', 'Hello World!');

// 发送富文本消息
$bot->sendHtml('user_id', '<b>Hello World!</b>');

// 构建复杂消息
$content = YunhuBotMessageBuilder::create('text')
    ->setText('Hello World!')
    ->addButton('点击我', 2, '', 'button_value')
    ->build();

$bot->send('user_id', 'text', $content);
```

### 4. 事件处理

```php
// 创建Webhook处理器
$webhook = new YunhuBotWebhook($bot);

// 注册事件处理器
$webhook->onInstruction(function($event, $bot) {
    $commandId = $event->getCommandInfo()['commandId'];
    $content = $event->getMessageContent();
    
    if ($commandId == 2247) {
        // 处理版本查询
        $bot->sendText($event->getBackObject(), '正在查询版本信息...');
        // ... 处理逻辑
    }
});

// 处理webhook
$webhook->run();
```

## API文档

### YunhuBotSDK类

#### 消息发送
- `send($object, $contentType, $content, $buttons, $parentId)` - 发送消息
- `sendText($object, $text, $buttons)` - 发送文本消息
- `sendHtml($object, $html, $buttons)` - 发送HTML消息
- `sendMarkdown($object, $markdown, $buttons)` - 发送Markdown消息
- `sendImage($object, $imageKey, $imageUrl)` - 发送图片消息
- `sendVideo($object, $videoKey)` - 发送视频消息
- `sendFile($object, $fileKey, $fileName, $fileUrl)` - 发送文件消息

#### 消息操作
- `edit($msgId, $object, $contentType, $content, $buttons)` - 编辑消息
- `recall($msgId, $object)` - 撤回消息
- `sendStream($object, $contentType, $content)` - 流式发送

#### 文件上传
- `uploadImage($filePath)` - 上传图片
- `uploadVideo($filePath)` - 上传视频
- `uploadFile($filePath)` - 上传文件
- `getFileUploader()` - 获取文件上传器

#### 看板功能
- `setBoard($contentType, $content, $isAll, $object, $memberId, $expireTime)` - 设置看板
- `unsetBoard($isAll, $object, $memberId)` - 取消看板

### YunhuBotMessageBuilder类

```php
// 创建消息构建器
$builder = YunhuBotMessageBuilder::create('text');

// 链式调用构建消息
$content = $builder->setText('Hello World!')
    ->addButton('按钮1', 2, '', 'value1')
    ->addButton('按钮2', 1, 'https://example.com')
    ->build();
```

### YunhuBotWebhook类

```php
// 注册事件处理器
$webhook->onMessage($callback);        // 普通消息
$webhook->onInstruction($callback);    // 指令消息
$webhook->onButton($callback);         // 按钮事件
$webhook->onEvent($eventType, $callback); // 任意事件

// 处理webhook
$webhook->run();
```

## 支持的指令

| 指令ID | 功能描述 | 参数 |
|--------|----------|------|
| 2215 | 帮助菜单 | 无 |
| 2247 | 版本信息查询 | 无 |
| 2248 | 用户信息查询 | 用户ID |
| 2249 | 群组信息查询 | 群组ID |
| 2250 | 机器人信息查询 | 机器人ID |

## 错误处理

SDK提供完善的错误处理机制：

```php
try {
    $result = $bot->sendText('user_id', 'Hello');
} catch (YunhuBotValidationException $e) {
    // 参数验证错误
    echo "参数错误: " . $e->getMessage();
} catch (YunhuBotNetworkException $e) {
    // 网络错误
    echo "网络错误: " . $e->getMessage();
} catch (YunhuBotException $e) {
    // 其他SDK错误
    echo "SDK错误: " . $e->getMessage();
}
```

## 日志配置

```php
$config = new YunhuBotConfig([
    'log_path' => 'bot.log',    // 日志文件路径
    'debug' => true             // 是否开启调试模式
]);

$bot = new YunhuBotSDK('token', $config);

// 手动记录日志
$bot->log('这是一条日志', 'INFO', ['context' => 'value']);
```

## 最佳实践

1. **配置管理**：使用 `YunhuBotConfig` 类统一管理配置
2. **错误处理**：始终使用try-catch处理可能抛出的异常
3. **日志记录**：合理使用日志来调试和监控机器人行为
4. **消息构建**：使用 `YunhuBotMessageBuilder` 构建复杂消息
5. **事件处理**：使用 `YunhuBotWebhook` 处理webhook请求

## 兼容性

- PHP 7.4+
- 完全向下兼容，不使用namespace
- 支持所有云湖机器人API

## 更新日志

### v4.0 (当前版本)
- 完全重构为面向对象设计
- 添加异常处理体系
- 改进消息构建器
- 增加事件驱动架构
- 优化日志系统
- 新增webhook处理器

### v3.0 (原版)
- 函数式API设计
- 基础消息发送功能
- 文件上传支持
- 看板功能

## 许可证

本项目采用 MIT 许可证。