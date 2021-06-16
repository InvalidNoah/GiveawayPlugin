<?php

namespace EmangGek\GiveawayPlugin;

use pocketmine\Player;

use pocketmine\plugin\PluginBase;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\command\ConsoleCommandSender;

use pocketmine\level\sound\AnvilFallSound;

use EmangGek\GiveawayPlugin\Database;

use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\CustomForm;

class GiveawayPlugin extends PluginBase
{
    public function onEnable()
    {
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();
        $this->saveResource("giveaways.json");
        $this->db = new Database($this->getDataFolder() . "giveaways.json");
    }

    public function onCommand(CommandSender $sender, Command $command, String $label, Array $args) : bool
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage("Only player can execute this command");
            return true;
        }
        switch($command->getName()) {
            case "glist":
                if ($sender->hasPermission("giveawayplugin.list")) {
                    $this->showList($sender);
                    return true;
                }
                break;
            case "gcreate":
                if ($sender->hasPermission("giveawayplugin.create")) {
                    $this->createGiveaway($sender);
                    return true;
                }
                break;
            case "gend":
                if ($sender->hasPermission("giveawayplugin.end")) {
                    $this->endGiveaway($sender);
                    return true;
                }
                break;
        }
        return false;
    }

    public function showList($player)
    {
        $giveaways = $this->db->get();
        $form = new SimpleForm(function(Player $player, Int $data = null) use ($giveaways)
        {
            if ($data === null) {
                return;
            }
            $this->showGiveaway($player, array_keys($giveaways)[$data]);
        });
        $form->setTitle("Giveaways List");
        $form->setContent("Giveaways List on This Server");
        foreach ($giveaways as $i => $g) {
            $form->addButton($g["name"]);
        }
        $form->sendToPlayer($player);
    }

    public function showGiveaway($player, $index)
    {
        $gdata = $this->db->get()[$index];
        $form = new SimpleForm(function(Player $player, Int $data = null) use ($index, $gdata)
        {
            if ($data === null) {
                return;
            }
            switch ($data) {
                case 0:
                    if (in_array($player->getName(), $gdata["players"])) {
                        $this->leaveGiveaway($player, $index);
                    } else {
                        $this->enterGiveaway($player, $index);
                    }
                    break;
                case 1:
                    $this->showList($player);
                    break;
            }
        });
        $form->setTitle("Giveaway - " . $gdata["name"]);
        $form->setContent("Giveaway Name: " . $gdata["name"] . "\nGiveaway Hosted By: " . $gdata["host"] . "\nPlayers Entered: " . count($gdata["players"]));
        if (in_array($player->getName(), $gdata["players"])) {
            $form->addButton("Leave Giveaway");
        } else {
            $form->addButton("Enter Giveaway");
        }
        $form->addButton("Back to List");
        $form->sendToPlayer($player);
    }

    public function enterGiveaway($player, $index)
    {
        $gdata = $this->db->get()[$index];
        $gdata["players"][] = $player->getName();
        $this->db->set($index, $gdata);
        $player->sendMessage($this->getConfig()->get("entered-giveaway"));
    }

    public function leaveGiveaway($player, $index)
    {
        $gdata = $this->db->get()[$index];
        if (($key = array_search($player->getName(), $gdata["players"])) !== false) {
            unset($gdata["players"][$key]);
            $this->db->set($index, $gdata);
            $player->sendMessage($this->getConfig()->get("leave-giveaway"));
        }
    }

    public function createGiveaway($player)
    {
        $form = new CustomForm(function(Player $player, Array $data = null)
        {
            if ($data === null) {
                return;
            }
            $this->db->set(uniqid("g_"), [
                "name" => $data[0],
                "prize" => $data[1],
                "host" => $player->getName(),
                "players" => []
            ]);
            foreach ($this->getServer()->getOnlinePlayers() as $p) {
                $p->sendMessage($this->getConfig()->get("new-giveaway-message"));
                $p->getLevel()->addSound(new AnvilFallSound($p));
            }
        });
        $form->setTitle("Create a Giveaway");
        $form->addInput("Giveaway Name", "Example: Free Diamond");
        $form->addInput("Prize Command", "Example: give {plr} diamond 64");
        $form->sendToPlayer($player);
    }

    public function endGiveaway($player)
    {
        $giveaways = $this->db->get();
        $form = new SimpleForm(function(Player $player, Int $data = null) use ($giveaways)
        {
            if ($data === null) {
                return;
            }
            $players = [];
            $g = $giveaways[array_keys($giveaways)[$data]];
            foreach ($this->getServer()->getOnlinePlayers() as $p) {
                if (in_array($p->getName(), $g["players"])) {
                    $players[] = $p;
                }
            }
            if (count($players) > 0) {
                $winner = $players[array_rand($players)];
                if ($winner) {
                    $this->getServer()->dispatchCommand(new ConsoleCommandSender(), str_replace("{plr}", $winner->getName(), $g["prize"]));
                    foreach ($this->getServer()->getOnlinePlayers() as $p) {
                        $p->sendMessage(str_replace("{plr}", $winner->getName(), str_replace("{ga}", $g["name"], $this->getConfig()->get("winner-message"))));
                    }
                    $this->db->delete(array_keys($giveaways)[$data]);
                    return;
                }
            }
            $player->sendMessage("There is no player online");
        });
        $form->setTitle("Giveaways List");
        $form->setContent("Select the giveaway you want to end");
        foreach ($giveaways as $i => $g) {
            $form->addButton($g["name"]);
        }
        $form->sendToPlayer($player);
    }
}
