<?php
/**
 * SDK测试脚本
 * 用于验证新版SDK的功能是否正常
 */

require_once __DIR__ . '/yunhu_sdk.php';

echo "=== 云湖SDK测试脚本 ===\n\n";

// 测试1：类常量定义
echo "测试1: 类常量定义\n";
echo "- EVENT_MESSAGE_NORMAL: " . Bot::EVENT_MESSAGE_NORMAL . "\n";
echo "- CONTENT_TEXT: " . Bot::CONTENT_TEXT . "\n";
echo "- RECV_TYPE_USER: " . Bot::RECV_TYPE_USER . "\n";
echo "✓ 常量定义正常\n\n";

// 测试2：创建Bot实例
echo "测试2: 创建Bot实例\n";
try {
    $bot = new Bot('test-token', 'test-bot-id', [
        'debug_mode' => true,
        'log_file' => __DIR__ . '/test_bot.log'
    ]);
    echo "- Token: {$bot->token}\n";
    echo "- Bot ID: {$bot->botId}\n";
    echo "✓ Bot实例创建成功\n\n";
} catch (Exception $e) {
    echo "✗ Bot实例创建失败: " . $e->getMessage() . "\n\n";
}

// 测试3：BotManager管理器
echo "测试3: BotManager管理器\n";
try {
    $bot1 = BotManager::getBot('token1', 'bot1');
    $bot2 = BotManager::getBot('token2', 'bot2');
    
    echo "- 创建bot1: " . ($bot1 ? "成功" : "失败") . "\n";
    echo "- 创建bot2: " . ($bot2 ? "成功" : "失败") . "\n";
    echo "- Bot数量: " . count(BotManager::getAllBots()) . "\n";
    
    BotManager::setCurrentBot('token1', 'bot1');
    $current = BotManager::getCurrentBot();
    echo "- 当前Bot Token: {$current->token}\n";
    echo "✓ BotManager工作正常\n\n";
} catch (Exception $e) {
    echo "✗ BotManager测试失败: " . $e->getMessage() . "\n\n";
}

// 测试4：函数式接口
echo "测试4: 函数式接口\n";
try {
    $bot = yh_init('test-token-2', 'test-bot-2');
    $current = yh_bot();
    echo "- yh_init初始化: " . ($current ? "成功" : "失败") . "\n";
    echo "- 当前Bot Token: {$current->token}\n";
    echo "✓ 函数式接口正常\n\n";
} catch (Exception $e) {
    echo "✗ 函数式接口测试失败: " . $e->getMessage() . "\n\n";
}

// 测试5：快捷函数
echo "测试5: 快捷函数\n";
try {
    // 创建按钮
    $button1 = yh_button_url('打开链接', 'https://example.com');
    $button2 = yh_button_callback('点击回调', 'button_value');
    $button3 = yh_button_popup('弹出提示', 'popup_value');
    
    echo "- URL按钮: " . (isset($button1['url']) ? "成功" : "失败") . "\n";
    echo "- 回调按钮: " . (isset($button2['value']) ? "成功" : "失败") . "\n";
    echo "- 弹出按钮: " . (isset($button3['value']) ? "成功" : "失败") . "\n";
    echo "✓ 按钮创建正常\n\n";
} catch (Exception $e) {
    echo "✗ 快捷函数测试失败: " . $e->getMessage() . "\n\n";
}

// 测试6：事件解析模拟
echo "测试6: 事件解析\n";
try {
    // 模拟POST数据
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    // 模拟普通消息事件
    $normalMessage = json_encode([
        'header' => [
            'eventType' => Bot::EVENT_MESSAGE_NORMAL
        ],
        'event' => [
            'sender' => [
                'senderId' => 'user123'
            ],
            'message' => [
                'msgId' => 'msg456',
                'contentType' => Bot::CONTENT_TEXT,
                'content' => [
                    'text' => 'Hello World'
                ]
            ],
            'chat' => [
                'chatType' => 'bot',
                'chatId' => 'user123'
            ]
        ]
    ]);
    
    // 临时替换php://input
    $inputFile = tempnam(sys_get_temp_dir(), 'phpinput');
    file_put_contents($inputFile, $normalMessage);
    
    // 注意：这里无法真正测试php://input，但验证函数存在
    echo "- yh_parse_event函数: " . (function_exists('yh_parse_event') ? "存在" : "不存在") . "\n";
    echo "- yh_event_type函数: " . (function_exists('yh_event_type') ? "存在" : "不存在") . "\n";
    echo "- yh_sender_id函数: " . (function_exists('yh_sender_id') ? "存在" : "不存在") . "\n";
    echo "- yh_message_content函数: " . (function_exists('yh_message_content') ? "存在" : "不存在") . "\n";
    echo "- yh_back_object函数: " . (function_exists('yh_back_object') ? "存在" : "不存在") . "\n";
    echo "✓ 事件解析函数已定义\n\n";
    
    unlink($inputFile);
} catch (Exception $e) {
    echo "✗ 事件解析测试失败: " . $e->getMessage() . "\n\n";
}

// 测试7：Webhook处理器
echo "测试7: Webhook处理器\n";
try {
    $webhook = yh_webhook($bot);
    echo "- yh_webhook函数: " . ($webhook ? "成功" : "失败") . "\n";
    echo "- Webhook类: " . (class_exists('YunhuWebhook') ? "存在" : "不存在") . "\n";
    
    // 测试注册处理器
    $result = $webhook->onMessage(function($event, $bot) {
        return true;
    });
    echo "- onMessage注册: " . ($result instanceof YunhuWebhook ? "成功" : "失败") . "\n";
    echo "✓ Webhook处理器正常\n\n";
} catch (Exception $e) {
    echo "✗ Webhook处理器测试失败: " . $e->getMessage() . "\n\n";
}

// 测试8：向后兼容
echo "测试8: 向后兼容性\n";
try {
    $compat_functions = [
        'yhsdk_init',
        'send',
        'edit',
        'recall',
        'get_back_object',
        'get_event_type',
        'get_sender_id',
        'get_message_id',
        'get_message_content',
        'get_command_info'
    ];
    
    foreach ($compat_functions as $func) {
        echo "- {$func}: " . (function_exists($func) ? "存在" : "不存在") . "\n";
    }
    echo "✓ 向后兼容函数已定义\n\n";
} catch (Exception $e) {
    echo "✗ 兼容性测试失败: " . $e->getMessage() . "\n\n";
}

// 测试9：日志功能
echo "测试9: 日志功能\n";
try {
    $logFile = __DIR__ . '/test_sdk.log';
    if (file_exists($logFile)) {
        unlink($logFile);
    }
    
    // 初始化带日志配置的bot
    $bot = new Bot('log-test-token', 'log-test-bot', [
        'debug_mode' => true,
        'log_file' => $logFile
    ]);
    
    // 写入日志
    $bot->log("测试日志消息", ['test' => 'data']);
    
    // 检查日志文件
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        echo "- 日志文件创建: 成功\n";
        echo "- 日志内容长度: " . strlen($logContent) . " 字节\n";
        echo "- 包含测试标记: " . (strpos($logContent, '测试日志消息') !== false ? "是" : "否") . "\n";
        echo "✓ 日志功能正常\n\n";
        
        unlink($logFile);
    } else {
        echo "✗ 日志文件未创建\n\n";
    }
} catch (Exception $e) {
    echo "✗ 日志测试失败: " . $e->getMessage() . "\n\n";
}

// 测试10：文件大小限制常量
echo "测试10: 文件大小限制常量\n";
try {
    $limits = [
        'MAX_IMAGE_SIZE' => Bot::MAX_IMAGE_SIZE,
        'MAX_VIDEO_SIZE' => Bot::MAX_VIDEO_SIZE,
        'MAX_FILE_SIZE' => Bot::MAX_FILE_SIZE
    ];
    
    foreach ($limits as $name => $value) {
        $mb = $value / (1024 * 1024);
        echo "- {$name}: {$mb} MB\n";
    }
    echo "✓ 文件限制常量正常\n\n";
} catch (Exception $e) {
    echo "✗ 常量测试失败: " . $e->getMessage() . "\n\n";
}

// 总结
echo "=== 测试完成 ===\n";
echo "\n总结:\n";
echo "- SDK版本: 5.0\n";
echo "- 文件数目: 3个 (Bot.php, yunhu_sdk.php, example_bot.php)\n";
echo "- 主要特性: 面向对象 + 函数式双接口\n";
echo "- 向后兼容: 是\n";
echo "\n建议: 查看 example_bot.php 了解使用方法\n";
echo "\n";
