<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 25/07/2016
 * Time: 23:03
 */

namespace App;

/**
 * Class Plugin
 * @package App
 */
class Plugin
{
    /**
     * @var
     */
    public $name;
    /**
     * @var
     */
    public $author;
    /**
     * @var
     */
    public $description;
    /**
     * @var
     */
    public $version;
    /**
     * @var
     */
    public $level;
    /**
     * @var mixed
     */
    public $triggers;
    /**
     * @var mixed
     */
    public $originalTriggers;
    /**
     * @var
     */
    public $configFile;
    /**
     * @var
     */
    public $config;
    /**
     * @var
     */
    public $output;

    /**
     * @var
     */
    public $teamSpeak3Bot;

    /**
     * Plugin constructor.
     * @param $config
     * @param $teamSpeak3Bot
     */
    public function __construct($config, $teamSpeak3Bot)
    {
        $this->teamSpeak3Bot = $teamSpeak3Bot;
        foreach($config as $name => $value) {
            switch ($name) {
                case "name":
                    $this->name = $value;
                    break;

                case "author":
                    $this->author = $value;
                    break;

                case "description":
                    $this->description = $value;
                    break;

                case "version":
                    $this->version = $value;
                    break;

                case "level":
                    $this->level = $value;
                    break;

                case "triggers":
                    preg_match_all("/'(.*?[^\\\\])'/", $value, $arr);
                    $triggers = $this->stripSlashes($arr[1]);
                    $this->triggers = $this->sortByLengthDESC($triggers);
                    $this->originalTriggers = $this->triggers;
                    break;

                case "configFile":
                    $this->configFile = $value;
                    break;

                default:
                    $this->CONFIG[$name] = $value;
                    break;
            }
        }
    }

    /**
     * @param $trigger
     * @return bool
     */
    function addTrigger($trigger) {
        array_push($this->triggers,$trigger);
        $this->triggers = $this->sortByLengthDESC($this->triggers);
        return true;
    }

    /**
     * @param $trigger
     * @return bool
     */
    function delTrigger($trigger) {
        $key = array_search($trigger,$this->triggers);
        if($key === false)
            return false;
        unset($this->triggers[$key]);
        return true;
    }

    function resetTriggers() {
        $this->triggers = $this->originalTriggers;
    }

    function trigger() {
        $this->output = [];
        $info_save = $this->info;
        $this->info = $info_save;
        $this->isTriggered();
    }

    /**
     * @param $array
     * @return array|string|stripslashes
     */
    protected function stripSlashes($array) {
        $value = is_array($array) ? array_map('stripslashes', $array) : stripslashes($array);
        return $value;
    }

    /**
     * @param $array
     * @return mixed
     */
    static function sortByLengthASC($array) {
        $tempFunction = create_function('$a,$b','return strlen($a)-strlen($b);');
        usort($array,$tempFunction);
        return $array;
    }

    /**
     * @param $array
     * @return mixed
     */
    static function sortByLengthDESC($array) {
        $tempFunction = create_function('$a,$b','return strlen($b)-strlen($a);');
        usort($array,$tempFunction);
        return $array;
    }

    /**
     * @param $text
     */
    function sendOutput($text) {
        $this->teamSpeak3Bot->printOutput($text);
        $this->teamSpeak3Bot->sendPrivateMsg($this->info['invokername'], $text);
    }

    /**
     *
     */
    function onMessage() {}

    /**
     *
     */
    function onChannelMessage() {}

    /**
     *
     */
    function onKick() {}


}