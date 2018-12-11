<?php
/**
 * @name Boss
 * @author alvin0319
 * @main alvin0319\Boss
 * @version 1.0.0
 * @api 4.0.0
 */
declare(strict_types=1);
namespace alvin0319;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\entity\Entity;
use pocketmine\entity\Monster;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\Config;

/**
 * Class Boss
 * @package alvin0319
 */
class Boss extends PluginBase{

    private $config;

    public $db;

    public $xyz;

    public $x;

    public function onEnable(){
        $this->config = new Config($this->getDataFolder() . 'Config.yml', Config::YAML);
        $this->db = $this->config->getAll();
        $this->xyz = new Config($this->getDataFolder() . 'Xyzs.yml', Config::YAML);
        $this->x = $this->xyz->getAll();
        $this->getServer()->getPluginManager()->registerEvents(new EventExcuter($this), $this);
        $this->getScheduler()->scheduleRepeatingTask(new BossCleaner($this), 1200 * 1);
        $this->getScheduler()->scheduleRepeatingTask(new BossCreateTask($this), 1200);
        $command = new PluginCommand('boss', $this);
        $command->setDescription('보스 관리 명령어 입니다');
        $command->setAliases([
            '보스',
        ]);
        $this->getServer()->getCommandMap()->register('boss', $command);
        Entity::registerEntity(BossEntity::class, true);
        FastAccess::$server = Server::getInstance();
    }
    public function onDisable(){
        $this->config->setAll($this->db);
        $this->config->save();
        $this->xyz->setAll($this->x);
        $this->xyz->save();
    }

    /**
     * @param CommandSender $sender
     * @param Command $command
     * @param string $label
     * @param array $args
     * @return bool
     */
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        switch($command->getName()){
            case 'boss':
            case '보스':
                if(!$sender instanceof Player){
                    FastAccess::message($sender, '콘솔에서는 사용하실수 없습니다');
                    return true;
                }
                if(!$sender->isOp()){
                    FastAccess::message($sender, '이 명령어를 수행할 권한이 없습니다');
                    return true;
                }
                if(!isset($args[0])){
                    FastAccess::message($sender, '/boss 스폰설정 [보스이름] [보스체력] [보스 데미지]');
                    FastAccess::message($sender, '/boss 스폰제거 [보스이름]');
                    FastAccess::message($sender, '/boss 스폰목록');
                    return true;
                }
                switch($args[0]){
                    case '스폰설정':
                        if(!isset($args[1])){
                            FastAccess::message($sender, '보스의 이름을 입력해주세요');
                            return true;
                        }
                        if(!isset($args[2]) or !is_numeric($args[2])){
                            FastAccess::message($sender, '보스의 체력을 입력해주세요');
                            return true;
                        }
                        if(!isset($args[3]) or !is_numeric($args[3])) {
                            FastAccess::message($sender, '보스가 때리는 데미지의 양을 설정해주세요');
                            return true;
                        }
                        if($sender->getInventory()->getItemInHand()->getId() === 0){
                            FastAccess::message($sender, '아이템은 공기가 아니여야 합니다');
                            return true;
                        }
                        $this->db['spawns'] [$args[1]] = [];
                        $this->db['spawns'] [$args[1]] ['drop'] = $sender->getInventory()->getItemInHand()->jsonSerialize();
                        $this->db['spawns'] [$args[1]] ['name'] = $args[1];
                        $this->db['spawns'] [$args[1]] ['health'] = $args[2];
                        $this->db['spawns'] [$args[1]] ['damage'] = $args[3];
                        $this->x[$args[1]] = $sender->x . ':' . $sender->y . ':' . $sender->z . ':' . $sender->getLevel()->getFolderName() . ':' . $args[1];
                        FastAccess::message($sender, '설정되었습니다');
                        break;
                    case '스폰제거':
                        if(!isset($args[1])){
                            FastAccess::message($sender, '스폰번호를 입력해주세요');
                            return true;
                        }
                        if(!isset($this->db['spawns'] [$args[1]])){
                            FastAccess::message($sender, '해당 스폰번호는 존재하지 않습니다');
                            return true;
                        }
                        unset($this->db['spawns'] [$args[1]]);
                        unset($this->x[$args[1]]);
                        FastAccess::message($sender, '제거되었습니다');
                        break;
                    case '스폰목록':
                        foreach($this->x as $name => $xyz){
                            $a = explode(':', $xyz);
                            FastAccess::message($sender, $name . ' 보스: 월드 ' . $a[3] . ' 의 ' .  $a[0] . ':' . $a[1] . $a[2]);
                        }
                        break;
                    default:
                        FastAccess::message($sender, '/boss 스폰설정 [보스이름] [보스체력] [보스 데미지]');
                        FastAccess::message($sender, '/boss 스폰제거 [보스이름]');
                        FastAccess::message($sender, '/boss 스폰목록');
                }
        }
        return true;
    }

    public function spawn(){
        foreach($this->x as $name => $xyz){
            $a = explode(':', $xyz);
            $nbt = Entity::createBaseNBT(new Position((float) $a[0], (float) $a[1], (float) $a[2]));
            $entity = Entity::createEntity('BossEntity', $this->getServer()->getLevelByName($a[3]), $nbt);
            $entity->setNameTag($a[4]);
            $entity->setMaxHealth((int) $this->db['spawns'] [$name] ['health']);
            $entity->setHealth((int) $this->db['spawns'] [$name] ['health']);
            $entity->spawnToAll();
        }
    }
}

/**
 * Class FastAccess
 * @package alvin0319
 */
abstract class FastAccess{

    public static $server;

    public static $prefix = '§d<§f시스템§d> §r';

    /**
     * @param CommandSender $sender
     * @param string $message
     * @return mixed
     */
    public static function message(CommandSender $sender, string $message){
        return $sender->sendMessage(FastAccess::$prefix . $message);
    }

    /**
     * @param string $message
     * @return mixed
     */
    public static function broadcast(string $message){
        return FastAccess::$server->broadcastMessage(FastAccess::$prefix . $message);
    }

    /**
     * @param EntityDamageEvent $event
     * @return mixed
     */
    abstract public function onDamage(EntityDamageEvent $event);
}
class EventExcuter extends FastAccess implements Listener{

    protected $plugin;

    public function __construct(Boss $plugin){
        $this->plugin = $plugin;
    }

    /**
     * @param EntityDamageEvent $event
     * @return mixed
     */
    public function onDamage(EntityDamageEvent $event){
        if($event instanceof EntityDamageByEntityEvent){
            if($event->isCancelled()) return;
            $entity = $event->getEntity();
            $player = $event->getDamager();
            if($entity instanceof BossEntity and $player instanceof Player){
                if($player->isCreative()) return;
                $player->setHealth($player->getHealth() - $this->plugin->db['spawns'] [$entity->getNameTag()] ['damage']);
                $player->setMotion(new Position($player->getMotion()->getX(), $player->getMotion()->getY() + 0.5, $player->getMotion()->getZ()));
            }
        }
    }
}

/**
 * Class BossEntity
 * @package alvin0319
 */
class BossEntity extends Monster{

    public const NETWORK_ID = self::ZOMBIE_VILLAGER;

    /** @var float */
    public $width = 0.6;

    /** @var float */
    public $height = 1.8;

    /**
     * @return string
     */
    public function getName() : string{
        return 'Boss';
    }

    /**
     * @return array
     */
    public function getDrops() : array{
        $plugin = Server::getInstance()->getPluginManager()->getPlugin('Boss');
        if(!isset($plugin->db['spawns'] [$this->getNameTag()])){
            return [];
        }
        $item = Item::jsonDeserialize($plugin->db['spawns'] [$this->getNameTag()] ['drop']);
        return [
            $item
        ];
    }

    /**
     * @return int
     */
    public function getXpDropAmount() : int{
        return mt_rand(1, 3);
    }
}

/**
 * Class BossCleaner
 * @package alvin0319
 */
class BossCleaner extends Task{

    private $time = 5;

    private $plugin;

    /**
     * BossCleaner constructor.
     * @param Boss $plugin
     */
    public function __construct(Boss $plugin){
        $this->plugin = $plugin;
    }

    /**
     * @param int $currentTick
     */
    public function onRun(int $currentTick){
        if($this->time !== 0){
            FastAccess::broadcast('보스가 §d' . --$this->time . ' §f분 후에 사라집니다');
        }else{
            foreach(Server::getInstance()->getLevels() as $level){
                foreach($level->getEntities() as $entity){
                    if($entity instanceof BossEntity){
                        $entity->kill();
                        $this->time = 5;
                    }
                }
            }
            FastAccess::broadcast('보스들이 제거되었습니다');
        }
    }
}

/**
 * Class BossCreateTask
 * @package alvin0319
 */
class BossCreateTask extends Task{

    private $plugin;

    /**
     * BossCreateTask constructor.
     * @param Boss $plugin
     */
    public function __construct(Boss $plugin){
        $this->plugin = $plugin;
    }

    /**
     * @param int $currentTick
     */
    public function onRun(int $currentTick){
        $this->plugin->spawn();
    }
}