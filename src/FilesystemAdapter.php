<?php namespace CupOfTea\Filesystem;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Illuminate\Support\Collection;
use League\Flysystem\FilesystemInterface;
use Illuminate\Filesystem\FilesystemAdapter as IlluminateFilesystemAdapter;

/**
 * @TODO: Filter out system files.
 */
class FilesystemAdapter extends IlluminateFilesystemAdapter
{
    /**
     * Ejector to eject the disk.
     *
     * @var callable
     */
    protected $ejector;
    
    /**
     * Create a new filesystem adapter instance.
     *
     * @param  \League\Flysystem\FilesystemInterface  $driver
     * @return void
     */
    public function __construct(FilesystemInterface $driver, callable $ejector)
    {
        parent::__construct($driver);
        
        $this->ejector = $ejector;
    }
    
    /**
     * Eject the disk.
     *
     * @return void
     */
    public function eject()
    {
        call_user_func($this->ejector, $this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function exists($path)
    {
        return parent::exists($this->resolvePath($path));
    }
    
    /**
     * {@inheritdoc}
     */
    public function get($path)
    {
        return parent::get($this->resolvePath($path));
    }
    
    /**
     * {@inheritdoc}
     */
    public function put($path, $contents, $visibility = null)
    {
        return parent::put($this->resolvePath($path), $contents, $visibility);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getVisibility($path)
    {
        return parent::getVisibility($this->resolvePath($path));
    }
    
    /**
     * {@inheritdoc}
     */
    public function setVisibility($path, $visibility)
    {
        return parent::setVisibility($this->resolvePath($path), $visibility);
    }
    
    /**
     * @see \CupOfTea\Magick\Filesystem\FilesystemAdapter::copy
     */
    public function cp($from, $to, $overwrite = false, $recursive = false)
    {
        return $this->copy($from, $to, $overwrite, $recursive);
    }
    
    /**
     * Copy a file to a new location.
     *
     * @param  string  $from
     * @param  string  $to
     * @return bool
     */
    public function copy($from, $to, $overwrite = false, $recursive = false)
    {
        $from = $this->resolvePath($from);
        $to = $this->resolvePath($to);
        
        $this->mask($from, $to, $overwrite, $recursive);
        
        if (is_array($from)) {
            $result = true;
            
            foreach (array_combine($from, $to) as $from => $to) {
                $result = $result && $this->copy($from, $to, $overwrite, $recursive);
            }
            
            return $result;
        }
        
        return $this->overwrite(__FUNCTION__, $from, $to, $overwrite, $recursive);
    }
    
    public function copyAndReplace($from, $to, $replacer, $replacements, $overwrite = false, $recursive = false)
    {
        $from = $this->resolvePath($from);
        $to = $this->resolvePath($to);
        
        $this->mask($from, $to, $overwrite, $recursive);
        
        $from = is_array($from) ? $from : [$from];
        $to = is_array($to) ? $to : [$to];
        
        extract($replacer);
        
        $replacer = '/' . preg_quote($prefix) . '\s*\$?([^' . preg_quote($suffix) . ']*?)\s*' . preg_quote($suffix) . '/';
        
        foreach (array_combine($from, $to) as $from => $to) {
            $contents = preg_replace_callback($replacer, function ($matches) use ($replacements) {
                return Arr::get($replacements, $matches[1]);
            }, $this->get($from));
            
            $this->put($to, $contents);
        }
    }
    
    /**
     * @see \CupOfTea\Magick\Filesystem\FilesystemAdapter::rename
     */
    public function mv($from, $to, $overwrite = false, $recursive = false)
    {
        return $this->rename($from, $to, $overwrite, $recursive);
    }
    
    /**
     * @see \CupOfTea\Magick\Filesystem\FilesystemAdapter::rename
     */
    public function move($from, $to, $overwrite = false, $recursive = false)
    {
        return $this->rename($from, $to, $overwrite, $recursive);
    }
    
    /**
     * Move a file to a new location.
     *
     * @param  string  $from
     * @param  string  $to
     * @return bool
     */
    public function rename($from, $to, $overwrite = false, $recursive = false)
    {
        $from = $this->resolvePath($from);
        $to = $this->resolvePath($to);
        
        $this->mask($from, $to, $overwrite, $recursive);
        
        if (is_array($from)) {
            $result = true;
            
            foreach (array_combine($from, $to) as $from => $to) {
                $result = $result && $this->rename($from, $to, $overwrite, $recursive);
            }
            
            return $result;
        }
        
        return $this->overwrite(__FUNCTION__, $from, $to, $overwrite, $recursive);
    }
    
    /**
     * Delete the file at a given path.
     *
     * @param  string|array  $paths
     * @return bool
     */
    public function delete($paths)
    {
        $paths = is_array($paths) ? $paths : func_num_args() == 1 ? $paths : func_get_args();
        $paths = $this->resolvePath($paths);
        
        if (is_array($paths)) {
            $result = true;
            
            foreach ($paths as $path) {
                if ($this->has($path) && $this->type($path) != 'dir') {
                    $result = $result && $this->delete($path);
                }
            }
            
            return $result;
        }
        
        if (Str::contains($paths, '*')) {
            preg_match('/(?:([^\*]*)\/)?(.*)/', $paths, $matches);
            $contents = $this->allContents($matches[1]);
            
            $paths = $this->filterContentsByActionable($this->filterContentsBy($contents, 'path', $paths));
        }
        
        return parent::delete($paths);
    }
    
    /**
     * @see \CupOfTea\Magick\Filesystem\FilesystemAdapter::delete
     */
    public function rm($paths)
    {
        return $this->delete($paths);
    }
    
    /**
     * {@inheritdoc}
     */
    public function makeDirectory($path)
    {
        return parent::makeDirectory($this->resolvePath($path));
    }
    
    /**
     * @see \Illuminate\Filesystem\FilesystemAdapter::makeDirectory
     */
    public function mkdir($path)
    {
        return $this->makeDirectory($path);
    }
    
    /**
     * Delete a directory at a given path.
     *
     * @param  string  $paths
     * @return bool
     */
    public function deleteDir($paths)
    {
        $this->mask($paths);
        
        if (! is_array($paths)) {
            $paths = [$paths];
        }
        
        $result = true;
        $paths = $this->resolvePath($paths);
        
        foreach ($paths as $path) {
            $result = $result && parent::deleteDir($paths);
        }
        
        return $result;
    }
    
    /**
     * {@inheritdoc}
     */
    public function files($directory = null, $recursive = false)
    {
        return parent::files($this->resolvePath($directory), $recursive);
    }
    
    /**
     * {@inheritdoc}
     */
    public function allFiles($directory = null)
    {
        return parent::allFiles($this->resolvePath($directory));
    }
    
    /**
     * Get all of the contents within a given directory.
     *
     * @param  string|null  $directory
     * @param  bool  $recursive
     * @return array
     */
    public function contents($directory = null, $recursive = false)
    {
        return $this->driver->listContents($this->resolvePath($directory), $recursive);
    }
    
    /**
     * Recursively get all of the contents within a given directory.
     *
     * @param  string|null  $directory
     * @return array
     */
    public function allContents($directory = null)
    {
        return $this->contents($directory, true);
    }
    
    /**
     * {@inheritdoc}
     */
    public function directories($directory = null, $recursive = false)
    {
        return parent::directories($this->resolvePath($directory), $recursive);
    }
    
    /**
     * {@inheritdoc}
     */
    public function allDirectories($directory = null)
    {
        return parent::allDirectories($this->resolvePath($directory));
    }
    
    /**
     * Get all of the contents within a given directory and group them by type.
     *
     * @param  string|null  $directory
     * @param  bool  $recursive
     * @return array
     */
    public function contentsByType($directory = null, $recursive = false)
    {
        return [
            'files' => $this->files($this->resolvePath($directory), $recursive),
            'directories' => $this->directories($this->resolvePath($directory), $recursive),
        ];
    }
    
    /**
     * Recursively get all of the contents within a given directory and group them by type.
     *
     * @param  string|null  $directory
     * @return array
     */
    public function allContentsByType($directory = null)
    {
        return [
            'files' => $this->allFiles($this->resolvePath($directory)),
            'directories' => $this->allDirectories($this->resolvePath($directory)),
        ];
    }
    
    /**
     * Retrieve type of a path.
     *
     * @param  string  $path
     * @return string
     */
    public function type($paths)
    {
        if (! is_array($paths) && func_num_args() > 1) {
            $paths = func_get_args();
        }
        
        return $this->meta($this->resolvePath($paths), 'type');
    }
    
    /**
     * Retrieve directory name of a path.
     *
     * @param  string  $path
     * @return string
     */
    public function dirname($paths)
    {
        if (! is_array($paths) && func_num_args > 1) {
            $paths = func_get_args();
        }
        
        return $this->meta($this->resolvePath($paths), 'dirname');
    }
    
    /**
     * Retrieve basename of a path.
     *
     * @param  string  $path
     * @return string
     */
    public function basename($paths)
    {
        if (! is_array($paths) && func_num_args > 1) {
            $paths = func_get_args();
        }
        
        return $this->meta($this->resolvePath($paths), 'basename');
    }
    
    /**
     * {@inheritdoc}
     */
    public function size($path)
    {
        return parent::size($this->resolvePath($path));
    }
    
    /**
     * {@inheritdoc}
     */
    public function mimeType($path)
    {
        return parent::mimeType($this->resolvePath($path));
    }
    
    /**
     * {@inheritdoc}
     */
    public function lastModified($path)
    {
        return parent::lastModified($this->resolvePath($path));
    }
    
    /**
     * @see \CupOfTea\Magick\Filesystem\FilesystemAdapter::extension
     */
    public function ext($paths)
    {
        if (! is_array($paths) && func_num_args > 1) {
            $paths = func_get_args();
        }
        
        return $this->extension($paths);
    }
    
    /**
     * Retrieve extension of a path.
     *
     * @param  string  $path
     * @return string
     */
    public function extension($paths)
    {
        if (! is_array($paths) && func_num_args > 1) {
            $paths = func_get_args();
        }
        
        return $this->meta($paths, 'extension');
    }
    
    /**
     * Retrieve filename of a path.
     *
     * @param  string  $path
     * @return string
     */
    public function filename($paths)
    {
        if (! is_array($paths) && func_num_args > 1) {
            $paths = func_get_args();
        }
        
        return $this->meta($paths, 'filename');
    }
    
    /**
     * Retrieve metadata for a path.
     *
     * @param  string  $path
     * @param  null|string  $key
     * @return mixed
     */
    public function meta($paths, $key = null)
    {
        if (is_array($paths)) {
            $meta = [];
            
            foreach ($paths as $path) {
                $meta[$path] = $this->meta($this->resolvePath($path), $key);
            }
            
            return $meta;
        }
        
        $paths = $this->resolvePath($paths);
        preg_match('/(.*)\/([^\/]+)/', $paths, $matches);
        $contents = $this->contents($matches[1] ? $matches[1] : null);
        $meta = Collection::make($contents)
            ->where('path', $paths)
            ->first();
        
        if (! is_null($key)) {
            if (! isset($meta[$key])) {
                throw new InvalidArgumentException('Could not fetch metadata: ' . $key);
            }
            
            return $meta[$key];
        }
        
        return $meta;
    }
    
    /**
     * Get the Disk's root.
     *
     * @return string
     */
    public function root()
    {
        return $this->driver->getAdapter()->getPathPrefix();
    }
    
    /**
     * Make the given path relative to the Disk's root.
     *
     * @param  string  $paths
     * @return string
     */
    protected function resolvePath($paths)
    {
        if (is_array($paths)) {
            foreach ($path as &$path) {
                $path = $this->resolvePath($path);
            }
            
            return $paths;
        }
        
        return str_replace($this->root(), '', $paths);
    }
    
    /**
     * Filter directory contents by type.
     *
     * @param  array  $contents
     * @param  string  $key
     * @param  string  $value
     * @return array
     */
    protected function filterContentsBy($contents, $key, $value)
    {
        return Collection::make($contents)
            ->filter(function ($item) use ($key, $value) {
                $regex = preg_quote($value, '/');
                $regex = str_replace('\*', '.*', $regex);
                $regex = '/' . $regex . '/';
                
                return preg_match($regex, $item[$key]);
            })
            ->pluck('path')
            ->values()
            ->all();
    }
    
    /**
     * Filter paths by actionable.
     *
     * @param  array  $paths
     * @return array
     */
    protected function filterContentsByActionable($paths)
    {
        $paths = Collection::make($paths);
        $paths->each(function ($path) use (&$paths) {
            if ($this->type($path) == 'dir') {
                $paths = $paths->filter(function ($check_path) use ($path) {
                    return preg_match('#' . preg_quote(Str::finish($path, '/')) . '.+#', $check_path) ? false : true;
                });
            }
        });
        
        return $paths->all();
    }
    
    /**
     * Overwrite existing files and directories when copying or moving.
     *
     * Deletes a file if overwrite is true and the file exists.
     * Deletes a directory if from is not a directory or if overwrite is true, recursive is false and the directory exists.
     * Replaces files resursively if resursive is true and both from and to are directories.
     *
     * @param string   $action
     * @param string   $from
     * @param string   $to
     * @param bool   $overwrite
     * @param bool   $recursive
     * @return void
     */
    private function overwrite($action, $from, $to, $overwrite, $recursive)
    {
        if ($overwrite && $this->has($to)) {
            if ($this->mimeType($to) != 'directory') {
                $this->delete($to);
                
                return $this->driver->$action($from, $to);
            }
            
            if ($this->mimeType($from) != 'directory' || ! $recursive) {
                $this->deleteDirectory($to);
                
                return $this->driver->$action($from, $to);
            }
            
            $files = $this->allFiles($from);
            $result = true;
            
            foreach ($files as $file) {
                $result = $result && $this->$action($file, str_replace($from, $to, $file), true, true);
            }
            
            if ($action == 'rename') {
                $this->deleteDirectory($from);
            }
            
            return $result;
        }
        
        return $this->driver->$action($from, $to);
    }
    
    /**
     * Resolves a mask to real paths.
     *
     * @param  string  $from
     * @param  string  $to
     * @param  bool  $overwrite
     * @param  bool  $recursive
     * @return void
     */
    public function mask(&$from, &$to = null, $overwrite = null, &$recursive = null)
    {
        if (is_bool($to)) {
            $recursive = $to;
            $to = null;
        }
        
        if (preg_match('/([^\*]+)\/\*$/', $from, $matches)) {
            $base_from_path = $matches[1];
            $from = Collection::make($this->contents($base_from_path))->pluck('path')->all();
            
            if (is_null($to)) {
                return;
            }
            
            $base_to_path = $to;
            $to = [];
            $recursive = true;
            
            foreach ($from as $path) {
                $to[] = str_replace($base_from_path, $base_to_path, $path);
            }
        } elseif (Str::contains($from, '*')) {
            $from_mask = $from;
            
            preg_match('/(?:([^\*]*)\/)?(.*)/', $from_mask, $matches);
            $base_from_path = $matches[1];
            $contents = $recursive ? $this->allContents($base_from_path) : $this->contents($base_from_path);
            
            $from = $this->filterContentsByActionable($this->filterContentsBy($contents, 'path', $from_mask));
            
            if (is_null($to)) {
                return;
            }
            
            $to_mask = rtrim($to, '/');
            $to_type = $this->has($to_mask) ? $this->type($to_mask) : false;
            $to = [];
            
            foreach ($from as $path) {
                $pattern = '#' . str_replace('\*', '(.*)', preg_quote($from_mask)) . '#';
                
                if (Str::contains($to_mask, '$')) {
                    preg_match($pattern, $path, $matches);
                    
                    foreach (array_slice($matches, 1) as $i => $match) {
                        $new_to = preg_replace('/\$' . ($i + 1) . '/', $match, $to_mask);
                    }
                    
                    $to[] = $new_to;
                } else {
                    $to[] = str_replace($base_from_path, $to_mask, $path);
                }
            }
        }
        
        if (is_array($from) && count($from) == 1) {
            $from = $from[0];
            $to = $to[0];
        }
    }
}
