<?php namespace CupOfTea\Filesystem;

use CupOfTea\Support\Str;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use League\Flysystem\Plugin\GetWithMetadata;
use League\Flysystem\Filesystem as Flysystem;

class FilesystemManager
{
    /**
     * The array of resolved filesystem drivers.
     *
     * @var array
     */
    protected $disks = [];
    
    /**
     * The array of resolved filesystem drivers.
     *
     * @var array
     */
    protected $ejectable_disks = [];
    
    /**
     * Retrieve a disk by name.
     *
     * @param  string  $name
     * @return \CupOfTea\Magick\Filesystem\FilesystemAdapter
     */
    public function disk($name)
    {
        return Arr::get($this->disks, $name, Arr::get($this->ejectable_disks, $name));
    }
    
    /**
     * Check if a disk exists.
     *
     * @param  string  $name
     * @return bool
     */
    public function hasDisk($name)
    {
        return isset($this->disks[$name]) || isset($this->ejectable_disks[$name]);
    }
    
    /**
     * Mount a disk.
     *
     * @param  string  $name
     * @param  string  $root
     * @param  bool  $ejectable
     * @return \CupOfTea\Magick\Filesystem\FilesystemAdapter
     */
    public function mount($name, $root, $ejectable = true)
    {
        return $this->hasDisk($name) ? $this->disk($name) : $ejectable ? ($this->ejectable_disks[$name] = $this->createDisk($root)) : ($this->disks[$name] = $this->createDisk($root));
    }
    
    /**
     * Mount an anonymous disk.
     *
     * @param  string  $root
     */
    public function create($root)
    {
        return $this->mount($root, $root);
    }
    
    /**
     * Create a new disk with the top-most common directory as root.
     *
     * @param  \CupOfTea\Magick\Filesystem\FilesystemAdapter  $disk1
     * @param  \CupOfTea\Magick\Filesystem\FilesystemAdapter  $disk2
     * @return \CupOfTea\Magick\Filesystem\FilesystemAdapter
     */
    public function merge(FilesystemAdapter $disk1, FilesystemAdapter $disk2, $eject = false)
    {
        $base_root = Str::intersect($disk1->root(), $disk2->root());
        
        if ($base_root === $disk1->root()) {
            if ($eject && $this->isEjectable($disk2)) {
                $this->eject($disk2);
            }
            
            return $disk1;
        } elseif ($base_root === $disk2->root()) {
            if ($eject && $this->isEjectable($disk1)) {
                $this->eject($disk1);
            }
            
            return $disk2;
        }
        
        if ($eject) {
            if ($eject && $this->isEjectable($disk1)) {
                $this->eject($disk1);
            }
            
            if ($this->isEjectable($disk2)) {
                $this->eject($disk2);
            }
        }
        
        return $this->createDisk($base_root);
    }
    
    public function isEjectable($name)
    {
        return ($name instanceof FilesystemAdapter && array_search($name, $this->ejectable_disks)) || isset($this->ejectable_disks[$name]) || ! $this->hasDisk($name);
    }
    
    /**
     * Eject a disk by name.
     *
     * @param  string  $name
     * @return void
     * @throws \InvalidArgumentException
     */
    public function eject($name)
    {
        if (! $this->isEjectable($name)) {
            throw new InvalidArgumentException(is_string($name) ? 'The disk "' . $name . '" is not ejectable.' : 'This disk is not ejectable.');
        }
        
        if (isset($this->ejectable_disks[$name])) {
            unset($this->ejectable_disks[$name]);
        }
    }
    
    /**
     * Create a new disk.
     *
     * @param  string  $root
     * @return \CupOfTea\Magick\Filesystem\FilesystemAdapter
     */
    protected function createDisk($root)
    {
        return new FilesystemAdapter(
            $this->createFlysystem(
                $this->createLocalAdapter($root)
            ),
            function ($disk) {
                $this->eject($disk);
            }
        );
    }
    
    /**
     * Create a new Flysystem instance.
     *
     * @param  \League\Flysystem\Adapter\Local  $adapter
     * @return \League\Flysystem\Filesystem
     */
    protected function createFlysystem(LocalAdapter $adapter)
    {
        $flysystem = new Flysystem($adapter);
        $flysystem->addPlugin(new GetWithMetadata);
        
        return $flysystem;
    }
    
    /**
     * Create a new LocalAdapter instance.
     *
     * @param  string  $root
     * @return \League\Flysystem\Adapter\Local
     */
    protected function createLocalAdapter($root)
    {
        return new LocalAdapter($root, LOCK_EX, LocalAdapter::SKIP_LINKS);
    }
    
    protected function findBasePath($path1, $path2)
    {
        if ($path1 === $path2) {
            return $path1;
        }
        
        $base = '';
        $path1 = str_split($path1);
        $path2 = str_split($path2);
        
        if (count($path1) > count($path2)) {
            list($path2, $path1) = [$path1, $path2];
        }
        
        foreach ($path1 as $i => $char) {
            if ($char !== $path2[$i]) {
                break;
            }
            
            $base .= $char;
        }
        
        return $base;
    }
}
