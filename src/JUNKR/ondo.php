<?php

namespace JUNKR;

use JetBrains\PhpStorm\Pure;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\AsyncTask;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\world\World;

class ondo extends PluginBase implements Listener{

    public string $prefix = '§l§[온도] §r§7';

    public $db, $database;

    private static ?ondo $instance = null;

    public static function getInstance() : self{
        return static::$instance;
    }

    public function onLoad() : void{
        self::$instance = $this;
    }

    public function onDisable() : void{
        $this->save();
    }

    public function save() : void{
        $this->database->setAll($this->db);
        $this->database->save();
    }

    public int $ondo = 20;

    public function onEnable() : void{
        $this->database = new Config($this->getDataFolder() . 'job.yml', Config::YAML, [
            "farm" => []
        ]);
        $this->db = $this->database->getAll();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->registerCommand("온도");

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
            ondo::getInstance()->getOndoFromWebServer();
        }), 20 * 60 * 10);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if($command->getName() === "온도"){
            $ondo = self::getOndo();
            $sender->sendMessage("§l§a[온도] §r§7현재 온도는 §6{$ondo}도 §7입니다.");
        }
        return true;
    }

    public function getOndoFromWebServer() : void{
        $this->getServer()->getAsyncPool()->submitTask(new class() extends AsyncTask{

            public int $ondo;

            public function onRun() : void{
                $ch = curl_init(); // 리소스 초기화

                $url = 'https://www.crsbe.kr/ondo/';

                // 옵션 설정
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);   //원격 서버의 인증서가 유효한지 검사 안함

                $this->ondo = curl_exec($ch); // 데이터 요청 후 수신

                curl_close($ch);  // 리소스 해제
            }

            #[Pure] public function onCompletion() : void{
                if(isset($this->ondo) && is_numeric($this->ondo)){
                    ondo::getInstance()->ondo = $this->ondo;
                }
            }

        });
    }

    public function registerCommand($name) : void{
        $cmd = new PluginCommand($name, $this, $this);
        $cmd->setDescription($name . ' 명령어 입니다');

        Server::getInstance()->getCommandMap()->register($this->getDescription()->getName(), $cmd);
    }

    #[Pure] public static function getOndo(?World $level = null) : int{
        if($level === null){
            return self::getInstance()->ondo;
        }
        if(isset(self::getInstance()->db[$level->getFolderName()])){
            return (int) self::getInstance()->db[$level->getFolderName()];
        }

        return self::getInstance()->ondo;
    }

}
