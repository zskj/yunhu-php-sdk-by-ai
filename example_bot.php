<?php
/**
 * 🤖 云湖机器人示例 - 使用新版SDK
 * 
 * 本示例展示了如何使用新版SDK的两种编程风格：
 * 1. 面向对象风格（推荐）
 * 2. 函数式风格（兼容旧版）
 * 
 * @version 2.0
 */

// 引入SDK
require_once __DIR__ . '/yunhu_sdk.php';

/**
 * ==================== 使用方式1：面向对象风格 ====================
 * 推荐使用这种方式，代码更清晰，易于维护
 */

class MyBot
{
    private $bot;
    private $supportedCommands = [2215, 2247]; // 帮助、版本查询
    
    public function __construct(string $token, string $botId)
    {
        $this->bot = new Bot($token, $botId, [
            'debug_mode' => true,
            'log_file' => __DIR__ . '/mybot.log'
        ]);
    }
    
    public function handleWebhook()
    {
        // 解析事件
        $eventData = yh_parse_event();
        if (!$eventData) {
            return;
        }
        
        $eventType = yh_event_type();
        $this->bot->log("收到事件: {$eventType}");
        
        // 根据事件类型分发处理
        switch ($eventType) {
            case Bot::EVENT_MESSAGE_INSTRUCTION:
                $this->handleCommand();
                break;
                
            case Bot::EVENT_MESSAGE_NORMAL:
                $this->handleMessage();
                break;
                
            case Bot::EVENT_BUTTON_INLINE:
                $this->handleButton();
                break;
                
            case Bot::EVENT_BOT_FOLLOWED:
                $this->handleFollow();
                break;
        }
    }
    
    private function handleCommand()
    {
        $cmd = yh_command_info();
        $content = yh_message_content();
        $back = yh_back_object();
        
        if (!$cmd || !isset($cmd['commandId'])) {
            return;
        }
        
        $commandId = intval($cmd['commandId']);
        $this->bot->log("处理指令: {$commandId}", ['content' => $content]);
        
        // 检查是否支持的指令
        if (!in_array($commandId, $this->supportedCommands)) {
            $this->bot->log("不支持的指令ID: {$commandId}");
            return;
        }
        
        // 发送加载提示
        $loading = $this->bot->send(
            $back['id'], 
            $back['type'], 
            Bot::CONTENT_HTML,
            '<div style="padding:10px;text-align:center;">⏳ 正在查询，请稍候...</div>'
        );
        
        $msgId = $loading['data']['messageInfo']['msgId'] ?? null;
        
        try {
            switch ($commandId) {
                case 2215: // 帮助
                    $this->sendHelp($back, $msgId);
                    break;
                    
                case 2247: // 版本查询
                    $this->sendVersionInfo($back, $msgId);
                    break;
            }
        } catch (\Exception $e) {
            $this->bot->log("处理失败: " . $e->getMessage(), ['error' => $e->getTraceAsString()]);
            $this->sendError($back, $msgId, $e->getMessage());
        }
    }
    
    private function handleMessage()
    {
        $content = yh_message_content();
        $senderId = yh_sender_id();
        $back = yh_back_object();
        
        $this->bot->log("收到消息: {$content}", ['sender' => $senderId]);
        
        // 简单 echo 机器人
        if (str_contains($content, '你好')) {
            $this->bot->send(
                $back['id'],
                $back['type'],
                Bot::CONTENT_TEXT,
                "你好！我收到了你的消息：{$content}"
            );
        }
    }
    
    private function handleButton()
    {
        $value = yh_button_value();
        $back = yh_back_object();
        
        $this->bot->log("按钮点击: {$value}");
        
        // 回复按钮点击
        $this->bot->send(
            $back['id'],
            $back['type'],
            Bot::CONTENT_TEXT,
            "你点击了按钮，值是: {$value}"
        );
    }
    
    private function handleFollow()
    {
        $senderId = yh_sender_id();
        $this->bot->log("新用户关注: {$senderId}");
        
        // 发送欢迎消息
        $this->bot->send(
            $senderId,
            Bot::RECV_TYPE_USER,
            Bot::CONTENT_TEXT,
            "欢迎使用云湖机器人！发送 /help 查看帮助。"
        );
    }
    
    private function sendHelp(array $back, ?string $msgId = null)
    {
        $helpText = <<<HELP
**云湖机器人使用帮助**

支持以下指令：
- `/help` - 显示此帮助信息
- `/version` - 查看版本信息
- 发送"你好" - 我会回复你的消息

其他功能：
- 支持按钮点击事件
- 支持用户关注事件
- 支持消息编辑和撤回
HELP;
        
        if ($msgId) {
            $this->bot->edit($msgId, $back['id'], $back['type'], Bot::CONTENT_MARKDOWN, $helpText);
        } else {
            $this->bot->send($back['id'], $back['type'], Bot::CONTENT_MARKDOWN, $helpText);
        }
    }
    
    private function sendVersionInfo(array $back, ?string $msgId = null)
    {
        $version = '1.0.0';
        $time = date('Y-m-d H:i:s');
        
        $versionHtml = <<<HTML
<div style="padding:15px; border-radius:10px; max-width:300px; background:#ffffff; border:1px solid #e0e0e0;">
  <h2 style="margin:0 0 12px 0; color:#333; font-size:18px; font-weight:bold; text-align:center;">机器人版本信息</h2>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><strong>当前版本:</strong> v{$version}</p>
  <p style="margin:0 0 5px 0; color:#555; font-size:14px;"><strong>SDK版本:</strong> 5.0</p>
  <p style="margin:0 0 12px 0; color:#555; font-size:14px;"><strong>查询时间:</strong> {$time}</p>
  <p style="margin:0; font-size:12px; color:#888; text-align:center;">Powered by 云湖 SDK v5.0</p>
</div>
HTML;
        
        if ($msgId) {
            $this->bot->edit($msgId, $back['id'], $back['type'], Bot::CONTENT_HTML, $versionHtml);
        } else {
            $this->bot->send($back['id'], $back['type'], Bot::CONTENT_HTML, $versionHtml);
        }
    }
    
    private function sendError(array $back, ?string $msgId, string $message)
    {
        $errorHtml = <<<HTML
<div style="padding:15px; border-radius:10px; max-width:300px; background:#fff5f5; border:1px solid #ffcccc; color:#d32f2f;">
  <h2 style="margin:0 0 12px 0; font-size:18px; font-weight:bold; text-align:center;">❌ 处理失败</h2>
  <p style="margin:0 0 12px 0; font-size:14px;"><strong>错误详情:</strong><br>{$message}</p>
  <p style="margin:0; font-size:12px; color:#888;"><span style="color:#d32f2f;">⏰</span> 发生时间: {:date('Y-m-d H:i:s')}</p>
</div>
HTML;
        
        if ($msgId) {
            $this->bot->edit($msgId, $back['id'], $back['type'], Bot::CONTENT_HTML, $errorHtml);
        } else {
            $this->bot->send($back['id'], $back['type'], Bot::CONTENT_HTML, $errorHtml);
        }
    }
}

/**
 * ==================== 使用方式2：函数式风格 ====================
 * 这种方式兼容旧版SDK的使用方式，适合简单场景
 */

function run_functional_bot()
{
    // 初始化机器人
    yh_init('your-token-here', 'your-bot-id-here', [
        'debug_mode' => true,
        'log_file' => __DIR__ . '/functional_bot.log'
    ]);
    
    // 解析事件
    if (!yh_parse_event()) {
        return;
    }
    
    // 获取事件类型
    $eventType = yh_event_type();
    yh_log("收到事件: {$eventType}");
    
    // 分发处理
    switch ($eventType) {
        case Bot::EVENT_MESSAGE_INSTRUCTION:
            handle_functional_command();
            break;
            
        case Bot::EVENT_MESSAGE_NORMAL:
            handle_functional_message();
            break;
            
        case Bot::EVENT_BUTTON_INLINE:
            handle_functional_button();
            break;
    }
}

function handle_functional_command()
{
    $cmd = yh_command_info();
    $content = yh_message_content();
    $back = yh_back_object();
    
    if (!$cmd) {
        return;
    }
    
    $commandId = intval($cmd['commandId']);
    $supportedCommands = [2215, 2247];
    
    if (!in_array($commandId, $supportedCommands)) {
        return;
    }
    
    // 发送加载提示
    $loading = yh_send_html($back, '<div style="padding:10px;text-align:center;">⏳ 正在处理...</div>');
    $msgId = $loading['data']['messageInfo']['msgId'] ?? null;
    
    try {
        switch ($commandId) {
            case 2215: // 帮助
                $helpText = "**功能列表**\n\n- 查看帮助\n- 查询版本\n- 普通消息回复\n- 按钮交互";
                if ($msgId) {
                    yh_edit($msgId, $back, Bot::CONTENT_MARKDOWN, $helpText);
                } else {
                    yh_send_markdown($back, $helpText);
                }
                break;
                
            case 2247: // 版本查询
                $versionText = "**当前版本**: v2.0\n**SDK版本**: 5.0\n**时间**: " . date('Y-m-d H:i:s');
                if ($msgId) {
                    yh_edit($msgId, $back, Bot::CONTENT_MARKDOWN, $versionText);
                } else {
                    yh_send_markdown($back, $versionText);
                }
                break;
        }
    } catch (\Exception $e) {
        yh_log("错误: " . $e->getMessage(), ['error' => $e->getTraceAsString()]);
        
        $errorHtml = '<div style="padding:10px; color:red;">❌ 处理失败: ' . htmlspecialchars($e->getMessage()) . '</div>';
        if ($msgId) {
            yh_edit($msgId, $back, Bot::CONTENT_HTML, $errorHtml);
        } else {
            yh_send_html($back, $errorHtml);
        }
    }
}

function handle_functional_message()
{
    $content = yh_message_content();
    $back = yh_back_object();
    
    // 简单关键词回复
    if (stripos($content, '你好') !== false) {
        yh_send_text($back, "你好！我收到了你的消息：{$content}");
    } elseif (stripos($content, '时间') !== false) {
        yh_send_text($back, "当前时间是：" . date('Y-m-d H:i:s'));
    }
}

function handle_functional_button()
{
    $value = yh_button_value();
    $back = yh_back_object();
    
    // 回复按钮点击
    yh_send_text($back, "你点击了按钮，值是：{$value}");
}

/**
 * ==================== 主入口 ====================
 */

// 根据参数选择运行方式
$style = $_GET['style'] ?? 'oop'; // 默认使用OOP方式

if ($style === 'oop') {
    // 面向对象方式
    $bot = new MyBot('your-token-here', 'your-bot-id-here');
    $bot->handleWebhook();
} else {
    // 函数式方式
    run_functional_bot();
}
