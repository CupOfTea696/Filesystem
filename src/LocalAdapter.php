<?php namespace CupOfTea\Filesystem;

use League\Flysystem\Adapter\Local as FlysystemLocalAdapter;

/**
 * @TODO: Filter out system files.
 */
class LocalAdapter extends FlysystemLocalAdapter
{
    protected $system_files = [
        '*~',
        '.directory',
        '.Trash-*',
        '.DS_Store',
        '.AppleDouble',
        '.LSOverride',
        "Icon\r\r",
        '.DocumentRevisions-V100',
        '.fseventsd',
        '.Spotlight-V100',
        '.TemporaryItems',
        '.Trashes',
        '.VolumeIcon.icns',
        '.AppleDB',
        '.AppleDesktop',
        '.apdisk',
        'Thumbs.db',
        'ehthumbs.db',
        'Desktop.ini',
        '$RECYCLE.BIN/',
        '*.lnk',
    ];
    
    /**
     * {@inheritdoc}
     */
    public function listContents($directory = '', $recursive = false)
    {
        $result = [];
        $location = $this->applyPathPrefix($directory) . $this->pathSeparator;
        
        if (! is_dir($location)) {
            return [];
        }
        
        $iterator = $recursive ? $this->getRecursiveDirectoryIterator($location) : $this->getDirectoryIterator($location);
        
        foreach ($iterator as $file) {
            $path = $this->getFilePath($file);
            $name = $file->getBasename();
            
            if (preg_match('#(^|/|\\\\)\.{1,2}$#', $path) || $this->isSystemFile($name)) {
                continue;
            }
            
            $result[] = $this->normalizeFileInfo($file);
        }
        
        return array_filter($result);
    }
    
    public function isSystemFile($path)
    {
        $file = preg_replace('#.*(?:^|/|\\\\)(.*?)$#', '$1', $path);
        
        foreach ($this->system_files as $system_file) {
            if (preg_match('#' . str_replace('\\*', '.*', preg_quote($system_file, '#')) . '#', $file)) {
                return true;
            }
        }
        
        return false;
    }
}
