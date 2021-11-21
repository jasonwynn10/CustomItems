<?php
declare(strict_types=1);
namespace jasonwynn10\customitems;

use pocketmine\block\BlockFactory;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\StringToItemParser;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class Main extends PluginBase {

	private Config $dataStore;

	public function onEnable() : void {
		$this->dataStore = new Config($this->getDataFolder().'items.json', Config::JSON);
		$this->dataStore->enableJsonOption(JSON_PRETTY_PRINT);
		foreach($this->dataStore->getAll() as $name => $data) {
			try{
				$item = $data[2] ?
					new Item(new ItemIdentifier($data[0], $data[1]), $name) :
					new ItemBlock(new ItemIdentifier($data[0], $data[1]), BlockFactory::getInstance()->get($data[7], $data[8]));
				foreach($data[3] as $enchantmentClass => $enchantmentLevel) {
					$item->addEnchantment(new EnchantmentInstance(new $enchantmentClass(), $enchantmentLevel));
				}
				/** @var CompoundTag $customDataTag */
				$customDataTag = (new LittleEndianNbtSerializer())->read($data[4])->getTag();
				$item->setLore($data[2])->setCustomBlockData($customDataTag)->setCanPlaceOn($data[4]);
				$item->setCanDestroy($data[5]);
				StringToItemParser::getInstance()->register(TextFormat::clean($item->getName()), fn() => $item);
			}catch(\Exception $e) {
				$this->getLogger()->error('Unable to load custom item "'.$name.'"');
				continue;
			}
		}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool {
		if(!$sender instanceof Player) {
			$sender->sendMessage(TextFormat::RED.'Please use this command in-game.');
			return true;
		}

		$item = $sender->getInventory()->getItemInHand();
		StringToItemParser::getInstance()->register(TextFormat::clean($item->getName()), fn() => $item);

		$enchantments = [];
		foreach($item->getEnchantments() as $enchantment) {
			$enchantments[get_class($enchantment->getType())] = $enchantment->getLevel();
		}
		$this->dataStore->set($item->getName(), [$item->getId(), $item->getMeta(), $item->getLore(), $enchantments, (new LittleEndianNbtSerializer())->write(new TreeRoot($item->getCustomBlockData())), $item->getCanPlaceOn(), $item->getCanDestroy(), $item->getBlock()->getId(), $item->getBlock()->getMeta()]);
		$sender->sendMessage(TextFormat::GREEN.'New Item "'.TextFormat::clean($item->getName()).'" is now available for use with /give');
		return true;
	}
}