<?php
class DiskCache {
	private $dir;

	public function __construct($dir) {
		$this->dir = basename($dir);
	}

	public function getDir() {
		return $this->dir;
	}

	public function isWritable() {
		return is_dir($this->dir) && is_writable($this->dir);
	}

	public function exists($filename) {
		return file_exists($this->getFullPath($filename));
	}

	public function getSize($filename) {
		if ($this->exists($filename))
			return filesize($this->getFullPath($filename));
		else
			return -1;
	}

	public function getFullPath($filename) {
		$filename = basename($filename);

		return CACHE_DIR . "/" . $this->dir . "/" . $filename;
	}

	public function put($filename, $data) {
		return file_put_contents($this->getFullPath($filename), $data);
	}

	public function touch($filename) {
		return touch($this->getFullPath($filename));
	}

	public function get($filename) {
		if ($this->exists($filename))
			return file_get_contents($this->getFullPath($filename));
		else
			return null;
	}

	public function getMimeType($filename) {
		if ($this->exists($filename))
			return mime_content_type($this->getFullPath($filename));
		else
			return null;
	}
}
