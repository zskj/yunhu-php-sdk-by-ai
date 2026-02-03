<?php
/**
 * 云湖机器人SDK - 统一OOP版本
 * 版本: 5.0
 * 基于参考Bot类重构
 */

class Bot
{
    public    $botId; //机器人云湖Id
    protected $token;  //云湖控制台 机器人发送消息配置信息处
    protected $config;  // ['debug_mode'=>false,'log_file'=>'','base_uri'=>'https://chat-go.jwzhd.com/open-apis/v1/','timeout'=>30 ]
    
    // 文件大小限制
    const MAX_IMAGE_SIZE = 10 * 1024 * 1024; // 10MB 限制图片大小
    const MAX_VIDEO_SIZE = 20 * 1024 * 1024; // 20MB 限制视频大小
    const MAX_FILE_SIZE = 20 * 1024 * 1024; // 20MB 限制文件大小
    
    /****************************************************
     *                    事件常量定义
     ****************************************************/

    /**
     * 普通消息事件
     * 当用户发送普通消息时触发
     * 事件类型: message.receive.normal
     */
    const EVENT_MESSAGE_NORMAL = 'message.receive.normal';

    /**
     * 指令消息事件
     * 当用户发送以"/"开头的指令消息时触发
     * 事件类型: message.receive.instruction
     */
    const EVENT_MESSAGE_INSTRUCTION = 'message.receive.instruction';

    /**
     * 关注机器人事件
     * 当用户关注机器人时触发
     * 事件类型: bot.followed
     */
    const EVENT_BOT_FOLLOWED = 'bot.followed';

    /**
     * 取消关注机器人事件
     * 当用户取消关注机器人时触发
     * 事件类型: bot.unfollowed
     */
    const EVENT_BOT_UNFOLLOWED = 'bot.unfollowed';

    /**
     * 加入群事件
     * 当用户加入群聊时触发
     * 事件类型: group.join
     */
    const EVENT_GROUP_JOIN = 'group.join';

    /**
     * 退出群事件
     * 当用户退出群聊时触发
     * 事件类型: group.leave
     */
    const EVENT_GROUP_LEAVE = 'group.leave';

    /**
     * 按钮事件
     * 当用户点击消息中的按钮时触发
     * 事件类型: button.report.inline
     */
    const EVENT_BUTTON_INLINE = 'button.report.inline';

    /**
     * 快捷菜单事件
     * 当用户点击聊天框上方的菜单按钮时触发
     * 事件类型: bot.shortcut.menu
     */
    const EVENT_SHORTCUT_MENU = 'bot.shortcut.menu';

    /**
     * 机器人设置消息事件
     * 当机器人在群内的设置发生变化时触发
     * 事件类型: bot.setting
     */
    const EVENT_BOT_SETTING = 'bot.setting';
    
    /****************************************************
     *                    消息类型常量
     ****************************************************/
    /**
     * 文本消息
     * 普通文本格式的消息
     */
    const CONTENT_TEXT = 'text';

    /**
     * 图片消息
     * 包含图片的消息
     */
    const CONTENT_IMAGE = 'image';

    /**
     * Markdown消息
     * 支持Markdown格式的消息
     */
    const CONTENT_MARKDOWN = 'markdown';

    /**
     * 文件消息
     * 包含文件的消息
     */
    const CONTENT_FILE = 'file';

    /**
     * 视频消息
     * 包含视频的消息
     */
    const CONTENT_VIDEO = 'video';

    /**
     * 语音消息
     * 包含语音的消息
     */
    const CONTENT_AUDIO = 'audio';

    /**
     * HTML消息
     * 支持HTML格式的消息
     */
    const CONTENT_HTML = 'html';

    /**
     * 表情消息
     * 包含表情的消息
     */
    const CONTENT_EXPRESSION = 'expression';

    /**
     * 帖子消息
     * 富文本格式的帖子消息
     */
    const CONTENT_POST = 'post';

    /**
     * 表单消息
     * 自定义指令的表单消息
     */
    const CONTENT_FORM = 'form';

    /**
     * 语音通话消息
     * 语音通话相关的消息
     */
    const CONTENT_AUDIO_CALL = 'audioCall';

    /**
     * 视频通话消息
     * 视频通话相关的消息
     */
    const CONTENT_VIDEO_CALL = 'videoCall';

    /**
     * 未知消息
     * 无法识别的消息类型
     */
    const CONTENT_UNKNOWN = 'unknown';

    /****************************************************
     *                    聊天类型常量
     ****************************************************/

    /**
     * 群聊类型
     * 消息发生在群聊环境中
     */
    const RECV_TYPE_GROUP = 'group'; // 接收者为群聊
    const RECV_TYPE_USER = 'user'; // 接收者为用户
    const CHAT_TYPE_GROUP = 'group'; // 聊天对象群聊
    const CHAT_TYPE_BOT = 'bot';// 聊天对象机器人

    /****************************************************
     *                    用户级别常量
     ****************************************************/
    /**
     * 群主
     * 用户在群内的身份为群主
     */
    const USER_LEVEL_OWNER = 'owner';
    /**
     * 管理员
     * 用户在群内的身份为管理员
     */
    const USER_LEVEL_ADMIN = 'administrator';

    /**
     * 普通成员
     * 用户在群内的身份为普通成员
     */
    const USER_LEVEL_MEMBER = 'member';

    /**
     * 未知身份
     * 用户身份无法识别
     */
    const USER_LEVEL_UNKNOWN = 'unknown';

    /**
     * 构造函数
     * @param string $token 机器人令牌
     * @param string $botId 机器人ID
     * @param array $config 配置选项 (可选)
     */
    public function __construct(string $token, string $botId, array $config = [])
    {
        $this->token  = $token;
        $this->botId  = $botId;
        $this->config = array_merge([
            'debug_mode' => false,
            'log_file'   => __DIR__ . '/bot_' . substr($token, 0, 8) . '_action.log',
            'base_uri'   => 'https://chat-go.jwzhd.com/open-apis/v1/',
            'timeout'    => 30,
        ], $config);
    }

    /**
     * 发送消息
     * @param string $recvId 接收者ID
     * @param string $recvType 接收者类型 (user/group)
     * @param string $contentType 消息类型
     * @param mixed $content 消息内容
     * @param array|null $buttons 按钮数组 (可选)
     * @param string|null $parentId 父消息ID (可选)
     * @return array API响应
     */
    public function send(
        string $recvId,
        string $recvType,
        string $contentType,
               $content,
        ?array $buttons = null,
        ?string $parentId = null
    ): array {
        $payload = $this->buildMessagePayload($recvId, $recvType, $contentType, $content, $buttons, $parentId);
        return $this->request('bot/send', $payload);
    }

    /**
     * 批量发送消息
     * @param array $recvIds 接收者ID数组
     * @param string $recvType 接收者类型
     * @param string $contentType 消息类型
     * @param mixed $content 消息内容
     * @param array|null $buttons 按钮数组 (可选)
     * @param string|null $parentId 父消息ID (可选)
     * @return array API响应
     */
    public function batchSend(
        array $recvIds,
        string $recvType,
        string $contentType,
        $content,
        ?array $buttons = null,
        ?string $parentId = null
    ): array {
        $payload = $this->buildMessagePayload($recvIds, $recvType, $contentType, $content, $buttons, $parentId, true);
        return $this->request('bot/batch_send', $payload);
    }

    /**
     * 流式发送消息
     * @param string $recvId 接收者ID
     * @param string $recvType 接收者类型
     * @param string $contentType 内容类型
     * @param \Generator $generator 数据生成器
     * @param int $chunkDelay 数据块延迟(毫秒)
     * @return array API响应
     */
    public function sendStream(
        string $recvId,
        string $recvType,
        string $contentType,
        \Generator $generator,
        int $delayMs = 1000
    ): array {
        $url = $this->config['base_uri'] . "bot/send-stream?token={$this->token}&recvId={$recvId}&recvType={$recvType}&contentType={$contentType}";

        $headers = [
            'Transfer-Encoding: chunked',
            'Content-Type: text/plain; charset=utf-8',
        ];

        $fp = fopen('php://temp', 'w+');
        foreach ($generator as $chunk) {
            fwrite($fp, $chunk);
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }
        rewind($fp);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_UPLOAD         => true,
            CURLOPT_INFILE         => $fp,
            CURLOPT_INFILESIZE     => -1,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->config['timeout'],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($error) {
            throw new \RuntimeException("CURL Error: $error");
        }

        return $this->parseResponse($response, $status);
    }


    /**
     * 简单的流式发送文本消息的快捷方法
     *
     * @param string $recvId 接收者ID
     * @param string $recvType 接收者类型
     * @param array $messages 要发送的消息数组
     * @param int $delayMs 消息块之间的延迟毫秒数 (默认1秒)
     * @return array API响应
     */
    public function sendStreamText(
        string $recvId,
        string $recvType,
        array $messages,
        int $delayMs = 1000
    ): array {
        $generator = function () use ($messages) {
            foreach ($messages as $msg) {
                yield $msg . "\n";
            }
        };

        return $this->sendStream($recvId, $recvType, self::CONTENT_TEXT, $generator(), $delayMs);
    }

    /**
     * 编辑消息
     * @param string $msgId 消息ID
     * @param string $recvId 接收者ID
     * @param string $recvType 接收者类型
     * @param string $contentType 消息类型
     * @param mixed $content 消息内容
     * @param array|null $buttons 按钮数组 (可选)
     * @return array API响应
     */
    public function edit(
        string $msgId,
        string $recvId,
        string $recvType,
        string $contentType,
               $content,
        ?array $buttons = null
    ): array {
        $payload = $this->buildMessagePayload($recvId, $recvType, $contentType, $content, $buttons);
        $payload['msgId'] = $msgId;
        return $this->request('bot/edit', $payload);
    }

    /**
     * 撤回消息
     * @param string $msgId 消息ID
     * @param string $chatId 聊天ID
     * @param string $chatType 聊天类型
     * @return array API响应
     */
    public function recall(string $msgId, string $chatId, string $chatType): array
    {
        return $this->request('bot/recall', [
            'msgId'    => $msgId,
            'chatId'   => $chatId,
            'chatType' => $chatType
        ]);
    }

    /**
     * 获取消息列表
     *
     * 此方法用于获取指定聊天中的消息记录
     *
     * @param string $chatId 聊天ID（用户ID或群组ID）
     * @param string $chatType 聊天类型（Bot::CHAT_TYPE_BOT 或 Bot::CHAT_TYPE_GROUP）
     * @param string|null $messageId 消息ID（可选，作为查询的定位点）
     * @param int|null $before 在此消息ID之前获取的消息数量
     * @param int|null $after 在此消息ID之后获取的消息数量
     * @return array API响应
     */
    public function getMessages(
        string $chatId,
        string $chatType,
        ?string $messageId = null,
        ?int $before = null,
        ?int $after = null
    ): array {
        if (!in_array($chatType, [self::CHAT_TYPE_BOT, self::CHAT_TYPE_GROUP])) {
            throw new \InvalidArgumentException("无效的聊天类型: {$chatType}");
        }

        $query = http_build_query(array_filter([
            'token'       => $this->token,
            'chat-id'     => $chatId,
            'chat-type'   => $chatType,
            'message-id'  => $messageId,
            'before'      => $before,
            'after'       => $after,
        ], function ($v) { return $v !== null; }));

        $url = $this->config['base_uri'] . "messages?{$query}";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->config['timeout'],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("CURL Error: $error");
        }

        return $this->parseResponse($response, $status);
    }



    /**
     * 上传图片文件
     *
     * 此方法用于上传图片文件到聊天平台，返回可用的图片URL
     *
     * @param string $imagePath 本地图片文件路径
     * @return array API响应数据
     */
    public function uploadImage(string $imagePath): array
    {
        if (!file_exists($imagePath)) {
            throw new \InvalidArgumentException("图片不存在: {$imagePath}");
        }

        if (filesize($imagePath) > self::MAX_IMAGE_SIZE) {
            throw new \InvalidArgumentException("图片太大，超过10MB");
        }

        $mimeType = $this->getMimeType($imagePath);
        $url = $this->config['base_uri'] . 'image/upload?token=' . $this->token;

        $postFields = [
            'image' => new \CURLFile($imagePath, $mimeType, basename($imagePath))
        ];

        return $this->curlPost($url, $postFields);
    }

    /**
     * 上传视频文件
     *
     * 此方法用于上传视频文件到聊天平台，返回可用的视频信息
     *
     * @param string $videoPath 本地视频文件路径
     * @return array API响应数据
     */
    public function uploadVideo(string $videoPath): array
    {
        if (!file_exists($videoPath)) {
            throw new \InvalidArgumentException("视频不存在: {$videoPath}");
        }

        if (filesize($videoPath) > self::MAX_VIDEO_SIZE) {
            throw new \InvalidArgumentException("视频太大，超过20MB");
        }

        $mimeType = $this->getMimeType($videoPath);
        $url = $this->config['base_uri'] . 'video/upload?token=' . $this->token;

        $postFields = [
            'video' => new \CURLFile($videoPath, $mimeType, basename($videoPath))
        ];

        return $this->curlPost($url, $postFields);
    }

    /**
     * 上传文件
     *
     * 此方法用于上传视频文件到聊天平台，返回可用的视频信息
     *
     * @param string $filePath 本地视频文件路径
     * @return array API响应数据
     */
    public function uploadFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("文件不存在: {$filePath}");
        }

        if (filesize($filePath) > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException("文件太大，超过20MB");
        }

        $mimeType = $this->getMimeType($filePath);
        $url = $this->config['base_uri'] . 'file/upload?token=' . $this->token;

        $postFields = [
            'file' => new \CURLFile($filePath, $mimeType, basename($filePath))
        ];

        return $this->curlPost($url, $postFields);
    }


    /**
     * 设置用户或群成员看板
     *
     * @param string $chatId 接收消息对象ID（用户ID或群ID）
     * @param string $chatType 接收对象类型（user 或 group）
     * @param string $contentType 消息类型（text、markdown、html）
     * @param string $content 内容文本
     * @param string|null $memberId 群成员用户ID，仅 chatType 为 group 时使用
     * @param int|null $expireTime 过期时间的时间戳，单位：秒，0 表示不过期
     * @return array API响应
     */
    public function setBoard(
        string $chatId,
        string $chatType,
        string $contentType,
        string $content,
        ?string $memberId = null,
        ?int $expireTime = null
    ): array {
        $payload = [
            'chatId'      => $chatId,
            'chatType'    => $chatType,
            'contentType' => $contentType,
            'content'     => $content
        ];
        if ($memberId !== null && $chatType === self::CHAT_TYPE_GROUP) {
            $payload['memberId'] = $memberId;
        }
        if ($expireTime !== null) {
            $payload['expireTime'] = $expireTime;
        }
        return $this->request('bot/board', $payload);
    }

    /**
     * 设置全局看板（对所有用户/群生效）
     *
     * @param string $contentType 消息类型（text、markdown、html）
     * @param string $content 内容文本
     * @param int|null $expireTime 过期时间的时间戳，单位：秒，0 表示不过期
     * @return array API响应
     */
    public function setGlobalBoard(string $contentType, string $content, ?int $expireTime = null): array
    {
        $payload = compact('contentType', 'content');
        if ($expireTime !== null) {
            $payload['expireTime'] = $expireTime;
        }
        return $this->request('bot/board-all', $payload);
    }

    /**
     * 取消用户或群成员看板
     *
     * @param string $chatId 接收看板对象ID（用户ID或群ID）
     * @param string $chatType 接收对象类型（user 或 group）
     * @param string|null $memberId 群成员用户ID，仅 chatType 为 group 时有效
     * @return array API响应
     */
    public function dismissBoard(string $chatId, string $chatType, ?string $memberId = null): array
    {
        $payload = compact('chatId', 'chatType');
        if ($memberId && $chatType === self::CHAT_TYPE_GROUP) {
            $payload['memberId'] = $memberId;
        }
        return $this->request('bot/board-dismiss', $payload);
    }

    /**
     * 取消全局看板（清除所有用户默认展示内容）
     * @return array API响应
     */
    public function dismissGlobalBoard(): array
    {
        return $this->request('bot/board-all-dismiss');
    }

    // ================== 内部方法 ==================

    /**
     * 执行 POST 请求（JSON）
     */
    protected function request(string $endpoint, array $data = []): array
    {
        $url = $this->config['base_uri'] . $endpoint . '?token=' . $this->token;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json; charset=utf-8'
            ],
            CURLOPT_TIMEOUT        => $this->config['timeout'],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);
        if ($error) {
            throw new \RuntimeException("CURL Error: $error");
        }

        return $this->parseResponse($response, $status);
    }

    /**
     * 通用 cURL POST（用于文件上传）
     */
    protected function curlPost(string $url, array $postFields): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->config['timeout'],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);
        if ($error) {
            throw new \RuntimeException("上传失败: $error");
        }
        return $this->parseResponse($response, $status);
    }
    
    /**
     * 解析响应
     */
    protected function parseResponse(string $response, int $status): array
    {
        $data = json_decode($response, true) ?: [];

        if ($status >= 400) {
            $msg = $data['message'] ?? '未知错误';
            throw new \RuntimeException("API错误 [{$status}]: {$msg}");
        }
        return $data;
    }

    /**
     * 构建消息负载
     */
    protected function buildMessagePayload(
        $recvId,
        string $recvType,
        string $contentType,
        $content,
        ?array $buttons = null,
        ?string $parentId = null,
        bool $isBatch = false
    ): array {
        $payload = [
            'recvType'    => $recvType,
            'contentType' => $contentType,
            'content'     => [],
            'parentId'    => $parentId
        ];

        if ($isBatch) {
            $payload['recvIds'] = $recvId;
        } else {
            $payload['recvId'] = $recvId;
        }

        switch ($contentType) {
            case self::CONTENT_TEXT:
            case self::CONTENT_MARKDOWN:
            case self::CONTENT_HTML:
                $payload['content']['text'] = is_array($content) ? ($content['text'] ?? '') : $content;
                if (is_array($content) && isset($content['at'])) {
                    $payload['content']['at'] = $content['at'];
                }
                break;

            case self::CONTENT_IMAGE:
                if ($this->isUrl($content)) {
                    $payload['content']['imageUrl'] = $content;
                } else {
                    $payload['content']['imageKey'] = $content;
                }
                break;

            case self::CONTENT_FILE:
                $payload['content'] = [
                    'fileName' => $content['name'] ?? '',
                    'fileUrl'  => $content['url'] ?? ''
                ];
                break;
                
            case self::CONTENT_VIDEO:
                $payload['content'] = [
                    'videoKey' => $content
                ];
                break;
                
            default:
                throw new \InvalidArgumentException("不支持的 contentType: {$contentType}");
        }
        if ($buttons) {
            $payload['content']['buttons'] = $buttons;
        }
        return $payload;
    }
    
    /**
     * 判断是否为 URL
     */
    protected function isUrl(string $input): bool
    {
        return filter_var($input, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * 获取 MIME 类型
     */
    protected function getMimeType(string $path): string
    {
        $mimeTypes = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'bmp' => 'image/bmp', 'webp' => 'image/webp',
            'mp4' => 'video/mp4', 'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo', 'mkv' => 'video/x-matroska', 'webm' => 'video/webm',
            'pdf' => 'application/pdf', 'doc' => 'application/msword',
            'zip' => 'application/zip', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain'
        ];

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return $mimeTypes[$ext] ?? 'application/octet-stream';
    }

    /**
     * 日志记录
     */
    public function log(string $message, array $context = []): void
    {
        if ($this->config['debug_mode']) {
            $log = date('Y-m-d H:i:s') . " | {$message} | " . json_encode($context, JSON_UNESCAPED_UNICODE) . "\n";
            file_put_contents($this->config['log_file'], $log, FILE_APPEND);
        }
    }

}

/**
 * Bot管理器 - 支持多机器人实例
 */
class BotManager
{
    private static $instances = [];
    private static $currentBot = null;
    
    /**
     * 创建或获取机器人实例
     */
    public static function getBot(string $token, string $botId = '', array $config = [])
    {
        $key = $token . '_' . $botId;
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new Bot($token, $botId, $config);
        }
        
        self::$currentBot = self::$instances[$key];
        return self::$instances[$key];
    }
    
    /**
     * 获取所有机器人实例
     */
    public static function getAllBots(): array
    {
        return self::$instances;
    }
    
    /**
     * 设置当前活跃机器人
     */
    public static function setCurrentBot(string $token, string $botId = ''): bool
    {
        $key = $token . '_' . $botId;
        if (isset(self::$instances[$key])) {
            self::$currentBot = self::$instances[$key];
            return true;
        }
        return false;
    }
    
    /**
     * 获取当前活跃机器人
     */
    public static function getCurrentBot()
    {
        return self::$currentBot;
    }
}
