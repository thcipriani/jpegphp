<?php

class Jpeg {
  const SALIENT_FILE_NAME   = 'salient.png';
  const DEFAULT_TILE_SIZE   = 64;
  const MAX_COMPRESSION     = 50;
  const DEFAULT_COMPRESSION = 90;

  // how important does this have to be before you compress it (scale 0-255)
  const MIN_SALIENCE = 1;

  public $img;
  public $height;
  public $width;
  public $rows;
  public $cols;
  protected $_tileSize;
  protected $_threshold;
  protected $_isValid;
  protected $_isCompressed;
  protected $_isSliced;
  protected $_path;
  protected $_salient;
  protected $_quality;

  public function __construct($img = false)
  {
    if ($img)
      $this->setImage($img);
  }

  public function setImage($img)
  {
    $this->img = $img;

    if (! $this->isValid())
      throw new Exception('Cannot call Jpeg on a non-jpeg image');
  }

  public function isFile()
  {
    if (! is_file($this->img))
      return false;

    return true;
  }

  public function isJpeg()
  {
    $info = getimagesize($this->img);
    if ($info['mime'] !== 'image/jpeg')
      return false;

    return true;
  }

  public function isValid()
  {
    if (isset($this->_isValid))
      return $this->_isValid;

    if (! isset($this->img) || ! $this->isFile() || ! $this->isJpeg())
      return false;

    $this->_isValid = true;
    return true;
  }

  public function setDims()
  {
    if (isset($this->width) && isset($this->height))
      return;

    $this->height = `identify -format %h {$this->img}`;
    $this->width = `identify -format %w {$this->img}`;
  }

  public function setCols()
  {
    if (isset($this->rows) && isset($this->cols))
      return;

    $rCols = floor($this->width / $this->getTileSize());
    $rRows = floor($this->height / $this->getTileSize());

    $cols = $rCols * $this->getTileSize() < $this->width
      ? $rCols + 1
      : $rCols;

    $rows = $rRows * $this->getTileSize() < $this->height
      ? $rRows + 1
      : $rRows;

    $this->rows = $rows;
    $this->cols = $cols;
  }

  protected function setDefaultQuality()
  {
    if (isset($this->_quality))
      return;

    $currentCompression = `identify -format "%Q" {$this->img}`;
    $this->_quality = min($currentCompression, self::DEFAULT_COMPRESSION);
  }

  public function getDefaultQuality()
  {
    $this->setDefaultQuality();
    return $this->_quality;
  }

  public function getTileSize()
  {
    if (isset($this->_tileSize))
      return $this->_tileSize;

    if (! $this->width || ! $this->height)
      $this->setDims();

    $smallestDim = $this->width < $this->height
      ? $this->width
      : $this->height;

    $this->_tileSize = self::DEFAULT_TILE_SIZE;

    if ($smallestDim < 256) {
      $this->_tileSize = 16;
    } else if ($smallestDim >= 257 && $smallestDim <= 512 ) {
      $this->_tileSize = 32;
    } else if ( $smallestDim >= 1025 && $smallestDim <= 2560 ) {
      $this->_tileSize = 128;
    } else if ( $smallestDim >= 2561 ) {
      $this->_tileSize = 256;
    }

    return $this->_tileSize;
  }

  public function getThreshold()
  {
    if (isset($this->_threshold))
      return $this->_threshold;

    $upper = 100;
    $lower = 0;
    $redo = false;
    $meanGray = 0;
    $this->_threshold = 50;

    $getMeanGray = function($opts) {
      return `SaliencyDetector -q -L{$opts['lower']} -U{$opts['upper']} "{$this->img}" "png:-" | identify -format "%[fx:100*mean]" -`;
    };

    while (($meanGray > 40 || $meanGray < 20) && $lower < $upper - 1) {
      $meanGray = $getMeanGray([
        'lower' => $lower,
        'upper' => $upper,
      ]);

      if ($meanGray > 40)
        $lower = $this->_threshold;

      if ($meanGray < 20)
        $upper = $this->_threshold;

      $this->_threshold = ($upper - $lower)/ 2 + $lower;
    }

    return $this->_threshold;
  }

  protected function getStorage()
  {
    if (isset($this->_path))
      return $this->_path;

    $hash = md5(microtime());
    $this->_path = '/tmp/' . implode('/', str_split(substr($hash, 0, 5)));

    if (! is_file($this->_path))
      if (! mkdir($this->_path, 0700, true))
        throw new Exception('Cannot create path for temp jpeg storage');

    return $this->_path;
  }

  protected function makeSalient()
  {
    if (! $this->isValid())
      throw new Exception('Cannot make salient image without valid image');

    if (isset($this->_salient))
      return $this->_salient;

    $this->_salient = $this->getStorage() . '/' . self::SALIENT_FILE_NAME;
    `SaliencyDetector -q -L0 -U25 "{$this->img}" {$this->_salient}`;

    return $this->_salient;
  }

  protected function makeSlices()
  {
    if (isset($this->_isSliced) && $this->_isSliced)
      return;

    $tileSize = $this->getTileSize();
    $quality = $this->getDefaultQuality();
    `convert "{$this->img}" -strip -quality {$quality} -crop "{$tileSize}"x"{$tileSize}" +repage +adjoin "{$this->getStorage()}/tile-%06d.jpg"`;
    $this->_isSliced = true;
  }

  protected function makeCompressed()
  {
    if (isset($this->_isCompressed) && $this->_isCompressed)
      return;

    $this->setDims();
    $this->setCols();
    $this->makeSalient();
    $this->makeSlices();

    $cols = $this->cols;
    $rows = $this->rows;

    $count = 0;

    // Loop through all the tiles
    for($y = 0; $y < $rows; $y++) {
      for($x = 0; $x < $cols; $x++) {
        $xOffset = $x * $this->getTileSize();
        $yOffset = $y * $this->getTileSize();

        $tileW = $this->getTileSize();
        $tileH = $this->getTileSize();

        if ($y + 1 === $rows && (($rows * $this->getTileSize()) > $this->height))
          $tileH = ($y + 1) * $this->getTileSize() - $this->height;

        if ($x + 1 === $rows && (($cols * $this->getTileSize()) > $this->width))
          $tileW = ($x + 1) * $this->getTileSize() - $this->width;

        // Find the mean saliency of the tile
        $mean = `identify -size "{$this->width}"x"{$this->height}" -channel Gray -format "%[fx:255*mean]" "{$this->_salient}[{$tileW}x{$tileH}+{$xOffset}+{$yOffset}]"`;

        $current = sprintf('tile-%06d', $count);
        // If it's not important, compress the shit out of it
        if ($mean < self::MIN_SALIENCE) {
          $file = "{$this->getStorage()}/{$current}.jpg";
          imagejpeg(imagecreatefromjpeg($file), $file, self::MAX_COMPRESSION);
        }
        $count++;
      }
    }

    $this->_isCompressed = true;
  }

  protected function makeAssembled()
  {
    if (! $this->_isCompressed)
      return false;

    $cols = $this->cols;
    $rows = $this->rows;
    $files = "$(find \"{$this->getStorage()}\" -name \"tile-*.jpg\" | sort)";
    $out = "{$this->getStorage()}/out.jpg";
    $final = "{$this->getStorage()}/final.jpg";
    $quality = $this->getDefaultQuality();

    `montage -strip -quality {$quality} -mode concatenate -tile "{$cols}x{$rows}" -- {$files} {$out}`;
    `jpegtran -copy none -optimize -progressive -perfect "{$out}" > "{$final}"`;

    return $final;
  }

  public function optimize()
  {
    $this->makeCompressed();
    return $this->makeAssembled();
  }
}
