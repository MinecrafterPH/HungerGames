<?php
namespace ResetChest;

use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\math\Vector3;
use pocketmine\tile\Chest;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat;

class Main extends PluginBase implements CommandExecutor, Listener
{
	private static $obj = null;
	public function onEnable()
	{
		if(!self::$obj instanceof Main)
		{
			self::$obj = $this;
		}
		$this->getServer()->getLogger()->info(TextFormat::RED."[RC] Plugin loaded!");
        @mkdir($this->getDataFolder());
        $this->iconfig=new Config($this->getDataFolder()."items.yml", Config::YAML, array());
        if(!$this->iconfig->exists("items"))
        {
        	$this->iconfig->set("items",array(17,0,57,0));
        	$this->iconfig->save();
        }
        $this->items=$this->iconfig->get("items");
        
        $this->config=new Config($this->getDataFolder()."chest.yml", Config::YAML, array());
        if(!$this->config->exists("chest"))
        {
        	$this->config->set("chest",array());
        	$this->config->save();
        }
        $this->chest=$this->config->get("chest");
        $this->set=array();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }
    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args)
    {
    	if(!isset($args[0])){unset($sender,$cmd,$label,$args);return false;};
    	switch($args[0])
    	{
    	case "reload":
    		unset($this->iconfig,$this->config);
    		@mkdir($this->getDataFolder());
        	$this->iconfig=new Config($this->getDataFolder()."items.yml", Config::YAML, array());
        	if(!$this->iconfig->exists("items"))
        	{
        		$this->iconfig->set("items",array(17,0,57,0));
        		$this->iconfig->save();
        	}
        	$this->items=$this->iconfig->get("items");
        	
        	$this->config=new Config($this->getDataFolder()."chest.yml", Config::YAML, array());
        	if(!$this->config->exists("chest"))
        	{
        		$this->config->set("chest",array());
        		$this->config->save();
        	}
        	$this->chest=$this->config->get("chest");
        	$this->set=array();
    		$sender->sendMessage("Reload successful!");
    		break;
    	case "reset":
    		$this->ResetChest();
    		$sender->sendMessage("Chest has been refilled!");
    		break;
    	case "clear":
    		$this->ClearChest();
    		$sender->sendMessage("Chests have been cleared.");
    		break;
    	case "add":
    	case "remove":
    		if(!$sender instanceof Player){$sender->sendMessage("Don't break it!");break;};
    		$this->set[$sender->getName()] = $args[0];
            $sender->sendMessage("Click the chest to confirm.");
    		break;
    	default:
    		unset($sender,$cmd,$label,$args);
			return false;
			break;
    	}
        unset($sender,$cmd,$label,$args);
        return true;
    }
    
    public static function getInstance()
	{
		return self::$obj;
	}
    
    public function onInteract(PlayerInteractEvent $event)
    {
    	$block=$event->getBlock();
        if(isset($this->set[$event->getPlayer()->getName()]))
        {
        	if($block->getId()!=54)
        	{
        		$event->getPlayer()->sendMessage("Please tap a chest.");
            	unset($event,$block,$key,$val);
            	return;
        	}
        	$a=$this->set[$event->getPlayer()->getName()];
        	unset($this->set[$event->getPlayer()->getName()]);
            switch($a)
            {
            case "add":
            	foreach($this->chest as $key=>$val)
            	{
            		if($val["x"]==$block->getX() && $val["y"]==$block->getY() && $val["z"]==$block->getZ() && $val["level"]==$block->getLevel()->getFolderName())
            		{
            			$event->getPlayer()->sendMessage("This chest is already added.");
            			unset($event,$block,$key,$val);
            			return;
            		}
            	}
            	$tmp=array();
            	$tmp["x"]=$block->getX();
            	$tmp["y"]=$block->getY();
            	$tmp["z"]=$block->getZ();
            	$tmp["level"]=$block->getLevel()->getFolderName();
                $this->chest[]=$tmp;
                unset($tmp,$key,$val);
                $event->getPlayer()->sendMessage("Adding to the reset list...");
                break;
            case "remove":
            	$msg="This chest isn't in the reset list.";
                foreach($this->chest as $key=>$val)
            	{
            		if($val["x"]==$block->getX() && $val["y"]==$block->getY() && $val["z"]==$block->getZ() && $val["level"]==$block->getLevel()->getFolderName())
            		{
            			array_splice($this->chest,$key,1);
            			$msg="Removing from the reset list...";
            			break;
            		}
            	}
            	$event->getPlayer()->sendMessage($msg);
            	unset($key,$val);
                break;
            }
            
        }
        $this->saveChest();
        unset($block,$event,$a);
    }
    public function onBreakEvent(BlockBreakEvent $event)
    {
    	$block=$event->getBlock();
    	foreach($this->chest as $key=>$val)
        {
        	if($val["x"]==$block->getX() && $val["y"]==$block->getY() && $val["z"]==$block->getZ() && $val["level"]==$block->getLevel()->getFolderName())
        	{
        		if(!$event->getPlayer()->isOp())
        		{
        			$event->getPlayer()->sendMessage("You can't break this chest.");
        			$event->setCancelled();
        			break;
        		}
            	array_splice($this->chest,$key,1);
            	$event->getPlayer()->sendMessage("Removing from reset list...");
            	break;
            }
        }
        unset($event,$block,$key,$val);
	}
	
    public function onDisable()
    {
        //$this->saveChest();
    }
    
    public function ClearChest()
    {
    	foreach($this->chest as $val)
    	{
    		if(!isset($val["level"])){continue;};
    		$level=$this->getServer()->getLevelByName($val["level"]);
    		if(!$level instanceof Level){continue;};
    		$v3=new Vector3($val["x"],$val["y"],$val["z"]);
    		if($level->getBlock($v3)->getId()!=54){continue;};
    		$chest=$level->getTile($v3);
    		for($i=0;$i<$chest->getSize();$i++)
    		{
    			$chest->getInventory()->setItem($i,Item::get(0,0));
    		}
    	}
    	unset($val,$level,$v3,$chest,$i,$rand,$rid,$item);
    }
    
    public function ResetChest()
    {
    	foreach($this->chest as $val)
    	{
    		if(!isset($val["level"])){continue;};
    		$level=$this->getServer()->getLevelByName($val["level"]);
    		if(!$level instanceof Level){continue;};
    		$v3=new Vector3($val["x"],$val["y"],$val["z"]);
    		if($level->getBlock($v3)->getId()!=54){continue;};
    		$chest=$level->getTile($v3);
    		for($i=0;$i<$chest->getSize();$i++)
    		{
    			$chest->getInventory()->setItem($i,Item::get(0,0));
    		}
    		$rand=mt_rand(5,10);
    		for($i=0;$i<$rand;$i++)
    		{
    			$rid=mt_rand(0,count($this->items)/2);
    			$item=Item::get((int)$this->items[$rid],(int)$this->items[$rid+1]);
    			$rid=mt_rand(0,$chest->getSize()-1);
    			while($chest->getInventory()->getItem($rid)->getId()!=0)
    			{
    				$rid=mt_rand(0,$chest->getSize()-1);
    			}
    			$chest->getInventory()->setItem($rid,$item);
    		}
    	}
    	unset($val,$level,$v3,$chest,$i,$rand,$rid,$item);
    }
    public function saveChest()
    {
    	$this->config->set("chest",$this->chest);
    	$this->config->save();
    }
}
