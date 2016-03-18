<?php

namespace Saffyre;

class Template
{
    public static $baseDir;
    public static $cacheDir;
    public static $disableCache;
    public static $cache = [];

    public $file;
    public $value = [];

    public function __construct($file, $vars = [])
    {
        $data = self::parseFile($file);
        if (!$data)
            throw new \Exception("Could not find template file '$file'!");
        $this->file = $data['file'];
        $this->value = $data['value'];

        foreach ($vars as $key => $value)
            $this->$key = $value;
    }

    private static function parseFile($file)
    {
        // Clean up the template base directory
        self::$baseDir = realpath(self::$baseDir);

        // Use the full $file path if it starts with /
        if ($file[0] != DIRECTORY_SEPARATOR)
            $file = realpath(rtrim(self::$baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file);
        else
            $file = realpath($file);

        // If the file wasn't found, exit
        if ($file === false)
            return false;

        // Use the in-memory cache, if possible
        if (!empty(self::$cache[$file]))
            return self::$cache[$file];

        // Name a temporary cache directory if one was not explicitly specified
        if (!self::$cacheDir)
            self::$cacheDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);

        // Create the necessary cache directories (follows the folder structure of the original file)
        if (!is_dir(dirname(self::$cacheDir . $file)))
            mkdir(dirname(self::$cacheDir . $file), 0777, true);

        if (!self::$disableCache)
        {
            if ($data = self::recursiveCacheCheck(self::$cacheDir . $file))
                return $data;
        }

        // Caching failed, so create an information dictionary containing data about the template
        self::$cache[$file] = $data = [
            'file' => $file,
            'mtime' => filemtime($file),
            'sections' => [],
            'value' => []
        ];

        // Find all the "@" directives
        $matches = null;
        preg_match_all('/^@(.*?)(?:$|[ ]+(.*?)$)/m', $content = file_get_contents($file), $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        // Start capturing the un-sectioned template content. The $main array will be glued together at the end of this function
        $main = [substr($content, 0, isset($matches[0]) ? $matches[0][0][1] : PHP_INT_MAX)];
        $explicitMain = false;

        for ($i = 0; $i < count($matches); $i++)
        {
            $match = $matches[$i];
            $cmd = $match[1][0];

            // Get the substr() indices for the template content between the current directive and the next one (or the end of the file)
            $nextContentStart = $match[count($match) - 1][1] + strlen($match[count($match) - 1][0]) + 1;
            $nextContentEnd = isset($matches[$i + 1]) ? $matches[$i + 1][0][1] - $nextContentStart : PHP_INT_MAX;

            switch ($cmd)
            {
                case 'include':
                    // Parse the included template file and add any section information to this template data
                    $includeData = self::parseFile($match[2][0]);
                    if (!$includeData)
                        throw new \Exception("Could not find included template file '{$match[2][0]}' (included in $file)");
                    $data['sections'] += $includeData['sections'];
                    $data['value'] += $includeData['value'];
                    break;

                case 'value':
                    $data['value'][trim(strtok($match[2][0], ' '))] = trim(substr($match[2][0], strpos($match[2][0], ' ') + 1));
                    break;

                case 'section':
                case 'main':
                    // Create a section-specific file
                    $section = $cmd == 'section' ? $match[2][0] : '';
                    file_put_contents(
                        $sectionFile = self::$cacheDir . "$file#$section",
                        // Write some non-outputting blank lines to keep line numbers in error reporting consistent.
                        "<?php" . str_repeat("\n", substr_count($content, "\n", 0, $nextContentStart)) . "?>"
                        . substr($content, $nextContentStart, $nextContentEnd)
                    );
                    $data['sections'][$section] = $sectionFile;
                    continue 2;
            }

            $main[] = substr($content, $nextContentStart, $nextContentEnd);
        }

        // Save the un-sectioned template content as the "main" template section, if there was no @main directive AND it's not all whitespace
        if (!$explicitMain && trim($main = implode("\n", $main)))
        {
            file_put_contents($sectionFile = self::$cacheDir . "$file#@main", $main);
            $data['sections']['#main'] = $sectionFile;
        }

        // Write the template data to a file
        file_put_contents(self::$cacheDir . $file, json_encode($data));
        return $data;
    }

    public function __invoke()
    {
        return $this->__call('#main', []);
    }

    public function __toString()
    {
        return (string)$this();
    }

    public function __call($name, $args)
    {
        $info = json_decode(file_get_contents(self::$cacheDir . $this->file));

        $filename = $info->sections->{$name};

        if (!file_exists($filename))
            throw new \Exception("Saffyre\\Template error: no template section named '$name' found!");

        ob_start([ $this, 'fixErrorPaths' ]);
        try {
            include $filename;
        } catch(\Exception $e) {
            echo $this->fixErrorPaths($e->__toString());
        }
        return $this->fixErrorPaths(ob_get_clean());
    }

    public function value($name)
    {
        return isset($this->value[$name]) ? $this->value[$name] : '';
    }

    private static function fixErrorPaths($output)
    {
        return str_replace(self::$cacheDir . self::$baseDir, self::$baseDir, $output);
    }

    private static function recursiveCacheCheck($file, &$checked = [])
    {
        if (in_array($file, $checked))
            return true;

        // Read the json data in the cached file; if the file modification time matches, just return the cached data
        $data = json_decode(file_exists($file) ? file_get_contents($file) : '', JSON_OBJECT_AS_ARRAY);
        if ($data && $data['mtime'] == filemtime(str_replace(self::$cacheDir, '', $file)))
        {
            $checked[] = $file;
            foreach ($data['sections'] as $subFile)
                if (!self::recursiveCacheCheck(substr($subFile, 0, strrpos($subFile, '#')), $checked))
                    return false;
            return $data;
        }
        else return null;
    }
}