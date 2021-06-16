<?php

namespace EmangGek\GiveawayPlugin;

class Database
{
    private $path = "";
    private $data = [];

    public function __construct($path)
    {
        $this->path = $path;
        if(!file_exists($path)){
            $fp = fopen($path, "wb");
            fwrite($fp, "{}");
            fclose($fp);
        }
        $this->data = json_decode(file_get_contents($path), true);
    }

    private function save()
    {
        file_put_contents($this->path, json_encode($this->data));
    }

    public function set($key, $data)
    {
        $this->data[$key] = $data;
        $this->save();
        return $this->data;
    }
    
    public function get($key = null)
    {
        if ($key) {
            return $this->data[$key];
        }
        return $this->data;
    }

    public function delete($key)
    {
        unset($this->data[$key]);
        $this->save();
        return $this->data;
    }
}

?>