<?php

namespace VanguardLTE\Support\Settings;

use Illuminate\Filesystem\Filesystem;

class JsonSettingStore
{
    protected $files;
    protected $path;
    protected $data = [];
    protected $loaded = false;
    protected $unsaved = false;
    protected $defaults = [];

    public function __construct(Filesystem $files, $path)
    {
        $this->files = $files;
        $this->path = $path;
    }

    public function setDefaults(array $defaults)
    {
        $this->defaults = $defaults;
    }

    public function get($key, $default = null)
    {
        if ($default === null) {
            $default = ArrayPath::get($this->defaults, $key);
        }

        $this->load();

        return ArrayPath::get($this->data, $key, $default);
    }

    public function has($key)
    {
        $this->load();

        return ArrayPath::has($this->data, $key);
    }

    public function set($key, $value = null)
    {
        $this->load();
        $this->unsaved = true;

        if (is_array($key)) {
            foreach ($key as $settingKey => $settingValue) {
                ArrayPath::set($this->data, $settingKey, $settingValue);
            }

            return;
        }

        ArrayPath::set($this->data, $key, $value);
    }

    public function forget($key)
    {
        $this->load();
        $this->unsaved = true;

        ArrayPath::forget($this->data, $key);
    }

    public function forgetAll()
    {
        $this->data = [];
        $this->loaded = true;
        $this->unsaved = true;
    }

    public function all()
    {
        $this->load();

        return $this->data;
    }

    public function load($force = false)
    {
        if ($this->loaded && !$force) {
            return;
        }

        $this->ensureStoreExists();

        $data = json_decode($this->files->get($this->path), true);
        if ($data === null) {
            throw new \RuntimeException("Invalid JSON in {$this->path}");
        }

        $this->data = $data;
        $this->loaded = true;
    }

    public function save()
    {
        if (!$this->unsaved) {
            return;
        }

        $this->ensureStoreExists();
        $this->files->put($this->path, $this->data ? json_encode($this->data) : '{}');
        $this->unsaved = false;
    }

    protected function ensureStoreExists()
    {
        $directory = dirname($this->path);

        if (!$this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        if (!$this->files->exists($this->path)) {
            $this->files->put($this->path, '{}');
        }

        if (!$this->files->isWritable($this->path)) {
            throw new \InvalidArgumentException("{$this->path} is not writable.");
        }
    }
}
