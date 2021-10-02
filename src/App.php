<?php
namespace App;

use TelegramBot\TelegramBot;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class App
{
    private TelegramBot $bot;

    function __construct(TelegramBot $bot)
    {
        $this->bot = $bot;
    }

    function processUpdate()
    {
        $update = $this->bot->getWebhookUpdate();

        if(!empty($update->callback_query)) {
            $null = '';
        } else {
            $command = $update->message->getCommand();
            if($command == '/download') {
                $link = $update->message->getArgs()[0];
                $re = '/^(http|https):\/\/(.*)vlive\.tv\/post\/(.*)/i';

                if(preg_match($re, $link)) {
                    $vliveId = explode('/', $link);
                    $vliveId = end($vliveId);
                    $cache = new FilesystemAdapter();
                    $cacheKey = "vlive_$vliveId";
                    $item = $cache->getItem($cacheKey);

                    if($item->isHit()) {
                        $json = $item->get();
                    } else {
                        exec("youtube-dl -j $link", $output);
                        $json = json_decode($output[0]);
                        $item->set($json);
                        $cache->save($item);
                    }

                    $buttons = [0 => []];
                    foreach($json->formats as $format) {
                        if($format->ext == 'mp4') {
                            $buttons[0][] = $this->bot->buildInlineKeyboardButton($format->height, '', $vliveId . '_format_id');
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
}