<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 25/07/2016
 * Time: 22:12
 */

namespace App;

use TeamSpeak3\TeamSpeak3;
use TeamSpeak3\Helper\Signal;
use TeamSpeak3\Ts3Exception;
use TeamSpeak3\Adapter\ServerQuery\Event;
use TeamSpeak3\Adapter\AbstractAdapter;

/**
 * Class TeamSpeak3Bot
 * @package App\TeamSpeak3Bot
 */
class TeamSpeak3Bot
{
    /**
     * @var string
     */
    protected static $_version = '0.0.1';

    /**
     * @var string
     */
    private $username;
    /**
     * @var string
     */
    private $password;
    /**
     * @var string
     */
    private $host;
    /**
     * @var string
     */
    private $port;
    /**
     * @var string
     */
    private $name;
    /**
     * @var string
     */
    private $serverPort;
    /**
     * @var
     */
    public $node;
    /**
     * @var
     */
    public $channel;
    /**
     * @var
     */
    public $plugins;

    /**
     * @var
     */
    private static $_username;
    /**
     * @var
     */
    private static $_password;
    /**
     * @var
     */
    private static $_host;
    /**
     * @var
     */
    private static $_port;
    /**
     * @var
     */
    private static $_name;
    /**
     * @var
     */
    private static $_serverPort;
    /**
     * @var
     */
    private static $_instance;

    /**
     * @var bool
     */
    public $online = FALSE;

    /**
     * TeamSpeak3Bot constructor.
     * @param string $username
     * @param string $password
     * @param string $host
     * @param string $port
     * @param string $name
     * @param string $serverPort
     */
    public function __construct($username = "serveradmin", $password = "", $host = "127.0.0.1", $port = "10011", $name = "DrBot", $serverPort = "9987")
    {
        $this->username = $username;
        $this->password = $password;
        $this->host = $host;
        $this->port = $port;
        $this->name = $name;
        $this->serverPort = $serverPort;
    }

    /**
     * Run the TeamSpeak3Bot instance
     */
    public function run()
    {
        $this->subscribe();
        try {
            $this->node = TeamSpeak3::factory("serverquery://{$this->username}:{$this->password}@{$this->host}:{$this->port}/?server_port={$this->serverPort}&blocking=0&nickname={$this->name}");
            $this->online = TRUE;
        } catch (Ts3Exception $e) {
            $this->printOutput("Error {$e->getCode()}: {$e->getMessage()}");

            return;
        }

        $this->initializePlugins();

        $this->register();
        $this->wait();
    }

    protected function subscribe()
    {
        Signal::getInstance()->subscribe("serverqueryWaitTimeout", [$this, "onWaitTimeout"]);
        Signal::getInstance()->subscribe("errorException", [$this, "onException"]);
        Signal::getInstance()->subscribe("notifyTextmessage", [$this, "onMessage"]);
        Signal::getInstance()->subscribe("serverqueryConnected", [$this, "onConnect"]);
        Signal::getInstance()->subscribe("notifyEvent", [$this, "onEvent"]);
        $this->printOutput("Events subscribed.");
    }

    protected function register()
    {
        $this->node->notifyRegister("textserver");
        $this->node->notifyRegister("textchannel");
        $this->node->notifyRegister("textprivate");
        $this->node->notifyRegister("server");
        $this->node->notifyRegister("channel");
        $this->printOutput("Registered.");
    }

    /**
     * @param $output
     * @param $eol
     */
    public function printOutput($output, $eol = TRUE)
    {
        echo $output, $eol ? PHP_EOL : '';
    }

    /**
     * Wait for messages
     */
    protected function wait()
    {
        while (1){
            $this->printOutput("wait");
            $this->node->getAdapter()->wait();
        }
    }

    /**
     * @param array $options
     */
    public static function setOptions(Array $options = [])
    {
        Self::$_username = $options['username'];
        Self::$_password = $options['password'];
        Self::$_host = $options['host'];
        Self::$_port = $options['port'];
        Self::$_name = $options['name'];
        Self::$_serverPort = $options['serverPort'];
    }

    /**
     * @return TeamSpeak3Bot
     */
    public static function getInstance()
    {
        if (Self::$_instance === NULL)
            self::$_instance = new Self(Self::$_username, Self::$_password, Self::$_host, Self::$_port, Self::$_name, Self::$_serverPort);

        return Self::$_instance;
    }

    /**
     * @return TeamSpeak3Bot
     */
    public static function getNewInstance()
    {
        self::$_instance = new Self(Self::$_username, Self::$_password, Self::$_host, Self::$_port, Self::$_name, Self::$_serverPort);

        return Self::$_instance;
    }

    /**
     * @return null
     */
    public static function getLastInstance()
    {
        if (Self::$_instance === NULL)
            self::$_instance = new Self(Self::$_username, Self::$_password, Self::$_host, Self::$_port, Self::$_name, Self::$_serverPort);

        return NULL;
    }

    private function initializePlugins()
    {
        $dir = opendir("config/plugins");

        while ($file = readdir($dir)) {
            if (substr($file, -5) == ".conf")
                $this->loadPlugin($file);
        }

        closedir($dir);
    }

    /**
     * @param $configFile
     * @return bool
     */
    private function loadPlugin($configFile)
    {
        $config = $this->parseConfigFile("config/plugins/" . $configFile);
        $config['configFile'] = $configFile;

        if (!$config) {
            $this->printOutput("Plugin with config file '" . $configFile .
                               "' has not been loaded because it doesn't exist.");

            return FALSE;
        }

        if (!isset($config['name'])) {
            $this->printOutput("Plugin with config file '" . $configFile .
                               "' has not been loaded because it has no name.");

            return FALSE;
        }

        $this->printOutput("Loading Plugin [{$config['name']}] by {$config['author']} ... ", FALSE);
        require_once("plugins/" . $config['name'] . ".php");

        $this->plugins[$config['name']] = new $config['name']($config, $this);
        $this->printOutput("OK");

        return TRUE;
    }

    /**
     * @param $file
     * @return array|bool
     */
    public function parseConfigFile($file)
    {
        if (!file_exists($file))
            return FALSE;

        $array = [];
        $fp = fopen($file, "r");
        while ($row = fgets($fp)) {
            $row = trim($row);
            if (preg_match('/^([A-Za-z0-9_]+?)\s+=\s+(.+?)$/', $row, $arr))
                $array[$arr[1]] = $arr[2];
        }

        fclose($fp);

        return $array;
    }

    /**
     * @param $channel
     */
    public function joinChannel($channel)
    {
        try {
            $this->channel = $this->node->channelGetByName($channel);
            $this->node->clienMove($this->whoAmI(), $this->channel);
        } catch (Ts3Exception $e) {
            $this->printOutput("Error {$e->getCode()}: {$e->getMessage()}");
        }
    }

    /**
     * @return mixed
     */
    public function whoAmI()
    {
        return $this->node->whoAmI();
    }

    /**
     * @param $username
     * @param int $reason
     * @param string $message
     */
    public function kick($username, $reason = TeamSpeak3::KICK_CHANNEL, $message = "")
    {
        try {
            $client = $this->node->clientGetByName($username);
            $client->kick($reason, $message);
        } catch (Ts3Exception $e) {
            $this->printOutput("Error {$e->getCode()}: {$e->getMessage()}");
        }
    }

    /**
     * @param $target
     * @param $text
     */
    public function sendPrivateMsg($target, $text)
    {
        try {
            $client = $this->node->clientGetByName($target);
            $client->message($text);
        } catch (Ts3Exception $e) {
            $this->printOutput("Error {$e->getCode()}: {$e->getMessage()}");
        }
    }

    /**
     * @param $text
     */
    public function sendServerMsg($text)
    {
        $this->node->message($text);
    }

    /**
     * @param $channel
     * @param $text
     */
    public function sendChannelMsg($channel, $text)
    {
        $this->joinChannel($channel);
        $this->channel->message($text);
    }

    /**
     * @param $target
     * @param $text
     */
    public function sendPoke($target, $text)
    {
        try {
            $client = $this->node->clientGetByName($target);
            $client->poke($text);
        } catch (Ts3Exception $e) {
            $this->printOutput("Error {$e->getCode()}: {$e->getMessage()}");
        }
    }

    /**
     * @param AbstractAdapter $adapter
     */
    public function onConnect(AbstractAdapter $adapter)
    {
        $this->printOutput("Connected!");
    }

    /**
     * @param Event $event
     */
    public function onMessage(Event $event)
    {
        $this->info['PRIVMSG'] = $event->getData();
        $info = $this->info['PRIVMSG'];

        foreach ($this->plugins as $name => $config) {
            $this->plugins[$name]->info = $info;
            $this->plugins[$name]->onMessage();

            foreach ($config->triggers as $trigger) {
                if ($event["msg"]->startsWith($trigger)) {
                    $info['triggerUsed'] = $trigger;
                    $text = $event["msg"]->substr(strlen($trigger) + 1);
                    $info['fullText'] = $event["msg"];
                    unset($info['text']);

                    if (!empty($text->toString()))
                        $info['text'] = $text;

                    $this->plugins[$name]->info = $info;
                    $this->plugins[$name]->trigger();
                    break;
                }
            }
        }
    }

    /**
     * @param Event $event
     */
    public function onEvent(Event $event)
    {
        $this->node->clientListReset();
        $this->node->channelListReset();
        //var_dump($event);
        //$this->wait();
    }

    public function onWaitTimeout($time, AbstractAdapter $adapter)
    {
        if ($adapter->getQueryLastTimestamp() < time() - 120)
            $adapter->request("clientupdate");
        $this->node->clientListReset();
        $this->node->channelListReset();
        var_dump($time);
        //$this->wait();
    }

    /**
     * @param Ts3Exception $e
     */
    public function onException(Ts3Exception $e)
    {
        $this->printOutput("Error {$e->getCode()}: {$e->getMessage()}");
    }

}