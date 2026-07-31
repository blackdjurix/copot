<?php

final class MediaVariantFilesystemStorage
{
    private const KEY='/^[a-f0-9]{32}\.(jpg|png|webp)$/';
    public function __construct(private string $root, private int $maxBytes=16777216, private $deleteOperation=null) {}
    public function stageGenerated(callable $writer): MediaStagedFile { $this->ensure();$token='.variant-'.bin2hex(random_bytes(16));$path=$this->root.DIRECTORY_SEPARATOR.'.tmp'.DIRECTORY_SEPARATOR.$token;$handle=@fopen($path,'xb');if(!$handle)throw new MediaProcessingStorageException('Variant storage is unavailable.');fclose($handle);try{$writer($path);$size=filesize($path);if($size===false||$size<=0||$size>$this->maxBytes)throw new RuntimeException();}catch(Throwable $e){@unlink($path);throw $e instanceof MediaProcessingException?$e:new MediaProcessingStorageException('Variant storage could not stage the output.',0,$e);}return new MediaStagedFile($path,$token); }
    public function activate(MediaStagedFile $staged,string $storageKey):void{if(!preg_match(self::KEY,$storageKey)||!$this->inside($staged->path(),$this->root.DIRECTORY_SEPARATOR.'.tmp'))throw new MediaProcessingStorageException('Variant storage could not activate the output.');$this->ensure();$dir=$this->root.DIRECTORY_SEPARATOR.'variants'.DIRECTORY_SEPARATOR.substr($storageKey,0,2).DIRECTORY_SEPARATOR.substr($storageKey,2,2);$this->mkdirSafe($dir);$destination=$dir.DIRECTORY_SEPARATOR.$storageKey;if(file_exists($destination)||is_link($destination)||!@rename($staged->path(),$destination))throw new MediaProcessingStorageException('Variant storage could not activate the output.');@chmod($destination,0644);}
    public function resolve(string $storageKey):?string{if(!preg_match(self::KEY,$storageKey))return null;$this->ensure();$path=$this->root.DIRECTORY_SEPARATOR.'variants'.DIRECTORY_SEPARATOR.substr($storageKey,0,2).DIRECTORY_SEPARATOR.substr($storageKey,2,2).DIRECTORY_SEPARATOR.$storageKey;return is_file($path)&&!is_link($path)&&$this->inside($path,$this->root.DIRECTORY_SEPARATOR.'variants')?$path:null;}
    public function delete(string $storageKey):void{$path=$this->resolve($storageKey);if($path&&$this->deleteOperation){($this->deleteOperation)($path);return;}if($path)@unlink($path);}
    public function discard(MediaStagedFile $staged):void{if(is_file($staged->path())&&!is_link($staged->path()))@unlink($staged->path());}
    private function ensure():void{$this->mkdirSafe($this->root);$this->mkdirSafe($this->root.DIRECTORY_SEPARATOR.'.tmp');$this->mkdirSafe($this->root.DIRECTORY_SEPARATOR.'variants');}
    private function mkdirSafe(string $path):void{if(is_link($path)||(!is_dir($path)&&!@mkdir($path,0755,true)&&!is_dir($path))||is_link($path))throw new MediaProcessingStorageException('Variant storage is unavailable.');}
    private function inside(string $path,string $base):bool{$base=realpath($base);$path=realpath($path);return $base!==false&&$path!==false&&($path===$base||str_starts_with($path,$base.DIRECTORY_SEPARATOR));}
}
