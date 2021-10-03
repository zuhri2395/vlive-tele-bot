<?php
namespace App;

use TelegramBot\TelegramBot;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use TelegramBot\Types\CallbackQuery;

class App
{
    private TelegramBot $bot;
    private FilesystemAdapter $cache;

    function __construct(TelegramBot $bot)
    {
        $this->bot = $bot;
        $this->cache = new FilesystemAdapter('', 0, dirname(__FILE__) . '../cache');
    }

    function processUpdate()
    {
        $update = $this->bot->getWebhookUpdate();

        if(!empty($update->callback_query)) {
            $keyList = ['quality', 'subs', 'change_quality', 'change_sub', 'cancel', 'ok'];
            $originalMessage = $update->callback_query->message;
            $link = $originalMessage->entities[0]->url;

            if($this->checkLink($link)) {
                $vliveId = explode('/', $link);
                $vliveId = end($vliveId);
                $callbackData = $update->callback_query->data;

                foreach($keyList as $key) {
                    if(strpos($callbackData, $key) !== FALSE) {
                        $this->handleCallbackQuery($key, $update->callback_query);
                    }
                }
            }
        } else {
            $command = $update->message->getCommand();
            if($command == '/download') {
                $link = $update->message->getArgs()[0];

                if($this->checkLink($link)) {
                    $vliveId = explode('/', $link);
                    $vliveId = end($vliveId);
                    $cacheKey = "vlive_$vliveId";
                    $item = $this->cache->getItem($cacheKey);

                    if($item->isHit()) {
                        $json = $item->get();
                    } else {
                        exec("youtube-dl -j $link", $output);
                        $json = json_decode($output[0]);
                        $item->set($json);
                        $this->cache->save($item);
                    }

                    $buttons = [0 => []];
                    foreach($json->formats as $format) {
                        if($format->ext == 'mp4') {
                            $buttons[0][] = $this->bot->buildInlineKeyboardButton($format->height, '', "quality_$format->height");
                        }
                    }

                    $text = "[<a href='$link'>VLIVE</a>] - $json->title" . PHP_EOL;
                    $text .= 'Silahkan pilih kualitas video';

                    $this->bot->sendMessage([
                        'chat_id' => $update->message->chat->id,
                        'reply_markup' => $this->bot->buildInlineKeyBoard($buttons),
                        'parse_mode' => 'HTML',
                        'text' => $text
                    ]);
                } else {
                    $this->bot->sendMessage([
                        'chat_id' => $update->message->chat->id,
                        'text' => 'Not Supported'
                    ]);
                }
            }
        }
    }

    private function handleCallbackQuery(String $key, CallbackQuery $cbq)
    {
        $keyList = ['quality', 'subs', 'change_quality', 'change_sub', 'cancel', 'ok'];
        switch ($key) {
            case 'ok':
                $msg = $cbq->message->text;

                break;
            case 'cancel':
                $this->bot->deleteMessage($cbq->message->chat->id, $cbq->message->message_id);
                break;
        }
    }

    private function checkLink(string $link)
    {
        $re = '/^(http|https):\/\/(.*)vlive\.tv\/post\/(.*)/i';
        return preg_match($re, $link);
    }
}