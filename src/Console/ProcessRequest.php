<?php
namespace App\Console;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TelegramBot\TelegramBot;
use App\Entity\Request;

class ProcessRequest extends Command
{
    protected static $defaultName = 'app:process-request';
    protected static $defaultDescription = "Process the vlive download request";
    private EntityManager $em;
    private $rootPath;

    public function __construct()
    {
        parent::__construct();
        include_once dirname(__FILE__) . '/../../bootstrap.php';
        $this->em = $entityManager;
        $this->rootPath = dirname(__FILE__) . '/../../';
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if(file_exists($this->rootPath . 'output/.lock')) {
            return Command::SUCCESS;
        }

        file_put_contents($this->rootPath . 'output/.lock', null);

        $bot = new TelegramBot($_ENV['BOT_TOKEN'], $_ENV['BOT_DOMAIN']);
        $requestRepo = $this->em->getRepository('App\Entity\Request');
        /** @var Request|null $request */
        $request = $requestRepo->findOneBy(['file_id' => null], ['id' => 'asc']);
        if(!empty($request)) {
            $existingRequest = $requestRepo->findExistingFileId($request->getLink(), $request->getQuality(), $request->getSubs());
            if(!empty($existingRequest)) {
                $fileID = $existingRequest[0]['file_id'];
                $request->setFileId($fileID);
                $this->em->persist($request);
                $this->em->flush();

                $bot->sendVideo([
                    'chat_id' => $request->getUserId(),
                    'video' => $fileID
                ]);
                unlink($this->rootPath . 'output/.lock');
                return Command::SUCCESS;
            }

            $userId = $request->getUserId();
            $text = "[<a href='tg://user?id=$userId'>Request</a>]Download dan hardsub video - " . $request->getLink() . PHP_EOL . PHP_EOL . '#processing';
            $bot->sendMessage([
                'chat_id' => '@z_test_group',
                'parse_mode' => 'HTML',
                'text' => $text
            ]);

            $exec = $_ENV['YTDL_PATH'] . ' -f ' . $request->getQuality() . ' --write-sub --sub-lang ' . $request->getSubs() . ' --embed-subs --exec "mkdir temp && ' . $_ENV['FFMPEG_PATH'] .' -i {} -crf 28 -movflags +faststart -vf subtitles={}:force_style=\'FontName=Arial\' -acodec copy temp/{} && mv -f temp/{} {} && rm -r temp && mv {} output/twice.mp4" --restrict-filenames ' . $request->getLink();
            exec($exec);
            if(file_exists($this->rootPath . 'output/twice.mp4')) {
                $cache = new FilesystemAdapter('', 0, $this->rootPath . 'cache');
                $vliveId = explode('/', $request->getLink());
                $vliveId = end($vliveId);

                $metadata = $cache->getItem("vlive_$vliveId");
                $metadata = json_decode($metadata->get());

                $thumbUrl = $metadata->thumbnail;
                if(!empty($thumbUrl)) {
                    $ch = curl_init($thumbUrl);
                    $fp = fopen($this->rootPath . 'output/thumb.jpg', 'wb');
                    curl_setopt($ch, CURLOPT_FILE, $fp);
                    curl_setopt($ch, CURLOPT_HEADER, 0);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_exec($ch);
                    curl_close($ch);
                    fclose($fp);

                    $image = imagecreatefromjpeg($this->rootPath . 'output/thumb.jpg');
                    $imgResized = imagescale($image , 320, 320);
                    imagejpeg($imgResized, $this->rootPath . 'output/thumb.jpg');
                }

                $width = 0;
                $height = 0;
                foreach($metadata->formats as $format) {
                    if($request->getQuality() == $format->format_id) {
                        $height = $format->height;
                        $width = $format->width;
                    }
                }

                $filename = $this->rootPath . 'output/twice.mp4';
                $video = new \CURLFile($filename, mime_content_type($filename), $filename );
                $videoParam = [
                    'chat_id' => $request->getUserId(),
                    'video' => $video,
                    'duration' => $metadata->duration,
                    'width' => $width,
                    'height' => $height,
                    'supports_streaming' => true,
		    'caption' => $request->getLink()
                ];
                $filename = $this->rootPath . 'output/thumb.jpg';
                if(file_exists($filename)) {
                    $thumb = new \CURLFile($filename, mime_content_type($filename), $filename );
                    $videoParam['thumb'] = $thumb;
                }

                $message = $bot->sendVideo($videoParam);
                if($message instanceof \TelegramBot\Types\Message) {
                    $request->setFileId($message->video->file_id);
                    $this->em->persist($request);
                    $this->em->flush();

                    unlink($this->rootPath . 'output/twice.mp4');
                    if(file_exists($this->rootPath . 'output/thumb.jpg')) {
                        unlink($this->rootPath . 'output/thumb.jpg');
                    }
                    unlink($this->rootPath . 'output/.lock');

                    $userId = $request->getUserId();
                    $text = "[<a href='tg://user?id=$userId'>Request</a>]Upload dan kirim - " . $request->getLink() . PHP_EOL . 'File ID : '  . $message->video->file_id . PHP_EOL . PHP_EOL . '#upload';
                    $bot->sendMessage([
                        'chat_id' => '@z_test_group',
                        'parse_mode' => 'HTML',
                        'text' => $text
                    ]);
                }
            }
        }
        return Command::SUCCESS;
    }
}
