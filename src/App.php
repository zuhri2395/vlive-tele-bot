<?php
namespace App;

use App\Entity\Request;
use Doctrine\ORM\EntityManager;
use TelegramBot\TelegramBot;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use TelegramBot\Types\CallbackQuery;

class App
{
    private TelegramBot $bot;
    private FilesystemAdapter $cache;
    private EntityManager $em;

    function __construct(TelegramBot $bot)
    {
        include_once dirname(__FILE__) . '/../bootstrap.php';
        $this->em = $entityManager;

        $this->bot = $bot;
        $this->cache = new FilesystemAdapter('', 0, dirname(__FILE__) . '/../cache');
    }

    function processUpdate()
    {
        $update = $this->bot->getWebhookUpdate();

        if(!empty($update->callback_query)) {
            $keyList = $this->getListOfQueryKey();
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
        } else if(!empty($update->message)) {
            $command = $update->message->getCommand();
            if($command == '/download') {
                $link = $update->message->getArgs()[0];

                if($this->checkLink($link)) {
                    $vliveId = explode('/', $link);
                    $vliveId = end($vliveId);
                    $cacheMetadataKey = "vlive_$vliveId";
                    $cacheDataKey = "vlive_$vliveId" . '_data';

                    $item = $this->cache->getItem($cacheMetadataKey);
                    if($item->isHit()) {
                        $metadata = $item->get();
                    } else {
                        exec("youtube-dl -j $link", $output);
                        $metadata = $output[0];
                        $item->set($metadata);
                        $this->cache->save($item);
                    }

                    $vliveData = $this->cache->getItem($cacheDataKey);
                    $vliveData->set(['quality' => '', 'subtitle' => '']);
                    $this->cache->save($vliveData);

                    $metadata = json_decode($metadata);

                    $buttons = $this->generateInlineKeyboardButton('quality', $metadata);

                    $text = "[<a href='$link'>VLIVE</a>] - $metadata->title" . PHP_EOL . PHP_EOL;
                    $text .= 'Silahkan pilih resolusi';

                    $this->bot->sendMessage([
                        'chat_id' => $update->message->chat->id,
                        'reply_markup' => $this->bot->buildInlineKeyBoard($buttons),
                        'reply_to_message_id' => $update->message->message_id,
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
        $link = $cbq->message->entities[0]->url;
        $vliveId = explode('/', $link);
        $vliveId = end($vliveId);

        switch ($key) {
            case 'ok':
                $metadataCache = $this->cache->getItem("vlive_$vliveId");
                $metadata = json_decode($metadataCache->get());

                $dataCache = $this->cache->getItem("vlive_$vliveId" . '_data');
                $data = $dataCache->get();

                $quality = '';

                foreach($metadata->formats as $format) {
                    if($format->ext == 'mp4' && $format->height == $data['quality']) {
                        $quality = $format->format_id;
                    }
                }

                $request = new Request();
                $request->setLink($link);
                $request->setQuality($quality);
                $request->setSubs($data['subtitle']);
                $request->setUserId($cbq->from->id);

                $this->em->persist($request);
                $this->em->flush();

                $this->bot->editMessageText([
                    'chat_id' => $cbq->from->id,
                    'message_id' => $cbq->message->message_id,
                    'parse_mode' => 'HTML',
                    'text' => 'Request dimasukkan ke dalam antrian'
                ]);

                break;
            case 'cancel':
                $this->bot->deleteMessage($cbq->message->chat->id, $cbq->message->message_id);
                break;
            case 'change_quality':
            case 'change_subtitle':
                $metadataCache = $this->cache->getItem("vlive_$vliveId");
                $metadata = json_decode($metadataCache->get());

                $item = $this->cache->getItem("vlive_$vliveId" . '_data');
                $data = $item->get();

                $explode = explode('change_', $key);

                $buttons = $this->generateInlineKeyboardButton($explode[1], $metadata);

                $text = "[<a href='$link'>VLIVE</a>] - $metadata->title" . PHP_EOL . PHP_EOL;
                $text .= "Kualitas = " . $data['quality'] . PHP_EOL;
                $text .= "Subtitle = " . $data['subtitle'] . PHP_EOL . PHP_EOL;
                $text .= 'Silahkan pilih ' . $explode[1] == 'quality' ? 'kualitas' : 'subtitle';

                $this->bot->editMessageText([
                    'chat_id' => $cbq->from->id,
                    'message_id' => $cbq->message->message_id,
                    'reply_markup' => $this->bot->buildInlineKeyBoard($buttons),
                    'parse_mode' => 'HTML',
                    'text' => $text
                ]);
                break;
            case 'quality':
                $metadataCache = $this->cache->getItem("vlive_$vliveId");
                $metadata = json_decode($metadataCache->get());

                $item = $this->cache->getItem("vlive_$vliveId" . '_data');
                $data = $item->get();

                $data['quality'] = str_replace('quality_', null, $cbq->data);
                $item->set($data);
                $this->cache->save($item);

                $buttons = $this->generateInlineKeyboardButton('subtitle', $metadata);

                $text = "[<a href='$link'>VLIVE</a>] - $metadata->title" . PHP_EOL . PHP_EOL;
                $text .= "Kualitas = " . $data['quality'] . PHP_EOL . PHP_EOL;
                $text .= 'Silahkan pilih subtitle';

                $this->bot->editMessageText([
                    'chat_id' => $cbq->from->id,
                    'message_id' => $cbq->message->message_id,
                    'reply_markup' => $this->bot->buildInlineKeyBoard($buttons),
                    'parse_mode' => 'HTML',
                    'text' => $text
                ]);
                break;
            case 'subtitle':
                $metadataCache = $this->cache->getItem("vlive_$vliveId");
                $metadata = json_decode($metadataCache->get());

                $item = $this->cache->getItem("vlive_$vliveId" . '_data');
                $data = $item->get();

                $subtitle = str_replace('subtitle_', null, $cbq->data);
                $data['subtitle'] = $subtitle;
                $item->set($data);
                $this->cache->save($item);

                $buttons = $this->generateInlineKeyboardButton('confirmation', $metadata);

                $text = "[<a href='$link'>VLIVE</a>] - $metadata->title" . PHP_EOL . PHP_EOL;
                $text .= "Kualitas = " . $data['quality'] . PHP_EOL;
                $text .= "Subtitle = " . $subtitle;

                $this->bot->editMessageText([
                    'chat_id' => $cbq->from->id,
                    'message_id' => $cbq->message->message_id,
                    'reply_markup' => $this->bot->buildInlineKeyBoard($buttons),
                    'parse_mode' => 'HTML',
                    'text' => $text
                ]);
                break;
        }
    }

    private function getListOfQueryKey()
    {
        return ['quality', 'subtitle', 'change_quality', 'change_sub', 'cancel', 'ok'];
    }

    private function generateInlineKeyboardButton(string $type, $metadata)
    {
        $buttons = [];
        $row = 0;
        $idx = 0;

        switch ($type) {
            case 'quality' :
                foreach($metadata->formats as $format) {
                    if($format->ext == 'mp4') {
                        $buttons[$row][] = $this->bot->buildInlineKeyboardButton($format->height, '', "quality_$format->height");
                        $idx++;
                        if($idx == 3) {
                            $row++;
                            $idx = 0;
                        }
                    }
                }
                break;
            case 'subtitle':
                foreach($metadata->subtitles as $key => $subtitle) {
                    $buttons[$row][] = $this->bot->buildInlineKeyboardButton($key, '', "subtitle_$key");
                    $idx++;
                    if($idx == 3) {
                        $row++;
                        $idx = 0;
                    }
                }
                break;
            case 'confirmation':
                $buttons = [
                    [
                        $this->bot->buildInlineKeyboardButton('Ubah Kualitas', '', 'change_quality'),
                        $this->bot->buildInlineKeyboardButton('Ubah Subtitle', '', 'change_sub'),
                    ],
                    [
                        $this->bot->buildInlineKeyboardButton('OK', '', 'ok'),
                    ],
                    [
                        $this->bot->buildInlineKeyboardButton('Batal', '', 'cancel'),
                    ]
                ];
                break;
            default:
                break;
        }

        return $buttons;
    }

    private function checkLink(string $link)
    {
        $re = '/^(http|https):\/\/(.*)vlive\.tv\/(post|video)\/(.*)/i';
        return preg_match($re, $link);
    }
}